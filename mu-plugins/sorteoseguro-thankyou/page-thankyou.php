<?php
/**
 * Plantilla thank-you – order-received
 */
if (!defined('ABSPATH')) {
	exit;
}

$order = class_exists('SorteoSeguro_Thankyou') ? SorteoSeguro_Thankyou::current_order() : null;
$ok    = $order instanceof WC_Order && !$order->has_status(['failed', 'cancelled', 'refunded']);
$assets = content_url('mu-plugins/sorteoseguro-thankyou/assets');
$contests = class_exists('SorteoSeguro_Home') ? SorteoSeguro_Home::get_contest_cards() : [];
$faq_url  = class_exists('SorteoSeguro_Thankyou') ? SorteoSeguro_Thankyou::faq_url() : home_url('/preguntas_frecuentes/');
$shop_url = class_exists('SorteoSeguro_Thankyou') ? SorteoSeguro_Thankyou::shop_url() : home_url('/');
$packs_url = class_exists('SorteoSeguro_Thankyou') ? SorteoSeguro_Thankyou::packs_url() : $shop_url;
$tickets_url = class_exists('SorteoSeguro_Thankyou') ? SorteoSeguro_Thankyou::tickets_url() : home_url('/mi-cuenta/');
$orders_url  = class_exists('SorteoSeguro_Thankyou') ? SorteoSeguro_Thankyou::orders_url() : home_url('/mi-cuenta/orders/');
$home_url = get_permalink(74938) ?: home_url('/');

$ico = [
	'check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M9.2 16.6 4.8 12.2l1.4-1.4 3 3 8.6-8.6 1.4 1.4z"/></svg>',
	'doc' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="M7 3.5h7.2L17.5 6.8V20a1.5 1.5 0 0 1-1.5 1.5H7A1.5 1.5 0 0 1 5.5 20V5A1.5 1.5 0 0 1 7 3.5z"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M14 3.5V7h3.5M8.5 11h6M8.5 14.5h6M8.5 18h4"/></svg>',
	'cal' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15.5" rx="2" stroke="currentColor" stroke-width="1.7"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M8 3.5V7M16 3.5V7M3.5 10h17"/></svg>',
	'mail' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5.5" width="18" height="13" rx="2" stroke="currentColor" stroke-width="1.7"/><path stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="m4 7.5 8 6 8-6"/></svg>',
	'dollar' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8.2" stroke="currentColor" stroke-width="1.7"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M12 7.2v9.6M9.2 9.2c.6-1 1.6-1.5 2.8-1.5 1.7 0 2.8.9 2.8 2.2s-1.2 2-2.9 2.3c-1.8.3-3 1-3 2.4 0 1.4 1.2 2.3 3.1 2.3 1.3 0 2.3-.5 2.9-1.5"/></svg>',
	'user' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8" r="3.2" stroke="currentColor" stroke-width="1.7"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M5.5 19c.8-3.2 3.3-5 6.5-5s5.7 1.8 6.5 5"/></svg>',
	'phone' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="M7.2 3.8h3.2l1.2 3.2-1.8 1.8a12.5 12.5 0 0 0 5.4 5.4l1.8-1.8 3.2 1.2v3.2c0 1.1-.9 2-2.1 2C9.6 18.8 5.2 14.4 5.2 6c0-1.2.9-2.2 2-2.2z"/></svg>',
	'ticket' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="M3.5 9.2V7a1.8 1.8 0 0 1 1.8-1.8h13.4A1.8 1.8 0 0 1 20.5 7v2.2a1.7 1.7 0 1 0 0 3.4V14a1.7 1.7 0 1 0 0 3.4V17a1.8 1.8 0 0 1-1.8 1.8H5.3A1.8 1.8 0 0 1 3.5 17v-1.2a1.7 1.7 0 1 0 0-3.4V12.6a1.7 1.7 0 1 0 0-3.4z"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-dasharray="2 2.4" d="M12 6.4v11.2"/></svg>',
	'bag' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="M6.2 8.5h11.6l-.8 11H7l-.8-11z"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M9 8.5V7.2A3 3 0 0 1 12 4.2 3 3 0 0 1 15 7.2v1.3"/></svg>',
	'arrow' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M9.3 6.7 10.7 5.3 17.4 12l-6.7 6.7-1.4-1.4L14.6 12z"/></svg>',
	'star' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m12 3.2 2.4 4.9 5.4.8-3.9 3.8.9 5.4L12 15.6 7.2 18.1l.9-5.4-3.9-3.8 5.4-.8z"/></svg>',
	'nav' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M8.1 3.6 10.3 1.8 20.2 12 10.3 22.2 8.1 20.4 15.8 12z"/></svg>',
	'gift' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.7" d="M4 10.5h16v10H4zM3.5 7.5h17v3h-17zM12 7.5v13"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M12 7.5c-2-3-4.6-3.6-6-1.8S7.5 9 12 7.5c2-3 4.6-3.6 6-1.8S16.5 9 12 7.5z"/></svg>',
	'shield' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="M12 3.2 5 6v5.2c0 4.6 3 8.6 7 9.8 4-1.2 7-5.2 7-9.8V6l-7-2.8z"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" d="m8.8 12 2.2 2.2 4.3-4.4"/></svg>',
	'seal' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="10.5" r="6.2" stroke="currentColor" stroke-width="1.7"/><path stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="m9.2 19 2.8-3.2L14.8 19l-1.1-4.2"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" d="m9.2 10.5 1.8 1.8 3.8-3.8"/></svg>',
	'headset' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M4.8 13V11a7.2 7.2 0 0 1 14.4 0v2"/><rect x="3.2" y="12.2" width="4.2" height="6.2" rx="1.4" stroke="currentColor" stroke-width="1.7"/><rect x="16.6" y="12.2" width="4.2" height="6.2" rx="1.4" stroke="currentColor" stroke-width="1.7"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M19 18.4v.8A2.6 2.6 0 0 1 16.4 21.8h-2"/></svg>',
];

