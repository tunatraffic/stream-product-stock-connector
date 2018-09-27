<?php
/**
 * Plugin Name: Stream Product Stock Connector
 * Description: Logs product stock changes via Stream.
 * Author: Tuna Traffic
 * Author URI: https://tunatraffic.com
 * Version: 1.0
 * Requires PHP: 7.0
 *
 * GitHub Plugin URI: tunatraffic/stream-product-stock-connector
 */

namespace TunaTraffic\Stream;

function register_connector($connectors)
{
	require_once __DIR__ . '/src/ProductStockConnector.php';

	$connectors[] = new ProductStockConnector();

	return $connectors;
}
add_filter('wp_stream_connectors', __NAMESPACE__ . '\\register_connector');
