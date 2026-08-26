<?php
/**
 * Iconos SVG de la ficha (viewBox 0 0 24 24).
 */
if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('ss_pdp_icon')) {
	function ss_pdp_icon(string $name): string {
		$icons = [
			'calendar' => '<path d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 16H5V10h14v10z"/>',
			'status'   => '<path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm-1 5h2v6h-2V7zm0 8h2v2h-2v-2z"/>',
			'owners'   => '<path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-4 0-8 2-8 4v2h16v-2c0-2-4-4-8-4z"/>',
			'clock'    => '<path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm1 11H7v-2h4V7h2v6z"/>',
			'pin'      => '<path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 14.5 9 2.5 2.5 0 0 1 12 11.5z"/>',
			'bed'      => '<path d="M4 11V7a2 2 0 0 1 2-2h5a2 2 0 0 1 2 2v4h7V8h2v12h-2v-3H4v3H2V11h2zm2-4v4h5V7H6z"/>',
			'home'     => '<path d="M12 3l9 8h-3v9h-5v-6H11v6H6v-9H3l9-8z"/>',
			'terrain'  => '<path d="M3 19h18l-6.5-8.5-3.5 4.6-2.2-2.8L3 19zm2.8-2l3-3.9 2.2 2.8 3.4-4.5 4.2 5.6H5.8z"/>',
			'bath'     => '<path d="M7 4a2 2 0 1 1 0 4 2 2 0 0 1 0-4zM4 10h16v2H4v-2zm1 3h14v4a3 3 0 0 1-3 3v1h-2v-1H9v1H7v-1a3 3 0 0 1-3-3v-4h1z"/>',
			'moto'     => '<path d="M5 16l-1 5h16l-1-5H5zm1.2-2h11.6l.7-3H5.5l.7 3zM7 4h10l1 5H6l1-5z"/>',
			'car'      => '<path d="M5 11l1.2-4.5A2 2 0 0 1 8.1 5h7.8a2 2 0 0 1 1.9 1.5L19 11h1a1 1 0 0 1 1 1v3h-2a2.5 2.5 0 0 1-5 0H10a2.5 2.5 0 0 1-5 0H3v-3a1 1 0 0 1 1-1h1zm2.2-4l-.8 3h11.2l-.8-3H7.2zM7.5 16.5A1.5 1.5 0 1 0 6 15a1.5 1.5 0 0 0 1.5 1.5zm9 0A1.5 1.5 0 1 0 15 15a1.5 1.5 0 0 0 1.5 1.5z"/>',
			'house'    => '<path d="M12 3l9 8h-2v9h-6v-6H11v6H5v-9H3l9-8z"/>',
			'land'     => '<path d="M3 20h18v-2H3v2zm2-4h4l2-3 3 4 3-5 4 4v2H5v-2z"/>',
			'cash'     => '<path d="M20 7H4v2h16V7zm-1 4H5l-1 9h16l-1-9zM9 3h6v2H9V3z"/>',
			'hybrid'   => '<path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>',
			'abs'      => '<path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>',
			'shield'   => '<path d="M12 2l7 4v6c0 5-3.5 9.4-7 10-3.5-.6-7-5-7-10V6l7-4zm0 4.2L7 8.1v3.9c0 3.5 2.2 6.7 5 7.5 2.8-.8 5-4 5-7.5V8.1l-5-1.9z"/>',
			'star'     => '<path d="M12 2l2.4 4.9L20 8l-4 3.9.9 5.4L12 14.8 7.1 17.3 8 11.9 4 8l5.6-1.1L12 2z"/>',
			'list'     => '<path d="M4 6h16v2H4V6zm0 5h16v2H4v-2zm0 5h10v2H4v-2z"/>',
			'edit'     => '<path d="M3 17.25V21h3.75L17.8 9.94l-3.75-3.75L3 17.25zM20.7 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>',
			'doc'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm1 7V3.5L18.5 9H15zM8 13h8v2H8v-2zm0 4h8v2H8v-2zm0-8h5v2H8V9z"/>',
			'engine'   => '<path d="M7 4h10v2H7V4zm-2 4h14l-1.2 8H6.2L5 8zm3 10a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm8 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/>',
			'figure'   => '<path d="M12 2C8 2 4.5 4.5 3 8h18c-1.5-3.5-5-6-9-6zm-7 8v2h2v8h2v-5h2v5h2v-8h2v-2H5z"/>',
			'leaf'     => '<path d="M17 8C8 10 5.9 16.17 3.82 21.34l1.89.66.95-2.3c.09.06.2.12.34.12C19 20 22 3 22 3c-1 2-8 2.25-13 3.25S2 11.5 2 13.5s1.75 3.75 1.75 3.75C7 8 17 8 17 8z"/>',
		];
		$path = $icons[$name] ?? $icons['star'];
		return '<svg viewBox="0 0 24 24" aria-hidden="true">' . $path . '</svg>';
	}
}
