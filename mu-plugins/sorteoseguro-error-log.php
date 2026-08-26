<?php
/**
 * Plugin Name: Sorteo Seguro – Error log
 * Description: Captura errores PHP/fatales en wp-content/ss-error.log (sin mostrar en pantalla).
 * Author: Sorteo Seguro
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Error_Log {

	const LOG_FILE = 'ss-error.log';

	public static function init(): void {
		$path = self::path();
		if (!is_file($path)) {
			@touch($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch
		}
		@ini_set('log_errors', '1');
		@ini_set('display_errors', '0');
		@ini_set('error_log', $path);
		set_error_handler([__CLASS__, 'handle_error']);
		register_shutdown_function([__CLASS__, 'shutdown']);
	}

	public static function path(): string {
		return WP_CONTENT_DIR . '/' . self::LOG_FILE;
	}

	/** @param array{type:int,message:string,file:string,line:int} $error */
	private static function write(string $level, array $error): void {
		$line = sprintf(
			"[%s UTC] %s | %s | %s:%d\n",
			gmdate('Y-m-d H:i:s'),
			$level,
			$error['message'] ?? '',
			$error['file'] ?? '',
			(int) ($error['line'] ?? 0)
		);
		@file_put_contents(self::path(), $line, FILE_APPEND | LOCK_EX); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	public static function handle_error(int $errno, string $errstr, string $errfile, int $errline): bool {
		if (!(error_reporting() & $errno)) {
			return false;
		}
		self::write('PHP-' . $errno, [
			'type'    => $errno,
			'message' => $errstr,
			'file'    => $errfile,
			'line'    => $errline,
		]);
		return false;
	}

	public static function shutdown(): void {
		$error = error_get_last();
		if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
			return;
		}
		self::write('FATAL', $error);
	}
}

SorteoSeguro_Error_Log::init();
