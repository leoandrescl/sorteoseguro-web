<?php
/**
 * Layout Cyber: media + premios + características | nuevo diseño + packs + checkout.
 *
 * Copia de sorteoseguro-comprar/layout-comprar.php con el aside de /cyber/:
 * se elimina el "ss-bono-banner", se reemplaza el contador por el nuevo diseño.
 */
if (!defined('ABSPATH')) {
	exit;
}

require_once WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-pdp/icons.php';

$product_id = isset($product_id) ? (int) $product_id : (int) ($GLOBALS['ss_comprar_product_id'] ?? 0);
if ($product_id <= 0 && class_exists('SorteoSeguro_Comprar_Cyber')) {
	$product_id = SorteoSeguro_Comprar_Cyber::current_product_id();
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

$ss_buy_class = 'SorteoSeguro_Comprar';
if (class_exists('SorteoSeguro_Comprar_Cyber') && SorteoSeguro_Comprar_Cyber::is_cyber_child()) {
	$ss_buy_class = 'SorteoSeguro_Comprar_Cyber';
} elseif (!class_exists('SorteoSeguro_Comprar')) {
	$ss_buy_class = '';
}

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
<main class="ss-pdp ss-comprar ss-cyber" data-product-id="<?php echo (int) $product_id; ?>">
	<div class="ss-comprar__layout">

		<aside class="ss-comprar__buy ss-pdp__right purchase-panel">
			<?php
			$cyber_banner = [];
			if (class_exists('Promo_Engine_Stable') && method_exists('Promo_Engine_Stable', 'get_cyber_banner_data')) {
				$cyber_banner = Promo_Engine_Stable::get_cyber_banner_data($product_id);
			}
			if (is_array($cyber_banner) && !empty($cyber_banner)) :
				$cb_title   = trim((string) ($cyber_banner['pack_title'] ?? ''));
				if ($cb_title === '') {
					$cb_title = 'Pack Cyber';
				}
				$cb_pct     = (int) ($cyber_banner['percent'] ?? 0);
				$cb_qty     = (int) ($cyber_banner['qty'] ?? 0);
				$cb_price   = (float) ($cyber_banner['price'] ?? 0);
				$cb_list    = (float) ($cyber_banner['list_price'] ?? 0);
				$cb_end     = (int) ($cyber_banner['end_ts'] ?? 0);
				$cb_bullets = is_array($cyber_banner['bullets'] ?? null) ? $cyber_banner['bullets'] : [];
				$cb_money   = static function ($n) {
					return '$' . number_format((float) $n, 0, ',', '.');
				};
				$cb_words      = preg_split('/\s+/', $cb_title) ?: [];
				$cb_title_last = count($cb_words) > 1 ? (string) array_pop($cb_words) : '';
				$cb_title_head = trim(implode(' ', $cb_words));
				?>
				<div class="ss-cyber-banner" data-ss-cyber-banner data-ss-cyber-until="<?php echo (int) $cb_end; ?>">
					<span class="ss-cyber-banner__blob" aria-hidden="true"></span>

					<?php if ($cb_pct > 0) : ?>
					<div class="ss-cyber-banner__deco" aria-hidden="true">
						<span class="ss-cyber-banner__spark ss-cyber-banner__spark--1"></span>
						<span class="ss-cyber-banner__spark ss-cyber-banner__spark--2"></span>
						<span class="ss-cyber-banner__spark ss-cyber-banner__spark--3"></span>
						<span class="ss-cyber-banner__tag"><b><?php echo (int) $cb_pct; ?>%</b><i>DE DESCUENTO</i></span>
						<span class="ss-cyber-banner__deco-ticket">
							<span class="ss-cyber-banner__deco-ticket-house-wrap" aria-hidden="true">
								<svg class="ss-cyber-banner__deco-ticket-house-bg" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z" fill="currentColor"/></svg>
								<svg class="ss-cyber-banner__deco-ticket-house" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z" fill="currentColor"/></svg>
							</span>
							<?php if ($cb_title_head !== '') : ?>
							<span class="ss-cyber-banner__deco-ticket-head"><?php echo esc_html($cb_title_head); ?></span>
							<?php endif; ?>
							<span class="ss-cyber-banner__deco-ticket-last"><?php echo esc_html($cb_title_last !== '' ? $cb_title_last : $cb_title); ?></span>
						</span>
					</div>
					<?php endif; ?>

					<div class="ss-cyber-banner__head">
						<?php if ($cb_end > 0) : ?>
						<span class="ss-cyber-banner__pill">
							<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 7.2v5l3.2 1.9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
							POR TIEMPO LIMITADO
						</span>
						<?php endif; ?>
						<h2 class="ss-cyber-banner__title">
							<span class="ss-cyber-banner__title-1">Aprovecha el</span>
							<span class="ss-cyber-banner__title-2">Pack Especial</span>
							<span class="ss-cyber-banner__title-3">Cyber</span>
						</h2>
						<?php if ($cb_pct > 0) : ?>
						<p class="ss-cyber-banner__sub"><strong>Más de un <span class="ss-cyber-banner__pct"><?php echo (int) $cb_pct; ?>%</span> de descuento</strong><br>para tus DigiTickets</p>
						<?php endif; ?>
					</div>

					<div class="ss-cyber-banner__card">
						<div class="ss-cyber-banner__card-left">
							<div class="ss-cyber-banner__card-top">
								<span class="ss-cyber-banner__card-ico" aria-hidden="true">
									<svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z" fill="currentColor"/></svg>
								</span>
								<div class="ss-cyber-banner__card-body">
									<p class="ss-cyber-banner__packname"><?php echo esc_html($cb_title); ?></p>
									<p class="ss-cyber-banner__qty"><strong><?php echo (int) $cb_qty; ?></strong> DigiTickets</p>
								</div>
							</div>
							<?php if ($cb_bullets) : ?>
							<ul class="ss-cyber-banner__bullets">
								<?php foreach ($cb_bullets as $cb_bullet) : ?>
								<li>
									<span class="ss-cyber-banner__check" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
									<?php echo esc_html($cb_bullet); ?>
								</li>
								<?php endforeach; ?>
							</ul>
							<?php endif; ?>
						</div>
						<div class="ss-cyber-banner__card-right">
							<span class="ss-cyber-banner__card-spark ss-cyber-banner__card-spark--1" aria-hidden="true"></span>
							<span class="ss-cyber-banner__card-spark ss-cyber-banner__card-spark--2" aria-hidden="true"></span>
							<span class="ss-cyber-banner__only">POR SOLO</span>
							<span class="ss-cyber-banner__price"><?php echo esc_html($cb_money($cb_price)); ?></span>
							<?php if ($cb_list > $cb_price) : ?>
							<span class="ss-cyber-banner__list"><?php echo esc_html($cb_money($cb_list)); ?></span>
							<?php endif; ?>
							<?php if ($cb_pct > 0) : ?>
							<span class="ss-cyber-banner__save">MÁS DE <?php echo (int) $cb_pct; ?>% DE DESCUENTO</span>
							<?php endif; ?>
						</div>
					</div>

					<?php if ($cb_end > 0) : ?>
					<div class="ss-cyber-banner__timer">
						<span class="ss-cyber-banner__timer-ico" aria-hidden="true">
							<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 7.2v5l3.2 1.9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</span>
						<div class="ss-cyber-banner__timer-copy">
							<p class="ss-cyber-banner__timer-title">¡SOLO POR TIEMPO LIMITADO!</p>
							<p class="ss-cyber-banner__timer-sub">Este pack Cyber vence en:</p>
						</div>
						<div class="ss-cyber-banner__units">
							<div class="ss-cyber-banner__unit"><span data-ss-cyber-h>00</span><small>HORAS</small></div>
							<span class="ss-cyber-banner__sep">:</span>
							<div class="ss-cyber-banner__unit"><span data-ss-cyber-m>00</span><small>MINUTOS</small></div>
							<span class="ss-cyber-banner__sep">:</span>
							<div class="ss-cyber-banner__unit"><span data-ss-cyber-s>00</span><small>SEGUNDOS</small></div>
						</div>
					</div>
					<?php endif; ?>

					<div class="ss-cyber-banner__note">
						<span class="ss-cyber-banner__note-ico" aria-hidden="true">
							<svg viewBox="0 0 24 24"><path d="M12 8.5A3.5 3.5 0 1 0 15.5 12 3.5 3.5 0 0 0 12 8.5Zm9.4 5.1-1.7-.98a7.6 7.6 0 0 0 0-1.24l1.7-.98a.5.5 0 0 0 .18-.68l-1.5-2.6a.5.5 0 0 0-.6-.22l-1.87.75a7.6 7.6 0 0 0-1.07-.62l-.28-2a.5.5 0 0 0-.5-.42h-3a.5.5 0 0 0-.5.42l-.28 2a7.6 7.6 0 0 0-1.07.62l-1.87-.75a.5.5 0 0 0-.6.22l-1.5 2.6a.5.5 0 0 0 .18.68l1.7.98a7.6 7.6 0 0 0 0 1.24l-1.7.98a.5.5 0 0 0-.18.68l1.5 2.6a.5.5 0 0 0 .6.22l1.87-.75a7.6 7.6 0 0 0 1.07.62l.28 2a.5.5 0 0 0 .5.42h3a.5.5 0 0 0 .5-.42l.28-2a7.6 7.6 0 0 0 1.07-.62l1.87.75a.5.5 0 0 0 .6-.22l1.5-2.6a.5.5 0 0 0-.18-.68Z" fill="currentColor"/></svg>
						</span>
						<p>El <strong>descuento</strong> se aplica <strong>automáticamente</strong> después de que selecciones el Pack en tu preferencia.</p>
					</div>

					<div class="ss-cyber-banner__foot"><span></span><em>CONFIANZA EN CADA CONCURSO</em><span></span></div>
				</div>
			<?php else : ?>
				<div class="ss-cyber-nuevo-diseno" role="note" aria-label="Cyber Day">
					<p class="ss-cyber-nuevo-diseno__text">diseño nuevo aca</p>
				</div>
			<?php endif; ?>

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
				class="ss-comprar-checkout is-ready<?php echo ($ss_buy_class !== '' && class_exists($ss_buy_class) && $ss_buy_class::cart_has_current_product()) ? '' : ' is-preload'; ?>"
			>
				<div class="ss-comprar-checkout__head">
					<h2>Finaliza tu compra</h2>
					<p>Completa tus datos y elige tu DigiPack para pagar de forma segura.</p>
				</div>
				<div class="ss-comprar-checkout__form">
					<?php
					if ($ss_buy_class !== '' && class_exists($ss_buy_class)) {
						$ss_buy_class::render_embedded_checkout();
						if ($ss_buy_class::cart_has_current_product()) {
							$ss_buy_class::maybe_enqueue_wc_checkout_with_cart();
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

		<?php
		if (class_exists('SorteoSeguro_PDP_Templates')) {
			SorteoSeguro_PDP_Templates::render_marquee((int) $product_id);
		}
		?>

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
