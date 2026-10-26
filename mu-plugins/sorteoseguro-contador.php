<?php
/**
 * Plugin Name: Sorteo Seguro – Contador bono (hardcoded)
 * Description: Elemento de cuenta regresiva 24h con reseteo diario (nunca en 0). Independiente del Promo Engine.
 * Author: Sorteo Seguro
 * Version: 1.0.0
 *
 * Rollback: borrar este archivo y la carpeta sorteoseguro-contador/.
 */
if (!defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/sorteoseguro-contador/bono-contador.php';
