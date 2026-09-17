<?php

namespace Ollyo\PaymentHub\Payments\Razorpay;

use Ollyo\PaymentHub\Core\Payment\BaseConfig;
use Ollyo\PaymentHub\Contracts\Payment\ConfigContract;


class Config extends BaseConfig implements ConfigContract
{
	protected $name = 'razorpay';

	const API_GATEWAY_URL 	= 'https://api.razorpay.com/v1/';
	const RAZORPAY_FORM_URL = 'https://api.razorpay.com/v1/checkout/embedded';

	public function __construct()
	{
		parent::__construct();
	}


	public function getMode(): string
	{
		return 'test';
	}

	public function getWebhookSecretKey(): string
	{
		return 'fhgviosrthgishsru';
	}

	public function getWebhookUrl(): string
	{
		return 'https://example.com/webhook';
	}

	public function getSuccessUrl(): string
	{
		return 'https://example.com/success';
	}

	public function getCancelUrl(): string
	{
		return 'https://example.com/cancel';	
	}

	private function getKeyID(): string
	{
		return 'rzp_test_iza71DEOGsRBze';
	}

	private function getKeySecret() : string
	{
		return 'WMhqHlwbZBztMWUlp8tSQc7h';
	}

	public function createConfig(): void
	{
		parent::createConfig();

		$config = [
			'key_id' 			=> $this->getKeyID(),
			'key_secret' 		=> $this->getKeySecret(),
			'webhook_secret' 	=> $this->getWebhookSecretKey(),
			'api_gateway_url'   => self::API_GATEWAY_URL,
			'razorpay_form_url' => self::RAZORPAY_FORM_URL
		];

		$this->updateConfig($config);
	}
}