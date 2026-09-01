<?php
/**
 * Layout compra directa simplificado: media + premios + características | título + packs + checkout.
 */
if (!defined('ABSPATH')) {
	exit;
}

require_once WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-pdp/icons.php';

$product_id = isset($product_id) ? (int) $product_id : (int) ($GLOBALS['ss_comprar_product_id'] ?? 0);
if ($product_id <= 0 && class_exists('SorteoSeguro_Comprar')) {
	$product_id = SorteoSeguro_Comprar::current_product_id();
}

$product = $GLOBALS['ss_comprar_product'] ?? ($GLOBALS['product'] ?? null);
if (!$product instanceof WC_Product && $product_id > 0 && function_exists('wc_get_product')) {
	$product = wc_get_product($product_id);
}
if (!$product instanceof WC_Product) {
	return;
}
$GLOBALS['product'] = $product;

$title = $product->get_name();

$ss = is_array($ss ?? null) ? $ss : [];
$ss = array_merge([
	'prizes'         => [],
	'discover_title' => 'CARACTERÍSTICAS',
	'discover_icon'  => 'star',
	'discover_lead'  => '',
	'stats'          => [],
	'features'       => [],
	'checklist'      => [],
	'bases_url'      => '',
], $ss);

$sales_open = isset($sales_open) ? (bool) $sales_open : true;
$checkout_ready = isset($checkout_ready) ? (bool) $checkout_ready : $sales_open;

$gallery_ids = [];
$thumb_id    = (int) $product->get_image_id();
if ($thumb_id) {
	$gallery_ids[] = $thumb_id;
}
foreach ($product->get_gallery_image_ids() as $gid) {
	$gid = (int) $gid;
	if ($gid && !in_array($gid, $gallery_ids, true)) {
		$gallery_ids[] = $gid;
	}
}

$gallery = [];
foreach ($gallery_ids as $gid) {
	$large = wp_get_attachment_image_url($gid, 'large');
	$full  = wp_get_attachment_image_url($gid, 'full');
	$thumb = wp_get_attachment_image_url($gid, 'medium');
	if ($large) {
		$gallery[] = [
			'id'    => $gid,
			'large' => $large,
			'full'  => $full ?: $large,
			'thumb' => $thumb ?: $large,
			'alt'   => get_post_meta($gid, '_wp_attachment_image_alt', true) ?: $title,
		];
	}
}

$video     = class_exists('SorteoSeguro_PDP_Templates')
	? SorteoSeguro_PDP_Templates::product_video($product_id)
	: ['type' => '', 'embed_url' => '', 'file_url' => '', 'youtube_id' => ''];
$has_video = ($video['type'] === 'youtube' && !empty($video['embed_url'])) || ($video['type'] === 'file' && !empty($video['file_url']));
$video_poster = '';
if ($has_video) {
	if ($video['type'] === 'youtube' && !empty($video['youtube_id'])) {
		$video_poster = 'https://i.ytimg.com/vi/' . rawurlencode($video['youtube_id']) . '/hqdefault.jpg';
	} elseif (!empty($gallery[0]['full'])) {
		$video_poster = $gallery[0]['full'];
	} elseif (!empty($gallery[0]['large'])) {
		$video_poster = $gallery[0]['large'];
	}
}
$bases_url = (string) ($ss['bases_url'] ?: '');

add_filter('ss_chrome_bases_url', static function () use ($bases_url) {
	return $bases_url;
});

