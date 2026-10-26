<?php
/**
 * Elemento de cuenta regresiva "bono de bienvenida" (hardcoded, sin Promo Engine).
 *
 * Uso: ss_bono_contador_render();  // variante card (ficha de compra directa)
 *
 * El contador se resetea a diario a medianoche (hora del sitio) y nunca queda en 0:
 * al cruzar el límite vuelve a 24:00:00. El valor es igual para todos los visitantes.
 */
if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('ss_bono_contador_config')) {
	/**
	 * Contenido hardcoded del elemento. Editar aquí si cambia el monto o los textos.
	 *
	 * @return array<string, string>
	 */
	function ss_bono_contador_config(): array {
		return [
			'pill'         => 'POR TIEMPO LIMITADO',
			'title'        => 'Bienvenido a',
			'title_em'     => 'Sorteo Seguro',
			'sub'          => 'Aprovecha este <strong>regalo</strong> para ti en tu primera compra',
			'ticket_kicker'=> 'BONO DE BIENVENIDA',
			'amount'       => '$15.000',
			'ticket_label' => 'DE DESCUENTO',
			'ticket_note'  => 'EN TU PRIMERA COMPRA',
			'ticket_gift'  => 'TU BONO DE BIENVENIDA',
			'timer_title'  => '¡SOLO POR TIEMPO LIMITADO!',
			'timer_sub'    => 'Tu bono de bienvenida vence en:',
			'note'         => 'El bono se aplica <strong>automáticamente</strong> después de que selecciones el Packs de tu preferencia.',
			'unit_hours'   => 'Horas',
			'unit_minutes' => 'Minutos',
			'unit_seconds' => 'Segundos',
		];
	}
}

if (!function_exists('ss_bono_contador_asset_url')) {
	function ss_bono_contador_asset_url(string $file = ''): string {
		$file = ltrim($file, '/');
		if (!function_exists('content_url')) {
			return '';
		}
		return content_url('mu-plugins/sorteoseguro-contador/assets/' . $file);
	}
}

if (!function_exists('ss_bono_contador_is_active')) {
	/** Activo solo en las fichas de bono precargado (/bonificaciones/{slug}/). */
	function ss_bono_contador_is_active(): bool {
		return class_exists('SorteoSeguro_Comprar_Bono') && SorteoSeguro_Comprar_Bono::is_oferta_child();
	}
}

if (!function_exists('ss_bono_contador_time_left')) {
	/**
	 * Segundos restantes hasta la próxima medianoche (hora del sitio), tope 24h.
	 * Garantiza mínimo 1s para que nunca se muestre 00:00:00.
	 */
	function ss_bono_contador_time_left(): int {
		$tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('America/Santiago');
		try {
			$midnight = new DateTimeImmutable('now', $tz);
			$midnight = $midnight->modify('tomorrow')->setTime(0, 0, 0);
			$remaining = $midnight->getTimestamp() - time();
		} catch (Exception $e) {
			$remaining = 86400;
		}
		if ($remaining < 1) {
			$remaining = 86400;
		}
		if ($remaining > 86400) {
			$remaining = 86400;
		}
		return $remaining;
	}
}

