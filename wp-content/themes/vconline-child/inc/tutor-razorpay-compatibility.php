<?php
/**
 * Tutor LMS 4.2+ & Razorpay Compatibility Bridge
 *
 * Resolves compatibility issues between Tutor LMS 4.2+ and Tutor Razorpay plugin:
 * 1. Provides backwards-compatible Ollyo\PaymentHub\Core\Support\System class with
 *    currency conversion, default order data, and HTTP request handling methods.
 * 2. Suppresses the "Update Available" warning in Tutor LMS Pro Payment Methods settings.
 *
 * @package VCOnlineChild
 */

namespace Ollyo\PaymentHub\Core\Support;

use stdClass;
use ErrorException;
use Ollyo\PaymentHub\Exceptions\NotFoundException;
use Ollyo\PaymentHub\Exceptions\InvalidDataException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Ollyo\PaymentHub\Core\Support\System', false ) ) {

	/**
	 * System compatibility class for Ollyo PaymentHub.
	 */
	class System {

		/**
		 * Create an instance of the given class.
		 *
		 * @param string $class Fully qualified class name.
		 * @return object
		 * @throws NotFoundException If class does not exist.
		 */
		public static function createClassInstance( $class ) {
			if ( ! class_exists( $class ) ) {
				throw new NotFoundException( esc_html( sprintf( 'The class %s does not found!', $class ) ) );
			}

			return new $class();
		}

		/**
		 * Parse a URL, including URLs with multibyte (UTF-8) characters.
		 *
		 * @param string $url The URL to parse.
		 * @param int $component PHP_URL_* constant.
		 * @return mixed
		 */
		public static function parseUrl( $url, $component = -1 ) {
			if ( extension_loaded( 'mbstring' ) && mb_convert_encoding( $url, 'ISO-8859-1', 'UTF-8' ) === $url ) {
				return wp_parse_url( $url, $component );
			}

			$reservedUriCharactersMap = array(
				'%21' => '!',
				'%2A' => '*',
				'%27' => "'",
				'%28' => '(',
				'%29' => ')',
				'%3B' => ';',
				'%3A' => ':',
				'%40' => '@',
				'%26' => '&',
				'%3D' => '=',
				'%24' => '$',
				'%2C' => ',',
				'%2F' => '/',
				'%3F' => '?',
				'%23' => '#',
				'%5B' => '[',
				'%5D' => ']',
			);

			$parts = wp_parse_url( strtr( rawurlencode( $url ), $reservedUriCharactersMap ), $component );

			return $parts ? array_map( 'urldecode', $parts ) : $parts;
		}

		/**
		 * Helper to load Tutor vendor autoloader if Brick\Money is not yet loaded.
		 */
		private static function ensureMoneyLibraryLoaded(): void {
			if ( ! class_exists( '\Brick\Money\Money' ) ) {
				$tutor_path = function_exists( 'tutor' ) ? tutor()->path : ( defined( 'TUTOR_PATH' ) ? TUTOR_PATH : '' );
				$autoload   = $tutor_path . 'ecommerce/PaymentGateways/Paypal/vendor/autoload.php';
				if ( file_exists( $autoload ) ) {
					require_once $autoload;
				}
			}
		}

		/**
		 * Converts a major currency amount to its minor unit (e.g. INR to paise).
		 *
		 * @param float|string $amount The major currency amount.
		 * @param string $currency The currency code (e.g. INR, USD).
		 * @return int The minor currency amount.
		 */
		public static function getMinorAmountBasedOnCurrency( $amount, $currency ) {
			self::ensureMoneyLibraryLoaded();

			if ( ! empty( $amount ) ) {
				if ( class_exists( '\Tutor\PaymentGateways\Utils' ) ) {
					return (int) \Tutor\PaymentGateways\Utils::getMinorAmountBasedOnCurrency( $amount, $currency );
				}

				if ( class_exists( '\Brick\Money\Money' ) && class_exists( '\Brick\Math\RoundingMode' ) ) {
					return (int) \Brick\Money\Money::of( (float) $amount, $currency, null, \Brick\Math\RoundingMode::HALF_UP )->getMinorAmount()->toInt();
				}

				return (int) round( (float) $amount * 100 );
			}

			return 0;
		}

		/**
		 * Converts a minor currency amount to its major unit equivalent.
		 *
		 * @param int|string $amount The minor currency amount.
		 * @param string $currency The currency code.
		 * @return float Major currency amount.
		 */
		public static function convertMinorAmountToMajor( $amount, $currency ) {
			self::ensureMoneyLibraryLoaded();

			if ( ! empty( $amount ) ) {
				if ( class_exists( '\Tutor\PaymentGateways\Utils' ) ) {
					return (float) \Tutor\PaymentGateways\Utils::convertMinorAmountToMajor( $amount, $currency );
				}

				if ( class_exists( '\Brick\Money\Money' ) && class_exists( '\Brick\Math\RoundingMode' ) ) {
					return (float) \Brick\Money\Money::ofMinor( $amount, $currency, null, \Brick\Math\RoundingMode::HALF_UP )->getAmount()->toFloat();
				}

				return (float) ( $amount / 100 );
			}

			return 0.0;
		}

		/**
		 * Creates the default order data structure.
		 *
		 * @param string $type Type ('payment' or 'refund').
		 * @return object
		 */
		public static function defaultOrderData( $type = 'payment' ): object {
			if ( class_exists( '\Tutor\PaymentGateways\Utils' ) ) {
				return \Tutor\PaymentGateways\Utils::defaultOrderData( $type );
			}

			$returnData = new stdClass();

			if ( 'payment' === $type ) {
				$returnData = (object) array(
					'type'                 => 'payment',
					'id'                   => null,
					'payment_status'       => 'unpaid',
					'payment_error_reason' => '',
					'transaction_id'       => '',
					'payment_method'       => '',
					'payment_payload'      => '',
					'tax_amount'           => '',
					'fees'                 => '',
					'earnings'             => '',
				);
			} elseif ( 'refund' === $type ) {
				$returnData = (object) array(
					'type'                => 'refund',
					'id'                  => null,
					'refund_status'       => '',
					'refund_id'           => '',
					'payment_method'      => '',
					'refund_amount'       => '',
					'refund_error_reason' => '',
					'refund_payload'      => '',
				);
			}

			return $returnData;
		}

		/**
		 * Validates and sanitizes an email address.
		 *
		 * @param string $email Email address to validate.
		 * @return string Sanitized email.
		 * @throws InvalidDataException If invalid.
		 */
		public static function validateAndSanitizeEmailAddress( string $email ) {
			$sanitizeEmail = filter_var( $email, FILTER_SANITIZE_EMAIL );
			$validateEmail = filter_var( $email, FILTER_VALIDATE_EMAIL );

			if ( ! $sanitizeEmail || ! $validateEmail ) {
				throw new InvalidDataException( 'Invalid Email Address' );
			}

			return $email;
		}

		/**
		 * Extracts the first and last name parts from a full name string.
		 *
		 * @param string|null $name Full name.
		 * @return array
		 */
		public static function extractNameParts( $name ): array {
			if ( empty( $name ) ) {
				return array();
			}

			$nameParts = array_filter( explode( ' ', trim( $name ) ) );
			$lastName  = array_pop( $nameParts );
			$firstName = implode( ' ', $nameParts );

			return array( $firstName, $lastName );
		}

		/**
		 * Splits the address into two parts if it exceeds max length.
		 *
		 * @param object $data Address data.
		 * @param int $maxLength Maximum length for address1.
		 * @return array
		 */
		public static function splitAddress( $data, $maxLength ) {
			if ( empty( $data->address1 ) ) {
				return array();
			}

			$address_1 = mb_strimwidth( $data->address1, 0, $maxLength );
			$address_2 = ( strlen( $data->address1 ) > $maxLength ) ? mb_strimwidth( $data->address1, $maxLength, $maxLength ) : ( $data->address2 ?? '' );

			return array( $address_1, $address_2 );
		}

		/**
		 * Determines if total amount is zero.
		 *
		 * @param object $data Charge data.
		 * @return bool
		 */
		public static function isTotalAmountZero( &$data ): bool {
			$data->subtotal        ??= 0;
			$data->tax             ??= 0;
			$data->shipping_charge ??= 0;
			$data->coupon_discount ??= 0;

			$totalAmount = ( $data->subtotal + $data->tax + $data->shipping_charge ) - $data->coupon_discount;

			return empty( filter_var( $totalAmount, FILTER_VALIDATE_FLOAT ) );
		}

		/**
		 * Updates and returns webhook URL with encoded parameters.
		 *
		 * @param object $config Configuration object.
		 * @return string
		 */
		public static function updateWebhookUrl( $config ): string {
			$encodedUrlData = base64_encode(
				wp_json_encode(
					array(
						'success_url' => $config->get( 'success_url' ),
						'cancel_url'  => $config->get( 'cancel_url' ),
					)
				)
			);

			$webhookUrl = add_query_arg(
				array(
					'encodedData'    => $encodedUrlData,
					'payment_method' => $config->get( 'name' ),
				),
				$config->get( 'webhook_url' )
			);

			return $webhookUrl;
		}

		/**
		 * Sends an HTTP request for order creation and gateway communication.
		 *
		 * Replaces the old Guzzle-based client with WordPress HTTP API or Tutor Http.
		 *
		 * @param object $requestData Request data object with url, method, and options.
		 * @return object|null JSON-decoded response.
		 * @throws ErrorException If HTTP request fails.
		 */
		public static function sendHttpRequest( $requestData ) {
			$options = (array) ( $requestData->options ?? array() );
			$args    = array(
				'method'  => strtoupper( $options['method'] ?? $requestData->method ?? 'POST' ),
				'headers' => $options['headers'] ?? array(),
				'body'    => $options['body'] ?? array(),
				'timeout' => 30,
			);

			$response = wp_remote_request( $requestData->url, $args );

			if ( is_wp_error( $response ) ) {
				throw new ErrorException( esc_html( $response->get_error_message() ) );
			}

			$status = wp_remote_retrieve_response_code( $response );
			$body   = wp_remote_retrieve_body( $response );

			if ( $status >= 400 ) {
				throw new ErrorException( esc_html( 'Razorpay API returned error (' . $status . '): ' . $body ) );
			}

			return json_decode( $body );
		}
	}
}

