<?php
/**
 * Plantilla Bases legales – página ID 75047
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

$ico = static function (string $name): string {
	return class_exists('SorteoSeguro_Bases') ? SorteoSeguro_Bases::icon($name) : '';
};

$rows  = class_exists('SorteoSeguro_Bases') ? SorteoSeguro_Bases::contest_rows() : [];
$video = class_exists('SorteoSeguro_Bases') ? SorteoSeguro_Bases::hero_video() : ['embed' => '', 'id' => '', 'thumb' => ''];

get_header();
?>
<main class="ss-bases">
	<section class="ss-bases__shell ss-bases-hero" aria-label="Introducción bases legales">
		<div class="ss-bases-hero__grid">
			<div class="ss-bases-hero__copy">
				<h1 class="ss-bases-hero__title">
					Revisa todas las <em>bases legales</em> aquí.
				</h1>
				<p class="ss-bases-hero__lead">
					Cada concurso de Sorteo Seguro cuenta con sus condiciones claras y establecidas para que las revises antes de participar.
				</p>
				<div class="ss-bases-hero__note">
					<span class="ss-bases-hero__note-ico" aria-hidden="true"><?php echo $ico('shield'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div class="ss-bases-hero__note-body">
						<p>Todos nuestros eventos se realizan una vez se complete el mínimo de DigiTickets informado en cada una de las bases legales.</p>
						<p>Las fechas establecidas son estimativas y solo promocionales. <strong>La condición esencial siempre es el cumplimiento del mínimo de DigiTickets indicado en cada base legal.</strong></p>
					</div>
				</div>
			</div>
			<div class="ss-bases-hero__media">
				<?php if (!empty($video['embed']) && !empty($video['id'])) : ?>
					<div class="ss-bases-hero__video">
						<iframe
							class="ss-bases-hero__iframe skip-lazy"
							src="<?php echo esc_url($video['embed']); ?>"
							title="Bases legales: transparencia y confianza"
							width="1280"
							height="720"
							allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
							allowfullscreen
							loading="eager"
							referrerpolicy="strict-origin-when-cross-origin"
							data-category="functional"
							data-no-lazy="1"
						></iframe>
					</div>
				<?php endif; ?>
				<p class="ss-bases-hero__caption">
					<span class="ss-bases-hero__caption-ico" aria-hidden="true"><?php echo $ico('shield'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span>Conoce en este video por qué nuestras bases legales garantizan transparencia y confianza en cada sorteo.</span>
				</p>
			</div>
		</div>
	</section>

	<section class="ss-bases__shell ss-bases-list" aria-labelledby="ss-bases-list-title">
		<header class="ss-bases-list__head">
			<span class="ss-bases-list__head-ico" aria-hidden="true"><?php echo $ico('doc'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div>
				<h2 id="ss-bases-list-title" class="ss-bases-list__title">Sorteos activos</h2>
				<p class="ss-bases-list__sub">Descarga las bases legales de cada concurso y revisa todas las condiciones de participación.</p>
			</div>
		</header>

		<ul class="ss-bases-list__cards">
			<?php foreach ($rows as $row) : ?>
				<li class="ss-bases-card">
					<div class="ss-bases-card__media">
						<?php if ($row['image'] !== '') : ?>
							<img src="<?php echo esc_url($row['image']); ?>" alt="" loading="lazy" decoding="async" width="280" height="180">
						<?php endif; ?>
					</div>
					<div class="ss-bases-card__body">
						<span class="ss-bases-card__badge"><?php echo esc_html($row['category']); ?></span>
						<h3 class="ss-bases-card__title"><?php echo esc_html($row['title']); ?></h3>
						<ul class="ss-bases-card__meta">
							<?php if ($row['date'] !== '') : ?>
								<li>
									<span class="ss-bases-card__meta-ico" aria-hidden="true"><?php echo $ico('calendar'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<span><strong>Fecha estimada:</strong> <?php echo esc_html($row['date']); ?></span>
								</li>
							<?php endif; ?>
							<li>
								<span class="ss-bases-card__meta-ico" aria-hidden="true"><?php echo $ico('ticket'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<span><strong>Valor DigiTicket:</strong> <?php echo esc_html($row['price']); ?></span>
							</li>
						</ul>
						<a class="ss-bases-card__more" href="<?php echo esc_url($row['url']); ?>">
							Ver más información del concurso <span aria-hidden="true">›</span>
						</a>
					</div>
					<div class="ss-bases-card__download">
						<?php if ($row['pdf_url'] !== '') : ?>
							<a class="ss-bases-card__dl" href="<?php echo esc_url($row['pdf_url']); ?>" target="_blank" rel="noopener noreferrer">
								<span class="ss-bases-card__dl-ico" aria-hidden="true"><?php echo $ico('download'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<strong>Descargar bases legales</strong>
								<small><?php echo esc_html($row['pdf_label']); ?></small>
							</a>
						<?php else : ?>
							<span class="ss-bases-card__dl ss-bases-card__dl--disabled">
								<span class="ss-bases-card__dl-ico" aria-hidden="true"><?php echo $ico('download'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<strong>Bases no disponibles</strong>
								<small>PDF</small>
							</span>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<?php
	// Mismos bloques del checkout (FAQ primero según diseño de esta página).
	if (class_exists('SorteoSeguro_Checkout')) {
		SorteoSeguro_Checkout::render_help_block();
		SorteoSeguro_Checkout::render_legal_block();
	}
	?>
</main>
<?php
get_footer();
