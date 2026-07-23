<?php

namespace TutorRazorpay;

use Tutor\Ecommerce\Settings;
use Ollyo\PaymentHub\Payments\Razorpay\Config;
use TutorPro\Ecommerce\Config as EcommerceConfig;
use Tutor\PaymentGateways\Configs\PaymentUrlsTrait;
use Ollyo\PaymentHub\Contracts\Payment\ConfigContract;

/**
 * RazorpayConfig class.
 *
 * This class handles the configuration for the Razorpay payment gateway.
 * It extends the BaseConfig class and implements the ConfigContract interface.
 *
 * @since 1.0.0
 */
class RazorpayConfig extends Config implements ConfigContract {

	use PaymentUrlsTrait;

	/**
	 * Environment setting.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private $environment;

	/**
	 * Key id.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private $key_id;

	/**
	 * Key secret.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private $key_secret;

	/**
	 * Webhook signature key.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private $webhook_secret;

	/**
	 * The name of the payment gateway.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'razorpay';

	/**
	 * Constructor.
	 *
	 * Initializes the RazorpayConfig object.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct();
		$settings    = Settings::get_payment_gateway_settings( 'razorpay' );
		$config_keys = array_keys( EcommerceConfig::get_razorpay_config_keys() );
		foreach ( $config_keys as $key ) {
			if ( 'webhook_url' !== $key ) {
				$this->$key = $this->get_field_value( $settings, $key );
			}
		}
	}

	/**
	 * Retrieves the mode of the Razorpay payment gateway.
	 *
	 * @since 1.0.0
	 *
	 * @return string The mode of the payment gateway ('test' or 'live').
	 */
	public function getMode(): string {
		return $this->environment;
	}

	/**
	 * Key secret
	 *
	 * @return string
	 */
	public function getKeySecret(): string {
		return $this->key_secret;
	}

	/**
	 * Key id
	 *
	 * @return string
	 */
	public function getKeyID(): string {
		return $this->key_id;
	}

	/**
	 * Webhook secret
	 *
	 * @return string
	 */
	public function getWebhookSecretKey(): string {
		return $this->webhook_secret;
	}

	public function getRazorpayUrl() {
		return 'https://api.razorpay.com/v1/checkout/embedded';
	}

	/**
	 * Determine whether payment gateway configured properly
	 *
	 * @since 1.0.0
	 *
	 * @return boolean
	 */
	public function is_configured() {
		return $this->key_id && $this->key_secret && $this->webhook_secret;
	}

	/**
	 * Creates and updates the configuration for the payment gateway.
	 *
	 * @return void
	 */
	public function createConfig(): void {
		parent::createConfig();

		$config = array(
			'key_id'         => $this->getKeyID(),
			'key_secret'     => $this->getKeySecret(),
			'webhook_secret' => $this->getWebhookSecretKey(),
		);

		$this->updateConfig( $config );
	}
}
