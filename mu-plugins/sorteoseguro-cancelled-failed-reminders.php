<?php
/**
 * Plugin Name: Sorteo Seguro – Recordatorios cancelados/fallidos
 * Description: Recordatorios automáticos (3) para pedidos cancelados y fallidos, con carrito precargado de DigiTickets. Adicional: no modifica el flujo de pendientes existente.
 * Author: Sorteo Seguro
 * Version: 1.0.0
 *
 * mu-plugin: rollback = borrar este archivo. No toca datos existentes.
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Cancelled_Failed_Reminders {

	const VERSION = '1.0.0';
	const LOG_SOURCE = 'sorteoseguro-cfr';
	const OPT_LOG = 'ss_cfr_log';
	const OPT_DELAY_C1 = 'ss_cfr_delay_cancelled_1';
	const OPT_DELAY_C2 = 'ss_cfr_delay_cancelled_2';
	const OPT_DELAY_C3 = 'ss_cfr_delay_cancelled_3';
	const OPT_DELAY_F1 = 'ss_cfr_delay_failed_1';
	const OPT_DELAY_F2 = 'ss_cfr_delay_failed_2';
	const OPT_DELAY_F3 = 'ss_cfr_delay_failed_3';
	const META_SCHEDULED = '_ss_cfr_scheduled';
	const META_FLOW = '_ss_cfr_flow';
	const META_R1 = '_ss_cfr_r1_at';
	const META_R2 = '_ss_cfr_r2_at';
	const META_R3 = '_ss_cfr_r3_at';
	const AS_HOOK = 'ss_cfr_send_reminder';
	const ADMIN_TEST = 'ss_cfr_send_test';
	const ADMIN_MANUAL = 'ss_cfr_send_manual';
	const LOG_MAX = 1000;
	const DEFAULT_DELAY_1 = 30;
	const DEFAULT_DELAY_2 = 120;
	const DEFAULT_DELAY_3 = 3;
	const TOKEN_TTL_DAYS = 30;
	const CANDIDATE_DAYS = 30;
	const MENU_VISIBLE = true;
	const OPT_HOLDOUT_C = 'ss_cfr_holdout_cancelled';
	const OPT_HOLDOUT_F = 'ss_cfr_holdout_failed';

	public static function init(): void {
		add_action('woocommerce_order_status_cancelled', array(__CLASS__, 'schedule_for_order'), 20, 1);
		add_action('woocommerce_order_status_failed', array(__CLASS__, 'schedule_for_order'), 20, 1);
		add_action(self::AS_HOOK, array(__CLASS__, 'send_reminder'), 10, 2);
		add_action('template_redirect', array(__CLASS__, 'handle_preload_landing'), 4);
		add_action('template_redirect', array(__CLASS__, 'handle_preload'), 5);
		add_action('woocommerce_before_single_product', array(__CLASS__, 'maybe_show_preload_error_notice'), 5);
		add_action('woocommerce_checkout_update_order_meta', array(__CLASS__, 'attach_source_by_id'), 20, 2);
		add_action('woocommerce_store_api_checkout_order_processed', array(__CLASS__, 'attach_source_to_order'), 20, 1);
		add_action('woocommerce_order_status_processing', array(__CLASS__, 'mark_source_recovered'), 20, 1);
		add_action('woocommerce_order_status_completed', array(__CLASS__, 'mark_source_recovered'), 20, 1);
		add_action('woocommerce_cart_calculate_fees', array(__CLASS__, 'apply_recovery_discount'), 21, 1);
		add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'clear_discount_session'), 99, 1);
		add_action('woocommerce_store_api_checkout_order_processed', array(__CLASS__, 'clear_discount_session'), 99, 1);
		add_action('admin_init', array(__CLASS__, 'ensure_defaults'), 5);
		add_action('admin_menu', array(__CLASS__, 'register_admin_menu'), 61);
		add_action('admin_post_' . self::ADMIN_TEST, array(__CLASS__, 'handle_send_test'));
		add_action('admin_post_' . self::ADMIN_MANUAL, array(__CLASS__, 'handle_send_manual'));
	}

	private static function flow_for_status(string $status): string {
		return $status === 'failed' ? 'failed' : 'cancelled';
	}

	private static function flow_label(string $flow): string {
		return $flow === 'failed' ? 'fallido' : 'cancelado';
	}

	private static function page_slug(string $flow): string {
		return $flow === 'failed' ? 'ss-failed-reminders' : 'ss-cancelled-reminders';
	}

	private static function holdout_option(string $flow): string {
		return $flow === 'failed' ? self::OPT_HOLDOUT_F : self::OPT_HOLDOUT_C;
	}

	private static function get_holdout(string $flow): int {
		$value = (int) get_option(self::holdout_option($flow), 0);
		return max(0, min(100, $value));
	}

	private static function delay_option_name(string $flow, int $which): string {
		$map = array(
			'cancelled' => array(1 => self::OPT_DELAY_C1, 2 => self::OPT_DELAY_C2, 3 => self::OPT_DELAY_C3),
			'failed'    => array(1 => self::OPT_DELAY_F1, 2 => self::OPT_DELAY_F2, 3 => self::OPT_DELAY_F3),
		);
		return $map[$flow][$which] ?? '';
	}

	private static function delay_default(string $flow, int $which): int {
		$defaults = array(
			'cancelled' => array(1 => 10080, 2 => 30, 3 => 120),
			'failed'    => array(1 => self::DEFAULT_DELAY_1, 2 => self::DEFAULT_DELAY_2, 3 => self::DEFAULT_DELAY_3),
		);
		return $defaults[$flow][$which] ?? 1;
	}

	private static function delay_unit(string $flow, int $which): string {
		if ($flow === 'cancelled') {
			return 'minutes';
		}
		return $which === 3 ? 'days' : 'minutes';
	}

	private static function delay_unit_label(string $flow, int $which): string {
		return self::delay_unit($flow, $which) === 'days' ? 'días' : 'minutos';
	}

	private static function get_delay(string $flow, int $which): int {
		$name    = self::delay_option_name($flow, $which);
		$default = self::delay_default($flow, $which);
		$value   = $name !== '' ? (int) get_option($name, $default) : $default;
		$max     = self::delay_unit($flow, $which) === 'days' ? 365 : 10080;
		return max(1, min($max, $value));
	}

	private static function delay_seconds(string $flow, int $which): int {
		$value = self::get_delay($flow, $which);
		return self::delay_unit($flow, $which) === 'days' ? $value * DAY_IN_SECONDS : $value * MINUTE_IN_SECONDS;
	}

	public static function ensure_defaults(): void {
		// Migración: los cancelados pasan a 3 recordatorios en minutos (7 días / +30 min / +120 min).
		if (get_option('ss_cfr_cancelled_minutes_migrated') !== 'yes') {
			update_option(self::OPT_DELAY_C1, 10080, false);
			update_option(self::OPT_DELAY_C2, 30, false);
			update_option(self::OPT_DELAY_C3, 120, false);
			update_option('ss_cfr_cancelled_minutes_migrated', 'yes', false);
		}

		$defaults = array();
		foreach (array('cancelled', 'failed') as $flow) {
			foreach (array(1, 2, 3) as $which) {
				$name = self::delay_option_name($flow, $which);
				if ($name !== '') {
					$defaults[ $name ] = self::delay_default($flow, $which);
				}
			}
		}
		foreach ($defaults as $name => $value) {
			if (get_option($name, null) === null) {
				update_option($name, $value, false);
			}
		}

		// Holdout (grupo de control): 0% por defecto (sin efecto).
		foreach (array('cancelled', 'failed') as $flow) {
			if (get_option(self::holdout_option($flow), null) === null) {
				update_option(self::holdout_option($flow), 0, false);
			}
		}

		// Migración v2 de plantillas: limpiar claves viejas/nuevas para recargar los 6 textos.
		if (get_option('ss_cfr_templates_v2_migrated') !== 'yes') {
			$old = array(
				'ss_cfr_tpl_c_subject', 'ss_cfr_tpl_c_preheader', 'ss_cfr_tpl_c_heading', 'ss_cfr_tpl_c_body', 'ss_cfr_disc_c',
				'ss_cfr_tpl_f12_subject', 'ss_cfr_tpl_f12_preheader', 'ss_cfr_tpl_f12_heading', 'ss_cfr_tpl_f12_body', 'ss_cfr_disc_f12',
				'ss_cfr_tpl_f3_subject', 'ss_cfr_tpl_f3_preheader', 'ss_cfr_tpl_f3_heading', 'ss_cfr_tpl_f3_body', 'ss_cfr_disc_f3',
			);
			foreach ($old as $k) {
				delete_option($k);
			}
			foreach (self::template_keys() as $k) {
				foreach ($k as $name) {
					delete_option($name);
				}
			}
			update_option('ss_cfr_templates_v2_migrated', 'yes', false);
		}

		// Plantillas de correo por defecto.
		$keys = self::template_keys();
		foreach (self::template_defaults() as $id => $tpl) {
			if (!isset($keys[ $id ])) {
				continue;
			}
			if (get_option($keys[ $id ]['subject'], null) === null) {
				update_option($keys[ $id ]['subject'], $tpl['subject'], false);
				update_option($keys[ $id ]['preheader'], $tpl['preheader'], false);
				update_option($keys[ $id ]['heading'], $tpl['heading'], false);
				update_option($keys[ $id ]['body'], $tpl['body'], false);
				update_option($keys[ $id ]['discount'], $tpl['discount'], false);
			}
		}
	}

	private static function template_keys(): array {
		return array(
			'cancelled1' => array('subject' => 'ss_cfr_tpl_c1_subject', 'preheader' => 'ss_cfr_tpl_c1_preheader', 'heading' => 'ss_cfr_tpl_c1_heading', 'body' => 'ss_cfr_tpl_c1_body', 'discount' => 'ss_cfr_disc_c1'),
			'cancelled2' => array('subject' => 'ss_cfr_tpl_c2_subject', 'preheader' => 'ss_cfr_tpl_c2_preheader', 'heading' => 'ss_cfr_tpl_c2_heading', 'body' => 'ss_cfr_tpl_c2_body', 'discount' => 'ss_cfr_disc_c2'),
			'cancelled3' => array('subject' => 'ss_cfr_tpl_c3_subject', 'preheader' => 'ss_cfr_tpl_c3_preheader', 'heading' => 'ss_cfr_tpl_c3_heading', 'body' => 'ss_cfr_tpl_c3_body', 'discount' => 'ss_cfr_disc_c3'),
			'failed1'    => array('subject' => 'ss_cfr_tpl_f1_subject', 'preheader' => 'ss_cfr_tpl_f1_preheader', 'heading' => 'ss_cfr_tpl_f1_heading', 'body' => 'ss_cfr_tpl_f1_body', 'discount' => 'ss_cfr_disc_f1'),
			'failed2'    => array('subject' => 'ss_cfr_tpl_f2_subject', 'preheader' => 'ss_cfr_tpl_f2_preheader', 'heading' => 'ss_cfr_tpl_f2_heading', 'body' => 'ss_cfr_tpl_f2_body', 'discount' => 'ss_cfr_disc_f2'),
			'failed3'    => array('subject' => 'ss_cfr_tpl_f3_subject', 'preheader' => 'ss_cfr_tpl_f3_preheader', 'heading' => 'ss_cfr_tpl_f3_heading', 'body' => 'ss_cfr_tpl_f3_body', 'discount' => 'ss_cfr_disc_f3'),
		);
	}

	private static function template_id_for(string $flow, int $which): string {
		$which = max(1, min(3, (int) $which));
		return ($flow === 'failed' ? 'failed' : 'cancelled') . $which;
	}

	private static function template_ids_for_flow(string $flow): array {
		return $flow === 'failed' ? array('failed1', 'failed2', 'failed3') : array('cancelled1', 'cancelled2', 'cancelled3');
	}

	private static function template_defaults(): array {
		$btn  = 'display:inline-block;background:#6b3bb8;color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:8px;font-weight:700;font-size:15px;';
		$p    = 'margin:0 0 14px;font-size:15px;color:#3c434a;line-height:1.65;';
		$list = 'margin:0 0 6px;font-size:14px;color:#3c434a;line-height:1.55;';
		$h2   = 'margin:24px 0 8px;font-size:15px;color:#1d2327;font-weight:600;';
		$foot = 'margin:0;font-size:13px;color:#8a8f98;line-height:1.6;';

		$failed12_body = '<p style="margin:0 0 14px;font-size:16px;color:#1d2327;">Hola {nombre},</p>'
			. '<p style="' . $p . '">Vimos que intentaste realizar una compra para participar en uno de nuestros concursos, pero el pago no pudo completarse correctamente.</p>'
			. '<p style="' . $p . '">No te preocupes. Si todavía quieres participar, puedes realizar un nuevo intento de compra de forma simple y segura.</p>'
			. '<p style="' . $p . '">Como el intento anterior no llegó a completarse, los DigiTickets asociados a esa selección ya no se encuentran reservados.</p>'
			. '<p style="' . $p . '">Al continuar, nuestro sistema generará una nueva selección de DigiTickets disponibles para el mismo concurso y podrás realizar nuevamente el pago.</p>'
			. '<p style="text-align:center;margin:22px 0;"><a href="{url}" style="' . $btn . '">🎟️ INTENTAR MI COMPRA NUEVAMENTE</a></p>'
			. '<p style="' . $h2 . '">¿Qué ocurrirá al continuar?</p>'
			. '<p style="' . $list . '">✅ Volverás al mismo concurso que habías elegido.</p>'
			. '<p style="' . $list . '">✅ Se generará una nueva selección de DigiTickets disponibles.</p>'
			. '<p style="' . $list . '">✅ Podrás revisar tus nuevos DigiTickets antes de realizar el pago.</p>'
			. '<p style="' . $list . '">✅ Una vez confirmado el pago, tus DigiTickets quedarán asignados y tu participación estará confirmada.</p>'
			. '<p style="' . $h2 . '">¿Tuviste algún inconveniente?</p>'
			. '<p style="' . $p . '">Si experimentaste algún problema durante el proceso de pago, puedes simplemente intentarlo nuevamente. También puedes utilizar cualquiera de los medios de pago disponibles en nuestra plataforma para completar tu compra.</p>'
			. '<p style="text-align:center;margin:22px 0;"><a href="{url}" style="' . $btn . '">🎟️ VOLVER A INTENTAR EL PAGO</a></p>'
			. '<p style="margin:0 0 14px;font-size:14px;color:#6b7280;line-height:1.6;">Recuerda que solo las compras cuyo pago haya sido confirmado generan una participación válida en el concurso. Si necesitas ayuda para completar tu compra, escríbenos y nuestro equipo estará disponible para ayudarte.</p>'
			. '<p style="' . $foot . '">📧 contacto@sorteoseguro.cl<br />🌐 www.sorteoseguro.cl<br />Equipo Sorteo Seguro — Transparencia, seguridad y confianza en cada participación.</p>';

		$failed3_body = '<p style="margin:0 0 14px;font-size:16px;color:#1d2327;">Hola {nombre},</p>'
			. '<p style="' . $p . '">Hace unos días intentaste realizar tu compra para participar en uno de nuestros concursos, pero el pago no llegó a completarse.</p>'
			. '<p style="' . $p . '">Sabemos que estuviste muy cerca de quedar participando, y queremos darte una oportunidad especial para que puedas completar tu compra.</p>'
			. '<p style="' . $p . '">Por eso, hemos activado exclusivamente para ti un:</p>'
			. '<p style="text-align:center;margin:0 0 2px;font-size:28px;font-weight:800;color:#6b3bb8;">{descuento}% DE DESCUENTO</p>'
			. '<p style="text-align:center;margin:0 0 14px;font-size:13px;color:#3c434a;letter-spacing:.5px;">SOBRE EL TOTAL DE TU COMPRA</p>'
			. '<p style="' . $p . '">Y lo mejor es que no necesitas ingresar ningún código ni realizar ningún paso adicional.</p>'
			. '<p style="' . $p . '">Al hacer clic en el botón, se generará un nuevo carrito para el concurso que habías elegido, con nuevos DigiTickets disponibles y tu {descuento}% de descuento aplicado automáticamente.</p>'
			. '<p style="text-align:center;margin:22px 0;"><a href="{url}" style="' . $btn . '">🎟️ PARTICIPAR CON MI {descuento}% DE DESCUENTO</a></p>'
			. '<p style="' . $h2 . '">Tu beneficio ya está activado</p>'
			. '<p style="' . $p . '">Solo tienes que ingresar desde el botón de este email.</p>'
			. '<p style="' . $list . '">✅ Se generará una nueva selección de DigiTickets para el concurso que elegiste.</p>'
			. '<p style="' . $list . '">✅ Tu {descuento}% de descuento se aplicará automáticamente al total de tu compra.</p>'
			. '<p style="' . $list . '">✅ No necesitas ingresar ningún código de descuento.</p>'
			. '<p style="' . $list . '">✅ Revisa tu carrito y completa el pago.</p>'
			. '<p style="' . $p . '">Una vez confirmado el pago, tus DigiTickets quedarán asignados y estarás oficialmente participando en el concurso.</p>'
			. '<p style="' . $h2 . '">🍀 No te quedes fuera</p>'
			. '<p style="' . $p . '">Estuviste a un paso de completar tu compra y queremos que tengas una nueva oportunidad para participar con un beneficio especial. Aprovecha tu {descuento}% de descuento, completa tu compra y queda participando por el premio que elegiste.</p>'
			. '<p style="text-align:center;margin:22px 0;"><a href="{url}" style="' . $btn . '">🎟️ APROVECHAR MI {descuento}% Y PARTICIPAR</a></p>'
			. '<p style="margin:0 0 14px;font-size:14px;color:#6b7280;line-height:1.6;">{descuento}% de descuento aplicado automáticamente a tu nuevo carrito. Si necesitas ayuda durante el proceso de compra, puedes escribirnos y nuestro equipo estará disponible para ayudarte.</p>'
			. '<p style="' . $foot . '">📧 contacto@sorteoseguro.cl<br />🌐 www.sorteoseguro.cl<br />Equipo Sorteo Seguro — Transparencia, seguridad y confianza en cada participación.</p>';

		$cancelled12_body = '<p style="margin:0 0 14px;font-size:16px;color:#1d2327;">Hola {nombre},</p>'
			. '<p style="' . $p . '">Hace unos días intentaste participar en uno de nuestros concursos, pero finalmente tu compra no llegó a completarse.</p>'
			. '<p style="' . $p . '">Como la reserva de DigiTickets es temporal, los números asociados a esa compra fueron liberados y volvieron a quedar disponibles.</p>'
			. '<p style="' . $p . '">Pero aún puedes participar. Si todavía quieres ser parte del concurso que elegiste, hemos dejado todo preparado para que puedas retomar tu participación fácilmente.</p>'
			. '<p style="' . $p . '">Al continuar, nuestro sistema generará una nueva selección de DigiTickets disponibles para el mismo concurso.</p>'
			. '<p style="text-align:center;margin:22px 0;"><a href="{url}" style="' . $btn . '">🎟️ VOLVER A PARTICIPAR</a></p>'
			. '<p style="' . $h2 . '">¿Qué pasará al continuar?</p>'
			. '<p style="' . $list . '">✅ Volverás al mismo concurso que habías elegido.</p>'
			. '<p style="' . $list . '">✅ El sistema generará nuevos DigiTickets disponibles.</p>'
			. '<p style="' . $list . '">✅ Podrás revisar tu nueva selección antes de pagar.</p>'
			. '<p style="' . $list . '">✅ Tus DigiTickets quedarán confirmados definitivamente solo una vez realizado el pago.</p>'
			. '<p style="' . $h2 . '">⏳ Recuerda</p>'
			. '<p style="' . $p . '">Los DigiTickets no quedan reservados de manera indefinida. Por eso, los números que seleccionaste anteriormente podrían haber sido asignados a otros participantes. Si decides volver a participar, recibirás una nueva selección de DigiTickets.</p>'
			. '<p style="' . $p . '">Tu oportunidad de participar en el concurso sigue disponible.</p>'
			. '<p style="text-align:center;margin:22px 0;"><a href="{url}" style="' . $btn . '">🎟️ QUIERO VOLVER A PARTICIPAR</a></p>'
			. '<p style="margin:0 0 14px;font-size:14px;color:#6b7280;line-height:1.6;">Si tuviste algún inconveniente durante tu compra anterior o necesitas ayuda para completar el proceso, puedes escribirnos y nuestro equipo te ayudará.</p>'
			. '<p style="' . $foot . '">📧 contacto@sorteoseguro.cl<br />🌐 www.sorteoseguro.cl<br />Equipo Sorteo Seguro</p>';

		$cancelled3_body = '<p style="margin:0 0 14px;font-size:16px;color:#1d2327;">Hola {nombre},</p>'
			. '<p style="' . $p . '">Hace unos días estuviste a punto de participar en uno de nuestros concursos, pero tu compra finalmente no llegó a completarse.</p>'
			. '<p style="' . $p . '">Y no queremos que te quedes fuera.</p>'
			. '<p style="' . $p . '">Por eso, hemos activado especialmente para ti un beneficio para que puedas realizar tu compra y quedar participando en el concurso que habías elegido:</p>'
			. '<p style="text-align:center;margin:0 0 2px;font-size:28px;font-weight:800;color:#6b3bb8;">{descuento}% DE DESCUENTO</p>'
			. '<p style="text-align:center;margin:0 0 14px;font-size:13px;color:#3c434a;letter-spacing:.5px;">SOBRE EL TOTAL DE TU COMPRA</p>'
			. '<p style="' . $p . '">Y no necesitas ingresar ningún código.</p>'
			. '<p style="' . $p . '">Al hacer clic en el botón, se generará un nuevo carrito para el mismo concurso, con nuevos DigiTickets disponibles y tu {descuento}% de descuento aplicado automáticamente.</p>'
			. '<p style="text-align:center;margin:22px 0;"><a href="{url}" style="' . $btn . '">🎟️ PARTICIPAR CON MI {descuento}% DE DESCUENTO</a></p>'
			. '<p style="' . $h2 . '">Tu beneficio ya está listo</p>'
			. '<p style="' . $p . '">Hemos hecho el proceso lo más simple posible:</p>'
			. '<p style="' . $list . '">✅ Volverás al concurso que habías elegido.</p>'
			. '<p style="' . $list . '">✅ Se generará una nueva selección de DigiTickets disponibles.</p>'
			. '<p style="' . $list . '">✅ Tu {descuento}% de descuento se aplicará automáticamente al total de tu compra.</p>'
			. '<p style="' . $list . '">✅ No tendrás que ingresar ningún código.</p>'
			. '<p style="' . $list . '">✅ Solo tendrás que revisar tu carrito y completar el pago.</p>'
			. '<p style="' . $p . '">Una vez confirmado el pago, tus DigiTickets quedarán asignados y estarás oficialmente participando en el concurso.</p>'
			. '<p style="' . $h2 . '">🍀 No te quedes fuera</p>'
			. '<p style="' . $p . '">Si todavía quieres participar por el premio que elegiste, esta es una excelente oportunidad para hacerlo con un beneficio especial del {descuento}% de descuento.</p>'
			. '<p style="' . $p . '">Tu beneficio ya está preparado. Solo tienes que ingresar desde el botón y completar tu compra.</p>'
			. '<p style="text-align:center;margin:22px 0;"><a href="{url}" style="' . $btn . '">🎟️ APROVECHAR MI {descuento}% Y PARTICIPAR</a></p>'
			. '<p style="margin:0 0 14px;font-size:14px;color:#6b7280;line-height:1.6;">{descuento}% de descuento aplicado automáticamente a tu nuevo carrito. Si necesitas ayuda durante tu compra, puedes escribirnos y nuestro equipo estará disponible para ayudarte.</p>'
			. '<p style="' . $foot . '">📧 contacto@sorteoseguro.cl<br />🌐 www.sorteoseguro.cl<br />Equipo Sorteo Seguro — Transparencia, seguridad y confianza en cada participación.</p>';

		return array(
			'cancelled1' => array(
				'subject'   => '¿Aún quieres participar? Tus DigiTickets te están esperando 🎟️',
				'preheader' => 'Puedes volver a participar fácilmente y recibir nuevos DigiTickets para el concurso que elegiste.',
				'heading'   => '¿Aún quieres participar? 🎟️',
				'body'      => $cancelled12_body,
				'discount'  => 0,
			),
			'cancelled2' => array(
				'subject'   => 'Todavía puedes volver a participar 🎟️',
				'preheader' => 'Si aún quieres participar en el concurso que elegiste, puedes hacerlo en pocos pasos.',
				'heading'   => '¿Aún quieres participar? 🎟️',
				'body'      => $cancelled12_body,
				'discount'  => 0,
			),
			'cancelled3' => array(
				'subject'   => 'No te quedes fuera 🎟️ Activamos un {descuento}% de descuento para ti',
				'preheader' => 'Tienes un beneficio especial para participar: tu {descuento}% de descuento se aplicará automáticamente en tu nuevo carrito.',
				'heading'   => '🎁 Tenemos un beneficio especial para ti',
				'body'      => $cancelled3_body,
				'discount'  => 30,
			),
			'failed1'    => array(
				'subject'   => 'Tu pago no pudo completarse 🎟️ Puedes intentarlo nuevamente',
				'preheader' => 'Tu compra no se completó. Genera nuevos DigiTickets e intenta nuevamente tu pago para participar.',
				'heading'   => 'Tu pago no pudo completarse',
				'body'      => $failed12_body,
				'discount'  => 0,
			),
			'failed2'    => array(
				'subject'   => '¿Tuviste un inconveniente con el pago? Puedes intentarlo nuevamente',
				'preheader' => 'Aún puedes completar tu compra y participar. Te asignaremos nuevos DigiTickets para intentarlo nuevamente.',
				'heading'   => 'Tu pago no pudo completarse',
				'body'      => $failed12_body,
				'discount'  => 0,
			),
			'failed3'    => array(
				'subject'   => 'No te quedes fuera 🎟️ Activamos un {descuento}% de descuento para ti',
				'preheader' => 'Tu {descuento}% ya está listo. Haz clic, revisa tus nuevos DigiTickets y completa tu compra con el descuento aplicado automáticamente.',
				'heading'   => '🎁 Activamos un beneficio especial para ti',
				'body'      => $failed3_body,
				'discount'  => 30,
			),
		);
	}

	private static function get_template(string $id): array {
		$keys     = self::template_keys();
		$defaults = self::template_defaults();
		$d        = $defaults[ $id ] ?? array('subject' => '', 'preheader' => '', 'heading' => '', 'body' => '', 'discount' => 0);
		if (!isset($keys[ $id ])) {
			return $d;
		}
		$k = $keys[ $id ];
		return array(
			'subject'   => (string) get_option($k['subject'], $d['subject']),
			'preheader' => (string) get_option($k['preheader'], $d['preheader']),
			'heading'   => (string) get_option($k['heading'], $d['heading']),
			'body'      => (string) get_option($k['body'], $d['body']),
			'discount'  => (float) get_option($k['discount'], $d['discount']),
		);
	}

	private static function save_template(string $id, string $subject, string $preheader, string $heading, string $body, float $discount): void {
		$keys = self::template_keys();
		if (!isset($keys[ $id ])) {
			return;
		}
		$k = $keys[ $id ];
		update_option($k['subject'], $subject, false);
		update_option($k['preheader'], $preheader, false);
		update_option($k['heading'], $heading, false);
		update_option($k['body'], $body, false);
		update_option($k['discount'], max(0, min(100, $discount)), false);
		foreach ($k as $name) {
			if (function_exists('wp_cache_delete')) {
				wp_cache_delete($name, 'options');
			}
		}
	}

	private static function restore_template_default(string $id): void {
		$defaults = self::template_defaults();
		if (!isset($defaults[ $id ])) {
			return;
		}
		$tpl = $defaults[ $id ];
		self::save_template($id, (string) $tpl['subject'], (string) $tpl['preheader'], (string) $tpl['heading'], (string) $tpl['body'], (float) $tpl['discount']);
	}

	private static function persist_delay(string $flow, int $which, int $value): void {
		$name = self::delay_option_name($flow, $which);
		if ($name === '') {
			return;
		}

		$max   = self::delay_unit($flow, $which) === 'days' ? 365 : 10080;
		$value = max(1, min($max, $value));

		update_option($name, $value, false);

		if (function_exists('wp_cache_delete')) {
			wp_cache_delete($name, 'options');
		}
		if (function_exists('wp_cache_get') && function_exists('wp_cache_set')) {
			$notoptions = wp_cache_get('notoptions', 'options');
			if (is_array($notoptions) && array_key_exists($name, $notoptions)) {
				unset($notoptions[$name]);
				wp_cache_set('notoptions', $notoptions, 'options');
			}
		}
	}

	private static function reminder_meta(int $which): string {
		$map = array(1 => self::META_R1, 2 => self::META_R2, 3 => self::META_R3);
		return $map[$which] ?? '';
	}

	private static function enqueue(int $order_id, int $which, int $timestamp): void {
		$args = array($order_id, $which);
		if (function_exists('as_schedule_single_action')) {
			as_schedule_single_action($timestamp, self::AS_HOOK, $args, 'sorteoseguro-cfr');
			return;
		}
		wp_schedule_single_event($timestamp, self::AS_HOOK, $args);
	}

	public static function schedule_for_order($order_id): void {
		$order = wc_get_order(absint($order_id));
		if (!$order instanceof WC_Order) {
			return;
		}
		$status = $order->get_status();
		if (!in_array($status, array('cancelled', 'failed'), true)) {
			return;
		}
		if ($order->get_meta(self::META_SCHEDULED) === 'yes') {
			return;
		}

		$flow = self::flow_for_status($status);

		// Grupo de control (holdout): a un % no se le envían recordatorios.
		$holdout = self::get_holdout($flow);
		if ($holdout > 0 && (crc32($order->get_id() . '|' . $flow) % 100) < $holdout) {
			$order->update_meta_data(self::META_SCHEDULED, 'yes');
			$order->update_meta_data(self::META_FLOW, $flow);
			$order->update_meta_data('_ss_cfr_holdout', 'yes');
			$order->add_order_note('Sorteo Seguro: pedido en grupo de control (holdout); no se envían recordatorios.');
			$order->save();
			self::log('info', 'CFR holdout', array('order_id' => $order->get_id(), 'flow' => $flow));
			return;
		}

		$order->update_meta_data(self::META_SCHEDULED, 'yes');
		$order->update_meta_data(self::META_FLOW, $flow);
		$order->add_order_note(
			sprintf(
				'Sorteo Seguro: programado recordatorio de pago para pedido %s (1.º en %d %s; 2.º en %d %s; 3.º en %d %s).',
				self::flow_label($flow),
				self::get_delay($flow, 1),
				self::delay_unit_label($flow, 1),
				self::get_delay($flow, 2),
				self::delay_unit_label($flow, 2),
				self::get_delay($flow, 3),
				self::delay_unit_label($flow, 3)
			)
		);
		$order->save();

		self::enqueue($order->get_id(), 1, time() + self::delay_seconds($flow, 1));
		self::log('info', 'CFR scheduled', array('order_id' => $order->get_id(), 'flow' => $flow));
	}

	public static function send_reminder($order_id, $which = 1): void {
		$order_id = absint($order_id);
		$which    = (int) $which;
		if (!$order_id || !in_array($which, array(1, 2, 3), true)) {
			return;
		}

		$order = wc_get_order($order_id);
		if (!$order instanceof WC_Order) {
			return;
		}

		$status = $order->get_status();
		if (!in_array($status, array('cancelled', 'failed'), true)) {
			self::append_log(array('order_id' => $order_id, 'which' => (string) $which, 'result' => 'skipped_not_status', 'status' => $status, 'flow' => self::flow_for_status($status)));
			return;
		}

		$flow = (string) $order->get_meta(self::META_FLOW);
		if (!in_array($flow, array('cancelled', 'failed'), true)) {
			$flow = self::flow_for_status($status);
		}

		$meta_key = self::reminder_meta($which);
		if ($meta_key === '' || $order->get_meta($meta_key)) {
			return;
		}

		if (self::customer_already_bought($order)) {
			self::append_log(array('order_id' => $order_id, 'flow' => $flow, 'which' => (string) $which, 'email' => $order->get_billing_email(), 'result' => 'skipped_purchased', 'status' => $status));
			self::log('info', 'CFR skipped: customer already bought', array('order_id' => $order_id));
			return;
		}

		$ok = self::send_reminder_email($order, $flow, (string) $which);
		if (!$ok) {
			self::append_log(array('order_id' => $order_id, 'flow' => $flow, 'which' => (string) $which, 'email' => $order->get_billing_email(), 'result' => 'send_failed', 'status' => $status));
			self::log('error', 'CFR send failed', array('order_id' => $order_id, 'which' => $which));
			return;
		}

		$order->update_meta_data($meta_key, gmdate('Y-m-d H:i:s'));
		$order->add_order_note(
			sprintf(
				'Sorteo Seguro: recordatorio de compra #%d para pedido %s enviado a %s.',
				$which,
				self::flow_label($flow),
				$order->get_billing_email()
			)
		);
		$order->save();

		self::append_log(array('order_id' => $order_id, 'flow' => $flow, 'which' => (string) $which, 'email' => $order->get_billing_email(), 'result' => 'sent', 'status' => $status));
		self::log('info', 'CFR sent', array('order_id' => $order_id, 'which' => $which, 'flow' => $flow));

		if ($which < 3) {
			self::enqueue($order_id, $which + 1, time() + self::delay_seconds($flow, $which + 1));
		}
	}

	private static function customer_already_bought(WC_Order $order): bool {
		return self::customer_purchase_order_id($order) > 0;
	}

	private static function customer_purchase_order_id(WC_Order $order): int {
		$email = $order->get_billing_email();
		if ($email === '' || !is_email($email) || !function_exists('wc_get_orders')) {
			return 0;
		}
		$created = $order->get_date_created();
		$after   = $created ? $created->getTimestamp() : (time() - (90 * DAY_IN_SECONDS));

		$found = wc_get_orders(
			array(
				'billing_email' => $email,
				'status'        => array('processing', 'completed'),
				'limit'         => 1,
				'orderby'       => 'date',
				'order'         => 'DESC',
				'return'        => 'ids',
				'date_created'  => '>' . $after,
			)
		);

		return !empty($found[0]) ? (int) $found[0] : 0;
	}

	private static function send_reminder_email(WC_Order $order, string $flow, string $which = ''): bool {
		if (!function_exists('WC') || !WC()->mailer()) {
			return false;
		}

		$to = $order->get_billing_email();
		if ($to === '' || !is_email($to)) {
			return false;
		}

		$email  = self::build_email_from_items(
			self::email_items_from_order($order),
			(int) $order->get_id(),
			(string) $order->get_billing_first_name(),
			$flow,
			$which
		);
		$mailer = WC()->mailer();
		$html   = $mailer->wrap_message($email['heading'], $email['body']);

		try {
			return (bool) $mailer->send($to, $email['subject'], $html);
		} catch (Throwable $e) {
			self::log('error', 'CFR email exception: ' . $e->getMessage(), array('to' => $to));
			return false;
		}
	}

	private static function find_pack_campaign_id(int $product_id, int $qty): int {
		if (!class_exists('Promo_Engine_Stable') || !method_exists('Promo_Engine_Stable', 'get_product_page_campaigns')) {
			return 0;
		}
		$campaigns = Promo_Engine_Stable::get_product_page_campaigns($product_id);
		if (!is_array($campaigns)) {
			return 0;
		}
		$best_id  = 0;
		$best_pay = PHP_INT_MAX;
		foreach ($campaigns as $c) {
			if (empty($c['id']) || !isset($c['buy'])) {
				continue;
			}
			if ((int) $c['buy'] !== (int) $qty) {
				continue;
			}
			$pay = isset($c['pay']) ? (int) $c['pay'] : (int) $qty;
			if ($pay < $best_pay) {
				$best_pay = $pay;
				$best_id  = (int) $c['id'];
			}
		}
		return $best_id;
	}

	private static function email_items_from_order(WC_Order $order): array {
		$items = array();
		foreach ($order->get_items() as $item) {
			if (!is_object($item) || !method_exists($item, 'get_product')) {
				continue;
			}
			$product = $item->get_product();
			if (!$product || !function_exists('lty_is_lottery_product') || !lty_is_lottery_product($product)) {
				continue;
			}

			$pid     = (int) $item->get_product_id();
			$numbers = $item->get_meta('_lty_lottery_tickets');
			$qty     = (is_array($numbers) && $numbers) ? count($numbers) : (int) $item->get_quantity();
			if ($qty < 1) {
				continue;
			}

			$title = trim((string) preg_replace('/^DigiTicket\s*\|\s*/iu', '', wp_strip_all_tags((string) $item->get_name())));
			if ($title === '') {
				$title = 'Sorteo #' . $pid;
			}

			$image = '';
			if (is_object($product) && method_exists($product, 'get_image')) {
				$image = (string) $product->get_image('woocommerce_thumbnail', array('style' => 'width:64px;height:64px;object-fit:cover;border-radius:8px;display:block;border:0;'), false);
			}

			$campaign = (int) $item->get_meta('ss_pack_campaign_id');
			if ($campaign <= 0) {
				$campaign = self::find_pack_campaign_id($pid, $qty);
			}

			$items[] = array(
				'p'     => $pid,
				'q'     => $qty,
				'c'     => $campaign,
				'name'  => $title,
				'image' => $image,
			);
		}
		return $items;
	}

	private static function render_email_items_card(array $items): string {
		if (!$items) {
			return '';
		}

		$html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 0;border:1px solid #e5e7eb;border-radius:10px;background:#ffffff;">';
		foreach ($items as $index => $it) {
			$border = $index > 0 ? 'border-top:1px solid #eef0f3;' : '';
			$html  .= '<tr><td style="padding:14px 16px;' . $border . '">';
			$html  .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"><tr>';
			if (!empty($it['image'])) {
				$html .= '<td width="72" valign="middle" style="padding:0 12px 0 0;">' . $it['image'] . '</td>';
			}
			$html .= '<td valign="middle" style="padding:0;">';
			$html .= '<div style="font-size:15px;font-weight:600;color:#1d2327;line-height:1.3;">' . esc_html((string) $it['name']) . '</div>';
			$html .= '<div style="font-size:13px;color:#6b7280;margin-top:3px;">' . (int) $it['q'] . ' DigiTicket' . ((int) $it['q'] > 1 ? 's' : '') . '</div>';
			$html .= '</td></tr></table>';
			$html .= '</td></tr>';
		}
		$html .= '</table>';

		return $html;
	}

	private static function render_preload_button(array $items, string $flow, int $order_id, string $which = '', float $discount = 0): string {
		if (!$items) {
			return '';
		}

		$url   = self::build_preload_url($items, $flow, $order_id, $which, $discount);
		$total = 0;
		foreach ($items as $it) {
			$total += (int) $it['q'];
		}
		$label = 'Retomar mi compra (' . $total . ' DigiTicket' . ($total > 1 ? 's' : '') . ')';
		if ($discount > 0) {
			$label .= ' · ' . (int) $discount . '% dcto';
		}

		return '<a href="' . esc_url($url) . '" style="display:inline-block;background:#6b3bb8;color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:8px;font-weight:700;font-size:15px;letter-spacing:.2px;">' . esc_html($label) . '</a>';
	}

	private static function build_email_from_items(array $items, int $order_id, string $name, string $flow, string $which): array {
		$id       = self::template_id_for($flow, (int) $which);
		$tpl      = self::get_template($id);
		$discount = (float) $tpl['discount'];

		$url    = $items ? self::build_preload_url($items, $flow, $order_id, $which, $discount) : home_url('/');
		$card   = $items ? self::render_email_items_card($items) : '';
		$button = $items
			? self::render_preload_button($items, $flow, $order_id, $which, $discount)
			: '<a href="' . esc_url(home_url('/')) . '" style="display:inline-block;background:#6b3bb8;color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:8px;font-weight:700;font-size:15px;">Ver sorteos</a>';

		$replace = array(
			'{nombre}'  => esc_html(trim($name)),
			'{pedido}'  => esc_html((string) $order_id),
			'{sorteos}' => $card,
			'{boton}'   => $button,
			'{url}'     => esc_url($url),
		);

		$body = self::apply_discount_text(strtr($tpl['body'], $replace), $discount);
		if ($items && strpos($tpl['body'], '{boton}') === false && strpos($tpl['body'], '{url}') === false) {
			$body .= '<p style="margin:22px 0 0;text-align:center;">' . $button . '</p>';
		}

		$preheader = trim(self::apply_discount_text(strtr((string) $tpl['preheader'], $replace), $discount));
		if ($preheader !== '') {
			$body = '<div style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">' . esc_html($preheader) . '</div>' . $body;
		}

		return array(
			'subject'  => self::apply_discount_text(strtr($tpl['subject'], $replace), $discount),
			'heading'  => self::apply_discount_text(strtr($tpl['heading'], $replace), $discount),
			'body'     => $body,
			'discount' => $discount,
		);
	}

	private static function apply_discount_text(string $text, float $discount): string {
		if ($discount > 0) {
			return strtr($text, array('{descuento}' => (string) (int) $discount));
		}
		// Sin descuento: quitar las frases que quedarían vacías.
		$text = preg_replace('/\s*<strong>\s*\{descuento\}%\s*de\s*descuento\s*<\/strong>/iu', '', $text);
		$text = preg_replace('/\s*\{descuento\}%\s*de\s*descuento/iu', '', $text);
		$text = preg_replace('/\s*\{descuento\}%/iu', '', $text);
		$text = preg_replace('/\s+con\s*(?=[.,;:!?—|]|$)/iu', '', $text);
		$text = preg_replace('/\s+tu\s*(?=[.,;:!?])/iu', '', $text);
		$text = preg_replace('/\s{2,}/', ' ', $text);
		return strtr($text, array('{descuento}' => ''));
	}

	private static function render_email_body(WC_Order $order, string $flow, string $which = ''): string {
		$email = self::build_email_from_items(
			self::email_items_from_order($order),
			(int) $order->get_id(),
			(string) $order->get_billing_first_name(),
			$flow,
			$which
		);
		return $email['body'];
	}

	private static function build_preload_url(array $items, string $flow, int $order_id = 0, string $which = '', float $discount = 0): string {
		$clean = array();
		foreach ($items as $it) {
			if (!is_array($it)) {
				continue;
			}
			$pid = isset($it['p']) ? absint($it['p']) : 0;
			if ($pid <= 0) {
				continue;
			}
			$clean[] = array(
				'p' => $pid,
				'q' => isset($it['q']) ? max(1, (int) $it['q']) : 1,
				'c' => isset($it['c']) ? absint($it['c']) : 0,
			);
		}
		if (!$clean) {
			return home_url('/');
		}

		$payload = array(
			'i' => $clean,
			'f' => $flow,
			'o' => absint($order_id),
			'w' => (string) $which,
			'd' => max(0, min(100, (float) $discount)),
			'e' => time() + (self::TOKEN_TTL_DAYS * DAY_IN_SECONDS),
		);
		$encoded = self::b64url_encode((string) wp_json_encode($payload));
		$sig     = self::sign($encoded);
		// Enlace "landing": dispara la precarga por JS (evita prefetch de escáneres de correo).
		return add_query_arg(
			array(
				'ss_cfr_go'  => '1',
				'ss_cfr_p'   => $encoded,
				'ss_cfr_sig' => $sig,
			),
			home_url('/')
		);
	}

	public static function handle_preload_landing(): void {
		if (!isset($_GET['ss_cfr_go'])) {
			return;
		}

		$encoded = isset($_GET['ss_cfr_p']) ? (string) wp_unslash($_GET['ss_cfr_p']) : '';
		$sig     = isset($_GET['ss_cfr_sig']) ? (string) wp_unslash($_GET['ss_cfr_sig']) : '';
		if ($encoded === '' || $sig === '' || !hash_equals(self::sign($encoded), $sig)) {
			self::redirect_preload_error(0);
		}

		$preload = add_query_arg(
			array(
				'ss_cfr_reload' => '1',
				'ss_cfr_p'      => $encoded,
				'ss_cfr_sig'    => $sig,
			),
			home_url('/')
		);

		nocache_headers();
		header('Content-Type: text/html; charset=utf-8');
		echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Retomando tu compra…</title>';
		echo '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;background:#f6f4fb;color:#1d2327;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;padding:16px;box-sizing:border-box}';
		echo '.ss-cfr-land{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:28px 24px;text-align:center;max-width:380px;width:100%}';
		echo '.ss-cfr-land button{display:inline-block;background:#6b3bb8;color:#fff;border:0;text-decoration:none;padding:14px 28px;border-radius:8px;font-weight:700;font-size:15px;cursor:pointer;margin-top:12px}';
		echo '.ss-cfr-sp{width:28px;height:28px;border:3px solid #e5e7eb;border-top-color:#6b3bb8;border-radius:50%;margin:0 auto 14px;animation:ss 1s linear infinite}@keyframes ss{to{transform:rotate(360deg)}}</style>';
		echo '</head><body><div class="ss-cfr-land"><div class="ss-cfr-sp"></div><p style="margin:0;font-size:15px;">Retomando tu compra…</p>';
		echo '<noscript><p style="margin:12px 0 0;font-size:14px;">Pulsa para continuar:</p><form method="get" action="' . esc_url(home_url('/')) . '">';
		echo '<input type="hidden" name="ss_cfr_reload" value="1" /><input type="hidden" name="ss_cfr_p" value="' . esc_attr($encoded) . '" /><input type="hidden" name="ss_cfr_sig" value="' . esc_attr($sig) . '" />';
		echo '<button type="submit">Retomar mi compra</button></form></noscript>';
		echo '</div><script>window.location.replace(' . wp_json_encode($preload) . ');</script></body></html>';
		exit;
	}

	private static function token_items(array $data): array {
		$items = array();
		if (!empty($data['i']) && is_array($data['i'])) {
			foreach ($data['i'] as $it) {
				if (!is_array($it)) {
					continue;
				}
				$pid = (int) ($it['p'] ?? 0);
				if ($pid <= 0) {
					continue;
				}
				$items[] = array(
					'p' => $pid,
					'q' => max(1, (int) ($it['q'] ?? 1)),
					'c' => (int) ($it['c'] ?? 0),
				);
			}
			return $items;
		}

		if (!empty($data['p'])) {
			$items[] = array(
				'p' => (int) $data['p'],
				'q' => max(1, (int) ($data['q'] ?? 1)),
				'c' => (int) ($data['c'] ?? 0),
			);
		}
		return $items;
	}

	private static function sign(string $data): string {
		return self::b64url_encode(hash_hmac('sha256', $data, wp_salt('auth'), true));
	}

	private static function b64url_encode(string $data): string {
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	private static function b64url_decode(string $data): string {
		$data = strtr($data, '-_', '+/');
		$pad  = strlen($data) % 4;
		if ($pad) {
			$data .= str_repeat('=', 4 - $pad);
		}
		$out = base64_decode($data, true);
		return is_string($out) ? $out : '';
	}

	public static function handle_preload(): void {
		if (!isset($_GET['ss_cfr_reload'])) {
			return;
		}

		$encoded = isset($_GET['ss_cfr_p']) ? (string) wp_unslash($_GET['ss_cfr_p']) : '';
		$sig     = isset($_GET['ss_cfr_sig']) ? (string) wp_unslash($_GET['ss_cfr_sig']) : '';

		if ($encoded === '' || $sig === '' || !hash_equals(self::sign($encoded), $sig)) {
			self::redirect_preload_error(0);
		}

		$data = json_decode(self::b64url_decode($encoded), true);
		if (!is_array($data)) {
			self::redirect_preload_error(0);
		}

		$items = self::token_items($data);
		if (!$items) {
			self::redirect_preload_error(0);
		}
		$first_product_id = (int) $items[0]['p'];

		if (isset($data['e']) && time() > (int) $data['e']) {
			self::redirect_preload_error($first_product_id);
		}

		$source_id   = (int) ($data['o'] ?? 0);
		$source_w    = isset($data['w']) ? sanitize_key((string) $data['w']) : '';
		$source_flow = isset($data['f']) ? sanitize_key((string) $data['f']) : '';
		$discount    = isset($data['d']) ? max(0, min(100, (float) $data['d'])) : 0.0;
		if (!in_array($source_flow, array('cancelled', 'failed'), true)) {
			$source_flow = '';
		}

		if (!function_exists('WC') || !class_exists('SorteoSeguro_Packs_Lottery')) {
			self::redirect_preload_error($first_product_id);
		}

		$source_order = ($source_id > 0) ? wc_get_order($source_id) : null;
		if ($source_order instanceof WC_Order) {
			if ($source_flow === '') {
				$source_flow = (string) $source_order->get_meta(self::META_FLOW);
			}
			// Un solo uso: si el pedido ya fue recuperado (o ya generó una compra),
			// el enlace deja de otorgar el descuento.
			if ((string) $source_order->get_meta('_ss_cfr_recovered') === 'yes' || self::customer_purchase_order_id($source_order) > 0) {
				$discount = 0.0;
			}
		}

		if (WC()->session) {
			WC()->session->set('ss_cfr_discount_pct', $discount);
		}

		if ($source_order instanceof WC_Order && WC()->session) {
			WC()->session->set('ss_cfr_source_order', $source_id);
			WC()->session->set('ss_cfr_source_which', $source_w);
			WC()->session->set('ss_cfr_source_flow', $source_flow);
			$source_order->update_meta_data('_ss_cfr_preloaded_at', gmdate('Y-m-d H:i:s'));
			$source_order->update_meta_data('_ss_cfr_preloaded_which', $source_w);
			$source_order->save();
			self::append_log(
				array(
					'flow'     => $source_flow,
					'which'    => 'click',
					'order_id' => $source_id,
					'email'    => $source_order->get_billing_email(),
					'result'   => 'preload_click',
				)
			);
		}

		$loaded = 0;
		foreach ($items as $it) {
			$campaign_id = (int) $it['c'];
			if ($campaign_id <= 0) {
				// Blindaje: deducir la campaña del pack por producto + cantidad.
				$campaign_id = self::find_pack_campaign_id((int) $it['p'], (int) $it['q']);
			}
			$result = SorteoSeguro_Packs_Lottery::preload_cart_for_product((int) $it['p'], (int) $it['q'], $campaign_id, array('replace' => true));
			if (is_wp_error($result)) {
				self::log('warning', 'CFR preload failed: ' . $result->get_error_message(), array('product_id' => $it['p'], 'qty' => $it['q']));
			} else {
				$loaded++;
			}
		}

		if ($loaded === 0) {
			self::redirect_preload_error($first_product_id);
		}

		$checkout = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/');
		wp_safe_redirect($checkout);
		exit;
	}

	private static function redirect_preload_error(int $product_id): void {
		$url = $product_id > 0 ? get_permalink($product_id) : home_url('/');
		if (!is_string($url) || $url === '') {
			$url = home_url('/');
		}
		wp_safe_redirect(add_query_arg('ss_cfr_err', '1', $url));
		exit;
	}

	public static function maybe_show_preload_error_notice(): void {
		if (!isset($_GET['ss_cfr_err']) || !function_exists('wc_add_notice')) {
			return;
		}
		wc_add_notice('No pudimos precargar tu compra (el sorteo cerró o no hay números disponibles). Elige tus DigiTickets aquí.', 'error');
	}

	public static function apply_recovery_discount($cart): void {
		if (!function_exists('WC') || !WC()->session || !is_object($cart)) {
			return;
		}
		if (is_admin() && !wp_doing_ajax()) {
			return;
		}

		// Quitar nuestro fee previo para recalcularlo en cada cálculo.
		$fees = method_exists($cart, 'get_fees') ? (array) $cart->get_fees() : array();
		$keep = array();
		$had_ours = false;
		foreach ($fees as $fee) {
			$name = isset($fee->name) ? (string) $fee->name : '';
			if (strpos($name, 'Descuento recuperación') === 0) {
				$had_ours = true;
				continue;
			}
			$keep[] = array(
				'id'        => isset($fee->id) ? (string) $fee->id : '',
				'name'      => $name,
				'amount'    => isset($fee->amount) ? (float) $fee->amount : 0.0,
				'taxable'   => isset($fee->taxable) ? $fee->taxable : false,
				'tax_class' => isset($fee->tax_class) ? (string) $fee->tax_class : '',
			);
		}
		if ($had_ours && method_exists($cart, 'fees_api')) {
			$cart->fees_api()->set_fees($keep);
		}

		$pct = (float) WC()->session->get('ss_cfr_discount_pct');
		if ($pct <= 0) {
			return;
		}

		// Subtotal de los ítems precargados y subtotal total del carrito.
		$pre_subtotal  = 0.0;
		$cart_subtotal = 0.0;
		foreach ($cart->get_cart() as $item) {
			$line = 0.0;
			if (isset($item['line_subtotal'])) {
				$line = (float) $item['line_subtotal'];
			} elseif (isset($item['data']) && is_object($item['data'])) {
				$line = (float) $item['data']->get_price() * (int) ($item['quantity'] ?? 1);
			}
			$cart_subtotal += $line;
			if (!empty($item['ss_cfr_preload'])) {
				$pre_subtotal += $line;
			}
		}
		if ($pre_subtotal <= 0) {
			return;
		}

		// Descuentos de promos ya aplicados (fees negativos que no son el nuestro).
		$other_discounts = 0.0;
		foreach ($keep as $fee) {
			$fee_amount = isset($fee['amount']) ? (float) $fee['amount'] : 0.0;
			if ($fee_amount < 0) {
				$other_discounts += $fee_amount;
			}
		}

		// Prorratear los descuentos de promo a la parte precargada.
		$share = ($cart_subtotal > 0) ? ($pre_subtotal / $cart_subtotal) : 1.0;
		$base  = $pre_subtotal + ($other_discounts * $share);
		if ($base <= 0) {
			return;
		}

		$decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;
		$amount   = round($base * $pct / 100, $decimals);
		if ($amount <= 0) {
			return;
		}

		$pct_label = rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.');
		$cart->add_fee('Descuento recuperación (' . $pct_label . '%)', -$amount, false);
	}

	public static function clear_discount_session($order = null): void {
		if (function_exists('WC') && WC()->session) {
			WC()->session->set('ss_cfr_discount_pct', 0);
		}
	}

	public static function attach_source_by_id($order_id, $data = null): void {
		$order = wc_get_order(absint($order_id));
		if ($order instanceof WC_Order) {
			self::store_session_source($order);
		}
	}

	public static function attach_source_to_order($order): void {
		if ($order instanceof WC_Order) {
			self::store_session_source($order);
		}
	}

	public static function mark_source_recovered($order_id): void {
		$order = wc_get_order(absint($order_id));
		if (!$order instanceof WC_Order) {
			return;
		}
		$source_id = (int) $order->get_meta('_ss_cfr_source_order');
		if ($source_id <= 0) {
			return;
		}
		$source_order = wc_get_order($source_id);
		if ($source_order instanceof WC_Order && (string) $source_order->get_meta('_ss_cfr_recovered') !== 'yes') {
			$source_order->update_meta_data('_ss_cfr_recovered', 'yes');
			$source_order->save();
		}
	}

	private static function store_session_source(WC_Order $order): void {
		if (!function_exists('WC') || !WC()->session) {
			return;
		}
		$source_id = (int) WC()->session->get('ss_cfr_source_order');
		if ($source_id <= 0) {
			return;
		}
		$source_order = wc_get_order($source_id);
		$source_flow  = (string) WC()->session->get('ss_cfr_source_flow');
		if (!in_array($source_flow, array('cancelled', 'failed'), true)) {
			$source_flow = $source_order instanceof WC_Order ? (string) $source_order->get_meta(self::META_FLOW) : '';
		}

		$order->update_meta_data('_ss_cfr_source_order', $source_id);
		$order->update_meta_data('_ss_cfr_source_which', (string) WC()->session->get('ss_cfr_source_which'));
		$order->update_meta_data('_ss_cfr_source_flow', $source_flow);
		$order->save();

		WC()->session->set('ss_cfr_source_order', 0);
		WC()->session->set('ss_cfr_source_which', '');
		WC()->session->set('ss_cfr_source_flow', '');
	}

	public static function register_admin_menu(): void {
		global $_registered_pages;

		$pages = array(
			'ss-cancelled-reminders' => array('Recordatorios cancelados', 'Cancelados', 'render_cancelled_page'),
			'ss-failed-reminders'    => array('Recordatorios fallidos', 'Fallidos', 'render_failed_page'),
		);

		foreach ($pages as $slug => $cfg) {
			$hook = add_submenu_page(
				'woocommerce',
				$cfg[0],
				$cfg[1],
				'manage_woocommerce',
				$slug,
				array(__CLASS__, $cfg[2])
			);

			if (self::MENU_VISIBLE) {
				continue;
			}

			remove_submenu_page('woocommerce', $slug);

			$fallback = 'admin_page_' . $slug;
			if (!has_action($fallback)) {
				add_action($fallback, array(__CLASS__, $cfg[2]));
			}
			add_action(
				'load-' . $fallback,
				static function () use ($cfg) {
					$GLOBALS['title'] = $cfg[0];
				}
			);

			$_registered_pages[ $fallback ] = true;
			if (is_string($hook) && $hook !== '') {
				$_registered_pages[ $hook ] = true;
			}
		}
	}

	public static function render_cancelled_page(): void {
		self::render_flow_page('cancelled');
	}

	public static function render_failed_page(): void {
		self::render_flow_page('failed');
	}

	private static function render_flow_page(string $flow): void {
		if (!current_user_can('manage_woocommerce')) {
			return;
		}

		$saved = false;
		if (isset($_POST['ss_cfr_save_settings']) && check_admin_referer('ss_cfr_save_' . $flow)) {
			$posted = array(
				1 => isset($_POST['ss_cfr_delay_1']) ? (int) wp_unslash($_POST['ss_cfr_delay_1']) : self::DEFAULT_DELAY_1,
				2 => isset($_POST['ss_cfr_delay_2']) ? (int) wp_unslash($_POST['ss_cfr_delay_2']) : self::DEFAULT_DELAY_2,
				3 => isset($_POST['ss_cfr_delay_3']) ? (int) wp_unslash($_POST['ss_cfr_delay_3']) : self::DEFAULT_DELAY_3,
			);
			foreach ($posted as $which => $value) {
				self::persist_delay($flow, (int) $which, (int) $value);
			}
			$holdout = isset($_POST['ss_cfr_holdout']) ? (int) wp_unslash($_POST['ss_cfr_holdout']) : 0;
			update_option(self::holdout_option($flow), max(0, min(100, $holdout)), false);

			$saved = true;
			self::log(
				'info',
				'CFR delays updated',
				array(
					'flow'  => $flow,
					'posted' => $posted,
					'after'  => array(self::get_delay($flow, 1), self::get_delay($flow, 2), self::get_delay($flow, 3)),
				)
			);
		}

		$templates_saved = false;
		if (isset($_POST['ss_cfr_restore_template']) && check_admin_referer('ss_cfr_save_templates_' . $flow)) {
			$restore_id = sanitize_key(wp_unslash($_POST['ss_cfr_restore_template']));
			self::restore_template_default($restore_id);
			$templates_saved = true;
			self::log('info', 'CFR template restored', array('flow' => $flow, 'id' => $restore_id));
		}

		if (isset($_POST['ss_cfr_save_templates']) && check_admin_referer('ss_cfr_save_templates_' . $flow)) {
			foreach (self::template_ids_for_flow($flow) as $id) {
				$subject   = isset($_POST['ss_cfr_tpl_' . $id . '_subject']) ? (string) wp_unslash($_POST['ss_cfr_tpl_' . $id . '_subject']) : '';
				$preheader = isset($_POST['ss_cfr_tpl_' . $id . '_preheader']) ? (string) wp_unslash($_POST['ss_cfr_tpl_' . $id . '_preheader']) : '';
				$heading   = isset($_POST['ss_cfr_tpl_' . $id . '_heading']) ? (string) wp_unslash($_POST['ss_cfr_tpl_' . $id . '_heading']) : '';
				$body      = isset($_POST['ss_cfr_tpl_' . $id . '_body']) ? (string) wp_unslash($_POST['ss_cfr_tpl_' . $id . '_body']) : '';
				$disc      = isset($_POST['ss_cfr_tpl_' . $id . '_discount']) ? (float) wp_unslash($_POST['ss_cfr_tpl_' . $id . '_discount']) : 0;
				self::save_template($id, $subject, $preheader, $heading, $body, $disc);
			}
			$templates_saved = true;
			self::log('info', 'CFR templates updated', array('flow' => $flow));
		}

		$d1 = self::get_delay($flow, 1);
		$d2 = self::get_delay($flow, 2);
		$d3 = self::get_delay($flow, 3);
		$label = self::flow_label($flow);

		echo '<div class="wrap">';
		echo '<h1>Recordatorios de compra — pedidos ' . esc_html($label) . 's</h1>';

		if ($saved) {
			echo '<div class="notice notice-success is-dismissible"><p>Tiempos guardados. Aplican a <strong>pedidos nuevos</strong>.</p></div>';
		}
		if ($templates_saved) {
			echo '<div class="notice notice-success is-dismissible"><p>Contenido de los correos guardado.</p></div>';
		}
		self::render_notices();

		$other = $flow === 'failed' ? 'cancelado' : 'fallido';
		echo '<div style="max-width:980px;background:#fff;border:1px solid #c3c4c7;border-left:3px solid #6b3bb8;padding:12px 16px;margin:10px 0 16px;">';
		echo '<p style="margin:0 0 6px;"><strong>¿Qué hace esta página?</strong></p>';
		echo '<p style="margin:0 0 8px;">Envía <strong>recordatorios automáticos por correo</strong> a los clientes cuyo pedido quedó <strong>' . esc_html($label) . '</strong>, invitándolos a <strong>retomar su compra</strong>. El correo trae un botón que les deja el carrito listo con la misma cantidad de DigiTickets y números nuevos.</p>';
		echo '<ul style="margin:6px 0;padding-left:20px;">';
		echo '<li>Cuando un pedido pasa a <strong>' . esc_html($label) . '</strong>, se programa el <strong>1.er recordatorio</strong>. Si el cliente no compra, se envía el <strong>2.º</strong> y después el <strong>3.º</strong>.</li>';
		echo '<li>Los tiempos se configuran abajo: R1 en <strong>' . esc_html(self::delay_unit_label($flow, 1)) . '</strong>, R2 en <strong>' . esc_html(self::delay_unit_label($flow, 2)) . '</strong> y R3 en <strong>' . esc_html(self::delay_unit_label($flow, 3)) . '</strong>.</li>';
		echo '<li>Si el cliente <strong>ya compró</strong>, no se le envía nada más.</li>';
		echo '<li>Puedes enviar un recordatorio manual con el botón <strong>"Enviar ahora"</strong> de cada pedido (cuenta como el 1.º).</li>';
		echo '<li>Los pedidos ' . esc_html($label) . 's <strong>anteriores a activar esta función</strong> no reciben recordatorios automáticos; si quieres, mándales uno con "Enviar ahora".</li>';
		echo '<li>Esta pantalla es solo para pedidos <strong>' . esc_html($label) . 's</strong>; los <strong>' . esc_html($other) . 's</strong> tienen su propia pantalla.</li>';
		echo '</ul>';
		echo '</div>';

		$range = self::current_range();
		self::render_date_filter($flow, $range);
		self::render_kpis($flow, $range);

		echo '<details style="margin:14px 0;"><summary style="cursor:pointer;font-weight:600;font-size:14px;">Configuración de tiempos</summary>';
		echo '<form method="post" style="max-width:680px;background:#fff;border:1px solid #c3c4c7;padding:16px 20px;margin:12px 0 16px;">';
		wp_nonce_field('ss_cfr_save_' . $flow);
		echo '<table class="form-table" role="presentation"><tbody>';
		$u1 = self::delay_unit_label($flow, 1);
		$u2 = self::delay_unit_label($flow, 2);
		$u3 = self::delay_unit_label($flow, 3);
		$m1 = self::delay_unit($flow, 1) === 'days' ? 365 : 10080;
		$m2 = self::delay_unit($flow, 2) === 'days' ? 365 : 10080;
		$m3 = self::delay_unit($flow, 3) === 'days' ? 365 : 10080;

		echo '<tr><th scope="row"><label for="ss_cfr_delay_1">1.er recordatorio (' . esc_html($u1) . ')</label></th>';
		echo '<td><input name="ss_cfr_delay_1" id="ss_cfr_delay_1" type="number" min="1" max="' . $m1 . '" step="1" value="' . esc_attr((string) $d1) . '" class="small-text" /> <p class="description">' . esc_html(ucfirst($u1)) . ' después de que el pedido pasó a ' . esc_html($label) . '.</p></td></tr>';
		echo '<tr><th scope="row"><label for="ss_cfr_delay_2">2.º recordatorio (' . esc_html($u2) . ')</label></th>';
		echo '<td><input name="ss_cfr_delay_2" id="ss_cfr_delay_2" type="number" min="1" max="' . $m2 . '" step="1" value="' . esc_attr((string) $d2) . '" class="small-text" /> <p class="description">' . esc_html(ucfirst($u2)) . ' después del 1.er correo.</p></td></tr>';
		echo '<tr><th scope="row"><label for="ss_cfr_delay_3">3.er recordatorio (' . esc_html($u3) . ')</label></th>';
		echo '<td><input name="ss_cfr_delay_3" id="ss_cfr_delay_3" type="number" min="1" max="' . $m3 . '" step="1" value="' . esc_attr((string) $d3) . '" class="small-text" /> <p class="description">' . esc_html(ucfirst($u3)) . ' después del 2.º correo.</p></td></tr>';
		echo '</tbody></table>';
		echo '<p class="submit"><button type="submit" name="ss_cfr_save_settings" class="button button-primary" value="1">Guardar tiempos</button></p>';
		echo '</form>';

		echo '<p style="max-width:900px;">Cada correo incluye un botón que <strong>precarga el carrito</strong> con la misma cantidad de DigiTickets del sorteo que el cliente intentó comprar (números nuevos, elegidos al azar al hacer clic). Si el cliente ya compró, no se envía nada más.</p>';
		echo '</details>';

		self::render_templates_editor($flow);

		self::render_test_box($flow);
		self::render_candidates($flow, $range);
		self::render_log($flow, $range);

		echo '</div>';
	}

	private static function current_range(): array {
		$from = isset($_GET['ss_cfr_from']) ? sanitize_text_field(wp_unslash($_GET['ss_cfr_from'])) : '';
		$to   = isset($_GET['ss_cfr_to']) ? sanitize_text_field(wp_unslash($_GET['ss_cfr_to'])) : '';
		$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : '';
		$to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : '';
		if ($from !== '' && $to !== '' && $from > $to) {
			$swap = $from;
			$from = $to;
			$to   = $swap;
		}
		return array('from' => $from, 'to' => $to);
	}

	private static function range_query_args(array $range): array {
		$args = array();
		if (!empty($range['from'])) {
			$args['ss_cfr_from'] = $range['from'];
		}
		if (!empty($range['to'])) {
			$args['ss_cfr_to'] = $range['to'];
		}
		return $args;
	}

	private static function render_date_filter(string $flow, array $range): void {
		$active = ($range['from'] !== '' || $range['to'] !== '');
		$title  = 'Filtrar por fecha';
		if ($active) {
			$title .= ' · activo: ' . ($range['from'] !== '' ? $range['from'] : '…') . ' → ' . ($range['to'] !== '' ? $range['to'] : '…');
		}

		echo '<details style="margin:14px 0;"' . ($active ? ' open' : '') . '><summary style="cursor:pointer;font-weight:600;font-size:14px;">' . esc_html($title) . '</summary>';
		echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" style="max-width:680px;background:#fff;border:1px solid #c3c4c7;padding:12px 16px;margin:12px 0 16px;">';
		echo '<input type="hidden" name="page" value="' . esc_attr(self::page_slug($flow)) . '" />';
		echo '<label style="margin-right:14px;">Desde <input type="date" name="ss_cfr_from" value="' . esc_attr((string) $range['from']) . '" /></label>';
		echo '<label style="margin-right:14px;">Hasta <input type="date" name="ss_cfr_to" value="' . esc_attr((string) $range['to']) . '" /></label>';
		echo '<button type="submit" class="button button-primary">Filtrar</button> ';
		echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=' . self::page_slug($flow))) . '">Limpiar</a>';
		echo '<p class="description" style="margin:8px 0 0;">Muestra solo los pedidos ' . esc_html(self::flow_label($flow)) . 's creados en el rango (y el registro de envíos en ese rango). Sin fechas, se muestran los últimos ' . esc_html((string) self::CANDIDATE_DAYS) . ' días.</p>';
		echo '</form>';
		echo '</details>';
	}

	private static function render_notices(): void {
		if (!isset($_GET['ss_cfr_result'])) {
			return;
		}
		$code = sanitize_key((string) wp_unslash($_GET['ss_cfr_result']));
		$map  = array(
			'sent'           => array('success', 'Correo enviado.'),
			'test_sent'      => array('success', 'Correo de prueba enviado.'),
			'test_invalid'   => array('error', 'Ingresa un correo válido.'),
			'test_failed'    => array('error', 'No se pudo enviar el correo de prueba.'),
			'already_bought' => array('warning', 'El cliente ya tiene una compra pagada; no se envió.'),
			'send_failed'    => array('error', 'No se pudo enviar el correo.'),
			'missing'        => array('error', 'No se encontró el pedido o no está cancelado/fallido.'),
		);
		if (!isset($map[$code])) {
			return;
		}
		echo '<div class="notice notice-' . esc_attr($map[$code][0]) . ' is-dismissible"><p>' . esc_html($map[$code][1]) . '</p></div>';
	}

	private static function compute_kpis(string $flow, array $range): array {
		$cache_key = 'ss_cfr_kpi5_' . self::VERSION . '_' . $flow . '_' . md5((string) wp_json_encode($range));
		$cached    = get_transient($cache_key);
		if (is_array($cached) && array_key_exists('reminded', $cached)) {
			return $cached;
		}

		$from = isset($range['from']) ? (string) $range['from'] : '';
		$to   = isset($range['to']) ? (string) $range['to'] : '';

		$emails_sent  = 0;
		$click_orders = array();

		$log = get_option(self::OPT_LOG, array());
		if (!is_array($log)) {
			$log = array();
		}
		foreach ($log as $row) {
			if (!is_array($row) || (string) ($row['flow'] ?? '') !== $flow) {
				continue;
			}
			$ts = isset($row['time']) ? strtotime((string) $row['time']) : 0;
			if ($ts && ($from !== '' || $to !== '')) {
				$date = wp_date('Y-m-d', $ts);
				if ($from !== '' && $date < $from) {
					continue;
				}
				if ($to !== '' && $date > $to) {
					continue;
				}
			}
			$which  = (string) ($row['which'] ?? '');
			$result = (string) ($row['result'] ?? '');
			if ($result === 'sent' || $result === 'manual_sent') {
				$emails_sent++;
			}
			if ($which === 'click') {
				$oid = (int) ($row['order_id'] ?? 0);
				if ($oid > 0) {
					$click_orders[ $oid ] = true;
				}
			}
		}

		$candidates = self::list_candidates_all($flow, $range);
		$total      = count($candidates);

		$reminded        = 0;
		$recovered       = 0;
		$revenue         = 0.0;
		$attributed      = 0;
		$total_recovered = 0;
		$total_revenue   = 0.0;
		$holdout_count     = 0;
		$holdout_recovered = 0;

		$seen_total     = array();
		$seen_recovered = array();
		$seen_attr      = array();

		foreach ($candidates as $candidate) {
			$reminder_ts  = self::first_reminder_ts($candidate);
			$has_reminder = ($reminder_ts > 0);
			if ($has_reminder) {
				$reminded++;
			}

			$purchase_id = self::customer_purchase_order_id($candidate);

			if ((string) $candidate->get_meta('_ss_cfr_holdout') === 'yes') {
				$holdout_count++;
				if ($purchase_id > 0) {
					$holdout_recovered++;
				}
			}

			if ($purchase_id <= 0) {
				continue;
			}
			$purchase = wc_get_order($purchase_id);
			if (!$purchase instanceof WC_Order) {
				continue;
			}

			$created     = $purchase->get_date_created();
			$purchase_ts = $created ? $created->getTimestamp() : 0;
			$amount      = (float) $purchase->get_total();

			if (!isset($seen_total[ $purchase_id ])) {
				$seen_total[ $purchase_id ] = true;
				$total_recovered++;
				$total_revenue += $amount;
			}

			if (!$has_reminder) {
				continue;
			}
			if ($purchase_ts > 0 && $purchase_ts < $reminder_ts) {
				continue;
			}

			if (!isset($seen_recovered[ $purchase_id ])) {
				$seen_recovered[ $purchase_id ] = true;
				$recovered++;
				$revenue += $amount;
			}

			if ((int) $purchase->get_meta('_ss_cfr_source_order') === (int) $candidate->get_id() && !isset($seen_attr[ $purchase_id ])) {
				$seen_attr[ $purchase_id ] = true;
				$attributed++;
			}
		}

		$rate = $reminded > 0 ? round(($recovered / $reminded) * 100, 1) : 0.0;

		$result = array(
			'emails_sent'     => $emails_sent,
			'clicks'          => count($click_orders),
			'total'           => $total,
			'reminded'        => $reminded,
			'recovered'       => $recovered,
			'attributed'      => $attributed,
			'revenue'         => $revenue,
			'total_recovered'   => $total_recovered,
			'total_revenue'     => $total_revenue,
			'holdout'           => $holdout_count,
			'holdout_recovered' => $holdout_recovered,
			'rate'              => $rate,
		);

		set_transient($cache_key, $result, 3 * MINUTE_IN_SECONDS);

		return $result;
	}

	private static function first_reminder_ts(WC_Order $order): int {
		$times = array();
		foreach (array(self::META_R1, self::META_R2, self::META_R3) as $key) {
			$value = (string) $order->get_meta($key);
			if ($value !== '') {
				$ts = strtotime($value . ' UTC');
				if ($ts) {
					$times[] = $ts;
				}
			}
		}
		return $times ? (int) min($times) : 0;
	}

	private static function render_kpis(string $flow, array $range): void {
		$k = self::compute_kpis($flow, $range);

		$money = function_exists('wc_price')
			? wp_strip_all_tags(wc_price($k['total_revenue']))
			: number_format_i18n((float) $k['total_revenue'], 0);

		$rate = ((int) $k['total'] > 0) ? round(((int) $k['total_recovered'] / (int) $k['total']) * 100, 1) : 0.0;

		echo '<h2 style="margin-bottom:4px;">Resultados de la campaña de correos</h2>';
		echo '<p class="description" style="margin:0 0 10px;">Resumen de los pedidos ' . esc_html(self::flow_label($flow)) . 's del rango (de los ' . (int) $k['total'] . '). Respeta el filtro de fechas.</p>';
		echo '<div style="display:flex;gap:14px;flex-wrap:wrap;margin:0 0 14px;">';
		self::kpi_card('Clientes contactados', (string) $k['reminded'], 'de ' . $k['total'] . ' pedidos en el rango');
		self::kpi_card('Correos enviados (total)', (string) $k['emails_sent'], 'automáticos + manuales');
		self::kpi_card('Clics al enlace', (string) $k['clicks']);
		self::kpi_card('Volvieron a comprar', (string) $k['total_recovered'], $rate . ' % del rango');
		self::kpi_card('Tras el correo', (string) $k['attributed'], 'de esos, por el enlace');
		self::kpi_card('Monto recuperado', $money);
		echo '</div>';

		echo '<div style="max-width:980px;margin:0 0 20px;padding:12px 16px;background:#fff;border:1px solid #c3c4c7;border-radius:6px;">';
		echo '<p style="margin:0 0 8px;"><strong>¿Qué significa cada indicador?</strong></p>';
		echo '<ul style="margin:0;padding-left:20px;color:#555;line-height:1.6;">';
		echo '<li><strong>Clientes contactados</strong>: cuántos pedidos (clientes) del rango recibieron al menos un correo (automático o manual). A cada cliente se le cuenta una sola vez.</li>';
		echo '<li><strong>Correos enviados (total)</strong>: cuántos correos salieron en total (automáticos + envíos manuales "Enviar ahora"; no cuenta los de prueba). Un mismo cliente puede recibir R1, R2 y R3, por eso suele ser mayor o igual que "Clientes contactados".</li>';
		echo '<li><strong>Clics al enlace</strong>: cuántos clientes hicieron clic en el botón del correo.</li>';
		echo '<li><strong>Volvieron a comprar</strong>: pedidos ' . esc_html(self::flow_label($flow)) . 's del rango que tuvieron una compra después, <strong>hayan recibido correo o no</strong>.</li>';
		echo '<li><strong>Tras el correo</strong>: de esos, cuántos entraron por el enlace del correo (atribuido).</li>';
		echo '<li><strong>Monto recuperado</strong>: suma de esas compras.</li>';
		echo '</ul>';
		echo '</div>';
	}

	private static function kpi_card(string $label, string $value, string $sub = ''): void {
		echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:12px 16px;min-width:150px;">';
		echo '<div style="color:#666;font-size:12px;">' . esc_html($label) . '</div>';
		echo '<div style="font-size:22px;font-weight:600;margin-top:4px;color:#1d2327;">' . esc_html($value) . '</div>';
		if ($sub !== '') {
			echo '<div style="color:#8a8f98;font-size:11px;margin-top:2px;">' . esc_html($sub) . '</div>';
		}
		echo '</div>';
	}

	private static function purchase_has_same_lottery(WC_Order $source, WC_Order $purchase): bool {
		$source_pids = array();
		foreach ($source->get_items() as $item) {
			if (is_object($item) && method_exists($item, 'get_product_id')) {
				$source_pids[ (int) $item->get_product_id() ] = true;
			}
		}
		if (!$source_pids) {
			return false;
		}
		foreach ($purchase->get_items() as $item) {
			if (is_object($item) && method_exists($item, 'get_product_id') && isset($source_pids[ (int) $item->get_product_id() ])) {
				return true;
			}
		}
		return false;
	}

	private static function render_templates_editor(string $flow): void {
		$ids        = self::template_ids_for_flow($flow);
		$flow_label = self::flow_label($flow);
		$titles     = array();
		foreach ($ids as $i => $id) {
			$n             = $i + 1;
			$titles[ $id ] = 'Recordatorio ' . $n . ' — ' . ucfirst($flow_label) . ($n === 3 ? ' (con descuento)' : '');
		}

		echo '<h2 style="margin-top:18px;">Contenido de los correos</h2>';
		echo '<p class="description" style="max-width:900px;">Edita el asunto, el preheader (texto de vista previa), el encabezado, el cuerpo (HTML) y el descuento de cada recordatorio. Los cambios aplican a los <strong>próximos envíos</strong>.</p>';
		echo '<form method="post" style="max-width:820px;">';
		wp_nonce_field('ss_cfr_save_templates_' . $flow);

		foreach ($ids as $id) {
			$tpl = self::get_template($id);
			echo '<details style="margin:14px 0;background:#fff;border:1px solid #c3c4c7;padding:12px 16px;">';
			echo '<summary style="cursor:pointer;font-weight:600;font-size:14px;">' . esc_html($titles[ $id ]) . '</summary>';
			echo '<p class="description" style="margin:10px 0 4px;">Etiquetas: <code>{nombre}</code>, <code>{pedido}</code>, <code>{url}</code> (enlace de retomar), <code>{boton}</code> (botón "Retomar"), <code>{sorteos}</code> (tarjeta de sorteos) y <code>{descuento}</code>.</p>';
			echo '<p><label><strong>Asunto</strong></label><br /><input type="text" name="ss_cfr_tpl_' . esc_attr($id) . '_subject" value="' . esc_attr((string) $tpl['subject']) . '" style="width:100%;" /></p>';
			echo '<p><label><strong>Preheader (vista previa)</strong></label><br /><input type="text" name="ss_cfr_tpl_' . esc_attr($id) . '_preheader" value="' . esc_attr((string) $tpl['preheader']) . '" style="width:100%;" /></p>';
			echo '<p><label><strong>Encabezado</strong></label><br /><input type="text" name="ss_cfr_tpl_' . esc_attr($id) . '_heading" value="' . esc_attr((string) $tpl['heading']) . '" style="width:100%;" /></p>';
			echo '<p><label><strong>Cuerpo (HTML)</strong></label><br /><textarea name="ss_cfr_tpl_' . esc_attr($id) . '_body" rows="14" style="width:100%;font-family:Consolas,monospace;font-size:12px;">' . esc_textarea((string) $tpl['body']) . '</textarea></p>';
			echo '<p><label><strong>Descuento (%)</strong></label> <input type="number" min="0" max="100" step="1" name="ss_cfr_tpl_' . esc_attr($id) . '_discount" value="' . esc_attr((string) (int) $tpl['discount']) . '" class="small-text" /> <span class="description">Se aplica al carrito precargado al hacer clic en el botón. 0 = sin descuento.</span></p>';
			echo '<p style="margin:10px 0 0;"><button type="submit" name="ss_cfr_restore_template" value="' . esc_attr($id) . '" class="button" onclick="return confirm(\'¿Restaurar el contenido por defecto de este correo?\');">Restaurar por defecto</button></p>';
			echo '</details>';
		}

		echo '<p class="submit"><button type="submit" name="ss_cfr_save_templates" class="button button-primary" value="1">Guardar contenido de correos</button></p>';
		echo '</form>';
	}

	private static function render_test_box(string $flow): void {
		$user    = wp_get_current_user();
		$prefill = ($user && is_email($user->user_email)) ? $user->user_email : '';

		echo '<details style="margin:14px 0;"><summary style="cursor:pointer;font-weight:600;font-size:14px;">Enviar correo de prueba</summary>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:680px;background:#fff;border:1px solid #c3c4c7;padding:16px 20px;margin:12px 0 16px;">';
		echo '<input type="hidden" name="action" value="' . esc_attr(self::ADMIN_TEST) . '" />';
		echo '<input type="hidden" name="flow" value="' . esc_attr($flow) . '" />';
		wp_nonce_field('ss_cfr_send_test');
		echo '<p>Envía el correo real (mismo diseño y texto que verá el cliente) a un email de prueba.</p>';
		echo '<p><input type="email" name="test_email" class="regular-text" value="' . esc_attr($prefill) . '" required /></p>';
		echo '<p><label><strong>¿Qué correo enviar?</strong></label><br />';
		echo '<select name="test_which"><option value="1">Recordatorio 1</option><option value="2">Recordatorio 2</option><option value="3">Recordatorio 3 (con descuento)</option></select></p>';
		echo '<p class="submit" style="margin:0;"><button type="submit" class="button">Enviar prueba</button></p>';
		echo '</form>';
		echo '</details>';
	}

	private static function render_candidates(string $flow, array $range): void {
		$per_page   = 20;
		$page       = self::current_page('ss_cfr_page');
		$data       = self::list_candidates_page($flow, $page, $per_page, $range);
		$candidates = $data['orders'];
		$total      = $data['total'];
		$max_pages  = $data['max_num_pages'];

		echo '<h2>Pedidos ' . esc_html(self::flow_label($flow)) . 's elegibles</h2>';
		echo '<p class="description" style="max-width:900px;">';
		echo '<strong>Recordatorios enviados</strong>: fecha y hora (local) de cada correo ya enviado — R1 y R2 en minutos, R3 en días. ';
		echo '<strong>¿Compró?</strong>: si el cliente volvió a comprar después de este pedido y si entró por el correo.';
		echo '</p>';
		echo '<table class="widefat striped" style="max-width:1100px;"><thead><tr>';
		echo '<th>Pedido</th><th>Email</th><th>Creado</th><th>Recordatorios enviados</th><th>¿Compró?</th><th>Acción</th>';
		echo '</tr></thead><tbody>';

		if (!$candidates) {
			echo '<tr><td colspan="6">No hay pedidos elegibles en los últimos ' . esc_html((string) self::CANDIDATE_DAYS) . ' días.</td></tr>';
		} else {
			foreach ($candidates as $order) {
				$oid     = $order->get_id();
				$created = $order->get_date_created();
				$r1      = (string) $order->get_meta(self::META_R1);
				$r2      = (string) $order->get_meta(self::META_R2);
				$r3      = (string) $order->get_meta(self::META_R3);
				$sent    = array();
				if ($r1 !== '') {
					$sent[] = 'R1: ' . self::format_local($r1);
				}
				if ($r2 !== '') {
					$sent[] = 'R2: ' . self::format_local($r2);
				}
				if ($r3 !== '') {
					$sent[] = 'R3: ' . self::format_local($r3);
				}

				echo '<tr>';
				echo '<td><a href="' . esc_url($order->get_edit_order_url()) . '">#' . esc_html((string) $oid) . '</a></td>';
				echo '<td>' . esc_html($order->get_billing_email()) . '</td>';
				echo '<td>' . esc_html($created ? $created->date_i18n('Y-m-d H:i') : '—') . '</td>';
				echo '<td>' . esc_html($sent ? implode(' · ', $sent) : 'Sin envíos') . '</td>';
				$purchase_id = self::customer_purchase_order_id($order);
				echo '<td>';
				if ($purchase_id > 0) {
					$purchase_order = wc_get_order($purchase_id);
					$purchase_link  = $purchase_order ? $purchase_order->get_edit_order_url() : admin_url('post.php?post=' . $purchase_id . '&action=edit');
					$src_order      = $purchase_order ? (int) $purchase_order->get_meta('_ss_cfr_source_order') : 0;
					$src_which      = $purchase_order ? (string) $purchase_order->get_meta('_ss_cfr_source_which') : '';
					if ($src_order === (int) $oid && $src_which !== '') {
						$tag = ' · tras ' . self::source_label($src_which);
					} else {
						$tag = ' · directo';
						if ($purchase_order && !self::purchase_has_same_lottery($order, $purchase_order)) {
							$tag .= ' · otro sorteo';
						}
					}
					echo '<a href="' . esc_url($purchase_link) . '">Sí · #' . esc_html((string) $purchase_id) . esc_html($tag) . '</a>';
				} else {
					$clicked = (string) $order->get_meta('_ss_cfr_preloaded_at');
					echo $clicked !== '' ? 'Clic sin compra' : 'No';
				}
				echo '</td>';
				echo '<td><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:0;">';
				echo '<input type="hidden" name="action" value="' . esc_attr(self::ADMIN_MANUAL) . '" />';
				echo '<input type="hidden" name="order_id" value="' . esc_attr((string) $oid) . '" />';
				echo '<input type="hidden" name="flow" value="' . esc_attr($flow) . '" />';
				wp_nonce_field('ss_cfr_send_manual_' . $oid);
				echo '<button type="submit" class="button">Enviar ahora</button></form></td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
		self::render_pager($page, $max_pages, $total, $per_page, 'ss_cfr_page', $flow, self::range_query_args($range));

		echo '<div style="max-width:900px;margin:14px 0 0;background:#fff;border:1px solid #c3c4c7;padding:12px 16px;">';
		echo '<p style="margin:0 0 8px;"><strong>¿Qué significa "¿Compró?"</strong></p>';
		echo '<ul style="margin:0;padding-left:20px;color:#555;list-style:disc;">';
		echo '<li><strong>Sí · #pedido · tras R1/R2/R3</strong> — compró después y entró por el enlace de ese recordatorio (atribuido).</li>';
		echo '<li><strong>Sí · #pedido · directo</strong> — compró después, pero no se pudo atribuir al correo (p. ej. clic en otro dispositivo/sesión).</li>';
		echo '<li><strong>· otro sorteo</strong> — la compra posterior fue de un sorteo distinto al de este pedido.</li>';
		echo '<li><strong>Clic sin compra</strong> — hizo clic en el enlace pero aún no hay compra pagada.</li>';
		echo '<li><strong>No</strong> — sin compra posterior ni clic.</li>';
		echo '</ul>';
		echo '<p style="margin:8px 0 0;color:#555;"><strong>Recordatorios enviados:</strong> R1 y R2 se cuentan en minutos; R3 en días. Las horas son locales. <strong>"Sin envíos"</strong> = todavía no se le ha mandado ningún recordatorio (por ejemplo, pedidos ' . esc_html(self::flow_label($flow)) . 's anteriores a activar esta función).</p>';
		echo '<p style="margin:6px 0 0;color:#555;"><strong>Acción "Enviar ahora":</strong> envía el correo al instante; si el pedido no tiene R1, cuenta como el 1.er recordatorio y agenda el 2.º.</p>';
		echo '</div>';
	}

	private static function render_log(string $flow, array $range = array()): void {
		$log = get_option(self::OPT_LOG, array());
		if (!is_array($log)) {
			$log = array();
		}
		$log = array_reverse($log);

		$flow_rows = array();
		foreach ($log as $row) {
			if (is_array($row) && (string) ($row['flow'] ?? '') === $flow) {
				$flow_rows[] = $row;
			}
		}

		$from = isset($range['from']) ? (string) $range['from'] : '';
		$to   = isset($range['to']) ? (string) $range['to'] : '';
		if ($from !== '' || $to !== '') {
			$flow_rows = array_values(
				array_filter(
					$flow_rows,
					static function ($row) use ($from, $to) {
						$ts = isset($row['time']) ? strtotime((string) $row['time']) : 0;
						if (!$ts) {
							return false;
						}
						$date = wp_date('Y-m-d', $ts);
						if ($from !== '' && $date < $from) {
							return false;
						}
						if ($to !== '' && $date > $to) {
							return false;
						}
						return true;
					}
				)
			);
		}

		$per_page  = 20;
		$page      = self::current_page('ss_cfr_log_page');
		$total     = count($flow_rows);
		$max_pages = (int) ceil($total / $per_page);
		$offset    = ($page - 1) * $per_page;
		$page_rows = array_slice($flow_rows, $offset, $per_page);

		echo '<details style="margin-top:18px;">';
		echo '<summary style="cursor:pointer;font-weight:600;font-size:14px;">Registro de envíos (historial detallado)</summary>';
		echo '<p class="description" style="max-width:900px;">Historial de todos los eventos de este flujo (correos enviados, clics, envíos manuales, pruebas y omitidos). Las fechas son <strong>locales</strong>. La tabla de arriba muestra el estado actual de cada pedido; este registro muestra qué pasó y cuándo.</p>';
		echo '<table class="widefat striped" style="max-width:1100px;margin-top:12px;"><thead><tr>';
		echo '<th>Fecha (local)</th><th>Pedido</th><th>Evento</th><th>Email</th><th>Resultado</th>';
		echo '</tr></thead><tbody>';

		if (!$page_rows) {
			echo '<tr><td colspan="5">Aún no hay envíos registrados.</td></tr>';
		} else {
			foreach ($page_rows as $row) {
				$oid  = isset($row['order_id']) ? (int) $row['order_id'] : 0;
				$link = $oid ? admin_url('post.php?post=' . $oid . '&action=edit') : '#';
				if ($oid && function_exists('wc_get_order')) {
					$o = wc_get_order($oid);
					if ($o) {
						$link = $o->get_edit_order_url();
					}
				}
				$row_time = isset($row['time']) ? strtotime((string) $row['time']) : 0;
				echo '<tr>';
				echo '<td>' . esc_html($row_time ? wp_date('Y-m-d H:i', $row_time) : (string) ($row['time'] ?? '')) . '</td>';
				echo '<td>' . ($oid ? '<a href="' . esc_url($link) . '">#' . esc_html((string) $oid) . '</a>' : '—') . '</td>';
				echo '<td>' . esc_html(self::label_event((string) ($row['which'] ?? ''))) . '</td>';
				echo '<td>' . esc_html((string) ($row['email'] ?? '')) . '</td>';
				echo '<td>' . esc_html(self::label_result((string) ($row['result'] ?? ''))) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
		self::render_pager($page, $max_pages, $total, $per_page, 'ss_cfr_log_page', $flow, self::range_query_args($range));
		echo '</details>';
	}

	private static function label_event(string $which): string {
		$map = array(
			'1'      => '1.er recordatorio',
			'2'      => '2.º recordatorio',
			'3'      => '3.er recordatorio',
			'manual' => 'Envío manual',
			'test'   => 'Correo de prueba',
			'click'  => 'Clic al enlace',
		);
		return $map[$which] ?? ($which !== '' ? $which : '—');
	}

	private static function label_result(string $result): string {
		$map = array(
			'sent'               => 'Correo enviado',
			'manual_sent'        => 'Envío manual',
			'test_sent'          => 'Prueba enviada',
			'test_failed'        => 'Prueba fallida',
			'skipped_purchased'  => 'No enviado: ya compró',
			'skipped_not_status' => 'No enviado: ya no está cancelado/fallido',
			'send_failed'        => 'Error al enviar',
			'preload_click'      => 'Clic al enlace (carrito precargado)',
		);
		return $map[$result] ?? ($result !== '' ? $result : '—');
	}

	private static function source_label(string $which): string {
		$map = array(
			'1'      => 'recordatorio #1',
			'2'      => 'recordatorio #2',
			'3'      => 'recordatorio #3',
			'manual' => 'envío manual',
			'test'   => 'prueba',
		);
		return $map[$which] ?? ('recordatorio ' . $which);
	}

	public static function handle_send_test(): void {
		if (!current_user_can('manage_woocommerce')) {
			wp_die('No autorizado.');
		}
		check_admin_referer('ss_cfr_send_test');

		$to    = isset($_POST['test_email']) ? sanitize_email(wp_unslash($_POST['test_email'])) : '';
		$flow  = isset($_POST['flow']) ? sanitize_key(wp_unslash($_POST['flow'])) : 'cancelled';
		$which = isset($_POST['test_which']) ? (string) absint(wp_unslash($_POST['test_which'])) : '1';
		if (!in_array($flow, array('cancelled', 'failed'), true)) {
			$flow = 'cancelled';
		}
		if (!in_array($which, array('1', '2', '3'), true)) {
			$which = '1';
		}

		$result = 'test_invalid';
		if (is_email($to)) {
			$ok     = self::send_test_email($to, $flow, $which);
			$result = $ok ? 'test_sent' : 'test_failed';
			self::append_log(array('flow' => $flow, 'which' => 'test', 'email' => $to, 'result' => $result));
		}

		wp_safe_redirect(add_query_arg('ss_cfr_result', $result, admin_url('admin.php?page=' . self::page_slug($flow))));
		exit;
	}

	public static function handle_send_manual(): void {
		if (!current_user_can('manage_woocommerce')) {
			wp_die('No autorizado.');
		}

		$order_id = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;
		$flow     = isset($_POST['flow']) ? sanitize_key(wp_unslash($_POST['flow'])) : 'cancelled';
		if (!in_array($flow, array('cancelled', 'failed'), true)) {
			$flow = 'cancelled';
		}
		check_admin_referer('ss_cfr_send_manual_' . $order_id);

		$result = 'missing';
		$order  = wc_get_order($order_id);
		if ($order instanceof WC_Order && in_array($order->get_status(), array('cancelled', 'failed'), true)) {
			if (self::customer_already_bought($order)) {
				$result = 'already_bought';
			} else {
				$ok = self::send_reminder_email($order, self::flow_for_status($order->get_status()), 'manual');
				if ($ok) {
					if (!$order->get_meta(self::META_R1)) {
						$order->update_meta_data(self::META_R1, gmdate('Y-m-d H:i:s'));
						$order->update_meta_data(self::META_SCHEDULED, 'yes');
						$order->update_meta_data(self::META_FLOW, self::flow_for_status($order->get_status()));
						$order->add_order_note('Sorteo Seguro: recordatorio de compra enviado manualmente (cuenta como 1.º).');
						$order->save();
						self::enqueue($order_id, 2, time() + self::delay_seconds(self::flow_for_status($order->get_status()), 2));
					}
					self::append_log(array('order_id' => $order_id, 'flow' => self::flow_for_status($order->get_status()), 'which' => 'manual', 'email' => $order->get_billing_email(), 'result' => 'manual_sent', 'status' => $order->get_status()));
					$result = 'sent';
				} else {
					$result = 'send_failed';
				}
			}
		}

		wp_safe_redirect(add_query_arg('ss_cfr_result', $result, admin_url('admin.php?page=' . self::page_slug($flow))));
		exit;
	}

	private static function send_test_email(string $to, string $flow, string $which = '1'): bool {
		if (!function_exists('WC') || !WC()->mailer() || !is_email($to)) {
			return false;
		}

		$sample = null;
		if (function_exists('wc_get_products')) {
			$products = wc_get_products(array('type' => 'lottery', 'status' => 'publish', 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC'));
			if (!empty($products[0]) && is_object($products[0])) {
				$sample = $products[0];
			}
		}

		$items = array();
		if ($sample) {
			$image = method_exists($sample, 'get_image')
				? (string) $sample->get_image('woocommerce_thumbnail', array('style' => 'width:64px;height:64px;object-fit:cover;border-radius:8px;display:block;border:0;'), false)
				: '';
			$name  = trim((string) preg_replace('/^DigiTicket\s*\|\s*/iu', '', wp_strip_all_tags((string) $sample->get_name())));
			$items[] = array(
				'p'     => (int) $sample->get_id(),
				'q'     => 10,
				'c'     => self::find_pack_campaign_id((int) $sample->get_id(), 10),
				'name'  => $name !== '' ? $name : 'Sorteo',
				'image' => $image,
			);
		}

		$email  = self::build_email_from_items($items, 0, '', $flow, $which);
		$mailer = WC()->mailer();
		$html   = $mailer->wrap_message($email['heading'], $email['body']);

		try {
			return (bool) $mailer->send($to, $email['subject'], $html);
		} catch (Throwable $e) {
			self::log('error', 'CFR test email exception: ' . $e->getMessage(), array('to' => $to));
			return false;
		}
	}

	private static function list_candidates_page(string $flow, int $page, int $per_page, array $range = array()): array {
		$empty = array('orders' => array(), 'total' => 0, 'max_num_pages' => 0);
		if (!function_exists('wc_get_orders')) {
			return $empty;
		}

		$status = $flow === 'failed' ? 'failed' : 'cancelled';
		$result = wc_get_orders(
			array(
				'status'       => array($status),
				'limit'        => $per_page,
				'page'         => max(1, $page),
				'paginate'     => true,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'date_created' => self::range_date_arg($range),
				'return'       => 'objects',
			)
		);

		if (!is_object($result) || !isset($result->orders)) {
			return $empty;
		}

		$out = array();
		foreach ((array) $result->orders as $order) {
			if (!$order instanceof WC_Order) {
				continue;
			}
			if ($order->get_billing_email() === '') {
				continue;
			}
			if (!self::order_has_lottery_item($order)) {
				continue;
			}
			$out[] = $order;
		}

		return array(
			'orders'        => $out,
			'total'         => (int) ($result->total ?? 0),
			'max_num_pages' => (int) ($result->max_num_pages ?? 0),
		);
	}

	private static function range_date_arg(array $range): string {
		$from = isset($range['from']) ? (string) $range['from'] : '';
		$to   = isset($range['to']) ? (string) $range['to'] : '';
		if ($from !== '' && $to !== '') {
			return $from . '...' . $to;
		}
		if ($from !== '') {
			return '>=' . $from;
		}
		if ($to !== '') {
			return '<=' . $to;
		}
		return '>=' . (time() - (self::CANDIDATE_DAYS * DAY_IN_SECONDS));
	}

	private static function list_candidates_all(string $flow, array $range, int $limit = 1000): array {
		if (!function_exists('wc_get_orders')) {
			return array();
		}

		$status = $flow === 'failed' ? 'failed' : 'cancelled';
		$found  = wc_get_orders(
			array(
				'status'       => array($status),
				'limit'        => $limit,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'date_created' => self::range_date_arg($range),
				'return'       => 'objects',
			)
		);

		$out = array();
		foreach ((array) $found as $order) {
			if (!$order instanceof WC_Order) {
				continue;
			}
			if ($order->get_billing_email() === '') {
				continue;
			}
			if (!self::order_has_lottery_item($order)) {
				continue;
			}
			$out[] = $order;
		}
		return $out;
	}

	private static function current_page(string $key): int {
		$page = isset($_GET[$key]) ? absint(wp_unslash($_GET[$key])) : 1;
		return max(1, $page);
	}

	private static function render_pager(int $current, int $total_pages, int $total_items, int $per_page, string $key, string $flow, array $extra = array()): void {
		if ($total_pages <= 1) {
			if ($total_items > 0) {
				echo '<p class="description" style="margin:8px 0;">' . esc_html(sprintf('%s registros.', number_format_i18n($total_items))) . '</p>';
			}
			return;
		}

		$base = admin_url('admin.php?page=' . self::page_slug($flow));
		if (!empty($extra)) {
			$base = add_query_arg($extra, $base);
		}

		$link = static function (int $page) use ($base, $key) {
			return add_query_arg($key, max(1, $page), $base);
		};

		$from = (($current - 1) * $per_page) + 1;
		$to   = min($total_items, $current * $per_page);

		echo '<div class="tablenav" style="margin:10px 0;"><div class="tablenav-pages">';
		echo '<span class="displaying-num">' . esc_html(sprintf('%s–%s de %s', number_format_i18n($from), number_format_i18n($to), number_format_i18n($total_items))) . '</span> ';
		echo '<span class="pagination-links">';

		if ($current > 1) {
			echo '<a class="first-page button" href="' . esc_url($link(1)) . '"><span class="screen-reader-text">Primera página</span><span aria-hidden="true">«</span></a> ';
			echo '<a class="prev-page button" href="' . esc_url($link($current - 1)) . '"><span class="screen-reader-text">Página anterior</span><span aria-hidden="true">‹</span></a> ';
		} else {
			echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span> ';
			echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span> ';
		}

		echo '<span class="paging-input"><span class="tablenav-paging-text">Página <strong>' . (int) $current . '</strong> de <strong>' . (int) $total_pages . '</strong></span></span> ';

		if ($current < $total_pages) {
			echo '<a class="next-page button" href="' . esc_url($link($current + 1)) . '"><span class="screen-reader-text">Página siguiente</span><span aria-hidden="true">›</span></a> ';
			echo '<a class="last-page button" href="' . esc_url($link($total_pages)) . '"><span class="screen-reader-text">Última página</span><span aria-hidden="true">»</span></a>';
		} else {
			echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span> ';
			echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>';
		}

		echo '</span></div></div>';
	}

	private static function order_has_lottery_item(WC_Order $order): bool {
		if (!function_exists('lty_is_lottery_product')) {
			return false;
		}
		foreach ($order->get_items() as $item) {
			if (!is_object($item) || !method_exists($item, 'get_product')) {
				continue;
			}
			$product = $item->get_product();
			if ($product && lty_is_lottery_product($product)) {
				return true;
			}
		}
		return false;
	}

	private static function append_log(array $entry): void {
		$log = get_option(self::OPT_LOG, array());
		if (!is_array($log)) {
			$log = array();
		}
		$entry['time'] = $entry['time'] ?? gmdate('c');
		$log[]         = $entry;
		if (count($log) > self::LOG_MAX) {
			$log = array_slice($log, -self::LOG_MAX);
		}
		update_option(self::OPT_LOG, $log, false);
	}

	private static function format_local(string $gmt): string {
		if ($gmt === '') {
			return '';
		}
		$ts = strtotime($gmt . ' UTC');
		if (!$ts) {
			return $gmt;
		}
		return wp_date('Y-m-d H:i', $ts);
	}

	private static function log(string $level, string $message, array $context = array()): void {
		if (!function_exists('wc_get_logger')) {
			return;
		}
		$logger  = wc_get_logger();
		$payload = $message;
		if ($context) {
			$payload .= ' | ' . wp_json_encode($context);
		}
		$valid = array('emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug');
		if (in_array($level, $valid, true)) {
			$logger->{$level}($payload, array('source' => self::LOG_SOURCE));
		} else {
			$logger->info($payload, array('source' => self::LOG_SOURCE));
		}
	}
}

add_action(
	'plugins_loaded',
	static function () {
		if (!class_exists('WooCommerce')) {
			return;
		}
		SorteoSeguro_Cancelled_Failed_Reminders::init();
	},
	25
);
