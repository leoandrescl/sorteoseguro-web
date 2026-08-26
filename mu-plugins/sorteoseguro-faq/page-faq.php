<?php
/**
 * Plantilla FAQ – página Preguntas frecuentes (ID 15)
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

$faqs     = class_exists('SorteoSeguro_FAQ') ? SorteoSeguro_FAQ::get_faqs() : [];
$cats     = class_exists('SorteoSeguro_FAQ') ? SorteoSeguro_FAQ::categories_config() : [];
$contacto = get_permalink(1387) ?: home_url('/contacto/');

$icons = [
	'all'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z"/></svg>',
	'ticket' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 10V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v4a2 2 0 0 1 0 4v4a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-4a2 2 0 0 1 0-4zM4 8.5V6h16v2.5a3.5 3.5 0 0 0 0 7V18H4v-2.5a3.5 3.5 0 0 0 0-7z"/></svg>',
	'user'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-4 0-8 2-8 4v2h16v-2c0-2-4-4-8-4z"/></svg>',
	'trophy' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 2H6v3H3v2a5 5 0 0 0 5 5h.2A5 5 0 0 0 11 14.9V17H8v2h8v-2h-3v-2.1A5 5 0 0 0 15.8 12H16a5 5 0 0 0 5-5V5h-3V2zm-2 3v2.2A3 3 0 0 1 19 7V5h-3zM5 7a3 3 0 0 1 3 3.2V5H5v2z"/></svg>',
	'card'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4H4V6h16v2zM4 18v-6h16v6H4z"/></svg>',
	'star'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l2.9 6.1L22 9.2l-5 4.9 1.2 6.9L12 17.8 5.8 21l1.2-6.9-5-4.9 7.1-1.1L12 2z"/></svg>',
];

get_header();
?>
<main class="ss-faq">
	<section class="ss-faq-hero">
		<span class="ss-faq-hero__deco ss-faq-hero__deco--left" aria-hidden="true"></span>
		<span class="ss-faq-hero__deco ss-faq-hero__deco--right" aria-hidden="true"></span>
		<div class="ss-faq-hero__inner">
			<p class="ss-faq-hero__eyebrow">PREGUNTAS FRECUENTES</p>
			<h1 class="ss-faq-hero__title">¿Tienes alguna duda?</h1>
			<p class="ss-faq-hero__sub">Encuentra respuestas rápidas sobre DigiTickets, concursos, pagos y ganadores.</p>

			<label class="ss-faq-search">
				<span class="ss-faq-search__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24"><path d="M15.5 14h-.8l-.3-.3a6.5 6.5 0 1 0-.7.7l.3.3v.8l5 5 1.5-1.5-5-5zm-6 0A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z"/></svg>
				</span>
				<input type="search" id="ss-faq-search" placeholder="¿Qué necesitas saber?" autocomplete="off">
			</label>

			<div class="ss-faq-filters" role="tablist" aria-label="Categorías">
				<button type="button" class="ss-faq-filter is-active" data-cat="all" role="tab" aria-selected="true">
					<?php echo $icons['all']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span>Todos</span>
				</button>
				<?php foreach ($cats as $slug => $cfg) : ?>
					<button type="button" class="ss-faq-filter" data-cat="<?php echo esc_attr($slug); ?>" role="tab" aria-selected="false">
						<?php echo $icons[$cfg['icon']] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span><?php echo esc_html($cfg['name']); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="ss-faq-list-wrap">
		<?php if (!$faqs) : ?>
			<p class="ss-faq-empty">Pronto publicaremos las preguntas frecuentes.</p>
		<?php else : ?>
			<?php
			$mid   = (int) ceil(count($faqs) / 2);
			$cols  = [
				array_slice($faqs, 0, $mid),
				array_slice($faqs, $mid),
			];
			?>
			<div class="ss-faq-list" id="ss-faq-list">
				<?php foreach ($cols as $col_i => $col_faqs) : ?>
					<div class="ss-faq-col" data-col="<?php echo (int) $col_i; ?>">
						<?php foreach ($col_faqs as $i => $faq) : ?>
							<article
								class="ss-faq-item"
								data-cats="<?php echo esc_attr(implode(' ', $faq['cats'])); ?>"
								data-q="<?php echo esc_attr(function_exists('mb_strtolower') ? mb_strtolower($faq['q']) : strtolower($faq['q'])); ?>"
							>
								<button type="button" class="ss-faq-item__q" aria-expanded="false">
									<span><?php echo esc_html($faq['q']); ?></span>
									<span class="ss-faq-item__toggle" aria-hidden="true"></span>
								</button>
								<div class="ss-faq-item__a">
									<div class="ss-faq-item__a-inner">
										<?php echo $faq['a']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									</div>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<p class="ss-faq-empty" id="ss-faq-no-results" hidden>No encontramos resultados para tu búsqueda.</p>
	</section>

	<section class="ss-faq-support">
		<div class="ss-faq-support__inner">
			<div class="ss-faq-support__lead">
				<span class="ss-faq-support__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
						<path d="M14 9.5a3.5 3.5 0 0 0-3.5-3.5H6.5A3.5 3.5 0 0 0 3 9.5v2A3.5 3.5 0 0 0 6.5 15H7v2.5L10.2 15H10.5"/>
						<path d="M10.5 8.5h7A3.5 3.5 0 0 1 21 12v2a3.5 3.5 0 0 1-3.5 3.5H17v2.5L13.2 17.5H10.5A3.5 3.5 0 0 1 7 14"/>
						<circle cx="12.5" cy="12.5" r="0.7" fill="currentColor" stroke="none"/>
						<circle cx="15" cy="12.5" r="0.7" fill="currentColor" stroke="none"/>
						<circle cx="17.5" cy="12.5" r="0.7" fill="currentColor" stroke="none"/>
					</svg>
				</span>
				<div class="ss-faq-support__copy">
					<strong>¿Aún tienes dudas?</strong>
					<span>Nuestro equipo está aquí para ayudarte.</span>
				</div>
			</div>
			<div class="ss-faq-support__points">
				<div class="ss-faq-support__point">
					<span class="ss-faq-support__point-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
							<circle cx="12" cy="12" r="9"/>
							<path d="M12 7v5l3 2"/>
						</svg>
					</span>
					<div>
						<strong>Respuesta rápida</strong>
						<span>Te respondemos lo antes posible.</span>
					</div>
				</div>
				<div class="ss-faq-support__point">
					<span class="ss-faq-support__point-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
							<path d="M12 3l7 3v5c0 4.5-3 8.4-7 9.5C8 19.4 5 15.5 5 11V6l7-3z"/>
							<path d="M9.5 12.2l1.8 1.8 3.7-3.8"/>
						</svg>
					</span>
					<div>
						<strong>Atención segura</strong>
						<span>Tus datos siempre protegidos.</span>
					</div>
				</div>
				<div class="ss-faq-support__point">
					<span class="ss-faq-support__point-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
							<path d="M4 14v-2a8 8 0 0 1 16 0v2"/>
							<path d="M4 14v3a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-2H4z"/>
							<path d="M20 14v3a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-2h5z"/>
							<path d="M18 19v1a3 3 0 0 1-3 3"/>
						</svg>
					</span>
					<div>
						<strong>Soporte humano</strong>
						<span>Te atendemos personas, no bots.</span>
					</div>
				</div>
			</div>
			<a class="ss-faq-support__btn" href="<?php echo esc_url($contacto); ?>" target="_blank" rel="noopener noreferrer">
				CONTACTAR SOPORTE
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M9 6l6 6-6 6"/>
				</svg>
			</a>
		</div>
	</section>

	<section class="ss-faq-trust">
		<div class="ss-faq-trust__item">
			<span class="ss-faq-trust__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
					<path d="M12 3l7 3v5c0 4.5-3 8.4-7 9.5C8 19.4 5 15.5 5 11V6l7-3z"/>
					<path d="M9.5 12.2l1.8 1.8 3.7-3.8"/>
				</svg>
			</span>
			<div>
				<strong>Compra 100% segura</strong>
				<em>Tus datos y pagos protegidos</em>
			</div>
		</div>
		<div class="ss-faq-trust__item">
			<span class="ss-faq-trust__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
					<path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V9z"/>
					<path d="M9 7v10"/>
				</svg>
			</span>
			<div>
				<strong>DigiTickets digitales</strong>
				<em>Recibes por email al instante</em>
			</div>
		</div>
		<div class="ss-faq-trust__item">
			<span class="ss-faq-trust__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="12" cy="9.5" r="5.5"/>
					<path d="M8.5 13.5L7 21l5-2.5L17 21l-1.5-7.5"/>
					<path d="M12 7.2l.9 1.8 2 .3-1.45 1.4.35 2-1.8-.95-1.8.95.35-2L9.1 9.3l2-.3z"/>
				</svg>
			</span>
			<div>
				<strong>Concursos certificados</strong>
				<em>Bases legales ante Notaría</em>
			</div>
		</div>
		<div class="ss-faq-trust__item">
			<span class="ss-faq-trust__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
					<rect x="5" y="11" width="14" height="10" rx="2"/>
					<path d="M8 11V8a4 4 0 0 1 8 0v3"/>
					<circle cx="12" cy="16" r="1"/>
				</svg>
			</span>
			<div>
				<strong>Transparencia total</strong>
				<em>Procesos auditados y verificables</em>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
