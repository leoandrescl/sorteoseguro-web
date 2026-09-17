<?php
/**
 * Template: Oferta bono precargado (Sorteo Seguro)
 *
 * Superficie /bonificaciones/{slug}/ — mismo layout que compra directa + packs filtrados + bono sesión.
 */
if (!defined('ABSPATH')) {
	exit;
}

if (!have_posts()) {
	status_header(404);
	nocache_headers();
	include get_query_template('404');
	return;
}
the_post();

$product_id = class_exists('SorteoSeguro_Comprar_Bono') ? SorteoSeguro_Comprar_Bono::current_product_id() : 0;
$product    = ($product_id > 0 && function_exists('wc_get_product')) ? wc_get_product($product_id) : null;

if (!$product) {
	status_header(404);
	nocache_headers();
	include get_query_template('404');
	return;
}

$GLOBALS['product'] = $product;
$GLOBALS['ss_comprar_product']    = $product;
$GLOBALS['ss_comprar_product_id'] = $product_id;
global $post;
$post = get_post($product_id);
if ($post instanceof WP_Post) {
	setup_postdata($post);
}

$sales_open     = class_exists('SorteoSeguro_Comprar_Bono') && SorteoSeguro_Comprar_Bono::product_is_available($product_id);
$checkout_ready = $sales_open;

$ss = class_exists('SorteoSeguro_Comprar_Bono') ? SorteoSeguro_Comprar_Bono::product_config($product_id) : [];
$ss = array_merge([
	'intro'           => '',
	'details'         => [],
	'prizes'          => [],
	'discover_title'  => 'CARACTERÍSTICAS',
	'discover_icon'   => 'star',
	'discover_lead'   => '',
	'stats'           => [],
	'features'        => [],
	'checklist'       => [],
	'value'           => null,
	'bases_url'       => '',
	'notaria_img'     => 'https://sorteoseguro.cl/wp-content/uploads/2025/07/logo-notaria.jpg',
], is_array($ss) ? $ss : []);

get_header();
require WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-comprar/layout-comprar.php';
get_footer();
wp_reset_postdata();