if (!function_exists('ss_bono_contador_render')) {
	/**
	 * Imprime el elemento del contador.
	 *
	 * @param array<string, mixed> $args Sobrescrituras opcionales (variant, amount, texts).
	 */
	function ss_bono_contador_render(array $args = []): void {
		$cfg     = ss_bono_contador_config();
		$variant = isset($args['variant']) ? (string) $args['variant'] : 'card';

		$text = static function (string $key) use ($cfg, $args): string {
			return isset($args[$key]) ? (string) $args[$key] : (string) ($cfg[$key] ?? '');
		};

		$rich = static function (string $key) use ($text): string {
			$allowed = ['strong' => [], 'em' => [], 'br' => []];
			return wp_kses($text($key), $allowed);
		};

		$remaining = ss_bono_contador_time_left();
		$hours     = (int) floor($remaining / 3600);
		$minutes   = (int) floor(($remaining % 3600) / 60);
		$seconds   = $remaining % 60;
		$pad       = static fn(int $n): string => str_pad((string) $n, 2, '0', STR_PAD_LEFT);

		$until   = time() + $remaining;
		$ticket  = ss_bono_contador_asset_url('ticket-bono.png');
		$classes = 'ss-contador ss-contador--' . sanitize_html_class($variant);
		?>
		<div class="<?php echo esc_attr($classes); ?>"
			data-ss-contador
			data-ss-contador-until="<?php echo (int) $until; ?>"
			role="note"
			aria-label="<?php echo esc_attr($text('title') . ' ' . $text('title_em')); ?>">

			<span class="ss-contador__blob">
				<span class="ss-contador__blob-text" style='font-family: "Caveat", "Merienda", "Segoe Script", cursive !important;'>Tu próxima<br>gran historia<br>puede comenzar aquí</span>
			</span>

			<div class="ss-contador__top">
				<div class="ss-contador__head">
					<span class="ss-contador__clock" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7.2v5l3.2 1.9"/></svg>
					</span>
					<span class="ss-contador__pill"><?php echo esc_html($text('pill')); ?></span>
				</div>

				<p class="ss-contador__title">
					<span class="ss-contador__title-1"><?php echo esc_html($text('title')); ?></span>
					<span class="ss-contador__title-2"><?php echo esc_html($text('title_em')); ?></span>
				</p>
				<span class="ss-contador__rule" aria-hidden="true"></span>

				<p class="ss-contador__sub"><?php echo $rich('sub'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			</div>

			<div class="ss-contador__ticket">
				<?php if ($ticket !== '') : ?>
					<img class="ss-contador__ticket-bg" src="<?php echo esc_url($ticket); ?>" alt="" aria-hidden="true" loading="eager" decoding="async">
				<?php endif; ?>

				<span class="ss-contador__ticket-copy">
					<span class="ss-contador__ticket-kicker"><?php echo esc_html($text('ticket_kicker')); ?></span>
					<span class="ss-contador__ticket-amount"><?php echo esc_html($text('amount')); ?></span>
					<span class="ss-contador__ticket-label"><?php echo esc_html($text('ticket_label')); ?></span>
					<span class="ss-contador__ticket-note"><?php echo esc_html($text('ticket_note')); ?></span>
				</span>

				<span class="ss-contador__ticket-gift"><?php echo esc_html($text('ticket_gift')); ?></span>
			</div>

			<div class="ss-contador__band">
				<div class="ss-contador__timer">
					<span class="ss-contador__timer-clock" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7.2v5l3.2 1.9"/></svg>
					</span>
					<p class="ss-contador__timer-title"><?php echo esc_html($text('timer_title')); ?></p>
					<p class="ss-contador__timer-sub"><?php echo esc_html($text('timer_sub')); ?></p>

					<div class="ss-contador__units">
						<div class="ss-contador__unit">
							<span class="ss-contador__value" data-ss-contador-h><?php echo esc_html($pad($hours)); ?></span>
							<span class="ss-contador__label"><?php echo esc_html($text('unit_hours')); ?></span>
						</div>
						<span class="ss-contador__sep" aria-hidden="true">:</span>
						<div class="ss-contador__unit">
							<span class="ss-contador__value" data-ss-contador-m><?php echo esc_html($pad($minutes)); ?></span>
							<span class="ss-contador__label"><?php echo esc_html($text('unit_minutes')); ?></span>
						</div>
						<span class="ss-contador__sep" aria-hidden="true">:</span>
						<div class="ss-contador__unit">
							<span class="ss-contador__value" data-ss-contador-s><?php echo esc_html($pad($seconds)); ?></span>
							<span class="ss-contador__label"><?php echo esc_html($text('unit_seconds')); ?></span>
						</div>
					</div>
				</div>

				<div class="ss-contador__note">
					<span class="ss-contador__note-ico" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.14 12.94a7.07 7.07 0 0 0 .06-.94 7.07 7.07 0 0 0-.06-.94l2.03-1.58a.5.5 0 0 0 .12-.64l-1.92-3.32a.5.5 0 0 0-.61-.22l-2.39.96a7.31 7.31 0 0 0-1.63-.94l-.36-2.54a.5.5 0 0 0-.5-.42H9.76a.5.5 0 0 0-.5.42l-.36 2.54a7.31 7.31 0 0 0-1.63.94L4.88 6.2a.5.5 0 0 0-.61.22L2.35 9.74a.5.5 0 0 0 .12.64l2.03 1.58a7.07 7.07 0 0 0-.06.94 7.07 7.07 0 0 0 .06.94L2.47 15.42a.5.5 0 0 0-.12.64l1.92 3.32a.5.5 0 0 0 .61.22l2.39-.96a7.31 7.31 0 0 0 1.63.94l.36 2.54a.5.5 0 0 0 .5.42h3.68a.5.5 0 0 0 .5-.42l.36-2.54a7.31 7.31 0 0 0 1.63-.94l2.39.96a.5.5 0 0 0 .61-.22l1.92-3.32a.5.5 0 0 0-.12-.64ZM12 15.5A3.5 3.5 0 1 1 15.5 12 3.5 3.5 0 0 1 12 15.5Z"/></svg>
					</span>
					<p><?php echo $rich('note'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				</div>
			</div>

			<div class="ss-contador__foot">
				<span class="ss-contador__foot-line" aria-hidden="true"></span>
				<span class="ss-contador__foot-text">Confianza en cada concurso</span>
				<span class="ss-contador__foot-line" aria-hidden="true"></span>
			</div>
		</div>
		<?php
	}
}

if (!function_exists('ss_bono_contador_enqueue')) {
	function ss_bono_contador_enqueue(): void {
		if (!ss_bono_contador_is_active()) {
			return;
		}
		$dir  = __DIR__ . '/assets';
		$base = ss_bono_contador_asset_url();
		$ver_css = is_readable($dir . '/bono-contador.css') ? (string) filemtime($dir . '/bono-contador.css') : '1.0.0';
		$ver_js  = is_readable($dir . '/bono-contador.js') ? (string) filemtime($dir . '/bono-contador.js') : '1.0.0';

		if (is_readable($dir . '/bono-contador.css')) {
			wp_enqueue_style('ss-bono-contador', $base . 'bono-contador.css', [], $ver_css);
		}
		if (is_readable($dir . '/bono-contador.js')) {
			wp_enqueue_script('ss-bono-contador', $base . 'bono-contador.js', [], $ver_js, true);
		}
	}
}
add_action('wp_enqueue_scripts', 'ss_bono_contador_enqueue', 60);

if (!function_exists('ss_bono_contador_litespeed_excludes')) {
	/** Evita que LiteSpeed combine cachee estos assets y queden desactualizados. */
	function ss_bono_contador_litespeed_excludes(array $excludes): array {
		$excludes[] = 'bono-contador.css';
		$excludes[] = 'bono-contador.js';
		return $excludes;
	}
}
add_filter('litespeed_optimize_css_excludes', 'ss_bono_contador_litespeed_excludes');
add_filter('litespeed_optimize_js_excludes', 'ss_bono_contador_litespeed_excludes');
