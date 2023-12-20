<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\E2e;

use WooCommerce\PayPalCommerce\PPCP;
use Psr\Container\ContainerInterface;
use WC_Cart;
use WC_Customer;
use WC_Session;

class TestCase extends \PHPUnit\Framework\TestCase
{
	protected $container;

	protected function getContainer(): ContainerInterface {
		return PPCP::container();
	}

	protected function cart(): WC_Cart {
		return WC()->cart;
	}

	protected function customer(): WC_Customer {
		return WC()->customer;
	}

	protected function session(): WC_Session {
		return WC()->session;
	}
}
