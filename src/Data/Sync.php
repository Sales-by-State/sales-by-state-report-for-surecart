<?php
/**
 * Keeps the report table in step with SureCart orders.
 *
 * @package SalesByStateReportForSureCart
 */

namespace SBSSC\Data;

use SBSSC\Install\Schema;
use SureCart\Models\Checkout;
use SureCart\Models\Order;

defined( 'ABSPATH' ) || exit;

/**
 * Writes one row per order.
 *
 * Refunds are not modelled: an order that has been refunded carries a
 * canceled or similar status and is included or excluded by the status
 * filter like any other order.
 */
class Sync {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'surecart/checkout_confirmed', array( $this, 'on_checkout' ), 20, 1 );
		add_action( 'surecart/purchase_created', array( $this, 'on_purchase' ), 20, 1 );
	}

	/**
	 * Handle a confirmed checkout.
	 *
	 * @param mixed $checkout Checkout model.
	 * @return void
	 */
	public function on_checkout( $checkout ) {
		$order_id = $this->order_id_from( $checkout );

		if ( $order_id ) {
			$this->upsert( $order_id );
		}
	}

	/**
	 * Handle a purchase that may belong to an order.
	 *
	 * @param mixed $purchase Purchase model.
	 * @return void
	 */
	public function on_purchase( $purchase ) {
		$order_id = $this->order_id_from( $purchase );

		if ( $order_id ) {
			$this->upsert( $order_id );
		}
	}

	/**
	 * Insert or update the row for one order.
	 *
	 * @param string|object $order Order ID or model.
	 * @return bool
	 */
	public function upsert( $order ) {
		global $wpdb;

		if ( is_string( $order ) || is_numeric( $order ) ) {
			$order = $this->load_order( (string) $order );
		}

		if ( ! is_object( $order ) || is_wp_error( $order ) ) {
			return false;
		}

		$row = self::build_row( $order );

		if ( ! $row ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->replace(
			Schema::table(),
			$row,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f' )
		);
	}

	/**
	 * Remove the row for an order.
	 *
	 * @param string $order_id Order ID.
	 * @return void
	 */
	public function delete( $order_id ) {
		global $wpdb;

		$order_id = (string) $order_id;

		if ( ! $order_id ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( Schema::table(), array( 'order_id' => $order_id ), array( '%s' ) );
	}

	/**
	 * Build the row for a SureCart order.
	 *
	 * Money is stored in cents on the API and as decimals in the report table.
	 *
	 * @param object $order Order model.
	 * @return array<string,mixed>|false
	 */
	public static function build_row( $order ) {
		$order_id = isset( $order->id ) ? (string) $order->id : '';

		if ( ! $order_id ) {
			return false;
		}

		$checkout = self::checkout_of( $order );

		$billing  = self::address_of( $checkout, 'billing_address' );
		$shipping = self::address_of( $checkout, 'shipping_address' );

		$billing_country  = strtoupper( substr( (string) ( $billing['country'] ?? '' ), 0, 2 ) );
		$billing_state    = (string) ( $billing['state'] ?? '' );
		$shipping_country = strtoupper( substr( (string) ( $shipping['country'] ?? '' ), 0, 2 ) );
		$shipping_state   = (string) ( $shipping['state'] ?? '' );

		if ( '' === $shipping_country ) {
			$shipping_country = $billing_country;
			$shipping_state   = $billing_state;
		}

		$total          = self::from_cents( $checkout->total_amount ?? 0, $checkout );
		$tax            = self::from_cents( $checkout->tax_amount ?? 0, $checkout );
		$shipping_total = self::from_cents( $checkout->shipping_amount ?? 0, $checkout );

		if ( $tax <= 0 ) {
			$tax = self::from_cents( $checkout->tax_exclusive_amount ?? 0, $checkout );
		}

		if ( $tax <= 0 ) {
			$tax = self::from_cents( $checkout->tax_inclusive_amount ?? 0, $checkout );
		}

		$created = self::normalize_datetime( $order->created_at ?? null );
		$paid    = self::normalize_datetime( $checkout->paid_at ?? null );
		$sale    = self::sale_datetime( $order, $checkout );

		if ( $sale ) {
			$created = $sale;

			if ( in_array( self::normalize_status( $order->status ?? '' ), array( 'paid', 'processing' ), true ) ) {
				$paid = $sale;
			} elseif ( ! $paid ) {
				$paid = null;
			}
		}

		if ( ! $paid && in_array( self::normalize_status( $order->status ?? '' ), array( 'paid', 'processing' ), true ) ) {
			$paid = $created;
		}

		return array(
			'order_id'         => substr( $order_id, 0, 64 ),
			'status'           => substr( self::normalize_status( $order->status ?? '' ), 0, 32 ),
			'date_created'     => $created ? $created : '0000-00-00 00:00:00',
			'date_paid'        => $paid ? $paid : null,
			'billing_country'  => $billing_country,
			'billing_state'    => substr( $billing_state, 0, 50 ),
			'shipping_country' => $shipping_country,
			'shipping_state'   => substr( $shipping_state, 0, 50 ),
			'currency'         => strtoupper( substr( (string) ( $checkout->currency ?? 'usd' ), 0, 3 ) ),
			'total_sales'      => $total,
			'tax_total'        => $tax,
			'shipping_total'   => $shipping_total,
			'net_total'        => $total - $tax - $shipping_total,
		);
	}

	/**
	 * Load an order with addresses expanded.
	 *
	 * @param string $order_id Order ID.
	 * @return object|null
	 */
	private function load_order( $order_id ) {
		if ( ! class_exists( Order::class ) || ! $order_id ) {
			return null;
		}

		$order = Order::with( OrderSource::expands() )->find( $order_id );

		return is_wp_error( $order ) ? null : $order;
	}

	/**
	 * Checkout model on an order.
	 *
	 * @param object $order Order.
	 * @return object
	 */
	private static function checkout_of( $order ) {
		$checkout = $order->checkout ?? null;

		if ( is_string( $checkout ) && $checkout && class_exists( Checkout::class ) ) {
			$loaded   = Checkout::with( array( 'shipping_address', 'billing_address', 'invoice' ) )->find( $checkout );
			$checkout = is_wp_error( $loaded ) ? null : $loaded;
		}

		return is_object( $checkout ) ? $checkout : (object) array();
	}

	/**
	 * Sale datetime for the year filter.
	 *
	 * Prefers the invoice issue date when the checkout belongs to an invoice,
	 * then checkout.paid_at, then order.created_at.
	 *
	 * @param object $order    Order.
	 * @param object $checkout Checkout.
	 * @return string|null
	 */
	private static function sale_datetime( $order, $checkout ) {
		$invoice = self::invoice_of( $checkout );
		$issue   = is_object( $invoice ) ? self::normalize_datetime( $invoice->issue_date ?? null ) : null;

		if ( $issue ) {
			return $issue;
		}

		$paid = self::normalize_datetime( is_object( $checkout ) ? ( $checkout->paid_at ?? null ) : null );

		if ( $paid ) {
			return $paid;
		}

		return self::normalize_datetime( $order->created_at ?? null );
	}

	/**
	 * Invoice on a checkout, if expanded.
	 *
	 * @param object $checkout Checkout.
	 * @return object|null
	 */
	private static function invoice_of( $checkout ) {
		$invoice = is_object( $checkout ) ? ( $checkout->invoice ?? null ) : null;

		if ( is_object( $invoice ) ) {
			return $invoice;
		}

		return null;
	}

	/**
	 * Country and state from a checkout address relation.
	 *
	 * @param object $checkout Checkout.
	 * @param string $key      shipping_address or billing_address.
	 * @return array{country:string,state:string}
	 */
	private static function address_of( $checkout, $key ) {
		$address = is_object( $checkout ) ? ( $checkout->{$key} ?? null ) : null;

		if ( is_array( $address ) ) {
			return array(
				'country' => (string) ( $address['country'] ?? '' ),
				'state'   => (string) ( $address['state'] ?? '' ),
			);
		}

		if ( is_object( $address ) ) {
			return array(
				'country' => (string) ( $address->country ?? '' ),
				'state'   => (string) ( $address->state ?? '' ),
			);
		}

		return array(
			'country' => '',
			'state'   => '',
		);
	}

	/**
	 * Map SureCart API status onto the report filter keys.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private static function normalize_status( $status ) {
		$status = sanitize_key( (string) $status );

		if ( 'void' === $status ) {
			return 'canceled';
		}

		return $status;
	}

	/**
	 * Convert API cents to a decimal amount.
	 *
	 * @param mixed  $cents    Amount in cents.
	 * @param object $checkout Checkout, for zero-decimal currencies.
	 * @return float
	 */
	private static function from_cents( $cents, $checkout ) {
		$cents = (int) $cents;

		if ( is_object( $checkout ) && ! empty( $checkout->is_zero_decimal ) ) {
			return (float) $cents;
		}

		return round( $cents / 100, 2 );
	}

	/**
	 * Normalise a unix timestamp or datetime string.
	 *
	 * @param mixed $value Datetime.
	 * @return string|null
	 */
	private static function normalize_datetime( $value ) {
		if ( empty( $value ) || '0000-00-00 00:00:00' === $value ) {
			return null;
		}

		if ( is_numeric( $value ) ) {
			return gmdate( 'Y-m-d H:i:s', (int) $value );
		}

		$ts = strtotime( (string) $value );

		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : null;
	}

	/**
	 * Pull an order ID out of a SureCart event payload.
	 *
	 * @param mixed $payload Event payload.
	 * @return string
	 */
	private function order_id_from( $payload ) {
		if ( is_object( $payload ) && ! empty( $payload->order ) ) {
			$order = $payload->order;

			if ( is_object( $order ) && ! empty( $order->id ) ) {
				return (string) $order->id;
			}

			if ( is_string( $order ) ) {
				return $order;
			}
		}

		if ( is_object( $payload ) && isset( $payload->id ) && 0 === strpos( (string) $payload->id, 'order_' ) ) {
			return (string) $payload->id;
		}

		if ( is_array( $payload ) && ! empty( $payload['order'] ) ) {
			$order = $payload['order'];

			if ( is_array( $order ) && ! empty( $order['id'] ) ) {
				return (string) $order['id'];
			}

			if ( is_string( $order ) ) {
				return $order;
			}
		}

		return '';
	}
}
