<?php
/**
 * Template Name: Compra directa — índice (/comprar/)
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

$items = [];
if (class_exists('SorteoSeguro_Comprar')) {
	foreach (SorteoSeguro_Comprar::product_slugs() as $slug => $product_id) {
		$url = SorteoSeguro_Comprar::comprar_child_url_for_product((int) $product_id);
		if ($url === '') {
			continue;
		}
		$product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
		$title   = $product ? $product->get_name() : get_the_title($product_id);
		$open    = SorteoSeguro_Comprar::product_is_available((int) $product_id);
		$items[] = [
			'url'   => $url,
			'title' => $title,
			'open'  => $open,
			'img'   => $product ? wp_get_attachment_image_url((int) $product->get_image_id(), 'medium') : '',
		];
	}
}

get_header();
?>
<main class="ss-comprar-index-page">
	<div class="ss-comprar-index-page__shell">
		<header class="ss-comprar-index-page__head">
			<h1>Compra directa</h1>
			<p>Elige un sorteo activo para seleccionar tu DigiPack y pagar en la misma página.</p>
		</header>
		<?php if ($items) : ?>
			<div class="ss-comprar-index-page__grid">
				<?php foreach ($items as $item) : ?>
					<article class="ss-comprar-index-card">
						<?php if (!empty($item['img'])) : ?>
							<img class="ss-comprar-index-card__img" src="<?php echo esc_url($item['img']); ?>" alt="" loading="lazy">
						<?php endif; ?>
						<div class="ss-comprar-index-card__body">
							<h2><?php echo esc_html($item['title']); ?></h2>
							<?php if ($item['open']) : ?>
								<a class="ss-comprar-index-card__btn" href="<?php echo esc_url($item['url']); ?>">Comprar DigiTickets</a>
							<?php else : ?>
								<p class="ss-comprar-index-card__closed">Ventas cerradas</p>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="ss-comprar-index-page__empty">No hay sorteos disponibles en compra directa.</p>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
