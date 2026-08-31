<?php
/**
 * Layout compartido de ficha PDP. Espera $ss con la info del sorteo.
 */
if (!defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/icons.php';

if (!have_posts()) {
	status_header(404);
	nocache_headers();
	include get_query_template('404');
	return;
}
the_post();

$product_id = (int) get_the_ID();
$product    = wc_get_product($product_id);
if (!$product) {
	status_header(404);
	nocache_headers();
	include get_query_template('404');
	return;
}
$GLOBALS['product'] = $product;
$title = $product->get_name();

$ss = is_array($ss ?? null) ? $ss : [];
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
], $ss);

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
$notaria_img = (string) $ss['notaria_img'];

$event_raw = '';
if (method_exists($product, 'get_lty_end_date')) {
	$event_raw = (string) $product->get_lty_end_date();
}
if ($event_raw === '') {
	$event_raw = (string) get_post_meta($product_id, '_lty_end_date', true);
}
$event_ts = $event_raw !== '' ? strtotime($event_raw) : false;
$event_label = '';
if ($event_ts) {
	$event_label = sprintf(
		'%s de %s de %s',
		wp_date('j', $event_ts),
		strtolower(wp_date('F', $event_ts)),
		wp_date('Y', $event_ts)
	);
}

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

get_header();
?>
<main class="ss-pdp" data-product-id="<?php echo (int) $product_id; ?>">
	<div class="ss-pdp__container">

		<div id="ss-descripcion" class="ss-pdp-media">
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

		<aside class="ss-pdp__right purchase-panel">
			<h1 class="ss-pdp-title"><?php echo esc_html($title); ?></h1>
			<p class="ss-pdp-intro subtitle"><?php echo esc_html($ss['intro']); ?></p>

			<?php if (!empty($ss['details'])) : ?>
			<ul class="ss-pdp-details product-details">
				<?php foreach ($ss['details'] as $row) : ?>
					<li>
						<span class="ss-pdp-details__icon" aria-hidden="true"><?php echo ss_pdp_icon((string) ($row['icon'] ?? 'star')); ?></span>
						<span><?php echo esc_html((string) ($row['label'] ?? '')); ?></span>
						<strong><?php echo esc_html((string) ($row['value'] ?? '')); ?></strong>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>

			<div class="countdown-container">
				<?php if ($event_label !== '') : ?>
				<p class="countdown-title">Fecha estimativa del evento: <?php echo esc_html($event_label); ?></p>
				<?php endif; ?>
				<?php echo do_shortcode('[lty_lottery_count_down_timer]'); ?>
			</div>

			<div class="ss-pdp-packs-slot"></div>
		</aside>

		<nav class="ss-pdp-tabs" aria-label="Secciones del producto">
			<a class="ss-pdp-tabs__item is-active" href="#ss-descripcion">
				<?php echo ss_pdp_icon('list'); ?>
				<span>Descripción</span>
			</a>
			<a class="ss-pdp-tabs__item" href="#ss-premios">
				<?php echo ss_pdp_icon('star'); ?>
				<span>Premios</span>
			</a>
			<a class="ss-pdp-tabs__item" href="#ss-caracteristicas">
				<?php echo ss_pdp_icon('edit'); ?>
				<span>Características</span>
			</a>
			<a class="ss-pdp-tabs__item" href="#ss-bases">
				<?php echo ss_pdp_icon('doc'); ?>
				<span>Bases Legales</span>
			</a>
		</nav>

		<section class="ss-pdp__left product-column">
			<section id="ss-premios" class="ss-pdp-block">
				<h2 class="ss-pdp-block__title">
					<?php echo ss_pdp_icon('star'); ?>
					CONOCE LO QUE PUEDES GANAR
				</h2>
				<div class="ss-pdp-prizes" data-count="<?php echo (int) count($ss['prizes']); ?>">
					<?php foreach ($ss['prizes'] as $prize) :
						$prize_img = $resolve_prize_image($prize['image'] ?? '');
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

			<?php if (!empty($ss['value']['amount'])) : ?>
			<section class="ss-pdp-value">
				<div class="ss-pdp-value__icon" aria-hidden="true">
					<?php echo ss_pdp_icon('shield'); ?>
				</div>
				<div>
					<p class="ss-pdp-value__label">VALOR COMERCIAL APROXIMADO</p>
					<p class="ss-pdp-value__amount"><?php echo esc_html((string) $ss['value']['amount']); ?></p>
					<?php if (!empty($ss['value']['name'])) : ?>
						<p class="ss-pdp-value__name"><?php echo esc_html((string) $ss['value']['name']); ?></p>
					<?php endif; ?>
				</div>
			</section>
			<?php endif; ?>

			<section id="ss-bases" class="ss-pdp-legal">
				<div class="ss-pdp-legal__body">
					<div class="ss-pdp-legal__icon" aria-hidden="true">
						<svg viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.4 9.4-8 10-4.6-.6-8-5-8-10V6l8-4zm-1 6v7h2V8h-2zm0 9v2h2v-2h-2z"/></svg>
					</div>
					<div class="ss-pdp-legal__copy">
						<h2 class="ss-pdp-legal__title">CONCURSO CERTIFICADO Y TRANSPARENTE</h2>
						<p class="ss-pdp-legal__text">
							Este concurso cuenta con bases legales protocolizadas ante notario público.
							Participas con respaldo legal, DigiTicket digital único y proceso transparente.
						</p>
					</div>
					<img class="ss-pdp-legal__seal" src="<?php echo esc_url($notaria_img); ?>" alt="Notaría" loading="lazy">
					<div class="ss-pdp-legal__actions">
						<a class="ss-pdp-btn ss-pdp-btn--primary" href="<?php echo esc_url($bases_url); ?>" target="_blank" rel="noopener">
							VER BASES LEGALES
						</a>
						<a class="ss-pdp-btn ss-pdp-btn--ghost" href="<?php echo esc_url($bases_url); ?>" target="_blank" rel="noopener" download>
							DESCARGAR PDF
						</a>
					</div>
				</div>
			</section>

			<aside class="ss-pdp-disclaimer">
				<span class="ss-pdp-disclaimer__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none">
						<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
						<path d="M12 7.5v6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
						<circle cx="12" cy="16.6" r="1.15" fill="currentColor"/>
					</svg>
				</span>
				<p class="ss-pdp-disclaimer__text">
					La compra de esta ilustración digital incluye acceso promocional al concurso correspondiente según sus bases legales.
				</p>
			</aside>

			<aside class="ss-pdp-faq">
				<div class="ss-pdp-faq__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24"><path d="M12 3a7 7 0 0 0-7 7c0 2.4 1.2 4.5 3 5.7V18a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-2.3c1.8-1.2 3-3.3 3-5.7a7 7 0 0 0-7-7zm-1 14v-1h2v1h-2zm1-12a5 5 0 0 1 5 5c0 1.8-.9 3.4-2.4 4.3l-.6.4V15h-4v-.3l-.6-.4A4.96 4.96 0 0 1 7 10a5 5 0 0 1 5-5zm0 2a1.5 1.5 0 0 0-1.5 1.5h2A1.5 1.5 0 0 0 12 7zm-1.7 5.2c.3-.5.8-.8 1.7-1.2.6-.3.8-.5.8-.8a.8.8 0 0 0-1.6 0H9.5A2.5 2.5 0 0 1 12 7.7c1.4 0 2.5 1 2.5 2.3 0 .9-.5 1.5-1.3 1.9-.7.4-1.1.6-1.2 1.1H10.3z"/></svg>
				</div>
				<div class="ss-pdp-faq__copy">
					<strong>¿Tienes dudas?</strong>
					<span>Revisa las preguntas más frecuentes sobre el concurso, la compra y los DigiTickets.</span>
				</div>
				<a class="ss-pdp-faq__btn" href="<?php echo esc_url(get_permalink(15) ?: 'https://sorteoseguro.cl/preguntas_frecuentes/'); ?>" target="_blank" rel="noopener noreferrer">
					IR A PREGUNTAS FRECUENTES
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.3 6.7L10.7 5.3 17.4 12l-6.7 6.7-1.4-1.4L14.6 12z"/></svg>
				</a>
			</aside>
		</section>
	</div>
</main>
<?php
get_footer();