$resolve_prize_image = static function ($image) use ($gallery) {
	if ($image === 'featured') {
		return $gallery[0]['large'] ?? '';
	}
	if (is_int($image) || (is_string($image) && ctype_digit($image))) {
		return wp_get_attachment_image_url((int) $image, 'large') ?: '';
	}
	return is_string($image) ? $image : '';
};
?>
<main class="ss-pdp ss-comprar" data-product-id="<?php echo (int) $product_id; ?>">
	<div class="ss-comprar__layout">

		<aside class="ss-comprar__buy ss-pdp__right purchase-panel">
			<h1 class="ss-pdp-title"><?php echo esc_html($title); ?></h1>

			<?php if ($sales_open) : ?>
				<div class="ss-pdp-packs-slot"></div>
			<?php else : ?>
				<div class="ss-comprar-closed">
					<h2>Ventas cerradas</h2>
					<p>Este sorteo no está disponible para compra en este momento.</p>
				</div>
			<?php endif; ?>
		</aside>

		<?php if ($sales_open) : ?>
		<div class="ss-comprar-checkout-wrap">
			<section
				id="ss-comprar-checkout"
				class="ss-comprar-checkout is-ready<?php echo (class_exists('SorteoSeguro_Comprar') && SorteoSeguro_Comprar::cart_has_current_product()) ? '' : ' is-preload'; ?>"
			>
				<div class="ss-comprar-checkout__head">
					<h2>Finaliza tu compra</h2>
					<p>Completa tus datos y elige tu DigiPack para pagar de forma segura.</p>
				</div>
				<div class="ss-comprar-checkout__form">
					<?php
					if (class_exists('SorteoSeguro_Comprar')) {
						SorteoSeguro_Comprar::render_embedded_checkout();
						if (SorteoSeguro_Comprar::cart_has_current_product()) {
							SorteoSeguro_Comprar::maybe_enqueue_wc_checkout_with_cart();
						}
					} elseif (function_exists('woocommerce_checkout')) {
						woocommerce_checkout();
					} else {
						echo do_shortcode('[woocommerce_checkout]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					?>
				</div>
			</section>
		</div>
		<?php endif; ?>

		<div id="ss-descripcion" class="ss-comprar__media ss-pdp-media">
			<div class="ss-pdp-media__stage" data-mode="<?php echo $has_video ? 'video' : 'image'; ?>">
				<?php if ($has_video) : ?>
					<button type="button" class="ss-pdp-media__badge ss-pdp-media__play-badge" data-ss-play-video>
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7L8 5z"/></svg>
						VER VIDEO
					</button>
					<div class="ss-pdp-media__video-wrap">
						<?php if ($video_poster) : ?>
							<img
								class="ss-pdp-media__poster skip-lazy"
								src="<?php echo esc_url($video_poster); ?>"
								alt=""
								data-no-lazy="1"
							>
						<?php endif; ?>
						<?php if ($video['type'] === 'youtube') : ?>
							<iframe
								class="ss-pdp-media__iframe skip-lazy no-lazy cmplz-exclude"
								src="<?php echo esc_attr($video['embed_url']); ?>"
								data-ss-src="<?php echo esc_attr($video['embed_url']); ?>"
								title="<?php echo esc_attr($title); ?>"
								width="1600"
								height="900"
								allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
								allowfullscreen
								loading="eager"
								referrerpolicy="strict-origin-when-cross-origin"
								data-no-lazy="1"
								data-cmplz-exclude="1"
							></iframe>
						<?php else : ?>
							<video
								class="ss-pdp-media__video skip-lazy"
								src="<?php echo esc_url($video['file_url']); ?>"
								<?php if ($video_poster) : ?>poster="<?php echo esc_url($video_poster); ?>"<?php endif; ?>
								controls
								playsinline
								webkit-playsinline
								preload="auto"
								data-no-lazy="1"
							></video>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<img
					class="ss-pdp-media__image skip-lazy"
					src="<?php echo esc_url($gallery[0]['full'] ?? $gallery[0]['large'] ?? ''); ?>"
					alt="<?php echo esc_attr($gallery[0]['alt'] ?? $title); ?>"
					data-ss-main-image
					data-no-lazy="1"
				>
				<?php if ($has_video || count($gallery) > 1) : ?>
					<button type="button" class="ss-pdp-media__nav ss-pdp-media__nav--prev" data-ss-gallery-prev aria-label="Anterior">
						<svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true" focusable="false"><path d="M16.2 3.1 5.5 12l10.7 8.9 2.5-3L11.4 12l7.3-5.9z"/></svg>
					</button>
					<button type="button" class="ss-pdp-media__nav ss-pdp-media__nav--next" data-ss-gallery-next aria-label="Siguiente">
						<svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true" focusable="false"><path d="M7.8 3.1 18.5 12 7.8 20.9l-2.5-3L12.6 12 5.3 6.1z"/></svg>
					</button>
				<?php endif; ?>
			</div>

			<?php if ($gallery || $has_video) : ?>
				<div class="ss-pdp-thumbs" data-ss-thumbs>
					<?php if ($has_video) : ?>
						<?php
						$video_thumb = '';
						if ($video['type'] === 'youtube' && !empty($video['youtube_id'])) {
							$video_thumb = 'https://i.ytimg.com/vi/' . rawurlencode($video['youtube_id']) . '/mqdefault.jpg';
						} elseif (!empty($gallery[0]['thumb'])) {
							$video_thumb = $gallery[0]['thumb'];
						}
						?>
						<button
							type="button"
							class="ss-pdp-thumbs__item is-video is-active"
							data-ss-thumb-video
							aria-label="Ver video"
						>
							<?php if ($video_thumb) : ?>
								<img src="<?php echo esc_url($video_thumb); ?>" alt="" loading="lazy">
							<?php endif; ?>
							<span class="ss-pdp-thumbs__play" aria-hidden="true">
								<svg viewBox="0 0 24 24"><path d="M8 5v14l11-7L8 5z"/></svg>
							</span>
						</button>
					<?php endif; ?>
					<?php foreach ($gallery as $i => $img) : ?>
						<button
							type="button"
							class="ss-pdp-thumbs__item<?php echo (!$has_video && $i === 0) ? ' is-active' : ''; ?>"
							data-ss-thumb
							data-large="<?php echo esc_url($img['full'] ?: $img['large']); ?>"
							data-full="<?php echo esc_url($img['full'] ?: $img['large']); ?>"
							aria-label="Imagen <?php echo (int) ($i + 1); ?>"
						>
							<img src="<?php echo esc_url($img['thumb']); ?>" alt="" loading="lazy">
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="ss-comprar__below">
			<?php if (!empty($ss['prizes'])) : ?>
			<section id="ss-premios" class="ss-pdp-block">
				<h2 class="ss-pdp-block__title">
					<?php echo ss_pdp_icon('star'); ?>
					CONOCE LO QUE PUEDES GANAR
				</h2>
				<div class="ss-pdp-prizes" data-count="<?php echo (int) count($ss['prizes']); ?>">
					<?php foreach ($ss['prizes'] as $prize) :
						$prize_img  = $resolve_prize_image($prize['image'] ?? '');
						$prize_icon = (string) ($prize['icon'] ?? 'star');
						?>
					<article class="ss-pdp-prize">
						<div class="ss-pdp-prize__info">
							<span class="ss-pdp-prize__badge"><?php echo esc_html((string) ($prize['badge'] ?? '')); ?></span>
							<div class="ss-pdp-prize__icon" aria-hidden="true">
								<?php echo ss_pdp_icon($prize_icon); ?>
							</div>
							<h3 class="ss-pdp-prize__name"><?php echo esc_html((string) ($prize['name'] ?? '')); ?></h3>
							<?php if (!empty($prize['meta'])) : ?>
								<p class="ss-pdp-prize__meta"><?php echo esc_html((string) $prize['meta']); ?></p>
							<?php endif; ?>
						</div>
						<?php if ($prize_img) : ?>
							<img class="ss-pdp-prize__img" src="<?php echo esc_url($prize_img); ?>" alt="<?php echo esc_attr((string) ($prize['name'] ?? '')); ?>" loading="lazy">
						<?php elseif (!empty($prize['placeholder'])) : ?>
							<div class="ss-pdp-prize__placeholder"><?php echo esc_html((string) $prize['placeholder']); ?></div>
						<?php endif; ?>
					</article>
					<?php endforeach; ?>
				</div>
			</section>
			<?php endif; ?>

			<section id="ss-caracteristicas" class="ss-pdp-block">
				<h2 class="ss-pdp-block__title">
					<?php echo ss_pdp_icon((string) $ss['discover_icon']); ?>
					<?php echo esc_html((string) $ss['discover_title']); ?>
				</h2>
				<?php if ($ss['discover_lead'] !== '') : ?>
					<p class="ss-pdp-block__lead"><?php echo esc_html((string) $ss['discover_lead']); ?></p>
				<?php endif; ?>

				<?php if (!empty($ss['stats'])) : ?>
				<div class="ss-pdp-stats">
					<?php foreach ($ss['stats'] as $stat) : ?>
						<div class="ss-pdp-stat">
							<span class="ss-pdp-stat__icon" aria-hidden="true">
								<?php echo ss_pdp_icon((string) ($stat['icon'] ?? 'star')); ?>
							</span>
							<strong><?php echo esc_html((string) ($stat['label'] ?? '')); ?></strong>
						</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<?php if (!empty($ss['features'])) : ?>
				<div class="ss-pdp-features">
					<?php foreach ($ss['features'] as $feature) : ?>
						<div class="ss-pdp-feature"><span></span><?php echo esc_html((string) $feature); ?></div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<?php if (!empty($ss['checklist'])) : ?>
				<button type="button" class="ss-pdp-collapse-btn" data-ss-collapse aria-expanded="false" aria-controls="ss-caracteristicas-lista">
					<span data-ss-collapse-label>VER TODAS LAS CARACTERÍSTICAS</span>
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.4 8.6L12 13.2l4.6-4.6L18 10l-6 6-6-6z"/></svg>
				</button>

				<div id="ss-caracteristicas-lista" class="ss-pdp-collapse" hidden>
					<ul class="ss-pdp-checklist">
						<?php foreach ($ss['checklist'] as $item) : ?>
							<li><?php echo esc_html((string) $item); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>
			</section>
		</div>
	</div>
</main>
