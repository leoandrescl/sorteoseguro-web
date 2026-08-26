<?php
/**
 * Plantilla Propiedades – página ID 99
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

$hero_slides = class_exists('SorteoSeguro_Propiedades') ? SorteoSeguro_Propiedades::hero_slides() : [];
$realty      = class_exists('SorteoSeguro_Propiedades') ? SorteoSeguro_Propiedades::contest_cards() : [];
$soon        = class_exists('SorteoSeguro_Propiedades') ? SorteoSeguro_Propiedades::coming_soon_cards(max(0, 4 - count($realty))) : [];
$cards       = array_merge($realty, $soon);
$faqs        = class_exists('SorteoSeguro_Home') ? SorteoSeguro_Home::faq_teaser(5) : [];

$faq_url = get_permalink(15) ?: (class_exists('SorteoSeguro_Chrome') ? SorteoSeguro_Chrome::page_url('preguntas_frecuentes') : home_url('/preguntas_frecuentes/'));
$hero_base = content_url('mu-plugins/sorteoseguro-home/assets/hero');

$ico = [
	'arrow'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M9.3 6.7 10.7 5.3 17.4 12l-6.7 6.7-1.4-1.4L14.6 12z"/></svg>',
	'nav_arrow' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M8.1 3.6 10.3 1.8 20.2 12 10.3 22.2 8.1 20.4 15.8 12z"/></svg>',
	'clock'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm1 11H7v-2h4V6h2z"/></svg>',
	'pin'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a7 7 0 0 0-7 7c0 5.3 7 13 7 13s7-7.7 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 14.5 9 2.5 2.5 0 0 1 12 11.5z"/></svg>',
	'home'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 3 2 12h3v8h6v-6h2v6h6v-8h3z"/></svg>',
	'bed'    => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.8" d="M3 18v-5.5A2.5 2.5 0 0 1 5.5 10H21v8M3 18h18M4 14h16M7 10V7.6A1.6 1.6 0 0 1 8.6 6h4.2A1.6 1.6 0 0 1 14.4 7.6V10"/></svg>',
	'bath'   => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.8" stroke-linecap="round" d="M4 13h16v3.2A2.8 2.8 0 0 1 17.2 19H6.8A2.8 2.8 0 0 1 4 16.2V13zM6 13V8.2A2.2 2.2 0 0 1 8.2 6H10"/><path stroke="currentColor" stroke-width="1.8" stroke-linecap="round" d="M7 19v1.4M17 19v1.4"/></svg>',
	'ruler'  => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3.5" y="8" width="17" height="8" rx="1.5" stroke="currentColor" stroke-width="1.8"/><path stroke="currentColor" stroke-width="1.6" stroke-linecap="round" d="M7 8v3M11 8v2M15 8v3M19 8v2"/></svg>',
	'trees'  => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" d="M12 20v-5M6.5 20h11M12 4l4.2 7.5H7.8L12 4zM16.2 9.8 20 15h-5"/></svg>',
	'land'   => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" d="m3 16 5.5-7 4 5 3-3.5L21 16H3z"/><path stroke="currentColor" stroke-width="1.8" stroke-linecap="round" d="M4 19h16"/></svg>',
	'doc'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm1 7V3.5L18.5 9z"/></svg>',
	'search' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M15.5 14h-.8l-.3-.3a6.5 6.5 0 1 0-.7.7l.3.3v.8l5 5 1.5-1.5-5-5zm-6 0A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z"/></svg>',
	'star' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m12 3.2 2.4 4.9 5.4.8-3.9 3.8.9 5.4L12 15.6 7.2 18.1l.9-5.4-3.9-3.8 5.4-.8z"/></svg>',
	'ticket_outline' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" d="M3 9.5V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2.5a2 2 0 1 0 0 5V17a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2.5a2 2 0 1 0 0-5z"/><path stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-dasharray="2.2 2.4" d="M12 6.5v11"/></svg>',
	'tl_shield' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="M12 3.2 5 6v5.2c0 4.6 3 8.6 7 9.8 4-1.2 7-5.2 7-9.8V6l-7-2.8z"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" d="m8.8 12 2.2 2.2 4.3-4.4"/></svg>',
	'cta_digital' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="3.5" width="16" height="17" rx="2.2" stroke="currentColor" stroke-width="1.7"/><path stroke="currentColor" stroke-width="1.6" stroke-linecap="round" d="M8 8.2h8M8 12h5.5M8 15.8h3"/><circle cx="15.8" cy="15.8" r="1.1" fill="currentColor"/></svg>',
	'cta_public' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="M7 4.5h7.5L18.5 8v11.5A1.5 1.5 0 0 1 17 21H7a1.5 1.5 0 0 1-1.5-1.5v-14A1.5 1.5 0 0 1 7 4.5z"/><path stroke="currentColor" stroke-width="1.6" stroke-linecap="round" d="M14.2 4.5V8H18M8.5 12h7M8.5 15.2h7M8.5 18.2h4.5"/></svg>',
	'cta_ticket_bg' => '<svg viewBox="0 0 120 160" fill="none" aria-hidden="true"><path fill="currentColor" d="M28 8h64a12 12 0 0 1 12 12v18a14 14 0 1 0 0 28v18a14 14 0 1 0 0 28v18a12 12 0 0 1-12 12H28a12 12 0 0 1-12-12v-18a14 14 0 1 0 0-28V66a14 14 0 1 0 0-28V20A12 12 0 0 1 28 8z" opacity=".95"/><path stroke="#fff" stroke-opacity=".25" stroke-width="3" stroke-dasharray="6 8" d="M60 28v104"/></svg>',
];

get_header();
?>
<main class="ss-home ss-realty">

	<!-- Hero slider: Casa + Parcela -->
	<section class="ss-home-hero" aria-label="Propiedades destacadas" data-ss-hero-slider data-ss-hero-interval="5000">
		<div class="ss-home-hero__viewport">
			<?php foreach ($hero_slides as $i => $slide) :
				$is_active = $i === 0;
				$theme = sanitize_html_class((string) ($slide['theme'] ?? 'studio'));
				$key = sanitize_html_class((string) ($slide['key'] ?? 'slide'));
				$badge_icon = (string) ($slide['badge_icon'] ?? 'dot');
				$title_em = (string) ($slide['title_em'] ?? '');
				$title_tag = $i === 0 ? 'h1' : 'h2';
				$cta_url = (string) ($slide['url'] ?? '#ss-realty-concursos');
				?>
				<article
					class="ss-home-hero-slide is-theme-<?php echo esc_attr($theme); ?> is-key-<?php echo esc_attr($key); ?><?php echo $is_active ? ' is-active' : ''; ?>"
					data-ss-hero-slide="<?php echo (int) $i; ?>"
					aria-hidden="<?php echo $is_active ? 'false' : 'true'; ?>"
					aria-label="<?php echo esc_attr((string) ($slide['aria'] ?? $slide['title'] ?? '')); ?>"
				>
					<picture class="ss-home-hero-slide__media" aria-hidden="true">
						<source media="(max-width: 760px)" srcset="<?php echo esc_url($hero_base . '/' . $slide['img_mobile']); ?>">
						<img
							class="ss-home-hero-slide__bg"
							src="<?php echo esc_url($hero_base . '/' . $slide['img_pc']); ?>"
							alt=""
							width="1920"
							height="707"
							<?php echo $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"'; ?>
							decoding="<?php echo $i === 0 ? 'sync' : 'async'; ?>"
						>
					</picture>
					<span class="ss-home-hero-slide__scrim" aria-hidden="true"></span>
					<div class="ss-home__shell ss-home-hero-slide__inner">
						<div class="ss-home-hero-slide__copy">
							<span class="ss-home-hero__badge">
								<?php if ($badge_icon === 'dot') : ?>
									<span class="ss-home-hero__badge-dot" aria-hidden="true"></span>
								<?php elseif (!empty($ico[$badge_icon])) : ?>
									<span class="ss-home-hero__badge-ico" aria-hidden="true"><?php echo $ico[$badge_icon]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<?php endif; ?>
								<?php echo esc_html((string) $slide['badge']); ?>
							</span>

							<<?php echo $title_tag; ?> class="ss-home-hero__title">
								<?php if (!empty($slide['title_lines']) && is_array($slide['title_lines'])) : ?>
									<?php foreach ($slide['title_lines'] as $li => $line) :
										$line_text = (string) ($line['text'] ?? '');
										if ($li > 0) {
											echo '<br>';
										}
										if (!empty($line['em'])) {
											echo '<em>' . esc_html($line_text) . '</em>';
										} else {
											echo esc_html($line_text);
										}
									endforeach; ?>
								<?php else : ?>
									<?php echo esc_html((string) $slide['title']); ?>
									<?php if ($title_em !== '') : ?>
										<em><?php echo esc_html($title_em); ?></em>
									<?php endif; ?>
									<?php if (!empty($slide['title_after'])) : ?>
										<?php echo esc_html((string) $slide['title_after']); ?>
									<?php endif; ?>
								<?php endif; ?>
							</<?php echo $title_tag; ?>>

							<?php if (!empty($slide['kicker'])) : ?>
								<p class="ss-home-hero__kicker"><?php echo esc_html((string) $slide['kicker']); ?></p>
							<?php endif; ?>

							<?php if (!empty($slide['meta'])) :
								$meta_icon = (string) ($slide['meta_icon'] ?? '');
								?>
								<p class="ss-home-hero__meta">
									<?php if ($meta_icon !== '' && !empty($ico[$meta_icon])) : ?>
										<span aria-hidden="true"><?php echo $ico[$meta_icon]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<?php endif; ?>
									<?php echo esc_html((string) $slide['meta']); ?>
								</p>
							<?php endif; ?>

							<?php if (!empty($slide['sub'])) : ?>
								<p class="ss-home-hero__sub"><?php echo nl2br(esc_html((string) $slide['sub']), false); ?></p>
							<?php endif; ?>
							<?php if (!empty($slide['sub_line2']) || !empty($slide['sub_em']) || !empty($slide['sub_after'])) : ?>
								<p class="ss-home-hero__sub">
									<?php echo nl2br(esc_html((string) ($slide['sub_line2'] ?? '')), false); ?>
									<?php if (!empty($slide['sub_em'])) : ?>
										<strong><?php echo esc_html((string) $slide['sub_em']); ?></strong>
									<?php endif; ?>
									<?php echo nl2br(esc_html((string) ($slide['sub_after'] ?? '')), false); ?>
								</p>
							<?php endif; ?>

							<?php if (!empty($slide['specs'])) : ?>
								<ul class="ss-home-hero__specs">
									<?php foreach ($slide['specs'] as $spec) :
										$s_icon = (string) ($spec['icon'] ?? '');
										?>
										<li class="ss-home-hero__spec">
											<?php if ($s_icon !== '' && !empty($ico[$s_icon])) : ?>
												<span class="ss-home-hero__spec-ico" aria-hidden="true"><?php echo $ico[$s_icon]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<?php endif; ?>
											<?php if (!empty($spec['value'])) : ?>
												<span class="ss-home-hero__spec-copy">
													<strong><?php echo esc_html((string) $spec['value']); ?></strong>
													<small><?php echo esc_html((string) ($spec['label'] ?? '')); ?></small>
												</span>
											<?php else : ?>
												<?php echo esc_html((string) ($spec['label'] ?? '')); ?>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>

							<div class="ss-home-hero__foot">
							<div class="ss-home-hero__ctas">
								<?php if (!empty($slide['price'])) : ?>
									<span class="ss-home-hero__ticket">
										<span class="ss-home-hero__ticket-star" aria-hidden="true"><?php echo $ico['star']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										<span>
											<?php echo esc_html((string) ($slide['price_label'] ?? 'DigiTicket')); ?>
											<strong><?php echo esc_html((string) $slide['price']); ?></strong>
										</span>
									</span>
								<?php endif; ?>
								<a class="ss-home-btn ss-home-btn--primary<?php echo !empty($slide['price']) ? ' ss-home-btn--hero-dark' : ''; ?>" href="<?php echo esc_url($cta_url); ?>">
									<?php echo esc_html((string) ($slide['cta'] ?? 'Participar ahora')); ?>
									<span class="ss-home-btn__ico" aria-hidden="true"><?php echo $ico['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								</a>
							</div>
							</div>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>

		<?php if (count($hero_slides) > 1) : ?>
			<div class="ss-home-hero__nav">
				<div class="ss-home-hero__dots" role="tablist" aria-label="Banners de propiedades">
					<?php foreach ($hero_slides as $i => $slide) : ?>
						<button
							type="button"
							class="ss-home-hero__dot<?php echo $i === 0 ? ' is-active' : ''; ?>"
							data-ss-hero-dot="<?php echo (int) $i; ?>"
							role="tab"
							aria-label="<?php echo esc_attr((string) ($slide['dot_label'] ?? ('Banner ' . ($i + 1)))); ?>"
							aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
						></button>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</section>

	<!-- Cómo participar -->
	<section class="ss-realty-how" id="ss-realty-how" aria-labelledby="ss-realty-how-title">
		<div class="ss-home__shell">
			<div class="ss-realty-how__head">
				<p class="ss-realty-how__tagline">¿Cómo participar?</p>
				<h2 class="ss-realty-how__title" id="ss-realty-how-title">
					En Sorteo Seguro, participar es simple, rápido y 100% digital.<br>
					Adquiere tu DigiTicket, accede al evento correspondiente<br>
					sigue todo el proceso con total transparencia conforme a las bases legales publicadas
				</h2>
			</div>
			<div class="ss-realty-how__grid">
				<article class="ss-realty-how__step">
					<p class="ss-realty-how__num">PASO 1</p>
					<h3>Elige el evento que te interesa</h3>
					<p>Explora nuestras categorías disponibles como propiedades y vehículos. Cada evento cuenta con información clara, premios definidos y bases legales publicadas para que puedas participar con total tranquilidad.</p>
				</article>
				<article class="ss-realty-how__step">
					<p class="ss-realty-how__num">PASO 2</p>
					<h3>Adquiere tu DigiTicket de forma segura</h3>
					<p>El sistema te asigna un número automáticamente. Completa tu compra de forma rápida y segura y recibirás tu DigiTicket digital con folio único directamente en tu correo electrónico. La adquisición del DigiTicket corresponde a una ilustración digital y otorga acceso promocional al evento correspondiente según las bases legales publicadas.</p>
				</article>
				<article class="ss-realty-how__step">
					<p class="ss-realty-how__num">PASO 3</p>
					<h3>Sigue el evento y conoce a los ganadores</h3>
					<p>Revisa la cuenta regresiva y la fecha del evento correspondiente. Algunos eventos son transmitidos en vivo, pero no es necesario estar conectado para participar. Los resultados y ganadores siempre se publican de forma transparente conforme a las bases legales.</p>
				</article>
			</div>
			<div class="ss-realty-how__cta">
				<a class="ss-home-btn ss-home-btn--primary" href="#ss-realty-concursos">
					Ver eventos activos
					<span class="ss-home-btn__ico" aria-hidden="true"><?php echo $ico['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</a>
			</div>
		</div>
	</section>

	<!-- Propiedades activas -->
	<section class="ss-home-contests ss-realty-contests" id="ss-realty-concursos" aria-labelledby="ss-realty-contests-title">
		<div class="ss-home__shell">
			<header class="ss-home-contests__head">
				<div class="ss-home-contests__titles">
					<h2 id="ss-realty-contests-title">PROPIEDADES ACTIVAS</h2>
				</div>
			</header>

			<div class="ss-realty-contests__grid">
				<?php foreach ($cards as $c) :
					$is_soon = !empty($c['soon']);
					$end_attr = '';
					if (!$is_soon) {
						if (!empty($c['end_countdown'])) {
							$end_attr = (string) $c['end_countdown'];
						} elseif (!empty($c['end_ts'])) {
							$end_attr = gmdate('c', (int) $c['end_ts']);
						}
					}
					$price_txt = '$' . number_format((float) ($c['price_raw'] ?? 0), 0, ',', '.');
					$url = (string) ($c['url'] ?? '');
					?>
					<article class="ss-home-contest<?php echo $is_soon ? ' is-soon' : ''; ?>">
						<div class="ss-home-contest__media">
							<?php if ($is_soon) : ?>
								<span class="ss-home-contest__img" aria-hidden="true">
									<span class="ss-home-contest__ph"></span>
								</span>
							<?php else : ?>
								<a class="ss-home-contest__img" href="<?php echo esc_url($url); ?>">
									<?php if (!empty($c['image'])) : ?>
										<img src="<?php echo esc_url($c['image']); ?>" alt="<?php echo esc_attr($c['title']); ?>" loading="lazy" decoding="async">
									<?php else : ?>
										<span class="ss-home-contest__ph" aria-hidden="true"></span>
									<?php endif; ?>
								</a>
							<?php endif; ?>
							<span class="ss-home-contest__cat"><?php echo esc_html((string) ($c['category'] ?? 'INMOBILIARIO')); ?></span>
							<span class="ss-home-contest__badge"><?php echo $is_soon ? 'PRÓXIMAMENTE' : 'ACTIVO'; ?></span>
							<?php if ($end_attr) : ?>
								<div class="ss-home-contest__timebox" data-ss-countdown="<?php echo esc_attr($end_attr); ?>">
									<span class="ss-home-contest__timebox-ico" aria-hidden="true"><?php echo $ico['clock']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<span class="ss-home-contest__timebox-txt">
										<small>Quedan</small>
										<strong data-ss-countdown-label>…</strong>
									</span>
								</div>
							<?php endif; ?>
						</div>
						<div class="ss-home-contest__body">
							<h3 class="ss-home-contest__title">
								<?php if ($is_soon) : ?>
									<?php echo esc_html((string) $c['title']); ?>
								<?php else : ?>
									<a href="<?php echo esc_url($url); ?>"><?php echo esc_html((string) $c['title']); ?></a>
								<?php endif; ?>
							</h3>
							<?php if (!empty($c['meta'])) : ?>
								<p class="ss-home-contest__meta">
									<span aria-hidden="true"><?php echo $ico['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<?php echo esc_html((string) $c['meta']); ?>
								</p>
							<?php endif; ?>
							<div class="ss-home-contest__cta-row">
								<div class="ss-home-contest__price">
									<?php if (!$is_soon) : ?>
										<span>Desde</span>
										<strong><?php echo esc_html($price_txt); ?> <em>/ DigiTicket</em></strong>
									<?php endif; ?>
								</div>
								<?php if ($is_soon) : ?>
									<span class="ss-home-contest__btn">Muy pronto</span>
								<?php else : ?>
									<a class="ss-home-contest__btn" href="<?php echo esc_url($url); ?>">
										Participar ahora
										<span aria-hidden="true"><?php echo $ico['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									</a>
								<?php endif; ?>
							</div>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- FAQ teaser -->
	<section class="ss-home-faq" aria-labelledby="ss-home-faq-title">
		<div class="ss-home__shell ss-home-faq__layout">
			<div class="ss-home-faq__intro">
				<p class="ss-home-faq__eyebrow">FAQ'S</p>
				<h2 id="ss-home-faq-title">
					Resolvemos tus
					<span class="ss-home-faq__accent">dudas más comunes</span>
				</h2>
				<p class="ss-home-faq__lead">Encuentra respuestas rápidas sobre participación, compras, sorteos y más.</p>
				<a class="ss-home-btn ss-home-btn--ghost" href="<?php echo esc_url($faq_url); ?>">
					Ver todas las preguntas
					<span class="ss-home-btn__ico" aria-hidden="true"><?php echo $ico['nav_arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</a>
			</div>

			<div class="ss-home-faq__main">
				<label class="ss-home-faq__search">
					<span class="ss-home-faq__search-ico" aria-hidden="true"><?php echo $ico['search']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<input type="search" id="ss-home-faq-search" placeholder="Buscar en preguntas frecuentes..." autocomplete="off">
				</label>

				<?php if (!$faqs) : ?>
					<p class="ss-home-empty">Pronto publicaremos las preguntas frecuentes.</p>
				<?php else : ?>
					<div class="ss-home-faq__list" id="ss-home-faq-list">
						<?php foreach ($faqs as $faq) :
							$q_lower = function_exists('mb_strtolower') ? mb_strtolower($faq['q']) : strtolower($faq['q']);
							?>
							<article
								class="ss-home-faq__item"
								data-q="<?php echo esc_attr($q_lower); ?>"
							>
								<button type="button" class="ss-home-faq__q" aria-expanded="false">
									<span><?php echo esc_html($faq['q']); ?></span>
									<span class="ss-home-faq__toggle" aria-hidden="true"></span>
								</button>
								<div class="ss-home-faq__a">
									<div class="ss-home-faq__a-inner">
										<?php echo $faq['a']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									</div>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
					<p class="ss-home-empty" id="ss-home-faq-no-results" hidden>No encontramos resultados para tu búsqueda.</p>
				<?php endif; ?>
			</div>

			<div class="ss-home-faq__visual" aria-hidden="true">
				<img
					src="<?php echo esc_url(content_url('mu-plugins/sorteoseguro-home/assets/faq-visual.png')); ?>"
					alt=""
					width="440"
					height="420"
					loading="lazy"
					decoding="async"
				>
			</div>
		</div>
	</section>

	<!-- CTA final -->
	<section class="ss-home-cta" aria-label="Llamado a la acción">
		<div class="ss-home__shell">
			<div class="ss-home-cta__panel">
				<div class="ss-home-cta__copy">
					<span class="ss-home-cta__watermark" aria-hidden="true"><?php echo $ico['cta_ticket_bg']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<h2>
						<span class="ss-home-cta__line">Tu próximo premio</span>
						<span class="ss-home-cta__line">podría comenzar con</span>
						<span class="ss-home-cta__line">un <span class="ss-home-cta__accent">DigiTicket.</span></span>
					</h2>
				</div>

				<ul class="ss-home-cta__features">
					<li>
						<span class="ss-home-cta__fico" aria-hidden="true"><?php echo $ico['cta_digital']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span>100% digital</span>
					</li>
					<li>
						<span class="ss-home-cta__fico" aria-hidden="true"><?php echo $ico['tl_shield']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span>Seguro y transparente</span>
					</li>
					<li>
						<span class="ss-home-cta__fico" aria-hidden="true"><?php echo $ico['cta_public']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span>Resultados públicos</span>
					</li>
				</ul>

				<div class="ss-home-cta__action">
					<a class="ss-home-btn ss-home-btn--light" href="#ss-realty-concursos">
						Ver concursos activos
						<span class="ss-home-btn__ico" aria-hidden="true"><?php echo $ico['nav_arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</a>
					<p class="ss-home-cta__note">¡Participa hoy!</p>
				</div>
			</div>
		</div>
	</section>

</main>
<?php
get_footer();
