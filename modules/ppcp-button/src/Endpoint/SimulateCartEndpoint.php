<?php
/**
 * Endpoint to simulate adding products to the cart.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;

/**
 * Class SimulateCartEndpoint
 */
class SimulateCartEndpoint implements EndpointInterface {

	const ENDPOINT = 'ppc-simulate-cart';

	/**
	 * The cart object.
	 *
	 * @var \WC_Cart
	 */
	private $cart;

	/**
	 * The request data helper.
	 *
	 * @var RequestData
	 */
	private $request_data;

	/**
	 * The product data store.
	 *
	 * @var \WC_Data_Store
	 */
	private $product_data_store;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * ChangeCartEndpoint constructor.
	 *
	 * @param \WC_Cart        $cart The current WC cart object.
	 * @param RequestData     $request_data The request data helper.
	 * @param \WC_Data_Store  $product_data_store The data store for products.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		\WC_Cart $cart,
		RequestData $request_data,
		\WC_Data_Store $product_data_store,
		LoggerInterface $logger
	) {

		$this->cart               = clone $cart;
		$this->request_data       = $request_data;
		$this->product_data_store = $product_data_store;
		$this->logger             = $logger;
	}

	/**
	 * The nonce.
	 *
	 * @return string
	 */
	public static function nonce(): string {
		return self::ENDPOINT;
	}

	/**
	 * Handles the request.
	 *
	 * @return bool
	 */
	public function handle_request(): bool {
		try {
			return $this->handle_data();
		} catch ( Exception $error ) {
			$this->logger->error( 'Cart simulation failed: ' . $error->getMessage() );

			wp_send_json_error(
				array(
					'name'    => is_a( $error, PayPalApiException::class ) ? $error->name() : '',
					'message' => $error->getMessage(),
					'code'    => $error->getCode(),
					'details' => is_a( $error, PayPalApiException::class ) ? $error->details() : array(),
				)
			);
			return false;
		}
	}

	/**
	 * Handles the request data.
	 *
	 * @return bool
	 * @throws Exception On error.
	 */
	private function handle_data(): bool {
		$data     = $this->request_data->read_request( $this->nonce() );
		$products = $this->products_from_data( $data );
		if ( ! $products ) {
			wp_send_json_error(
				array(
					'name'    => '',
					'message' => __(
						'Necessary fields not defined. Action aborted.',
						'woocommerce-paypal-payments'
					),
					'code'    => 0,
					'details' => array(),
				)
			);
			return false;
		}

		$this->cart->empty_cart( false );
		$success = true;
		foreach ( $products as $product ) {
			$success = $success && ( ! $product['product']->is_type( 'variable' ) ) ?
				$this->add_product( $product['product'], $product['quantity'] )
				: $this->add_variable_product(
					$product['product'],
					$product['quantity'],
					$product['variations']
				);
		}
		if ( ! $success ) {
			$this->handle_error();
			return $success;
		}

		$this->cart->calculate_totals();

		$total = $this->cart->get_total( 'numeric' );

		unset( $this->cart );

		wp_send_json_success(
			array(
				'total' => $total,
			)
		);
		return $success;
	}

	/**
	 * Handles errors.
	 *
	 * @return bool
	 */
	private function handle_error(): bool {

		$message = __(
			'Something went wrong. Action aborted',
			'woocommerce-paypal-payments'
		);
		$errors  = wc_get_notices( 'error' );
		if ( count( $errors ) ) {
			$message = array_reduce(
				$errors,
				static function ( string $add, array $error ): string {
					return $add . $error['notice'] . ' ';
				},
				''
			);
			wc_clear_notices();
		}

		wp_send_json_error(
			array(
				'name'    => '',
				'message' => $message,
				'code'    => 0,
				'details' => array(),
			)
		);
		return true;
	}

	/**
	 * Returns product information from an data array.
	 *
	 * @param array $data The data array.
	 *
	 * @return array|null
	 */
	private function products_from_data( array $data ) {

		$products = array();

		if (
			! isset( $data['products'] )
			|| ! is_array( $data['products'] )
		) {
			return null;
		}
		foreach ( $data['products'] as $product ) {
			if ( ! isset( $product['quantity'] ) || ! isset( $product['id'] ) ) {
				return null;
			}

			$wc_product = wc_get_product( (int) $product['id'] );

			if ( ! $wc_product ) {
				return null;
			}
			$products[] = array(
				'product'    => $wc_product,
				'quantity'   => (int) $product['quantity'],
				'variations' => isset( $product['variations'] ) ? $product['variations'] : null,
			);
		}
		return $products;
	}

	/**
	 * Adds a product to the cart.
	 *
	 * @param \WC_Product $product The Product.
	 * @param int         $quantity The Quantity.
	 *
	 * @return bool
	 * @throws Exception When product could not be added.
	 */
	private function add_product( \WC_Product $product, int $quantity ): bool {
		return false !== $this->cart->add_to_cart( $product->get_id(), $quantity );
	}

	/**
	 * Adds variations to the cart.
	 *
	 * @param \WC_Product $product The Product.
	 * @param int         $quantity The Quantity.
	 * @param array       $post_variations The variations.
	 *
	 * @return bool
	 * @throws Exception When product could not be added.
	 */
	private function add_variable_product(
		\WC_Product $product,
		int $quantity,
		array $post_variations
	): bool {

		$variations = array();
		foreach ( $post_variations as $key => $value ) {
			$variations[ $value['name'] ] = $value['value'];
		}

		$variation_id = $this->product_data_store->find_matching_product_variation( $product, $variations );

		// ToDo: Check stock status for variation.
		return false !== $this->cart->add_to_cart(
			$product->get_id(),
			$quantity,
			$variation_id,
			$variations
		);
	}

}