namespace VCOnlineChild\TutorCompatibility;

/**
 * Suppress "Update Available" warning in Tutor LMS Pro Payment Methods settings.
 */
add_filter(
	'tutor_pro_payment_gateways',
	function ( $gateways ) {
		if ( isset( $gateways['razorpay'] ) ) {
			$gateways['razorpay']['update_available'] = false;
			if ( ! empty( $gateways['razorpay']['latest_version'] ) ) {
				$gateways['razorpay']['active_version'] = $gateways['razorpay']['latest_version'];
			}
		}
		return $gateways;
	},
	99
);

/**
 * Filter all_plugins to report Razorpay version as up to date.
 */
add_filter(
	'all_plugins',
	function ( $plugins ) {
		$plugin_file = 'tutor-razorpay/tutor-razorpay.php';
		if ( isset( $plugins[ $plugin_file ] ) ) {
			$plugins[ $plugin_file ]['Version'] = '1.1.1';
		}
		return $plugins;
	},
	99
);

/**
 * Handle the "Update Now" AJAX action gracefully without error.
 *
 * If the "Update Now" button was clicked in an existing admin session,
 * intercept the request and return success instead of failing with
 * "Payment gateway download failed" / "Invalid license".
 */
add_action(
	'wp_ajax_tutor_install_payment_gateway',
	function () {
		if ( isset( $_POST['slug'] ) && 'razorpay' === $_POST['slug'] ) {
			if ( function_exists( 'tutor_utils' ) ) {
				tutor_utils()->checking_nonce();
				tutor_utils()->check_current_user_capability();
			}

			wp_send_json(
				array(
					'status_code' => 200,
					'message'     => __( 'Razorpay payment gateway is up to date.', 'tutor-pro' ),
					'data'        => array(),
				)
			);
			exit;
		}
	},
	1
);
