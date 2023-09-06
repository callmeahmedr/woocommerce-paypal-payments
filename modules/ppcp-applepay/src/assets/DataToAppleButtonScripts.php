<?php
/**
 * Prepares the necessary data for the Apple button script.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Applepay\Assets;

/**
 * Class DataToAppleButtonScripts
 */
class DataToAppleButtonScripts {

	/**
	 * The URL to the SDK.
	 *
	 * @var string
	 */
	private $sdk_url;
	/**
	 * The settings.
	 *
	 * @var array
	 */
	private $settings;

	public function __construct($sdk_url, $settings) {
		$this->sdk_url = $sdk_url;
		$this->settings = $settings;
	}

	/**
	 * Sets the appropriate data to send to ApplePay script
	 * Data differs between product page and cart page
	 *
	 * @param bool $is_block
	 * @return array
	 */
	public function apple_pay_script_data( bool $is_block = false ): array {
		$base_location   = wc_get_base_location();
		$shop_country_code = $base_location['country'];
		$currency_code    = get_woocommerce_currency();
		$total_label      = get_bloginfo( 'name' );
		if ( is_product() ) {
			return $this->data_for_product_page(
				$shop_country_code,
				$currency_code,
				$total_label
			);
		}

		return $this->data_for_cart_page(
			$shop_country_code,
			$currency_code,
			$total_label
		);
	}

	/**
	 * Check if the product needs shipping
	 *
	 * @param $product
	 *
	 * @return bool
	 */
	protected function check_if_need_shipping($product ) {
		if (
			! wc_shipping_enabled()
			|| 0 === wc_get_shipping_method_count(
				true
			)
		) {
			return false;
		}
		$needs_shipping = false;

		if ( $product->needs_shipping() ) {
			$needs_shipping = true;
		}

		return $needs_shipping;
	}

	/**
	 * @param $shop_country_code
	 * @param $currency_code
	 * @param $tota_label
	 *
	 * @return array
	 */
	protected function data_for_product_page(
		$shop_country_code,
		$currency_code,
		$total_label
	) {
		$product = wc_get_product( get_the_id() );
		if ( ! $product ) {
			return array();
		}
		$is_variation = false;
		if ( $product->get_type() === 'variable' || $product->get_type() === 'variable-subscription' ) {
			$is_variation = true;
		}
		$product_need_shipping = $this->check_if_need_shipping( $product );
		$product_id           = get_the_id();
		$product_price        = $product->get_price();
		$product_stock        = $product->get_stock_status();
		$type                = $this->settings->get('applepay_button_type');
		$color                = $this->settings->get('applepay_button_color');
		$lang                 = $this->settings->get('applepay_button_language');

		return array(
			'sdk_url' => $this->sdk_url,
			'button'  => array(
				'wrapper'           => '#applepay-container',
				'mini_cart_wrapper' => '#applepay-container-minicart',
				'type' => $type,
				'color' => $color,
				'lang'  => $lang,
			),
			'product' => array(
				'needShipping' => $product_need_shipping,
				'id'           => $product_id,
				'price'        => $product_price,
				'isVariation'  => $is_variation,
				'stock'        => $product_stock,
			),
			'shop'    => array(
				'countryCode'  => $shop_country_code,
				'currencyCode' => $currency_code,
				'totalLabel'   => $total_label,
			),
			'ajax_url' => admin_url( 'admin-ajax.php' ),
		);
	}

	/**
	 * @param $shop_country_code
	 * @param $currency_code
	 * @param $total_label
	 *
	 * @return array
	 */
	protected function data_for_cart_page(
		$shop_country_code,
		$currency_code,
		$total_label
	) {

		$cart         = WC()->cart;
		$nonce        = wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' );
		$button_markup =
			'<div id="applepay-container">'
			. $nonce
			. '</div>';
		return array(
			'sdk_url' => $this->sdk_url,
			'button'  => array(
				'wrapper'           => '#applepay-container',
				'mini_cart_wrapper' => '#applepay-container-minicart',
			),
			'product'      => array(
				'needShipping' => $cart->needs_shipping(),
				'subtotal'     => $cart->get_subtotal(),
			),
			'shop'         => array(
				'countryCode'  => $shop_country_code,
				'currencyCode' => $currency_code,
				'totalLabel'   => $total_label,
			),
			'ajax_url'      => admin_url( 'admin-ajax.php' ),
			'buttonMarkup' => $button_markup,
		);
	}
}
