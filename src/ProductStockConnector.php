<?php

namespace TunaTraffic\Stream;

use WC_Product;

class ProductStockConnector extends \WP_Stream\Connector
{
	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public $name = 'product-stock';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public $actions = [
		'woocommerce_product_set_stock',
		'woocommerce_variation_set_stock',
	];

	public function get_label()
	{
		return 'Product Stock';
	}

	/**
	 * Master callback for all actions.
	 */
	public function callback()
	{
		// The method signature of this overloaded method must match the parent
		// so we have to get the passed arguments like so, or PHP will not be happy about it.
		$product = func_get_arg(0);

		if (! $product instanceof WC_Product || ! $product->managing_stock()) {
			return;
		}

		$message = '"%1$s" stock for %2$s product set to %3$d';

		if ($previous = $this->get_previous_stock()) {
			$message = '"%1$s" stock for %2$s product set from %4$d to %3$d';
		}

		$this->log(
			$message,
			[
				$product->get_formatted_name(),
				$product->get_type(),
				$product->get_stock_quantity('edit'),
				$previous,
			],
			$product->get_id(),
			$this->context(),
			'set_stock',
			get_current_user_id()
	    );
	}

	protected function context()
	{
		if (defined('WP_CLI')) {
			return 'wp-cli';
		}
		if (defined('REST_REQUEST')) {
			return 'rest';
		}
		if (defined('WOOCOMMERCE_CHECKOUT') && WOOCOMMERCE_CHECKOUT) {
			return 'checkout';
		}
		if (wp_doing_ajax()) {
			return 'ajax';
		}
		if (is_admin()) {
			return 'admin';
		}

		return 'front';
	}

	public function get_context_labels()
	{
		return [
			'wp-cli' => 'WP-CLI',
			'rest'   => 'REST API',
			'checkout' => 'Checkout',
			'front'  => 'Front End',
			'admin'  => 'WP Admin',
			'ajax'   => 'Ajax',
		];
	}

	public function get_action_labels()
	{
		return [];
	}

	protected function get_previous_stock()
	{
		$trace = debug_backtrace();

		while ($frame = array_shift($trace)) {
			if (! $function = $frame['function'] ?? '') {
				continue;
			}

			// Stock has not been changed on the product instance yet
			if ('wc_update_product_stock' === $function && $frame['args'][0] instanceof WC_Product) {
				return $frame['args'][0]->get_stock_quantity('edit');
			}

			// Stock was modified on the product instance and saved
			if ('save' === $function && $frame['object'] instanceof WC_Product && 'WC_Product' === ($frame['class'] ?? '')) {
				return $this->extract_previous_stock_quantity(clone $frame['object']);
			}
		}

		return false;
	}

	/**
	 * Get the previous stock quantity from the product instance.
	 *
	 * WC does not make a product's data available, but accessed via prop getters and setters.
	 * If a data (prop) has been changed, the getter will return the changed value instead of the original.
	 * This is a way to get the original value by force.
	 *
	 * @param WC_Product $product
	 *
	 * @return bool
	 */
	protected function extract_previous_stock_quantity(WC_Product $product)
	{
		if (! in_array('stock_quantity', array_keys($product->get_changes()))) {
			return false;
		}

		try {
			$product_data = new \ReflectionProperty($product, 'data');
			$product_data->setAccessible(true);
			$data = $product_data->getValue($product);
		} catch (\ReflectionException $e) {
			return false;
		}

		return $data['stock_quantity'];
	}
}