get_header();
?>
<main class="ss-ty<?php echo $ok ? '' : ' is-pending'; ?>">

	<section class="ss-ty-hero">
		<div class="ss-ty__shell ss-ty-hero__inner">
			<div class="ss-ty-hero__copy">
				<span class="ss-ty-hero__check" aria-hidden="true"><?php echo $ico['check']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<div>
					<?php if ($ok) : ?>
						<h1 class="ss-ty-hero__title">¡Participación <em>confirmada!</em></h1>
						<p class="ss-ty-hero__sub">Tus DigiTickets ya quedaron registrados en el concurso. Te los enviaremos a tu correo y también podrás verlos en tu cuenta.</p>
					<?php elseif ($order) : ?>
						<h1 class="ss-ty-hero__title">Estamos <em>procesando</em> tu pago</h1>
						<p class="ss-ty-hero__sub">Cuando se confirme, tus DigiTickets quedarán registrados y te avisaremos por correo.</p>
					<?php else : ?>
						<h1 class="ss-ty-hero__title">No encontramos este pedido</h1>
						<p class="ss-ty-hero__sub">Revisa el enlace de tu correo o entra a tu cuenta para ver tus participaciones.</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>

	<?php if ($order instanceof WC_Order) : ?>
		<?php
		$subtotal  = (float) $order->get_subtotal();
		$discount  = (float) $order->get_total_discount();
		$total     = (float) $order->get_total();
		$email     = (string) $order->get_billing_email();
		$phone     = (string) $order->get_billing_phone();
		$name      = trim($order->get_formatted_billing_full_name());
		?>
		<section class="ss-ty-meta">
			<div class="ss-ty__shell ss-ty-meta__grid">
				<article class="ss-ty-meta__card">
					<span class="ss-ty-meta__ico" aria-hidden="true"><?php echo $ico['doc']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div>
						<small>NÚMERO DE PEDIDO</small>
						<strong><?php echo esc_html((string) $order->get_order_number()); ?></strong>
					</div>
				</article>
				<article class="ss-ty-meta__card">
					<span class="ss-ty-meta__ico" aria-hidden="true"><?php echo $ico['cal']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div>
						<small>FECHA</small>
						<strong><?php echo esc_html(SorteoSeguro_Thankyou::format_date($order)); ?></strong>
					</div>
				</article>
				<article class="ss-ty-meta__card">
					<span class="ss-ty-meta__ico" aria-hidden="true"><?php echo $ico['mail']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div>
						<small>EMAIL</small>
						<strong class="ss-ty-meta__email"><?php echo esc_html($email); ?></strong>
					</div>
				</article>
				<article class="ss-ty-meta__card">
					<span class="ss-ty-meta__ico" aria-hidden="true"><?php echo $ico['dollar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div>
						<small>TOTAL PAGADO</small>
						<strong class="ss-ty-meta__paid"><?php echo esc_html(SorteoSeguro_Thankyou::money($total)); ?></strong>
					</div>
				</article>
			</div>
		</section>

		<section class="ss-ty-order">
			<div class="ss-ty__shell">
				<h2 class="ss-ty-section__title">Detalles del pedido</h2>
				<div class="ss-ty-table">
					<div class="ss-ty-table__head">
						<span>Producto</span>
						<span>Total</span>
					</div>
					<?php foreach ($order->get_items() as $item) :
						if (!$item instanceof WC_Order_Item_Product) {
							continue;
						}
						$product = $item->get_product();
						$img = '';
						if ($product) {
							$img_id = $product->get_image_id();
							if ($img_id) {
								$src = wp_get_attachment_image_url((int) $img_id, 'woocommerce_gallery_thumbnail');
								$img = $src ?: '';
							}
						}
						$tickets = SorteoSeguro_Thankyou::item_tickets($item);
						$qty = (int) $item->get_quantity();
						$line = (float) $item->get_subtotal();
						$item_name = $item->get_name();
						?>
						<div class="ss-ty-table__row">
							<div class="ss-ty-product">
								<?php if ($img !== '') : ?>
									<img src="<?php echo esc_url($img); ?>" alt="" width="72" height="52" loading="lazy" decoding="async">
								<?php endif; ?>
								<div>
									<p class="ss-ty-product__name"><?php echo esc_html($item_name); ?> <span>× <?php echo esc_html((string) $qty); ?></span></p>
									<?php if ($tickets) : ?>
										<p class="ss-ty-product__tickets">
											<?php foreach ($tickets as $t) : ?>
												<span><?php echo esc_html($t); ?></span>
											<?php endforeach; ?>
										</p>
									<?php endif; ?>
								</div>
							</div>
							<div class="ss-ty-table__total"><?php echo esc_html(SorteoSeguro_Thankyou::money($line)); ?></div>
						</div>
					<?php endforeach; ?>
					<div class="ss-ty-table__foot">
						<div><span>Subtotal</span><strong><?php echo esc_html(SorteoSeguro_Thankyou::money($subtotal)); ?></strong></div>
						<?php if ($discount > 0) : ?>
							<div class="is-discount"><span>Descuento</span><strong><?php echo esc_html(SorteoSeguro_Thankyou::money(-$discount)); ?></strong></div>
						<?php endif; ?>
						<div class="is-total"><span>Total</span><strong><?php echo esc_html(SorteoSeguro_Thankyou::money($total)); ?></strong></div>
					</div>
				</div>
			</div>
		</section>

		<section class="ss-ty-bill">
			<div class="ss-ty__shell">
				<h2 class="ss-ty-section__title">Dirección de facturación</h2>
				<div class="ss-ty-bill__grid">
					<article class="ss-ty-bill__card">
						<span aria-hidden="true"><?php echo $ico['user']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<strong><?php echo esc_html($name !== '' ? $name : '—'); ?></strong>
					</article>
					<article class="ss-ty-bill__card">
						<span aria-hidden="true"><?php echo $ico['phone']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<strong><?php echo esc_html($phone !== '' ? $phone : '—'); ?></strong>
					</article>
					<article class="ss-ty-bill__card">
						<span aria-hidden="true"><?php echo $ico['mail']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<strong><?php echo esc_html($email !== '' ? $email : '—'); ?></strong>
					</article>
				</div>
			</div>
		</section>

		<section class="ss-ty-actions">
			<div class="ss-ty__shell ss-ty-actions__row">
				<a class="ss-ty-btn" href="<?php echo esc_url($tickets_url); ?>">
					<span aria-hidden="true"><?php echo $ico['ticket']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					Ver mis DigiTickets
				</a>
				<a class="ss-ty-btn" href="<?php echo esc_url($orders_url); ?>">
					<span aria-hidden="true"><?php echo $ico['bag']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					Ir a mis pedidos
				</a>
			</div>
		</section>

		<?php
		$flash_code = 'SSD50OFF';
		$flash_bolt = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M13 2 4.5 13.2h6L9.2 22 19.5 9.8h-6.2L13 2z"/></svg>';
		$flash_copy = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M16 1H4a2 2 0 0 0-2 2v12h2V3h12zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2zm0 16H8V7h11z"/></svg>';
		?>
		<section class="ss-home-promo" aria-label="Promoción especial">
			<div class="ss-home__shell">
				<div class="ss-home-promo__panel">
					<div class="ss-home-promo__body">
						<div class="ss-home-promo__copy">
							<span class="ss-home-promo__badge">
								<span class="ss-home-promo__badge-ico" aria-hidden="true"><?php echo $flash_bolt; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								Promoción por tiempo limitado
							</span>
							<h2>
								Beneficio
								<em>especial</em>
								activo
							</h2>
							<p class="ss-home-promo__sub">Aprovecha este descuento exclusivo y participa hoy. Acumulable con otras promociones y descuentos.</p>
						</div>

						<div class="ss-home-promo__deal">
							<div class="ss-home-promo__discount" aria-hidden="true">
								<strong>50%dcto.</strong>
								<span>en DigiTickets seleccionados</span>
							</div>
							<div class="ss-home-promo__timer" data-ss-ty-flash-hours="3" aria-live="polite">
								<div class="ss-home-promo__unit"><strong data-ss-promo-d>00</strong><span>dias</span></div>
								<span class="ss-home-promo__sep" aria-hidden="true">:</span>
								<div class="ss-home-promo__unit"><strong data-ss-promo-h>03</strong><span>horas</span></div>
								<span class="ss-home-promo__sep" aria-hidden="true">:</span>
								<div class="ss-home-promo__unit"><strong data-ss-promo-m>00</strong><span>min</span></div>
								<span class="ss-home-promo__sep" aria-hidden="true">:</span>
								<div class="ss-home-promo__unit"><strong data-ss-promo-s>00</strong><span>seg</span></div>
							</div>
						</div>

						<span class="ss-home-promo__divider" aria-hidden="true"></span>

						<div class="ss-home-promo__offer">
							<span class="ss-home-promo__code-label">Usa el código:</span>
							<div class="ss-home-promo__code-row">
								<div class="ss-home-promo__codebox">
									<code><?php echo esc_html($flash_code); ?></code>
									<button type="button" class="ss-home-promo__copybtn" data-ss-copy="<?php echo esc_attr($flash_code); ?>" aria-label="Copiar código <?php echo esc_attr($flash_code); ?>">
										<span aria-hidden="true"><?php echo $flash_copy; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									</button>
								</div>
								<a class="ss-home-btn ss-home-btn--light ss-home-promo__cta" href="<?php echo esc_url($home_url); ?>#ss-home-concursos">
									Ver concursos
									<span class="ss-home-btn__ico" aria-hidden="true"><?php echo $ico['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								</a>
							</div>
							<p class="ss-home-promo__copied" data-ss-copy-toast hidden aria-live="polite">Código copiado</p>
						</div>
					</div>
					<p class="ss-home-promo__legal">Acumulable con otras promociones y descuentos. Promoción válida por tiempo limitado. Revisa las bases legales en cada concurso.</p>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<section class="ss-ty-promo">
		<div class="ss-ty__shell ss-ty-promo__panel">
			<div class="ss-ty-promo__copy">
				<h2>¡No te quedes fuera!</h2>
				<p>Siguen abiertos concursos con premios increíbles. Aprovecha ahora y suma más oportunidades de ganar.</p>
				<a class="ss-ty-btn ss-ty-btn--orange" href="<?php echo esc_url($home_url); ?>#ss-home-concursos">
					Ver sorteos activos
					<span aria-hidden="true"><?php echo $ico['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</a>
			</div>
			<div class="ss-ty-promo__art" aria-hidden="true">
				<img src="<?php echo esc_url($assets . '/banner-exito.png'); ?>" alt="" width="520" height="292" loading="lazy" decoding="async">
			</div>
		</div>
	</section>

	<section class="ss-ty-contests" aria-labelledby="ss-ty-contests-title">
		<div class="ss-ty__shell">
			<header class="ss-ty-contests__head">
				<h2 id="ss-ty-contests-title">
					<span class="ss-ty-contests__star" aria-hidden="true"><?php echo $ico['star']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					Sorteos activos
				</h2>
				<a href="<?php echo esc_url($home_url); ?>#ss-home-concursos">
					Ver todos los sorteos
					<span aria-hidden="true"><?php echo $ico['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</a>
			</header>
			<?php if ($contests) : ?>
				<div class="ss-ty-contests__carousel" data-ss-ty-carousel>
					<button type="button" class="ss-ty-nav ss-ty-nav--prev" data-ss-ty-prev aria-label="Anterior"><?php echo $ico['nav']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
					<div class="ss-ty-contests__viewport">
						<div class="ss-ty-contests__track">
							<?php foreach ($contests as $c) :
								$cat = SorteoSeguro_Thankyou::category_label((string) ($c['category_slug'] ?? ''), (string) ($c['category'] ?? ''));
								$cat_mod = ($c['category_slug'] ?? '') === 'realty' ? 'is-realty' : 'is-vehicle';
								$title = SorteoSeguro_Thankyou::contest_title((string) ($c['title'] ?? ''));
								$price = SorteoSeguro_Thankyou::money((float) ($c['price_raw'] ?? 0));
								?>
								<article class="ss-ty-card">
									<a class="ss-ty-card__img" href="<?php echo esc_url((string) $c['url']); ?>">
										<?php if (!empty($c['image'])) : ?>
											<img src="<?php echo esc_url((string) $c['image']); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy" decoding="async">
										<?php endif; ?>
										<span class="ss-ty-card__cat <?php echo esc_attr($cat_mod); ?>"><?php echo esc_html($cat); ?></span>
									</a>
									<div class="ss-ty-card__body">
										<h3><a href="<?php echo esc_url((string) $c['url']); ?>"><?php echo esc_html($title); ?></a></h3>
										<p>Valor DigiTicket: <strong><?php echo esc_html($price); ?></strong></p>
										<a class="ss-ty-card__more" href="<?php echo esc_url((string) $c['url']); ?>">
											Ver más información
											<span aria-hidden="true"><?php echo $ico['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</a>
									</div>
								</article>
							<?php endforeach; ?>
						</div>
					</div>
					<button type="button" class="ss-ty-nav ss-ty-nav--next" data-ss-ty-next aria-label="Siguiente"><?php echo $ico['nav']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<section class="ss-ty-tiles">
		<div class="ss-ty__shell ss-ty-tiles__row">
			<article class="ss-ty-tile ss-ty-tile--pink">
				<span class="ss-ty-tile__ico" aria-hidden="true"><?php echo $ico['gift']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<div>
					<h3>Promociones vigentes</h3>
					<p>Aprovecha descuentos activos y aumenta tus oportunidades de ganar.</p>
					<a href="<?php echo esc_url($home_url); ?>#ss-home-concursos">Ver promociones <span aria-hidden="true"><?php echo $ico['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></a>
				</div>
			</article>
			<article class="ss-ty-tile ss-ty-tile--orange">
				<span class="ss-ty-tile__ico" aria-hidden="true"><?php echo $ico['ticket']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<div>
					<h3>Packs de DigiTickets</h3>
					<p>Lleva más participaciones y ahorra en cada pack.</p>
					<a href="<?php echo esc_url($packs_url); ?>">Ver packs <span aria-hidden="true"><?php echo $ico['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></a>
				</div>
			</article>
		</div>
	</section>

	<section class="ss-ty-trust">
		<div class="ss-ty__shell ss-ty-trust__grid">
			<article>
				<span aria-hidden="true"><?php echo $ico['shield']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<strong>Eventos certificados y transparentes</strong>
				<p>Bases legales protocolizadas ante notario.</p>
			</article>
			<article>
				<span aria-hidden="true"><?php echo $ico['seal']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<strong>Respaldo legal y confianza</strong>
				<p>Procesos transparentes y verificables.</p>
			</article>
			<article>
				<span aria-hidden="true"><?php echo $ico['mail']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<strong>Entrega inmediata de DigiTickets</strong>
				<p>Llegan a tu email al instante.</p>
			</article>
			<article>
				<span aria-hidden="true"><?php echo $ico['headset']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<strong>Soporte disponible</strong>
				<p>Estamos para ayudarte cuando lo necesites.</p>
			</article>
		</div>
	</section>

	<section class="ss-ty-help">
		<div class="ss-ty__shell ss-ty-help__panel">
			<img src="<?php echo esc_url($assets . '/chat-exito.png'); ?>" alt="" width="88" height="88" loading="lazy" decoding="async">
			<div>
				<h2>¿Tienes dudas?</h2>
				<p>Revisa las Preguntas Frecuentes o contáctanos si necesitas una mano con tu participación.</p>
			</div>
			<a class="ss-ty-btn ss-ty-btn--blue" href="<?php echo esc_url($faq_url); ?>">
				Ir a Preguntas Frecuentes
				<span aria-hidden="true"><?php echo $ico['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			</a>
		</div>
	</section>
</main>
<?php
if ($order instanceof WC_Order && class_exists('SorteoSeguro_Thankyou')) {
	SorteoSeguro_Thankyou::fire_thankyou($order);
}
get_footer();
