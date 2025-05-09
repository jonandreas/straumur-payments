<?php

/**
 * Straumur Order Handler Class
 *
 * Handles order status transitions for Straumur payments.
 *
 * @package Straumur\Payments
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Straumur\Payments;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

use WC_Order;
use WP_Error;
use WC_Logger;

use function wc_get_logger;
use function wc_get_order;
use function round;
use function get_woocommerce_currency;

class WC_Straumur_Order_Handler
{

	/**
	 * Logger instance.
	 *
	 * @var \WC_Logger
	 */
	private WC_Logger $logger;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct()
	{
		$this->logger = wc_get_logger();
	}

	/**
	 * Handle cancellation (On-hold -> Cancelled).
	 *
	 * @param int $order_id Order ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function handle_cancellation(int $order_id)
	{
		$order = wc_get_order($order_id);
		if (! $order) {
			return new WP_Error('no_order', __('Invalid order.', 'straumur-payments-for-woocommerce'));
		}

		if ($order->get_payment_method() !== 'straumur') {
			return new WP_Error('not_straumur', __('Order not paid with Straumur.', 'straumur-payments-for-woocommerce'));
		}

		$payfac_reference = $order->get_meta('_straumur_payfac_reference');
		$reference        = $order->get_order_number();

		if (empty($payfac_reference) || empty($reference)) {
			return new WP_Error('no_reference', __('Cannot cancel: missing payment references.', 'straumur-payments-for-woocommerce'));
		}

		$api = new WC_Straumur_API();
		$this->logger->info("Attempting Straumur payment cancellation for order #{$order_id}", array('source' => 'straumur-payments-for-woocommerce'));

		$response = $api->reverse($reference, $payfac_reference);
		if ($response) {
			// Mark as "cancel requested" so that the webhook can recognize it as a cancellation event
			$order->update_meta_data('_straumur_cancel_requested', 'yes');
			$order->save();

			$order->add_order_note(__('Straumur payment cancellation request sent', 'straumur-payments-for-woocommerce'));
			return true;
		}

		$this->logger->error("Cancellation request failed for order #{$order_id}", array('source' => 'straumur-payments'));
		return new WP_Error('cancellation_failed', __('Failed to send cancellation request to Straumur.', 'straumur-payments-for-woocommerce'));
	}

	public function handle_capture(int $order_id)
	{
		$order = wc_get_order($order_id);
		if (! $order) {
			return new WP_Error('no_order', __('Invalid order.', 'straumur-payments-for-woocommerce'));
		}

		if ($order->get_payment_method() !== 'straumur') {
			return new WP_Error('not_straumur', __('Order not paid with Straumur.', 'straumur-payments-for-woocommerce'));
		}

		$payfac_reference = $order->get_meta('_straumur_payfac_reference');
		$reference        = $order->get_order_number();
		if (empty($payfac_reference) || empty($reference)) {
			return new WP_Error('no_reference', __('Cannot capture: missing payment references.', 'straumur-payments-for-woocommerce'));
		}

		// Call Straumur's API to request the capture
		$amount       = (float) $order->get_total();
		$amount_minor = (int) round($amount * 100);
		$currency     = get_woocommerce_currency();
		$api          = new WC_Straumur_API();

		$response = $api->capture($payfac_reference, $reference, $amount_minor, $currency);
		if ($response) {
			// Instead of payment_complete(), just add meta that capture was requested
			$order->update_meta_data('_straumur_capture_requested', 'yes');
			$order->add_order_note(__('Straumur capture request sent. Awaiting Straumur confirmation via webhook.', 'straumur'));
			$order->save();

			return true;
		}

		$this->logger->error("Capture request failed for order #{$order_id}", array('source' => 'straumur-payments'));
		return new WP_Error('capture_failed', __('Failed to send capture request to Straumur.', 'straumur-payments-for-woocommerce'));
	}

	/**
	 * Handle refund (Processing -> Refunded or Completed -> Refunded).
	 * This is triggered when the order status is manually changed in WC admin.
	 *
	 * @param int $order_id Order ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function handle_refund(int $order_id)
	{
		$order = wc_get_order($order_id);
		if (! $order) {
			return new WP_Error('no_order', __('Invalid order.', 'straumur-payments-for-woocommerce'));
		}

		// Ensure order was paid with Straumur
		if ($order->get_payment_method() !== $this->id) { // Assuming $this->id is 'straumur' in your gateway class context
			return new WP_Error('not_straumur', __('Order not paid with Straumur.', 'straumur-payments-for-woocommerce'));
		}

		// Prevent processing if already fully refunded in WooCommerce
		if ($order->get_total_refunded() >= $order->get_total()) {
			$order->add_order_note(__('Order is already fully refunded in WooCommerce.', 'straumur-payments-for-woocommerce'));
			// You might still want to check with Straumur if a refund was requested previously but not confirmed by webhook
			// For simplicity here, we assume WC is the source of truth for *already processed* refunds.
			return true;
		}

		$payfac_reference = $order->get_meta('_straumur_payfac_reference');
		$reference        = $order->get_order_number(); // Or whatever Straumur expects as 'reference'

		if (empty($payfac_reference) || empty($reference)) {
			return new WP_Error('no_reference', __('Cannot refund: missing payment references for Straumur.', 'straumur-payments-for-woocommerce'));
		}

		$api = new WC_Straumur_API(); // Ensure your API class is instantiated correctly
		$this->logger->info("Attempting Straumur payment refund for order #{$order_id}", array('source' => 'straumur-payments'));

		// Based on instructions, refund also uses reverse call
		$response = $api->reverse($reference, $payfac_reference); // Make sure $reference and $payfac_reference are what Straumur expects for a refund

		if ($response && !is_wp_error($response)) { // Check if API call was successful
			// Mark as "refund requested" so that the webhook can recognize it as a plugin-initiated refund event
			$order->update_meta_data('_straumur_refund_requested', 'yes');
			// Note: $order->save() will be called later or after WC refund creation

			$order->add_order_note(__('Straumur refund request has been sent to Straumur.', 'straumur-payments-for-woocommerce'));
			$this->logger->info("Refund request successful for order #{$order_id}. Straumur Response: " . wp_json_encode($response), array('source' => 'straumur-payments'));

			// --- Create WooCommerce Refund Object for FULL refund ---
			$refund_amount = $order->get_total(); // For a full refund
			$refund_reason = __('Order refunded via Straumur API after manual status change.', 'straumur-payments-for-woocommerce');

			// Prepare line items for full refund (recommended for accuracy with taxes/shipping)
			$line_items_to_refund = array();
			foreach ($order->get_items() as $item_id => $item) {
				$line_items_to_refund[$item_id] = array(
					'qty'          => $item->get_quantity(),
					'refund_total' => wc_format_decimal($item->get_total()),
					'refund_tax'   => array_map('wc_format_decimal', $item->get_taxes()['total']),
				);
			}
			// Refund shipping if applicable
			foreach ($order->get_items('shipping') as $item_id => $item) {
				$line_items_to_refund[$item_id] = array(
					'refund_total' => wc_format_decimal($item->get_total()),
					'refund_tax'   => array_map('wc_format_decimal', $item->get_taxes()['total']),
				);
			}
			// Refund fees if applicable
			foreach ($order->get_items('fee') as $item_id => $item) {
				$line_items_to_refund[$item_id] = array(
					'refund_total' => wc_format_decimal($item->get_total()),
					'refund_tax'   => array_map('wc_format_decimal', $item->get_taxes()['total']),
				);
			}


			$refund_args = array(
				'amount'         => $refund_amount,
				'reason'         => $refund_reason,
				'order_id'       => $order_id,
				'line_items'     => $line_items_to_refund,
				'refund_payment' => false, // IMPORTANT: Set to false because the payment is refunded via your API call, not WooCommerce itself.
				'restock_items'  => true   // Or false, or make it a plugin setting.
			);

			$wc_refund = wc_create_refund($refund_args);

			if (is_wp_error($wc_refund)) {
				$error_message = $wc_refund->get_error_message();
				$this->logger->error("Failed to create WooCommerce refund object for order #{$order_id}: {$error_message}", array('source' => 'straumur-payments'));
				$order->add_order_note(sprintf(__('Straumur refund processed via API, but failed to create WC refund object: %s', 'straumur-payments-for-woocommerce'), $error_message));
			} else {
				$order->add_order_note(sprintf(__('WooCommerce refund object created successfully. Refund ID: %s', 'straumur-payments-for-woocommerce'), $wc_refund->get_id()));
				// Optionally, if your API response contains a Straumur refund transaction ID, store it on the WC refund object:
				// if (isset($response->straumur_refund_id)) { // Adjust based on actual response structure
				//    $wc_refund->update_meta_data('_straumur_refund_transaction_id', $response->straumur_refund_id);
				//    $wc_refund->set_transaction_id($response->straumur_refund_id); // Also set the standard WC transaction ID
				//    $wc_refund->save();
				// }
			}
			// --- End WooCommerce Refund Object Creation ---

			$order->save(); // Save meta data and order notes

			// Check if this order is related to a subscription and cancel it
			if (function_exists('wcs_order_contains_subscription') || function_exists('wcs_get_subscriptions_for_order')) {
				$subscriptions = function_exists('wcs_get_subscriptions_for_order') ?
					wcs_get_subscriptions_for_order($order_id, array('order_type' => 'any')) : array();

				if (! empty($subscriptions)) {
					foreach ($subscriptions as $subscription) {
						if ($subscription->has_status(array('active', 'on-hold'))) { // Check for active or on-hold
							try {
								$subscription->update_status('cancelled', __('Subscription cancelled due to refunded parent order payment.', 'straumur-payments-for-woocommerce'));
								$this->logger->info("Subscription #{$subscription->get_id()} cancelled due to refund of order #{$order_id}", array('source' => 'straumur-payments'));
							} catch (Exception $e) {
								$this->logger->error("Error cancelling subscription #{$subscription->get_id()}: " . $e->getMessage(), array('source' => 'straumur-payments'));
							}
						}
					}
				}
			}

			return true;
		} else {
			$error_message = is_wp_error($response) ? $response->get_error_message() : __('Unknown API error', 'straumur-payments-for-woocommerce');
			$this->logger->error("Straumur refund API request failed for order #{$order_id}. Error: {$error_message}", array('source' => 'straumur-payments'));
			$order->add_order_note(sprintf(__('Failed to send refund request to Straumur. Error: %s', 'straumur-payments-for-woocommerce'), $error_message));
			$order->save();
			return new WP_Error('refund_api_failed', sprintf(__('Failed to send refund request to Straumur: %s', 'straumur-payments-for-woocommerce'), $error_message));
		}
	}
}
