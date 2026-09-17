<?php

namespace Ollyo\PaymentHub\Payments\Razorpay;

use Brick\Money\Money;
use Brick\Math\RoundingMode;
use Brick\Money\CurrencyConverter;
use Brick\Money\ExchangeRateProvider\ConfigurableProvider;
use Ollyo\PaymentHub\Exceptions\InvalidSignatureException;

final class RazorpayHelper
{
    const SHA256 = 'sha256';

    /**
     * Verifies the webhook signature to ensure the payload is authentic.
     *
     * @param   object                      $payload            The webhook payload, containing server headers and body data.
     * @param   string                      $webhookSecretKey   The secret key for calculating the expected signature.
     * @throws  InvalidSignatureException                       if the signature does not match.
     * @since   1.0.0
     */
    public static function verifyWebhookSignature($payload, $webhookSecretKey)
    {
        $actualSignature   = $payload->server['HTTP_X_RAZORPAY_SIGNATURE'];
        $expectedSignature = hash_hmac(self::SHA256, $payload->stream, $webhookSecretKey);

        // Use lang's built-in hash_equals if exists to mitigate timing attacks
        $verified = function_exists('hash_equals') ? hash_equals($expectedSignature, $actualSignature) : static::hashEquals($expectedSignature, $actualSignature);

        if (!$verified) {
            throw new InvalidSignatureException('Invalid Signature Passed');
        }
    }

    /**
     * Compares two strings in a way that mitigates timing attacks.
     *
     * @param   string $expectedSignature   The expected signature.
     * @param   string $actualSignature     The actual signature received.
     * @return  bool                        True if the signatures match, false otherwise.
     * @since   1.0.0
     */
    private static function hashEquals($expectedSignature, $actualSignature): bool
    {
        if (strlen($expectedSignature) === strlen($actualSignature))
        {
            $res    = $expectedSignature ^ $actualSignature;
            $return = 0;

            for ($i = strlen($res) - 1; $i >= 0; $i--)
            {
                $return |= ord($res[$i]);
            }

            return $return === 0;
        }

        return false;
    }

    /**
	 * Calculates the charges based on the payment payload.
	 *
	 * @param  object $paymentPayload 	The payment data containing currency, fee, tax, and base amount.
	 * @return array 					An associative array containing 'fee', 'tax', and 'earnings'.
	 * @since  1.0.0
	 */
	public static function calculateCharges($paymentPayload): array
	{
		$isForeignCurrency 	= $paymentPayload->currency !== 'INR';
		$fee 				= $isForeignCurrency ? static::convertedAmount($paymentPayload->fee, $paymentPayload) : $paymentPayload->fee;
		$tax 				= $isForeignCurrency ? static::convertedAmount($paymentPayload->tax, $paymentPayload) : $paymentPayload->tax;
		$baseAmount 		= $isForeignCurrency ? static::convertedAmount($paymentPayload->base_amount, $paymentPayload) : $paymentPayload->amount;
		$earnings 			= $baseAmount - $fee;

		return [
			'fee' 		=> $fee,
			'tax' 		=> $tax,
			'earnings' 	=> $earnings
		];
	}

    /**
	 * Converts the given amount from the base currency to the specified currency
	 * using the exchange rate from the payment payload.
	 *
	 * @param  float  $amount 	The amount to convert.
	 * @param  object $payload 	The payment payload.
	 * @return float 			The converted amount in the target currency.
	 * @since  1.0.0
	 */
	private static function convertedAmount($amount, $payload): float
	{
		$settlementCurrency = $payload->base_currency;
		$currency   		= $payload->currency;
		$exchangeRate 		= $payload->amount / $payload->base_amount ;

		$exchangeRateProvider = new ConfigurableProvider();
		$exchangeRateProvider->setExchangeRate($settlementCurrency, $currency, $exchangeRate);

		$money 				= Money::of($amount, $settlementCurrency);
		$converter 			= new CurrencyConverter($exchangeRateProvider);
		$convertedAmount 	= $converter->convert($money, $currency, null, RoundingMode::HALF_UP);

		return $convertedAmount->getAmount()->toFloat();
	}

    /**
	 * Retrieves the total price from the data.
	 *
	 * If the total price is 0 or 0.00, it returns a default value based on the currency:
	 * - Returns "1" if the currency is INR.
	 * - Returns "0.1" for all other currencies.
	 *
	 * If the total price is non-zero, it returns the actual total price.
	 *
	 * @return string The total price or a default value based on the currency.
	 * @since  1.0.0
	 */
	public static function getTotalPrice($data): string
	{
		if (empty(filter_var($data->total_price, FILTER_VALIDATE_FLOAT))) {
			return strtoupper($data->currency->code) === 'INR' ? '1' : '0.1';
		}

		return (string) $data->total_price;
	}

    /**
	 * This array holds the list of webhook events that the system monitors.
	 * @var 	array $eventMap
	 * @since 	1.0.0
	 */
    public static array $eventMap = ['payment.captured', 'payment.failed'];
}