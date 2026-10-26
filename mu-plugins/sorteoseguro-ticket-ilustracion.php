<?php
/**
 * Plugin Name: Sorteo Seguro – Ilustraciones DigiTicket
 * Description: Pool fijo de 100 ilustraciones por sorteo (sin quemar). Packs eligen N distintas. Conserva las ya asignadas a tickets vendidos.
 * Author: Sorteo Seguro
 * Version: 1.3.0
 *
 * mu-plugin: no se desactiva desde el admin.
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Ticket_Ilustracion {

	const VERSION       = '1.3.0';
	const META_ASSIGN   = '_lty_assigned_image';
	const META_ASSIGN_ID = '_ss_ilustracion_id';
	const META_POOL     = '_ss_ilustracion_pool';
	const POOL_SIZE     = 100;

	/** @var array<int,true>|null */
	private static $protected_cache = null;

	public static function init(): void {
		// API estática; se llama desde functions.php y la plantilla PDF del child theme.
	}

	/**
	 * URL de ilustración para un DigiTicket (correo PDF + descarga).
	 *
	 * @param object $ticket LTY_Lottery_Ticket
	 */
	public static function url_for_ticket($ticket): string {
		if (!is_object($ticket) || !method_exists($ticket, 'get_product_id')) {
			return '';
		}

		$product_id    = (int) $ticket->get_product_id();
		$ticket_id     = method_exists($ticket, 'get_id') ? (int) $ticket->get_id() : 0;
		$ticket_number = method_exists($ticket, 'get_lottery_ticket_number') ? (string) $ticket->get_lottery_ticket_number() : '';
		$exclude       = self::sibling_assigned_ids($ticket);

		return self::url_for_product($product_id, $ticket_id, $ticket_number, $exclude);
	}

	/**
	 * Fecha de compra (pedido), no la de creación del CPT ni la de descarga.
	 */
	public static function purchase_date_label($ticket): string {
		$order = (is_object($ticket) && method_exists($ticket, 'get_order')) ? $ticket->get_order() : null;
		$dt    = (is_object($order) && method_exists($order, 'get_date_created')) ? $order->get_date_created() : null;
		if ($dt && method_exists($dt, 'date_i18n')) {
			$fmt = trim((string) get_option('date_format') . ' ' . (string) get_option('time_format'));
			if ($fmt === '') {
				$fmt = 'F j, Y g:i a';
			}
			return (string) $dt->date_i18n($fmt);
		}
		if (is_object($ticket) && method_exists($ticket, 'get_formatted_created_date')) {
			return (string) $ticket->get_formatted_created_date();
		}
		return '';
	}

	/**
	 * Nombre del comprador (pedido). No usar el email como nombre.
	 */
	public static function customer_display_name($ticket): string {
		$candidates = array();
		$order      = (is_object($ticket) && method_exists($ticket, 'get_order')) ? $ticket->get_order() : null;
		if (is_object($order)) {
			if (method_exists($order, 'get_formatted_billing_full_name')) {
				$candidates[] = (string) $order->get_formatted_billing_full_name();
			}
			$first = method_exists($order, 'get_billing_first_name') ? (string) $order->get_billing_first_name() : '';
			$last  = method_exists($order, 'get_billing_last_name') ? (string) $order->get_billing_last_name() : '';
			$candidates[] = trim($first . ' ' . $last);
		}
		$uid = (is_object($ticket) && method_exists($ticket, 'get_user_id')) ? (int) $ticket->get_user_id() : 0;
		if ($uid > 0) {
			$candidates[] = trim((string) get_user_meta($uid, 'first_name', true) . ' ' . (string) get_user_meta($uid, 'last_name', true));
			$u = get_userdata($uid);
			if ($u) {
				$candidates[] = trim((string) $u->first_name . ' ' . (string) $u->last_name);
				$candidates[] = (string) $u->display_name;
			}
		}
		foreach ($candidates as $name) {
			$name = trim(preg_replace('/\s+/', ' ', (string) $name));
			if ($name === '' || strpos($name, '@') !== false) {
				continue;
			}
			return $name;
		}
		return '';
	}

	/**
	 * @param int   $product_id
	 * @param int   $ticket_id     0 = no persistir
	 * @param string $ticket_number número Lottery (J123 / PG45)
	 * @param int[] $exclude_ids   adjuntos ya usados en el mismo pedido (packs)
	 */
	public static function url_for_product(int $product_id, int $ticket_id = 0, string $ticket_number = '', array $exclude_ids = array()): string {
		$product = $product_id ? wc_get_product($product_id) : null;
		$cover   = ($product && $product->get_image_id()) ? (string) wp_get_attachment_url($product->get_image_id()) : '';
		$acf     = self::acf_url($product_id);
		$prefix  = self::pool_prefix($product_id);

		if ($ticket_id > 0) {
			$saved = (string) get_post_meta($ticket_id, self::META_ASSIGN, true);
			if (self::is_usable_pool_url($saved, $cover)) {
				$aid = (int) get_post_meta($ticket_id, self::META_ASSIGN_ID, true);
				if ($aid <= 0) {
					$aid = self::attachment_id_from_url($saved);
					if ($aid > 0) {
						update_post_meta($ticket_id, self::META_ASSIGN_ID, $aid);
					}
				}
				return $saved;
			}
		}

		if ($prefix === '') {
			$url = $acf !== '' ? $acf : $cover;
			if ($ticket_id > 0 && $url !== '') {
				update_post_meta($ticket_id, self::META_ASSIGN, $url);
			}
			return $url;
		}

		$att_id = self::pick_attachment_id($prefix, $ticket_number, $exclude_ids);
		$url    = $att_id ? (string) wp_get_attachment_url($att_id) : '';

		if (!self::is_usable_pool_url($url, $cover)) {
			$url = $acf !== '' ? $acf : $cover;
		}

		if ($ticket_id > 0 && $url !== '') {
			update_post_meta($ticket_id, self::META_ASSIGN, $url);
			if ($att_id > 0) {
				update_post_meta($ticket_id, self::META_ASSIGN_ID, $att_id);
			}
		}

		return $url;
	}

	public static function pool_prefix(int $product_id): string {
		$named = '';
		if (function_exists('get_field')) {
			$named = get_field('nombre_ilustraciones', $product_id);
		}
		if (!is_string($named) || trim($named) === '') {
			$named = (string) get_post_meta($product_id, 'nombre_ilustraciones', true);
		}
		$named = trim((string) $named);
		if ($named !== '') {
			return $named;
		}

		$lty = trim((string) get_post_meta($product_id, '_lty_ticket_prefix', true));
		if ($lty !== '') {
			$guess = strtolower($lty) . '-';
			if (self::prefix_library_size($guess) >= 50) {
				return $guess;
			}
		}

		if (has_term('propiedades', 'product_cat', $product_id)) {
			return '';
		}

		return has_term('Autos', 'product_cat', $product_id) ? 'SS-' : 'MM-';
	}

	/**
	 * Productos lottery. Prefix vacío = propiedades sin librería de ilustraciones (usan la de ACF).
	 *
	 * @return array<int,string> product_id => prefix
	 */
	public static function pool_products(): array {
		global $wpdb;

		$ids = $wpdb->get_col(
			"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_lty_maximum_tickets'"
		);
		$out = array();
		foreach ($ids as $id) {
			$id = (int) $id;
			if ($id <= 0) {
				continue;
			}
			$out[ $id ] = self::pool_prefix($id);
		}

		return $out;
	}

	private static function prefix_library_size(string $prefix): int {
		global $wpdb;
		$prefix = trim($prefix);
		if ($prefix === '') {
			return 0;
		}
		return (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_mime_type LIKE %s AND post_title LIKE %s",
			'image%',
			$wpdb->esc_like($prefix) . '%'
		));
	}

	/**
	 * Marca hasta POOL_SIZE ilustraciones por sorteo (sin tocar las ya vendidas).
	 * Idempotente.
	 *
	 * @return array<int,array{prefix:string,marked:int,total_pool:int}>
	 */
	public static function seed_active_pools(): array {
		$report = array();
		foreach (self::pool_products() as $product_id => $prefix) {
			if ($prefix === '') {
				$report[ $product_id ] = array(
					'prefix'     => '',
					'marked'     => 0,
					'total_pool' => 0,
					'keep_sold'  => 0,
					'skip'       => 'sin librería (ACF única)',
				);
				continue;
			}
			$keep    = self::referenced_attachment_ids($prefix);
			$current = self::pool_member_ids($prefix);
			$need    = self::POOL_SIZE - count($current);
			$added   = 0;

			if ($need > 0) {
				$candidates = self::candidate_ids_for_pool($prefix, $keep, $current);
				shuffle($candidates);
				foreach (array_slice($candidates, 0, $need) as $att_id) {
					update_post_meta((int) $att_id, self::META_POOL, '1');
					$added++;
				}
			}

			$report[ $product_id ] = array(
				'prefix'     => $prefix,
				'marked'     => $added,
				'total_pool' => count(self::pool_member_ids($prefix)),
				'keep_sold'  => count($keep),
			);
		}

		self::$protected_cache = null;

		return $report;
	}

	/**
	 * Borra adjuntos del prefix que no están vendidos ni en el pool de 100.
	 *
	 * @return array{deleted:int,skipped:int,errors:int,ids:int[]}
	 */
	public static function purge_unused_illustrations(int $limit = 200): array {
		$deleted = 0;
		$skipped = 0;
		$errors  = 0;
		$ids     = array();

		$keep_all = self::protected_attachment_ids();
		$prefixes = array_unique(array_values(self::pool_products()));

		foreach ($prefixes as $prefix) {
			$prefix = trim((string) $prefix);
			if ($prefix === '') {
				continue;
			}
			$batch = self::purge_candidate_ids($prefix, $keep_all, $limit - $deleted);
			foreach ($batch as $att_id) {
				if ($deleted >= $limit) {
					break 2;
				}
				if (isset($keep_all[ $att_id ])) {
					$skipped++;
					continue;
				}
				$result = wp_delete_attachment($att_id, true);
				if ($result) {
					$deleted++;
					$ids[] = $att_id;
				} else {
					$errors++;
				}
			}
		}

		return array(
			'deleted' => $deleted,
			'skipped' => $skipped,
			'errors'  => $errors,
			'ids'     => $ids,
		);
	}

	public static function purge_remaining_count(): int {
		$keep     = self::protected_attachment_ids();
		$prefixes = array_unique(array_values(self::pool_products()));
		$n        = 0;
		foreach ($prefixes as $prefix) {
			$prefix = trim((string) $prefix);
			if ($prefix === '') {
				continue;
			}
			$n += count(self::purge_candidate_ids($prefix, $keep, 50000));
		}
		return $n;
	}

	private static function acf_url(int $product_id): string {
		if (!function_exists('get_field')) {
			return '';
		}
		$v = get_field('ilustracion_ticket', $product_id);
		if (is_string($v) && $v !== '') {
			return $v;
		}
		if (is_array($v) && !empty($v['url'])) {
			return (string) $v['url'];
		}
		return '';
	}

	private static function url_belongs_to_prefix(string $url, string $prefix): bool {
		$prefix = trim($prefix);
		if ($prefix === '') {
			return true;
		}

		$id = self::attachment_id_from_url($url);
		if ($id > 0) {
			$title = (string) get_the_title($id);
			return strncasecmp($title, $prefix, strlen($prefix)) === 0;
		}

		$base = strtolower((string) basename((string) parse_url($url, PHP_URL_PATH)));
		$p    = strtolower(rtrim($prefix, '-_'));
		return $p !== '' && strpos($base, $p) === 0;
	}

	private static function is_usable_pool_url(string $url, string $cover): bool {
		$url = trim($url);
		if ($url === '') {
			return false;
		}
		if ($cover !== '' && untrailingslashit($url) === untrailingslashit($cover)) {
			return false;
		}
		return true;
	}

	/**
	 * Adjuntos ya usados por otros DigiTickets del mismo pedido y producto (pack 20×8 → 20 distintas).
	 *
	 * @return int[]
	 */
	private static function sibling_assigned_ids($ticket): array {
		if (!is_object($ticket) || !method_exists($ticket, 'get_order')) {
			return array();
		}
		$order = $ticket->get_order();
		if (!is_object($order) || !method_exists($order, 'get_id')) {
			return array();
		}

		$product_id = (int) $ticket->get_product_id();
		$self       = method_exists($ticket, 'get_id') ? (int) $ticket->get_id() : 0;
		$ticket_ids = array();
		if (function_exists('lty_get_lottery_ticket_ids_by_order_id')) {
			$ticket_ids = lty_get_lottery_ticket_ids_by_order_id($order->get_id(), $product_id);
		}
		if (!is_array($ticket_ids)) {
			return array();
		}

		$exclude = array();
		foreach ($ticket_ids as $tid) {
			$tid = (int) $tid;
			if ($tid <= 0 || $tid === $self) {
				continue;
			}
			$aid = (int) get_post_meta($tid, self::META_ASSIGN_ID, true);
			if ($aid <= 0) {
				$url = (string) get_post_meta($tid, self::META_ASSIGN, true);
				$aid = $url !== '' ? self::attachment_id_from_url($url) : 0;
			}
			if ($aid > 0) {
				$exclude[] = $aid;
			}
		}

		return array_values(array_unique($exclude));
	}

	/**
	 * 1) ya ligada a este número  2) del pool de 100, distinta a las del pack  3) cualquier del pool.
	 * No escribe _lty_ticket.
	 *
	 * @param int[] $exclude_ids
	 */
	private static function pick_attachment_id(string $prefix, string $ticket_number, array $exclude_ids = array()): int {
		global $wpdb;

		$prefix = trim($prefix);
		if ($prefix === '') {
			return 0;
		}

		$like    = $wpdb->esc_like($prefix) . '%';
		$exclude = array_values(array_filter(array_map('intval', $exclude_ids)));

		if ($ticket_number !== '') {
			$id = (int) $wpdb->get_var($wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_lty_ticket' AND m.meta_value = %s
				WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type LIKE %s
				AND p.post_title LIKE %s
				LIMIT 1",
				$ticket_number,
				'image%',
				$like
			));
			if ($id > 0) {
				return $id;
			}
		}

		$id = self::pick_from_pool($prefix, $exclude);
		if ($id > 0) {
			return $id;
		}

		$id = self::pick_from_pool($prefix, array());
		if ($id > 0) {
			return $id;
		}

		$not_in = '';
		if ($exclude) {
			$not_in = 'AND p.ID NOT IN (' . implode(',', $exclude) . ')';
		}

		$id = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type LIKE %s
			AND p.post_title LIKE %s {$not_in}
			ORDER BY RAND()
			LIMIT 1",
			'image%',
			$like
		));
		if ($id > 0) {
			return $id;
		}

		return (int) $wpdb->get_var($wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type LIKE %s
			AND p.post_title LIKE %s
			ORDER BY RAND()
			LIMIT 1",
			'image%',
			$like
		));
	}

	/**
	 * @param int[] $exclude_ids
	 */
	private static function pick_from_pool(string $prefix, array $exclude_ids): int {
		global $wpdb;

		$like    = $wpdb->esc_like($prefix) . '%';
		$not_in  = '';
		$exclude = array_values(array_filter(array_map('intval', $exclude_ids)));
		if ($exclude) {
			$not_in = 'AND p.ID NOT IN (' . implode(',', $exclude) . ')';
		}

		$sql = "SELECT p.ID FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pool ON pool.post_id = p.ID AND pool.meta_key = %s AND pool.meta_value = '1'
			WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type LIKE %s
			AND p.post_title LIKE %s {$not_in}
			ORDER BY RAND()
			LIMIT 1";

		return (int) $wpdb->get_var($wpdb->prepare($sql, self::META_POOL, 'image%', $like));
	}

	/**
	 * @return int[]
	 */
	private static function pool_member_ids(string $prefix): array {
		global $wpdb;
		$prefix = trim($prefix);
		if ($prefix === '') {
			return array();
		}
		$like = $wpdb->esc_like($prefix) . '%';
		$ids  = $wpdb->get_col($wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s AND m.meta_value = '1'
			WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_title LIKE %s",
			self::META_POOL,
			$like
		));
		return array_map('intval', is_array($ids) ? $ids : array());
	}

	/**
	 * Adjuntos vendidos / referenciados que NUNCA se borran.
	 *
	 * @return array<int,true>
	 */
	private static function referenced_attachment_ids(string $prefix): array {
		global $wpdb;
		$prefix = trim($prefix);
		if ($prefix === '') {
			return array();
		}
		$like = $wpdb->esc_like($prefix) . '%';
		$set  = array();

		$burned = $wpdb->get_col($wpdb->prepare(
			"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_lty_ticket'
			WHERE p.post_type = 'attachment' AND p.post_title LIKE %s",
			$like
		));
		foreach ((array) $burned as $id) {
			$set[ (int) $id ] = true;
		}

		foreach (self::assigned_image_attachment_ids() as $id) {
			$title = (string) get_the_title($id);
			if ($id > 0 && strncasecmp($title, $prefix, strlen($prefix)) === 0) {
				$set[ $id ] = true;
			}
		}

		return $set;
	}

	/**
	 * @return array<int,true>
	 */
	private static function protected_attachment_ids(): array {
		if (self::$protected_cache !== null) {
			return self::$protected_cache;
		}

		$keep = array();
		foreach (array_unique(array_values(self::pool_products())) as $prefix) {
			$prefix = trim((string) $prefix);
			if ($prefix === '') {
				continue;
			}
			foreach (self::referenced_attachment_ids($prefix) as $id => $_) {
				$keep[ (int) $id ] = true;
			}
			foreach (self::pool_member_ids($prefix) as $id) {
				$keep[ (int) $id ] = true;
			}
		}

		foreach (array_keys(self::pool_products()) as $product_id) {
			$p = wc_get_product($product_id);
			if (!$p) {
				continue;
			}
			$img = (int) $p->get_image_id();
			if ($img > 0) {
				$keep[ $img ] = true;
			}
			$gallery = $p->get_gallery_image_ids();
			if (is_array($gallery)) {
				foreach ($gallery as $gid) {
					$keep[ (int) $gid ] = true;
				}
			}
			$acf_id = self::attachment_id_from_url(self::acf_url((int) $product_id));
			if ($acf_id > 0) {
				$keep[ $acf_id ] = true;
			}
		}

		self::$protected_cache = $keep;

		return $keep;
	}

	/**
	 * @return int[]
	 */
	private static function assigned_image_attachment_ids(): array {
		global $wpdb;

		$urls = $wpdb->get_col(
			"SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_lty_assigned_image' AND meta_value <> ''"
		);
		$ids = $wpdb->get_col(
			"SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_ss_ilustracion_id' AND meta_value REGEXP '^[0-9]+$'"
		);

		$out = array();
		foreach ((array) $ids as $id) {
			$id = (int) $id;
			if ($id > 0) {
				$out[ $id ] = $id;
			}
		}
		foreach ((array) $urls as $url) {
			$aid = self::attachment_id_from_url((string) $url);
			if ($aid > 0) {
				$out[ $aid ] = $aid;
			}
		}

		return array_values($out);
	}

	/**
	 * Candidatos al pool de 100: preferir no vendidas; si no hay (Peugeot), reciclar del mismo prefix.
	 *
	 * @param array<int,true> $keep
	 * @param int[]           $already_pool
	 * @return int[]
	 */
	private static function candidate_ids_for_pool(string $prefix, array $keep, array $already_pool): array {
		global $wpdb;
		$like    = $wpdb->esc_like($prefix) . '%';
		$already = array_fill_keys(array_map('intval', $already_pool), true);

		$free = $wpdb->get_col($wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_lty_ticket'
			WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type LIKE %s
			AND p.post_title LIKE %s AND m.meta_id IS NULL",
			'image%',
			$like
		));

		$out = array();
		foreach ((array) $free as $id) {
			$id = (int) $id;
			if ($id <= 0 || isset($keep[ $id ]) || isset($already[ $id ])) {
				continue;
			}
			$out[] = $id;
		}

		if (count($out) >= self::POOL_SIZE - count($already_pool)) {
			return $out;
		}

		$any = $wpdb->get_col($wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type LIKE %s
			AND p.post_title LIKE %s",
			'image%',
			$like
		));
		foreach ((array) $any as $id) {
			$id = (int) $id;
			if ($id <= 0 || isset($already[ $id ])) {
				continue;
			}
			$out[] = $id;
		}

		return array_values(array_unique($out));
	}

	/**
	 * @param array<int,true> $keep
	 * @return int[]
	 */
	private static function purge_candidate_ids(string $prefix, array $keep, int $limit): array {
		global $wpdb;
		$like  = $wpdb->esc_like($prefix) . '%';
		$limit = max(1, (int) $limit);
		$not   = '';
		$keep_ids = array_values(array_filter(array_map('intval', array_keys($keep))));
		if ($keep_ids) {
			$not = 'AND p.ID NOT IN (' . implode(',', $keep_ids) . ')';
		}

		$ids = $wpdb->get_col($wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type LIKE %s
			AND p.post_title LIKE %s {$not}
			ORDER BY p.ID ASC
			LIMIT %d",
			'image%',
			$like,
			$limit
		));

		return array_map('intval', is_array($ids) ? $ids : array());
	}

	private static function attachment_id_from_url(string $url): int {
		$url = trim($url);
		if ($url === '') {
			return 0;
		}
		if (function_exists('attachment_url_to_postid')) {
			$id = (int) attachment_url_to_postid($url);
			if ($id > 0) {
				return $id;
			}
		}

		global $wpdb;
		$path = (string) parse_url($url, PHP_URL_PATH);
		$file = preg_replace('#^.*/uploads/#', '', $path);
		$file = preg_replace('/-\d+x\d+(?=\.[a-zA-Z]+$)/', '', (string) $file);
		if ($file === '') {
			return 0;
		}

		return (int) $wpdb->get_var($wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
			$file
		));
	}
}

SorteoSeguro_Ticket_Ilustracion::init();
