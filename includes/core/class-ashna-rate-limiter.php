<?php
/**
 * SMS-bombing protection: per-minute/hour/day limits per mobile number, IP and network,
 * a site-wide daily credit guard and an automatic "attack mode".
 *
 * Race-free by construction: a request first reserves its slot (a "pending" log row) and
 * only then re-counts everything up to and including its own row. Of N parallel requests
 * that all passed the quick pre-check, only those inside the limit keep their slot — the
 * rest are refused before any gateway is contacted. (Previously the log row was written
 * after sending, so a burst of parallel requests could all pass the limits.)
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Rate_Limiter {

	const ATTACK_OPTION = 'ashna_otp_attack_mode';
	const STATS_OPTION  = 'ashna_otp_block_stats';

	/**
	 * Effective limits from the settings (0 = off where allowed).
	 *
	 * @return array
	 */
	public static function limits() {
		return array(
			'cooldown'       => absint( Ashna_Helper::get_option( 'resend_cooldown', 60 ) ),
			'number_hour'    => max( 1, absint( Ashna_Helper::get_option( 'max_requests_per_hour', 5 ) ) ),
			'number_day'     => max( 1, absint( Ashna_Helper::get_option( 'max_requests_per_day', 10 ) ) ),
			'ip_minute'      => max( 1, absint( Ashna_Helper::get_option( 'max_ip_requests_per_minute', 5 ) ) ),
			'ip_hour'        => max( 2, absint( Ashna_Helper::get_option( 'max_ip_requests_per_hour', 15 ) ) ),
			'subnet_minute'  => absint( Ashna_Helper::get_option( 'max_subnet_requests_per_minute', 20 ) ),
			'daily_cap'      => absint( Ashna_Helper::get_option( 'daily_send_cap', 0 ) ),
			'attack_trigger' => absint( Ashna_Helper::get_option( 'attack_mode_threshold', 20 ) ),
			'attack_minutes' => max( 5, absint( Ashna_Helper::get_option( 'attack_mode_minutes', 30 ) ) ),
		);
	}

	/**
	 * Reserve a send slot for a recipient, or explain why not.
	 *
	 * Three steps, each safe to interleave with the same steps of parallel requests:
	 * precheck() (read-only) → Ashna_Logger::reserve() (one INSERT) → recount() (counts rows
	 * up to and including our own; refused rows are removed). See tests/security.php, which
	 * replays every interleaving of parallel requests through these steps.
	 *
	 * @param string $recipient Normalized phone or email.
	 * @param string $ip        Visitor IP (defaults to the resolved client IP).
	 * @return int|WP_Error Reservation ID.
	 */
	public static function reserve( $recipient, $ip = '' ) {
		$ip = '' !== $ip ? $ip : Ashna_Helper::get_client_ip();

		$check = self::precheck( $recipient, $ip );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$id = Ashna_Logger::reserve( $recipient, $ip, Ashna_IP::subnet( $ip ) );
		if ( ! $id ) {
			return new WP_Error( 'ashna_reserve_failed', 'خطای موقت در ثبت درخواست. لطفاً چند لحظه بعد دوباره تلاش کنید.' );
		}

		$check = self::recount( $recipient, $ip, $id );
		return is_wp_error( $check ) ? $check : $id;
	}

	/**
	 * Step 1 — blacklist, lockout and a quick read-only look at every limit. Most abusive
	 * requests stop here without writing anything.
	 *
	 * @param string $recipient Recipient.
	 * @param string $ip        IP.
	 * @return true|WP_Error
	 */
	public static function precheck( $recipient, $ip ) {
		$blacklist = Ashna_Security::check_blacklist( $recipient, $ip );
		if ( is_wp_error( $blacklist ) ) {
			return self::refuse( $blacklist, $recipient, $ip );
		}
		if ( Ashna_Security::is_whitelisted( $recipient, $ip ) ) {
			return true;
		}
		$lockout = Ashna_Security::check_lockout( $recipient, $ip );
		if ( is_wp_error( $lockout ) ) {
			return self::refuse( $lockout, $recipient, $ip );
		}
		$check = self::evaluate( $recipient, $ip, Ashna_IP::subnet( $ip ), 0 );
		return is_wp_error( $check ) ? self::refuse( $check, $recipient, $ip ) : true;
	}

	/**
	 * Step 3 — with our reservation stored, count everything up to and including it. Of any
	 * number of parallel requests, only those inside the limits keep their slot.
	 *
	 * @param string $recipient Recipient.
	 * @param string $ip        IP.
	 * @param int    $id        Reservation ID.
	 * @return true|WP_Error
	 */
	public static function recount( $recipient, $ip, $id ) {
		if ( Ashna_Security::is_whitelisted( $recipient, $ip ) ) {
			return true;
		}
		$check = self::evaluate( $recipient, $ip, Ashna_IP::subnet( $ip ), $id );
		if ( is_wp_error( $check ) ) {
			Ashna_Logger::delete( $id );
			return self::refuse( $check, $recipient, $ip );
		}
		self::maybe_enter_attack_mode();
		return true;
	}

	/**
	 * Check every window. With $upto_id = 0 the request is not stored yet (it would add one);
	 * otherwise rows up to and including the reservation are counted.
	 *
	 * @param string $recipient Recipient.
	 * @param string $ip        IP.
	 * @param string $subnet    Network.
	 * @param int    $upto_id   Reservation ID or 0.
	 * @return true|WP_Error
	 */
	public static function evaluate( $recipient, $ip, $subnet, $upto_id = 0 ) {
		global $wpdb;
		$table  = Ashna_Logger::table_name();
		$limits = self::limits();
		$now    = Ashna_Helper::local_now();
		$extra  = $upto_id ? 0 : 1;
		$upto   = $upto_id ? $wpdb->prepare( ' AND id <= %d', $upto_id ) : '';
		$before = $upto_id ? $wpdb->prepare( ' AND id < %d', $upto_id ) : '';
		$at     = static function ( $seconds ) use ( $now ) {
			return gmdate( 'Y-m-d H:i:s', $now - $seconds );
		};
		// Upper bound for the short windows: rows "from the future" were written before the clock went
		// back (DST ends, or the site timezone was changed) and must not count as this minute.
		$soon = $at( -5 );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		// Mobile number / e-mail: resend cooldown, per hour, per day (failed sends do not count).
		$number = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS day_count,
				        SUM(CASE WHEN created_at >= %s THEN 1 ELSE 0 END) AS hour_count,
				        MAX(CASE WHEN created_at >= %s AND created_at <= %s{$before} THEN created_at ELSE NULL END) AS last_recent
				 FROM {$table}
				 WHERE recipient = %s AND status <> 'failed' AND channel <> 'passkey' AND created_at >= %s{$upto}",
				$at( HOUR_IN_SECONDS ),
				$at( max( 1, $limits['cooldown'] ) ),
				$soon,
				$recipient,
				$at( DAY_IN_SECONDS )
			)
		);

		if ( $limits['cooldown'] > 0 && $number && ! empty( $number->last_recent ) ) {
			$remaining = min( $limits['cooldown'], max( 1, $limits['cooldown'] - ( $now - Ashna_Helper::local_ts( $number->last_recent ) ) ) );
			return new WP_Error(
				'ashna_cooldown',
				sprintf( 'لطفاً %d ثانیه دیگر برای درخواست مجدد کد صبر کنید.', $remaining ),
				array( 'remaining' => $remaining )
			);
		}
		if ( $number && (int) $number->hour_count + $extra > $limits['number_hour'] ) {
			return new WP_Error( 'ashna_rate_limit_recipient', 'تعداد درخواست‌های کد برای این شماره/ایمیل در یک ساعت گذشته بیش از حد مجاز است. لطفاً کمی بعد تلاش کنید.' );
		}
		if ( $number && (int) $number->day_count + $extra > $limits['number_day'] ) {
			return new WP_Error( 'ashna_rate_limit_recipient_day', 'سقف دریافت کد برای این شماره/ایمیل در ۲۴ ساعت گذشته پر شده است. لطفاً فردا دوباره تلاش کنید.' );
		}

		// IP: per minute and per hour.
		$by_ip = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS hour_count, SUM(CASE WHEN created_at >= %s AND created_at <= %s THEN 1 ELSE 0 END) AS minute_count
				 FROM {$table}
				 WHERE ip_address = %s AND channel <> 'passkey' AND created_at >= %s{$upto}",
				$at( MINUTE_IN_SECONDS ),
				$soon,
				$ip,
				$at( HOUR_IN_SECONDS )
			)
		);
		if ( $by_ip && (int) $by_ip->minute_count + $extra > $limits['ip_minute'] ) {
			return new WP_Error( 'ashna_rate_limit_ip_minute', 'درخواست‌های ارسالی از آی‌پی شما در یک دقیقه گذشته بیش از حد مجاز است. لطفاً یک دقیقه دیگر تلاش کنید.', array( 'remaining' => 60 ) );
		}
		if ( $by_ip && (int) $by_ip->hour_count + $extra > $limits['ip_hour'] ) {
			return new WP_Error( 'ashna_rate_limit_ip', 'تعداد درخواست‌های ارسالی از آی‌پی شما بیش از حد مجاز است. لطفاً یک ساعت دیگر تلاش کنید.' );
		}

		// Network (/24 or /64): stops attackers rotating addresses inside one range.
		if ( $limits['subnet_minute'] > 0 && '' !== $subnet ) {
			$net_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE ip_subnet = %s AND channel <> 'passkey' AND created_at >= %s AND created_at <= %s{$upto}",
					$subnet,
					$at( MINUTE_IN_SECONDS ),
					$soon
				)
			);
			if ( $net_count + $extra > $limits['subnet_minute'] ) {
				return new WP_Error( 'ashna_rate_limit_subnet', 'درخواست‌های زیادی از شبکه‌ی شما ارسال شده است. لطفاً چند دقیقه دیگر تلاش کنید.', array( 'remaining' => 60 ) );
			}
		}

		// Site-wide credit guard.
		if ( $limits['daily_cap'] > 0 ) {
			$today = gmdate( 'Y-m-d 00:00:00', $now );
			$sent  = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE status <> 'failed' AND channel <> 'passkey' AND created_at >= %s{$upto}",
					$today
				)
			);
			if ( $sent + $extra > $limits['daily_cap'] ) {
				self::notify_admin_once( 'daily_cap', 'سقف ارسال روزانه آشنا پر شد', sprintf( "سقف ارسال روزانه‌ی کد در سایت %s (%d کد) پر شد و ارسال کد تا پایان امروز متوقف است.\nاگر این حجم طبیعی است، سقف را در «امنیت و ضد اسپم» افزایش دهید؛ در غیر این صورت احتمالاً سایت هدف حمله پیامکی است.", home_url(), $limits['daily_cap'] ) );
				return new WP_Error( 'ashna_daily_cap', 'ارسال کد تایید موقتاً در دسترس نیست. لطفاً بعداً تلاش کنید یا با پشتیبانی سایت تماس بگیرید.' );
			}
		}

		// phpcs:enable

		return true;
	}

	/**
	 * Switch on attack mode when the whole site receives an unusual burst.
	 */
	public static function maybe_enter_attack_mode() {
		$limits = self::limits();
		if ( $limits['attack_trigger'] <= 0 || self::attack_mode_active() ) {
			return;
		}
		global $wpdb;
		$table = Ashna_Logger::table_name();
		$now   = Ashna_Helper::local_now();
		$since = gmdate( 'Y-m-d H:i:s', $now - MINUTE_IN_SECONDS );
		$soon  = gmdate( 'Y-m-d H:i:s', $now + 5 ); // Rows from before a clock change are not "this minute".
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE channel <> 'passkey' AND created_at >= %s AND created_at <= %s", $since, $soon ) );
		if ( $count < $limits['attack_trigger'] ) {
			return;
		}

		$until = time() + $limits['attack_minutes'] * MINUTE_IN_SECONDS;
		update_option(
			self::ATTACK_OPTION,
			array(
				'since' => time(),
				'until' => $until,
				'peak'  => $count,
			),
			false
		);
		self::notify_admin_once(
			'attack',
			'حالت حمله در آشنا فعال شد',
			sprintf( "در یک دقیقه %d درخواست کد به سایت %s رسید. برای %d دقیقه پاسخ‌دادن به کپچا برای همه‌ی درخواست‌ها اجباری شد.\nجزئیات در «امنیت و ضد اسپم» و لاگ کدها.", $count, home_url(), $limits['attack_minutes'] )
		);
		do_action( 'ashna_otp_attack_mode_started', $count, $until );
	}

	/**
	 * Whether attack mode is on.
	 *
	 * @return bool
	 */
	public static function attack_mode_active() {
		$state = get_option( self::ATTACK_OPTION, array() );
		return is_array( $state ) && ! empty( $state['until'] ) && (int) $state['until'] > time();
	}

	/**
	 * Attack mode state for the admin screen.
	 *
	 * @return array|null
	 */
	public static function attack_mode_state() {
		return self::attack_mode_active() ? get_option( self::ATTACK_OPTION ) : null;
	}

	/**
	 * End attack mode now.
	 */
	public static function end_attack_mode() {
		delete_option( self::ATTACK_OPTION );
	}

	/**
	 * Whether a captcha must be solved for a new code request.
	 *
	 * @return bool
	 */
	public static function captcha_required() {
		$type = Ashna_Helper::get_option( 'captcha_type', 'none' );
		return ( 'none' !== $type && '' !== $type ) || self::attack_mode_active();
	}

	/**
	 * Record a refusal and pass the error through.
	 *
	 * @param WP_Error $error     Error.
	 * @param string   $recipient Recipient.
	 * @param string   $ip        IP.
	 * @return WP_Error
	 */
	public static function refuse( WP_Error $error, $recipient = '', $ip = '' ) {
		self::record_block( $error->get_error_code(), $recipient, $ip );
		return $error;
	}

	/**
	 * Count refused requests per day and keep the last few for the admin screen.
	 *
	 * @param string $reason    Error code.
	 * @param string $recipient Recipient.
	 * @param string $ip        IP.
	 */
	public static function record_block( $reason, $recipient = '', $ip = '' ) {
		$today = gmdate( 'Y-m-d', Ashna_Helper::local_now() );
		$stats = get_option( self::STATS_OPTION, array() );
		if ( ! is_array( $stats ) || ! isset( $stats['day'] ) || $today !== $stats['day'] ) {
			$stats = array(
				'day'    => $today,
				'total'  => 0,
				'counts' => array(),
				'recent' => array(),
			);
		}
		$reason = sanitize_key( $reason );
		++$stats['total'];
		$stats['counts'][ $reason ] = isset( $stats['counts'][ $reason ] ) ? $stats['counts'][ $reason ] + 1 : 1;
		array_unshift(
			$stats['recent'],
			array(
				'time'      => current_time( 'mysql' ),
				'reason'    => $reason,
				'recipient' => '' !== $recipient ? Ashna_Helper::mask_identifier( $recipient ) : '',
				'ip'        => $ip,
			)
		);
		$stats['recent'] = array_slice( $stats['recent'], 0, 15 );
		update_option( self::STATS_OPTION, $stats, false );
	}

	/**
	 * Today's refusal statistics.
	 *
	 * @return array
	 */
	public static function block_stats() {
		$stats = get_option( self::STATS_OPTION, array() );
		$today = gmdate( 'Y-m-d', Ashna_Helper::local_now() );
		if ( ! is_array( $stats ) || ! isset( $stats['day'] ) || $today !== $stats['day'] ) {
			return array(
				'day'    => $today,
				'total'  => 0,
				'counts' => array(),
				'recent' => array(),
			);
		}
		return $stats;
	}

	/**
	 * E-mail the site admin, at most once per 6 hours per topic.
	 *
	 * @param string $topic   Topic key.
	 * @param string $subject Subject.
	 * @param string $message Body.
	 */
	private static function notify_admin_once( $topic, $subject, $message ) {
		$key = 'ashna_otp_notified_' . sanitize_key( $topic );
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, 6 * HOUR_IN_SECONDS );
		/**
		 * Whether to e-mail the admin about a protection event.
		 *
		 * @param bool   $send  Send.
		 * @param string $topic attack|daily_cap.
		 */
		if ( apply_filters( 'ashna_otp_notify_admin', true, $topic ) ) {
			wp_mail( get_option( 'admin_email' ), '[' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . '] ' . $subject, $message );
		}
	}
}
