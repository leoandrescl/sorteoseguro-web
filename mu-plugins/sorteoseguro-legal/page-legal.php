<?php
/**
 * Plantilla compartida de páginas legales (Términos, privacidad, cookies, envío, concursos).
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

$legal = class_exists('SorteoSeguro_Legal') ? SorteoSeguro_Legal::current_page() : null;
if (!$legal) {
	status_header(404);
	nocache_headers();
	include get_query_template('404');
	return;
}

$ico = static function (string $name): string {
	return class_exists('SorteoSeguro_Legal') ? SorteoSeguro_Legal::icon($name) : '';
};

$render_item = static function (array $item, int $n, bool $boxed = false) use ($ico): void {
	$icon  = (string) ($item['icon'] ?? 'doc');
	$title = (string) ($item['title'] ?? '');
	$html  = (string) ($item['html'] ?? '');
	$mod   = $boxed ? ' ss-legal-item--boxed' : '';
	?>
	<article class="ss-legal-item<?php echo esc_attr($mod); ?>" id="ss-legal-<?php echo (int) $n; ?>">
		<span class="ss-legal-item__n" aria-hidden="true"><?php echo (int) $n; ?></span>
		<div class="ss-legal-item__main">
			<?php if ($boxed) : ?>
				<span class="ss-legal-item__ico" aria-hidden="true"><?php echo $ico($icon); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<?php endif; ?>
			<h2 class="ss-legal-item__title">
				<?php if (!$boxed) : ?>
					<span class="ss-legal-item__ico" aria-hidden="true"><?php echo $ico($icon); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<?php endif; ?>
				<?php echo esc_html($title); ?>
			</h2>
			<div class="ss-legal-item__text">
				<?php echo class_exists('SorteoSeguro_Legal') ? SorteoSeguro_Legal::kses($html) : wp_kses_post($html); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
	</article>
	<?php
};

$items  = is_array($legal['items'] ?? null) ? $legal['items'] : [];
$total  = count($items);
$layout = (string) ($legal['layout'] ?? 'cols');
$mid    = isset($legal['split']) ? (int) $legal['split'] : (int) ceil($total / 2);
if ($mid < 1 || $mid >= $total) {
	$mid = (int) ceil($total / 2);
}

$hero_file = (string) ($legal['hero'] ?? '');
$hero_src  = '';
if ($hero_file !== '' && class_exists('SorteoSeguro_Legal')) {
	$abs = SorteoSeguro_Legal::DIR . '/assets/' . ltrim($hero_file, '/');
	if (is_readable($abs)) {
		$hero_src = SorteoSeguro_Legal::asset_url($hero_file);
	}
}

$hero_title  = (string) ($legal['title'] ?? get_the_title());
$hero_label  = wp_strip_all_tags($hero_title);
$hero_eye    = (string) ($legal['eyebrow'] ?? '');
$hero_lead   = (string) ($legal['lead'] ?? '');
$hero_upd    = (string) ($legal['updated'] ?? '');
$hero_alt    = (string) ($legal['hero_alt'] ?? '');
$hero_badge  = (string) ($legal['hero_badge'] ?? 'doc-badge');
$confirm     = (string) ($legal['confirm'] ?? '');
$confirm_t   = (string) ($legal['confirm_title'] ?? '');
$confirm_ico = (string) ($legal['confirm_icon'] ?? 'scales');
$confirm_mod = (string) ($legal['confirm_mod'] ?? '');
$inner_mod   = $hero_badge === '' ? ' ss-legal-hero__inner--nobadge' : '';

get_header();
?>
<main class="ss-legal">
	<section class="ss-legal-hero" aria-label="<?php echo esc_attr($hero_label); ?>">
		<span class="ss-legal-hero__blob ss-legal-hero__blob--l" aria-hidden="true"></span>
		<span class="ss-legal-hero__blob ss-legal-hero__blob--r" aria-hidden="true"></span>
		<div class="ss-legal__shell ss-legal-hero__inner<?php echo esc_attr($inner_mod); ?>">
			<?php if ($hero_badge !== '') : ?>
				<span class="ss-legal-hero__badge" aria-hidden="true"><?php echo $ico($hero_badge); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<?php endif; ?>
			<div class="ss-legal-hero__copy">
				<?php if ($hero_eye !== '') : ?>
					<p class="ss-legal-hero__eyebrow"><?php echo esc_html($hero_eye); ?></p>
				<?php endif; ?>
				<h1 class="ss-legal-hero__title"><?php echo wp_kses($hero_title, ['br' => []]); ?></h1>
				<?php if ($hero_lead !== '') : ?>
					<p class="ss-legal-hero__lead"><?php echo esc_html($hero_lead); ?></p>
				<?php endif; ?>
				<?php if ($hero_upd !== '') : ?>
					<p class="ss-legal-hero__updated">
						<span aria-hidden="true"><?php echo $ico('calendar'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span>Última actualización: <?php echo esc_html($hero_upd); ?></span>
					</p>
				<?php endif; ?>
			</div>
			<figure class="ss-legal-hero__art<?php echo $hero_src === '' ? ' ss-legal-hero__art--ph' : ''; ?>">
				<?php if ($hero_src !== '') : ?>
					<img src="<?php echo esc_url($hero_src); ?>" alt="<?php echo esc_attr($hero_alt); ?>" width="720" height="720" loading="eager" decoding="async">
				<?php endif; ?>
			</figure>
		</div>
	</section>

	<section class="ss-legal-body">
		<div class="ss-legal__shell">
			<div class="ss-legal-card">
				<?php if ($layout === 'rows') : ?>
					<div class="ss-legal-cols ss-legal-cols--rows">
						<?php foreach ($items as $i => $item) : ?>
							<?php $render_item($item, $i + 1); ?>
						<?php endforeach; ?>
					</div>
				<?php elseif ($layout === 'stack') : ?>
					<div class="ss-legal-cols ss-legal-cols--stack">
						<?php foreach ($items as $i => $item) : ?>
							<?php $render_item($item, $i + 1, true); ?>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="ss-legal-cols">
						<?php
						$cols   = [array_slice($items, 0, $mid), array_slice($items, $mid)];
						$offset = [0, $mid];
						foreach ($cols as $col_i => $col_items) :
							?>
							<div class="ss-legal-col">
								<?php foreach ($col_items as $i => $item) : ?>
									<?php $render_item($item, $offset[$col_i] + $i + 1); ?>
								<?php endforeach; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<?php if ($confirm !== '' || $confirm_t !== '') : ?>
					<aside class="ss-legal-confirm<?php echo $confirm_mod !== '' ? ' ss-legal-confirm--' . esc_attr($confirm_mod) : ''; ?>">
						<span class="ss-legal-confirm__ico" aria-hidden="true"><?php echo $ico($confirm_ico); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<div class="ss-legal-confirm__copy">
							<?php if ($confirm_t !== '') : ?>
								<strong><?php echo esc_html($confirm_t); ?></strong>
							<?php endif; ?>
							<?php if ($confirm !== '') : ?>
								<p><?php echo esc_html($confirm); ?></p>
							<?php endif; ?>
						</div>
					</aside>
				<?php endif; ?>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
