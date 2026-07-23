<?php

namespace Ollyo\PaymentHub\Payments\Razorpay;

use Exception;
use Throwable;
use ErrorException;
use Ollyo\PaymentHub\Core\Support\Arr;
use Ollyo\PaymentHub\Core\Support\View;
use Ollyo\PaymentHub\Core\Support\System;
use GuzzleHttp\Exception\RequestException;
use Ollyo\PaymentHub\Core\Payment\BasePayment;
use Ollyo\PaymentHub\Exceptions\InvalidDataException;
use Ollyo\PaymentHub\Contracts\Config\RepositoryContract;
use Ollyo\PaymentHub\Exceptions\InvalidSignatureException;

class Razorpay extends BasePayment {

	/**
	 * The RazorPay config Repository instance
	 *
	 * @var RepositoryContract
	 */
	protected $config;

	/**
	 * Store the instance of the payment gateway client
	 *
	 * @var string
	 */
	protected $client;

	/**
	 * Check if everything is OK before proceeding next.
	 *
	 * @return bool
	 */
	public function check(): bool {
		$configKeys = Arr::make( array( 'key_id', 'key_secret', 'mode', 'webhook_secret' ) );

		$isConfigOk = $configKeys->every(
			function ( $key ) {
				return $this->config->has( $key ) && ! empty( $this->config->get( $key ) );
			}
		);

		return $isConfigOk;
	}

	/**
	 * Setup the RazorPay settings for initiating the payment redirection.
	 *
	 * @return void
	 */
	public function setup(): void {
		$this->client = base64_encode( $this->config->get( 'key_id' ) . ':' . $this->config->get( 'key_secret' ) );
	}


	/**
	 * Sets data for the payment object.
	 *
	 * @param  object $data   The data to set on the object.
	 * @throws Throwable            If the parent `setData` method throws an error.
	 */
	public function setData( $data ): void {
		try {
			parent::setData( $data );
		} catch ( Throwable $error ) {
			throw $error;
		}
	}

	/**
	 * Creates a payment request and renders the RazorPay checkout form.
	 *
	 * @throws InvalidDataException if payment data is missing or incomplete.
	 * @throws ErrorException       if there is an error in the HTTP request.
	 * @since  1.0.0
	 */
	public function createPayment() {
		if ( empty( $this->getData() ) ) {
			throw new InvalidDataException( 'Payment Data is empty.' );
		}

		try {
			$data        = $this->getData();
			$amount      = RazorpayHelper::getTotalPrice( $data );
			$totalAmount = (int) System::getMinorAmountBasedOnCurrency( $amount, $data->currency->code );
			$currency    = strtoupper( $data->currency->code );
			$order       = static::createOrder( $totalAmount, $currency );

			$checkout = (object) array(
				'amount'            => $totalAmount,
				'currency'          => strtoupper( $data->currency->code ),
				'razorpay_order_id' => (string) $order->id,
				'order_id'          => $data->order_id,
				'username'          => $data->customer->name,
				'email'             => $data->customer->email,
				'store_name'        => $data->store_name,
				'description'       => $data->order_description,
				'callback_url'      => $this->config->get( 'success_url' ),
				'cancel_url'        => $this->config->get( 'cancel_url' ),
				'razorpay_url'      => $this->config->get( 'razorpay_form_url' ),
				'key_id'            => $this->config->get( 'key_id' ),
			);

			//phpcs:ignore 	WordPress.Security.EscapeOutput.OutputNotEscaped
			echo View::make( 'checkout-form', compact( 'checkout' ), __DIR__ . '/layout' )->render();
			exit;

		} catch ( RequestException $error ) {
			throw new ErrorException( esc_html( $error->getResponse()->getBody() ) );
		}
	}

	/**
	 * Verify the webhook signature and create the order data.
	 *
	 * @param object $payload   An associative array with (object) ['get' => $_GET, 'post' => $_POST, 'server' => $_SERVER, 'stream' => file_get_contents('php://input')]
	 * @return object
	 * @throws Throwable
	 */
	public function verifyAndCreateOrderData( object $payload ): object {
		$returnData = System::defaultOrderData();

		try {
			RazorpayHelper::verifyWebhookSignature( $payload, $this->config->get( 'webhook_secret' ) );

			$stream = json_decode( $payload->stream );

			if ( empty( $stream ) ) {
				throw new InvalidSignatureException( 'Invalid signature! RazorPay payload stream not found!' );
			}

			if ( ! in_array( $stream->event, RazorpayHelper::$eventMap ) ) {
				return new \stdClass();
			}

			$entity = $stream->payload->payment->entity;
			$status = $entity->status;

			if ( $status === 'captured' ) {
				$status = 'paid';
			}

			$charges                          = RazorpayHelper::calculateCharges( $entity );
			$returnData->id                   = $entity->notes->order_id;
			$returnData->payment_status       = $status;
			$returnData->payment_error_reason = $entity->error_reason;
			$returnData->transaction_id       = $entity->id;
			$returnData->tax_amount           = System::convertMinorAmountToMajor( $charges['tax'], $entity->currency );
			$returnData->fees                 = System::convertMinorAmountToMajor( $charges['fee'], strtoupper( $entity->currency ) );
			$returnData->earnings             = System::convertMinorAmountToMajor( $charges['earnings'], strtoupper( $entity->currency ) );
			$returnData->payment_method       = $this->config->get( 'name' );

			return $returnData;
		} catch ( Throwable $error ) {
			throw $error;
		}
	}

	/**
	 * Creates a new order by sending a request to the payment gateway.
	 *
	 * @param  int    $totalAmount    The total order amount in minor currency units.
	 * @param  string $currency       The currency code (e.g., USD, INR).
	 * @throws Exception                If the HTTP request fails.
	 * @since  1.0.0
	 */
	private function createOrder( $totalAmount, $currency ) {
		$url = $this->config->get( 'api_gateway_url' ) . '/orders';

		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => "Basic {$this->client}",
		);

		$data = array(
			'amount'          => $totalAmount,
			'currency'        => strtoupper( $currency ),
			'payment_capture' => 1,
		);

		$requestData = (object) array(
			'method'  => 'post',
			'url'     => $url,
			'options' => array(
				'headers' => $headers,
				'body'    => json_encode( $data ),
			),
		);

		return System::sendHttpRequest( $requestData );
	}
}
