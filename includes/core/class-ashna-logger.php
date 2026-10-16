<?php
/**
 * OTP Logger, Analytics & Log Table Repository
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Logger {

	/**
	 * Get table name
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'ashna_otp_logs';
	}

	/**
	 * Column sizes of the log table (characters; response_message is a TEXT column, kept short).
	 *
	 * On MySQL, wpdb refuses the whole INSERT/UPDATE when one value is longer than its column, so
	 * values are trimmed first (gateway error pages can be long; addresses are already capped).
	 *
	 * @param array $row Column => value.
	 * @return array
	 */
	private static function fit_columns( array $row ) {
		$limits = array(
			'recipient'        => 191,
			'channel'          => 32,
			'gateway'          => 50,
			'otp_code'         => 20,
			'otp_hash'         => 255,
			'status'           => 32,
			'ip_address'       => 64,
			'ip_subnet'        => 64,
			'response_message' => 2000,
		);
		foreach ( $limits as $column => $max ) {
			if ( isset( $row[ $column ] ) && is_string( $row[ $column ] ) && mb_strlen( $row[ $column ], 'UTF-8' ) > $max ) {
				$row[ $column ] = mb_substr( $row[ $column ], 0, $max, 'UTF-8' );
			}
		}
		return $row;
	}

	/**
	 * Insert a new OTP log/session entry
	 *
	 * @param array $data Log data.
	 * @return int|false Inserted ID or false on error.
	 */
	public static function insert( $data ) {
		global $wpdb;

		$now_ts     = Ashna_Helper::local_now();
		$now_local  = gmdate( 'Y-m-d H:i:s', $now_ts );
		$expiry_sec = absint( Ashna_Helper::get_option( 'otp_expiry', 120 ) );
		$expires_at = gmdate( 'Y-m-d H:i:s', $now_ts + $expiry_sec );

		$defaults = array(
			'recipient'        => '',
			'channel'          => 'sms',
			'gateway'          => 'sandbox',
			'otp_code'         => '',
			'otp_hash'         => '',
			'status'           => 'sent',
			'attempts'         => 0,
			'ip_address'       => Ashna_Helper::get_client_ip(),
			'ip_subnet'        => '',
			'response_message' => '',
			'expires_at'       => $expires_at,
			'verified_at'      => null,
			'created_at'       => $now_local,
		);

		$row = wp_parse_args( $data, $defaults );
		if ( '' === $row['ip_subnet'] ) {
			$row['ip_subnet'] = Ashna_IP::subnet( $row['ip_address'] );
		}
		$row = self::fit_columns( $row );

		// Expire previous active codes for this recipient
		self::expire_previous_codes( $row['recipient'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			self::table_name(),
			array(
				'recipient'        => $row['recipient'],
				'channel'          => $row['channel'],
				'gateway'          => $row['gateway'],
				'otp_code'         => $row['otp_code'],
				'otp_hash'         => $row['otp_hash'],
				'status'           => $row['status'],
				'attempts'         => (int) $row['attempts'],
				'ip_address'       => $row['ip_address'],
				'ip_subnet'        => $row['ip_subnet'],
				'response_message' => $row['response_message'],
				'expires_at'       => $row['expires_at'],
				'verified_at'      => $row['verified_at'],
				'created_at'       => $row['created_at'],
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		self::flush_stats_cache();
		return (int) $wpdb->insert_id;
	}

	/**
	 * Reserve a send slot BEFORE any gateway is contacted.
	 *
	 * The row exists from the first millisecond, so concurrent requests count it and a burst
	 * of parallel requests cannot all pass the limits before anything was logged.
	 *
	 * @param string $recipient Normalized phone or email.
	 * @param string $ip        Visitor IP.
	 * @param string $subnet    Visitor network (/24 or /64).
	 * @return int Reservation (log row) ID, 0 on failure.
	 */
	public static function reserve( $recipient, $ip, $subnet ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		$fit = self::fit_columns(
			array(
				'recipient'  => (string) $recipient,
				'ip_address' => (string) $ip,
				'ip_subnet'  => (string) $subnet,
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			self::table_name(),
			array(
				'recipient'        => $fit['recipient'],
				'channel'          => 'pending',
				'gateway'          => '',
				'otp_code'         => '',
				'otp_hash'         => '',
				'status'           => 'pending',
				'attempts'         => 0,
				'ip_address'       => $fit['ip_address'],
				'ip_subnet'        => $fit['ip_subnet'],
				'response_message' => '',
				'expires_at'       => $now,
				'created_at'       => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return false === $result ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * Turn a reservation into the final log entry once the gateways answered.
	 *
	 * @param int   $id   Reservation ID.
	 * @param array $data channel, gateway, otp_code (display value), otp_hash, status (sent|failed), response_message.
	 * @return bool
	 */
	public static function complete( $id, array $data ) {
		global $wpdb;
		$table = self::table_name();

		if ( isset( $data['status'] ) && 'sent' === $data['status'] ) {
			// The caller already knows the recipient (it is in $data), so the lookup only runs for
			// a caller that did not pass one — same behaviour, one query less on every send.
			$recipient = isset( $data['recipient'] ) ? (string) $data['recipient'] : '';
			if ( '' !== $recipient ) {
				// Whatever the reservation stored (fit_columns() truncates long values), the row
				// is addressed with exactly the same string.
				$fitted    = self::fit_columns( array( 'recipient' => $recipient ) );
				$recipient = (string) $fitted['recipient'];
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipient = (string) $wpdb->get_var( $wpdb->prepare( "SELECT recipient FROM {$table} WHERE id = %d", $id ) );
			}
			if ( '' !== $recipient ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'expired' WHERE recipient = %s AND status = 'sent' AND id <> %d", $recipient, $id ) );
			}
		}

		$expiry_sec = absint( Ashna_Helper::get_option( 'otp_expiry', 120 ) );
		$fields     = array_intersect_key( $data, array_flip( array( 'channel', 'gateway', 'otp_code', 'otp_hash', 'status', 'response_message' ) ) );

		$fields['expires_at'] = gmdate( 'Y-m-d H:i:s', Ashna_Helper::local_now() + $expiry_sec );
		return self::update( $id, $fields );
	}

	/**
	 * Remove a reservation that was refused by the limits.
	 *
	 * @param int $id Reservation ID.
	 */
	public static function delete( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( self::table_name(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * Flush cached admin analytics transients
	 */
	public static function flush_stats_cache() {
		delete_transient( 'ashna_admin_stats_cache' );
		delete_transient( 'ashna_admin_chart_cache' );
	}

	/**
	 * Mark previous sent codes for recipient as expired
	 *
	 * @param string $recipient Phone or email.
	 */
	public static function expire_previous_codes( $recipient ) {
		global $wpdb;
		$table = self::table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix (%i needs WP 6.2).
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'expired' WHERE recipient = %s AND status = 'sent'",
				$recipient
			)
		);
		// phpcs:enable
	}

	/**
	 * Update log status and message
	 *
	 * @param int   $id     Log ID.
	 * @param array $fields Fields to update.
	 * @return bool
	 */
	public static function update( $id, $fields ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = (bool) $wpdb->update(
			self::table_name(),
			self::fit_columns( $fields ),
			array( 'id' => absint( $id ) )
		);
		if ( $updated ) {
			self::flush_stats_cache();
		}
		return $updated;
	}

	/**
	 * Get latest active OTP record for a recipient
	 *
	 * @param string $recipient Normalized phone or email.
	 * @return object|null
	 */
	public static function get_latest_record( $recipient ) {
		global $wpdb;
		$table = self::table_name();

		// The caller needs the state of the code, never the gateway's message text: selecting the
		// columns saves reading (and moving) the whole `response_message` TEXT column on every
		// sign-in attempt. `id` and `otp_hash` are part of the result contract.
		$columns = 'id, recipient, channel, gateway, otp_code, otp_hash, status, attempts, ip_address, ip_subnet, expires_at, verified_at, created_at';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column list are fixed strings from $wpdb->prefix (%i needs WP 6.2).
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT {$columns} FROM {$table} WHERE recipient = %s AND status <> 'pending' AND channel <> 'passkey' ORDER BY id DESC LIMIT 1",
				$recipient
			)
		);
		// phpcs:enable
	}

	/**
	 * Get paginated logs for admin page
	 *
	 * @param array $args Query arguments.
	 * @return array { items: array, total: int }
	 */
	public static function get_logs( $args = array() ) {
		global $wpdb;
		$table = self::table_name();

		$defaults = array(
			'per_page' => 20,
			'paged'    => 1,
			'search'   => '',
			'status'   => '',
			'channel'  => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(recipient LIKE %s OR ip_address LIKE %s OR gateway LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['channel'] ) ) {
			$where[]  = 'channel = %s';
			$values[] = $args['channel'];
		}

		$where_sql = implode( ' AND ', $where );
		$offset    = max( 0, ( absint( $args['paged'] ) - 1 ) * absint( $args['per_page'] ) );
		$limit     = absint( $args['per_page'] );

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- placeholders live in $where_sql.
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $values ) );

			$query_values   = $values;
			$query_values[] = $limit;
			$query_values[] = $offset;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholders live in $where_sql.
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d", $query_values ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ) );
		}

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Get summary statistics for admin dashboard (Single aggregated SQL + 60s Transient Cache)
	 *
	 * @return array
	 */
	public static function get_stats() {
		$cached = get_transient( 'ashna_admin_stats_cache' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$table = self::table_name();
		$today = gmdate( 'Y-m-d 00:00:00', Ashna_Helper::local_now() );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$agg = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total_all,
				        SUM(CASE WHEN created_at >= %s THEN 1 ELSE 0 END) AS today_count,
				        SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) AS verified_count,
				        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_count
				 FROM {$table}",
				$today
			)
		);
		$otp_users = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'ashna_registered_via_otp'" );
		// phpcs:enable

		$total_all      = $agg ? (int) $agg->total_all : 0;
		$today_count    = $agg ? (int) $agg->today_count : 0;
		$verified_count = $agg ? (int) $agg->verified_count : 0;
		$failed_count   = $agg ? (int) $agg->failed_count : 0;

		$conversion_rate = $total_all > 0 ? round( ( $verified_count / $total_all ) * 100, 1 ) : 0;

		$stats = array(
			'total'           => $total_all,
			'today'           => $today_count,
			'verified'        => $verified_count,
			'failed'          => $failed_count,
			'otp_users'       => $otp_users,
			'conversion_rate' => $conversion_rate,
		);

		set_transient( 'ashna_admin_stats_cache', $stats, 60 );
		return $stats;
	}

	/**
	 * Get 7-day chart analytics data without double timezone offset (Cached 60s)
	 *
	 * @param int $days Number of days.
	 * @return array
	 */
	public static function get_daily_chart_data( $days = 7 ) {
		if ( 7 === $days ) {
			$cached = get_transient( 'ashna_admin_chart_cache' );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		global $wpdb;
		$table    = self::table_name();
		$now_ts   = Ashna_Helper::local_now();
		$start_dt = gmdate( 'Y-m-d 00:00:00', $now_ts - ( ( $days - 1 ) * DAY_IN_SECONDS ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix (%i needs WP 6.2).
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) as day_date,
				        COUNT(*) as total_count,
				        SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified_count,
				        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
				 FROM {$table}
				 WHERE created_at >= %s
				 GROUP BY DATE(created_at)
				 ORDER BY day_date ASC",
				$start_dt
			),
			OBJECT_K
		);
		// phpcs:enable

		$chart   = array();
		$max_val = 1;

		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$day_ts  = $now_ts - ( $i * DAY_IN_SECONDS );
			$day_key = gmdate( 'Y-m-d', $day_ts );
			// Pass true as 3rd param to date_i18n since $day_ts already includes site timezone offset
			$label   = date_i18n( 'j F', $day_ts, true );

			$total    = isset( $rows[ $day_key ] ) ? (int) $rows[ $day_key ]->total_count : 0;
			$verified = isset( $rows[ $day_key ] ) ? (int) $rows[ $day_key ]->verified_count : 0;
			$failed   = isset( $rows[ $day_key ] ) ? (int) $rows[ $day_key ]->failed_count : 0;

			if ( $total > $max_val ) {
				$max_val = $total;
			}

			$chart[] = array(
				'date'     => $day_key,
				'label'    => $label,
				'total'    => $total,
				'verified' => $verified,
				'failed'   => $failed,
			);
		}

		foreach ( $chart as &$point ) {
			$point['height_pct'] = $point['total'] > 0 ? max( 10, round( ( $point['total'] / $max_val ) * 100 ) ) : 4;
		}

		if ( 7 === $days ) {
			set_transient( 'ashna_admin_chart_cache', $chart, 60 );
		}

		return $chart;
	}

	/**
	 * Clear all logs or logs older than retention period
	 *
	 * @param bool $all Whether to truncate all logs.
	 * @return int Number of rows deleted.
	 */
	public static function clear_logs( $all = false ) {
		global $wpdb;
		$table = self::table_name();

		if ( $all ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$res = (int) $wpdb->query( "TRUNCATE TABLE {$table}" );
			self::flush_stats_cache();
			return $res;
		}

		$days   = max( 1, absint( Ashna_Helper::get_option( 'log_retention_days', 30 ) ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', Ashna_Helper::local_now() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$res = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
		self::flush_stats_cache();
		return $res;
	}

	/**
	 * Scheduled WP-Cron callback to purge expired logs automatically
	 */
	public static function run_scheduled_cleanup() {
		self::clear_logs( false );
		Ashna_Security::purge_used_captcha_tokens();
	}
}
