<?php
/**
 * Gateway Manager, Fast Failover Engine & Multi-Channel Dispatcher
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Gateway_Manager {

	/**
	 * Custom registered SMS gateways
	 *
	 * @var array<string, Ashna_Gateway_Interface>
	 */
	private static $custom_gateways = array();

	/**
	 * Register a custom SMS gateway driver programmatically
	 *
	 * @param Ashna_Gateway_Interface $gateway Gateway instance.
	 */
	public static function register_gateway( Ashna_Gateway_Interface $gateway ) {
		self::$custom_gateways[ $gateway->get_id() ] = $gateway;
	}

	/**
	 * Get all available SMS gateways
	 *
	 * @return array<string, Ashna_Gateway_Interface>
	 */
	public static function get_sms_gateways() {
		$gateways = array_merge(
			array(
				'sandbox'     => new Ashna_Gateway_Sandbox(),
				'smsir'       => new Ashna_Gateway_Smsir(),
				'kavenegar'   => new Ashna_Gateway_Kavenegar(),
				'melipayamak' => new Ashna_Gateway_Melipayamak(),
				'farazsms'    => new Ashna_Gateway_Farazsms(),
				'ippanel'     => new Ashna_Gateway_Ippanel(),
			),
			self::$custom_gateways
		);

		return apply_filters( 'ashna_otp_sms_gateways', $gateways );
	}

	/**
	 * Get active SMS gateway instance
	 *
	 * @return Ashna_Gateway_Interface
	 */
	public static function get_active_sms_gateway() {
		$gateways  = self::get_sms_gateways();
		$active_id = Ashna_Helper::get_option( 'active_sms_gateway', 'sandbox' );

		if ( isset( $gateways[ $active_id ] ) ) {
			return $gateways[ $active_id ];
		}

		return $gateways['sandbox'];
	}

	/**
	 * Get ordered list of configured backup SMS gateway instances (up to 3 priorities)
	 *
	 * @return array<int, Ashna_Gateway_Interface>
	 */
	public static function get_backup_sms_gateways() {
		$gateways  = self::get_sms_gateways();
		$active_id = Ashna_Helper::get_option( 'active_sms_gateway', 'sandbox' );

		$b1 = Ashna_Helper::get_option( 'backup_sms_gateway_1', Ashna_Helper::get_option( 'backup_sms_gateway', 'none' ) );
		$b2 = Ashna_Helper::get_option( 'backup_sms_gateway_2', 'none' );
		$b3 = Ashna_Helper::get_option( 'backup_sms_gateway_3', 'none' );

		$candidates = array( $b1, $b2, $b3 );
		$backups    = array();
		$seen       = array( $active_id => true );

		foreach ( $candidates as $gw_id ) {
			if ( ! empty( $gw_id ) && 'none' !== $gw_id && empty( $seen[ $gw_id ] ) && isset( $gateways[ $gw_id ] ) ) {
				$backups[]      = $gateways[ $gw_id ];
				$seen[ $gw_id ] = true;
			}
		}

		return $backups;
	}

	/**
	 * Ordered SMS chain: the primary gateway followed by up to three backups.
	 *
	 * @return array<int, Ashna_Gateway_Interface>
	 */
	public static function get_sms_chain() {
		return array_merge( array( self::get_active_sms_gateway() ), self::get_backup_sms_gateways() );
	}

	/**
	 * Whether a mobile OTP can fall back to another gateway or channel.
	 *
	 * @return bool
	 */
	public static function has_fallback() {
		$strategy = Ashna_Helper::get_option( 'mobile_delivery_channel', 'sms' );
		if ( in_array( $strategy, array( 'bale_fallback_sms', 'sms_fallback_bale', 'both' ), true ) ) {
			return true;
		}
		return 'bale' !== $strategy && count( self::get_backup_sms_gateways() ) > 0;
	}

	/**
	 * Delivery channel a gateway belongs to.
	 *
	 * @param Ashna_Gateway_Interface $gateway Gateway.
	 * @return string sms | bale | email
	 */
	private static function channel_of( $gateway ) {
		$id = $gateway->get_id();
		return in_array( $id, array( 'bale', 'email' ), true ) ? $id : 'sms';
	}

	/**
	 * Try gateways in order until one delivers (the fast-failover engine).
	 *
	 * - Every attempt has a short time slot and the whole dispatch shares one
	 *   budget (Ashna_Failover), so a hung provider cannot stall the visitor.
	 * - Gateways whose circuit is open are skipped while a healthy alternative
	 *   exists, and only tried as a last resort with a short time slot.
	 * - After a cooldown, one quick trial decides whether a gateway is back.
	 *
	 * @param array<int, Ashna_Gateway_Interface> $gateways  Ordered gateways.
	 * @param string                              $recipient Recipient.
	 * @param string                              $code      OTP code.
	 * @param bool                                $force     Ignore circuit state (admin tests).
	 * @return array{result: true|WP_Error, gateway: Ashna_Gateway_Interface|null, attempted: Ashna_Gateway_Interface|null, steps: array}
	 */
	public static function send_via_chain( array $gateways, $recipient, $code, $force = false ) {
		$queue    = array();
		$deferred = array();
		$steps    = array();

		foreach ( $gateways as $gateway ) {
			$state = $force ? 'closed' : Ashna_Failover::state( $gateway->get_id() );
			if ( 'open' === $state ) {
				$deferred[] = array( $gateway, 'last_resort' );
			} else {
				$queue[] = array( $gateway, $state );
			}
		}
		$queue = array_merge( $queue, $deferred );

		$last_error = null;
		$attempted  = null;
		$tried      = array(); // Gateway IDs actually called.
		$blocked    = array(); // Channels whose recipient was rejected.

		foreach ( $queue as $position => $item ) {
			list( $gateway, $state ) = $item;
			$id                      = $gateway->get_id();
			$channel                 = self::channel_of( $gateway );

			if ( isset( $blocked[ $channel ] ) ) {
				$steps[] = array(
					'id'      => $id,
					'title'   => $gateway->get_title(),
					'outcome' => 'skip',
					'type'    => 'input',
				);
				continue;
			}

			if ( ! Ashna_Failover::has_time_for_attempt() ) {
				foreach ( array_slice( $queue, $position ) as $left ) {
					$steps[] = array(
						'id'      => $left[0]->get_id(),
						'title'   => $left[0]->get_title(),
						'outcome' => 'budget',
					);
				}
				if ( null === $last_error ) {
					$last_error = new WP_Error( 'ashna_time_exhausted', 'مهلت ارسال کد به پایان رسید؛ لطفاً دوباره تلاش کنید.' );
				}
				break;
			}

			$slot = Ashna_Failover::gateway_timeout();
			if ( 'trial' === $state ) {
				Ashna_Failover::claim_trial( $id );
				$slot = min( $slot, Ashna_Failover::TRIAL_TIMEOUT );
			} elseif ( 'last_resort' === $state ) {
				$slot = min( $slot, Ashna_Failover::TRIAL_TIMEOUT );
			}

			Ashna_Failover::start_attempt( $slot );
			Ashna_Abstract_Gateway::reset_trace();
			$started = microtime( true );
			try {
				$result = $gateway->send( $recipient, $code );
			} catch ( Throwable $e ) {
				$result = new WP_Error( 'ashna_gateway_exception', 'خطای داخلی درگاه: ' . $e->getMessage() );
			}
			$ms = (int) round( ( microtime( true ) - $started ) * 1000 );
			Ashna_Failover::end_attempt();
			$attempted    = $gateway;
			$tried[ $id ] = true;

			if ( ! is_wp_error( $result ) ) {
				Ashna_Failover::record_success( $id, $ms );
				$steps[] = array(
					'id'      => $id,
					'title'   => $gateway->get_title(),
					'outcome' => 'ok',
					'ms'      => $ms,
				);
				return array(
					'result'    => true,
					'gateway'   => $gateway,
					'attempted' => $gateway,
					'steps'     => array_merge( self::skipped_steps( $deferred, $tried ), $steps ),
				);
			}

			$trace  = $gateway instanceof Ashna_Abstract_Gateway ? Ashna_Abstract_Gateway::get_trace() : null;
			$type   = Ashna_Failover::classify( $result, $trace );
			$opened = 'budget' !== $type && Ashna_Failover::record_failure( $id, $type, $result->get_error_message(), $ms );

			$steps[] = array(
				'id'      => $id,
				'title'   => $gateway->get_title(),
				'outcome' => 'fail',
				'type'    => $type,
				'ms'      => $ms,
				'message' => $result->get_error_message(),
			);

			/**
			 * Fires after a gateway attempt fails.
			 *
			 * @param string   $id     Gateway ID.
			 * @param string   $type   Error type (see Ashna_Failover::classify()).
			 * @param WP_Error $result Gateway error.
			 * @param int      $ms     Attempt duration in milliseconds.
			 * @param bool     $opened Whether the gateway is now sidelined.
			 */
			do_action( 'ashna_otp_gateway_failure', $id, $type, $result, $ms, $opened );

			$last_error = $result;
			if ( 'input' === $type ) {
				$blocked[ $channel ] = true;
			}
		}

		return array(
			'result'    => $last_error ? $last_error : new WP_Error( 'ashna_no_gateway', 'هیچ درگاهی برای ارسال کد در دسترس نیست.' ),
			'gateway'   => null,
			'attempted' => $attempted,
			'steps'     => array_merge( self::skipped_steps( $deferred, $tried ), $steps ),
		);
	}

	/**
	 * Trace entries for sidelined gateways that were never called.
	 *
	 * @param array $deferred Deferred queue items.
	 * @param array $tried    IDs of gateways that were called.
	 * @return array
	 */
	private static function skipped_steps( $deferred, $tried ) {
		$steps = array();
		foreach ( $deferred as $item ) {
			$id = $item[0]->get_id();
			if ( empty( $tried[ $id ] ) ) {
				$steps[] = array(
					'id'      => $id,
					'title'   => $item[0]->get_title(),
					'outcome' => 'skip',
					'type'    => 'circuit',
				);
			}
		}
		return $steps;
	}

	/**
	 * Send SMS via the primary gateway with the fast failover chain.
	 *
	 * @param string $recipient         Normalized phone.
	 * @param string $code              OTP code.
	 * @param string $forced_gateway_id Optional specific gateway ID to test (bypasses the circuit breaker).
	 * @param bool   $force             Ignore circuit state (admin tests).
	 * @return array { result: true|WP_Error, gateway_id: string, message: string, steps: array }
	 */
	public static function send_sms_with_failover( $recipient, $code, $forced_gateway_id = '', $force = false ) {
		$gateways = self::get_sms_gateways();
		if ( ! empty( $forced_gateway_id ) && isset( $gateways[ $forced_gateway_id ] ) ) {
			$chain = array( $gateways[ $forced_gateway_id ] );
			$force = true;
		} else {
			$chain = self::get_sms_chain();
		}

		$run = self::send_via_chain( $chain, $recipient, $code, $force );
		$gw  = $run['gateway'] ? $run['gateway'] : ( $run['attempted'] ? $run['attempted'] : $chain[0] );

		return array(
			'result'     => $run['result'],
			'gateway_id' => $gw->get_id(),
			'message'    => self::summarize( $run, $chain ),
			'steps'      => $run['steps'],
		);
	}

	/**
	 * Short human message for a chain run.
	 *
	 * @param array $run   Result of send_via_chain().
	 * @param array $chain Gateways in configured order.
	 * @return string
	 */
	private static function summarize( $run, $chain ) {
		if ( is_wp_error( $run['result'] ) ) {
			return $run['result']->get_error_message();
		}

		$gateway  = $run['gateway'];
		$position = array_search( $gateway, $chain, true );
		if ( 'bale' === $gateway->get_id() ) {
			return 'ارسال موفق از طریق پیام‌رسان بله';
		}
		if ( 0 === $position || false === $position ) {
			return 'ارسال موفق از طریق ' . $gateway->get_title();
		}

		$labels = array( 1 => 'اول', 2 => 'دوم', 3 => 'سوم' );
		$label  = isset( $labels[ $position ] ) ? $labels[ $position ] : (string) $position;
		return sprintf( 'ارسال از طریق پشتیبان %s: %s', $label, $gateway->get_title() );
	}

	/**
	 * Deliver an OTP according to the configured strategy, within the failover budget.
	 *
	 * @param string $recipient         Normalized phone or email.
	 * @param string $code              OTP code.
	 * @param string $identifier_type   'phone' or 'email'.
	 * @param string $forced_channel    Forced channel ('sms', 'bale', 'email').
	 * @param string $forced_gateway_id Forced SMS gateway ID.
	 * @return array{result: true|WP_Error, channel: string, gateway: string, message: string, steps: array}
	 */
	private static function deliver( $recipient, $code, $identifier_type, $forced_channel, $forced_gateway_id ) {
		// Admin tests always reach the requested gateway, even while it is sidelined.
		$force = '' !== (string) $forced_channel || '' !== (string) $forced_gateway_id;

		if ( 'email' === $identifier_type || 'email' === $forced_channel ) {
			$run = self::send_via_chain( array( new Ashna_Gateway_Email() ), $recipient, $code, true );
			return array(
				'result'  => $run['result'],
				'channel' => 'email',
				'gateway' => 'email',
				'message' => is_wp_error( $run['result'] ) ? $run['result']->get_error_message() : 'ارسال موفق به ایمیل',
				'steps'   => $run['steps'],
			);
		}

		$strategy = ! empty( $forced_channel ) ? $forced_channel : Ashna_Helper::get_option( 'mobile_delivery_channel', 'sms' );
		$gateways = self::get_sms_gateways();
		$sms      = ( ! empty( $forced_gateway_id ) && isset( $gateways[ $forced_gateway_id ] ) )
			? array( $gateways[ $forced_gateway_id ] )
			: self::get_sms_chain();
		$bale     = new Ashna_Gateway_Bale();

		if ( 'both' === $strategy ) {
			return self::deliver_both( $sms, $bale, $recipient, $code, $force );
		}

		switch ( $strategy ) {
			case 'bale':
				$chain = array( $bale );
				break;
			case 'bale_fallback_sms':
				$chain = array_merge( array( $bale ), $sms );
				break;
			case 'sms_fallback_bale':
				$chain = array_merge( $sms, array( $bale ) );
				break;
			case 'sms':
			default:
				$chain = $sms;
				break;
		}

		$run     = self::send_via_chain( $chain, $recipient, $code, $force );
		$gateway = $run['gateway'] ? $run['gateway'] : ( $run['attempted'] ? $run['attempted'] : $chain[0] );
		$channel = 'bale' === $gateway->get_id() ? 'bale' : 'sms';

		if ( is_wp_error( $run['result'] ) ) {
			$message = $run['result']->get_error_message();
		} elseif ( 'bale' === $channel ) {
			$message = 'bale' === $chain[0]->get_id() ? 'ارسال موفق از طریق پیام‌رسان بله' : 'ارسال از طریق بله پس از عدم موفقیت پیامک';
		} elseif ( 'bale' === $chain[0]->get_id() ) {
			$message = sprintf( 'ارسال از طریق پیامک با %s، پس از عدم موفقیت بله', $gateway->get_title() );
		} else {
			$message = self::summarize( $run, $sms );
		}

		return array(
			'result'  => $run['result'],
			'channel' => $channel,
			'gateway' => $gateway->get_id(),
			'message' => $message,
			'steps'   => $run['steps'],
		);
	}

	/**
	 * "Both" strategy: SMS chain and Bale. Succeeds when either delivers.
	 *
	 * @param array                   $sms       SMS chain.
	 * @param Ashna_Gateway_Interface $bale      Bale gateway.
	 * @param string                  $recipient Recipient.
	 * @param string                  $code      OTP code.
	 * @param bool                    $force     Ignore circuit state.
	 * @return array
	 */
	private static function deliver_both( $sms, $bale, $recipient, $code, $force ) {
		$sms_run = self::send_via_chain( $sms, $recipient, $code, $force );
		$sms_ok  = ! is_wp_error( $sms_run['result'] );

		// SMS already delivered: a sidelined Bale is skipped instead of delaying the visitor.
		if ( $sms_ok && ! $force && 'open' === Ashna_Failover::state( $bale->get_id() ) ) {
			$bale_run = array(
				'result' => new WP_Error( 'ashna_skipped', 'skipped' ),
				'steps'  => array(
					array(
						'id'      => $bale->get_id(),
						'title'   => $bale->get_title(),
						'outcome' => 'skip',
						'type'    => 'circuit',
					),
				),
			);
		} else {
			$bale_run = self::send_via_chain( array( $bale ), $recipient, $code, $force );
		}
		$bale_ok = ! is_wp_error( $bale_run['result'] );

		$sms_gw = $sms_run['gateway'] ? $sms_run['gateway'] : ( $sms_run['attempted'] ? $sms_run['attempted'] : $sms[0] );
		$steps  = array_merge( $sms_run['steps'], $bale_run['steps'] );

		if ( $sms_ok && $bale_ok ) {
			return array(
				'result'  => true,
				'channel' => 'sms+bale',
				'gateway' => $sms_gw->get_id() . '+bale',
				'message' => 'ارسال همزمان پیامک و بله',
				'steps'   => $steps,
			);
		}
		if ( $sms_ok || $bale_ok ) {
			return array(
				'result'  => true,
				'channel' => $sms_ok ? 'sms' : 'bale',
				'gateway' => $sms_ok ? $sms_gw->get_id() : 'bale',
				'message' => $sms_ok ? self::summarize( $sms_run, $sms ) : 'ارسال موفق از طریق پیام‌رسان بله',
				'steps'   => $steps,
			);
		}
		return array(
			'result'  => $sms_run['result'],
			'channel' => 'sms+bale',
			'gateway' => $sms_gw->get_id() . '+bale',
			'message' => $sms_run['result']->get_error_message(),
			'steps'   => $steps,
		);
	}

	/**
	 * Dispatch OTP code to recipient and log result
	 *
	 * @param string $recipient         Normalized phone or email.
	 * @param string $identifier_type   'phone' or 'email'.
	 * @param string $forced_channel    Optional forced channel ('sms', 'bale', 'email').
	 * @param string $forced_gateway_id Optional forced SMS gateway ID for testing.
	 * @return array|WP_Error { code, channel, gateway, log_id, response, elapsed_ms, trace }
	 */
	public static function dispatch_otp( $recipient, $identifier_type = 'phone', $forced_channel = '', $forced_gateway_id = '' ) {
		$length   = absint( Ashna_Helper::get_option( 'otp_length', 5 ) );
		$code     = Ashna_Helper::generate_otp_code( $length );
		$otp_hash = wp_hash_password( $code );

		$started = microtime( true );
		Ashna_Failover::begin();
		try {
			$outcome = self::deliver( $recipient, $code, $identifier_type, (string) $forced_channel, (string) $forced_gateway_id );
		} finally {
			Ashna_Failover::end();
		}
		$elapsed_ms = (int) round( ( microtime( true ) - $started ) * 1000 );

		$send_result  = $outcome['result'];
		$is_error     = is_wp_error( $send_result );
		$response_msg = $is_error ? $send_result->get_error_message() : $outcome['message'];
		$trace        = Ashna_Failover::describe( $outcome['steps'] );

		$log_id = Ashna_Logger::insert(
			array(
				'recipient'        => $recipient,
				'channel'          => $outcome['channel'],
				'gateway'          => $outcome['gateway'],
				'otp_code'         => $code,
				'otp_hash'         => $otp_hash,
				'status'           => $is_error ? 'failed' : 'sent',
				'response_message' => '' !== $trace ? $response_msg . ' | مسیر: ' . $trace : $response_msg,
			)
		);

		if ( $is_error ) {
			return $send_result;
		}

		do_action( 'ashna_otp_sent', $recipient, $code, $outcome['channel'], $outcome['gateway'], $log_id );

		return array(
			'code'       => $code,
			'channel'    => $outcome['channel'],
			'gateway'    => $outcome['gateway'],
			'log_id'     => $log_id,
			'response'   => $response_msg,
			'elapsed_ms' => $elapsed_ms,
			'trace'      => $trace,
		);
	}
}
