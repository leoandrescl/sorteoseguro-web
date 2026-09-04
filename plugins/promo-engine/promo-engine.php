<?php
/**
 * Plugin Name: Promo Engine (Stable 1.3.0) — Non-Stacking Logic
 * Description: Sistema de promociones con lógica de inventario virtual (no acumulable por ítem) y prioridad por volumen de compra (BxPy).
 * Version: 1.3.0-stable-p11
 * p10: bonos fixed/% acumulables aplican sobre líneas DigiPack (ya no las bloquea el inventario BxPy).
 * p11: bonos fixed/% = fee único de carrito sin nombre de producto; min_total = total a pagar tras packs.
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('Promo_Engine_Stable')) :

    final class Promo_Engine_Stable {

        const CPT = 'promo_campaign';

        const META = [
            'stackable'         => '_promo_stackable', // Nuevo: Control de acumulación
            'type'              => '_promo_type',
            'amount'            => '_promo_amount',
            'min_total'         => '_promo_min_total',
            'start'             => '_promo_start',
            'end'               => '_promo_end',
            'limit_total'       => '_promo_limit_total',
            'limit_per_user'    => '_promo_limit_per_user',
            'segment_type'      => '_promo_segment_type',
            'segment_data'      => '_promo_segment_data',
            'reg_start'         => '_promo_reg_start', 
            'reg_end'           => '_promo_reg_end',   
            'reg_days_limit'    => '_promo_reg_days_limit', // Agregado: Lógica de 3 días
            'reg_before_date'   => '_promo_reg_before_date', // Agregado: Registro anterior a fecha
            'email_subject'     => '_promo_email_subject',
            'email_body'        => '_promo_email_body',
            'redemptions'       => '_promo_redemptions',
            'target_categories' => '_promo_target_categories',
            'target_products'   => '_promo_target_products',
            'bxpy_buy'          => '_promo_bxpy_buy',
            'bxpy_pay'          => '_promo_bxpy_pay',
            'show_on_product'   => '_promo_show_on_product',
            'target_lotteries'  => '_promo_target_lotteries',
            'product_badge'     => '_promo_product_badge',
            'product_sort'      => '_promo_product_sort',
        ];

        /** Packs activos en ficha: se les asignan todos los sorteos actuales una vez. */
        const PACK_LOTTERY_SEED_IDS = [74487, 45526, 44653, 44640];
        const PACK_LOTTERY_SEED_FLAG = 'promo_engine_pack_lotteries_seeded_p9';

        const SESSION_APPLIED = 'promo_engine_applied_campaign_ids';
        const SESSION_SOURCE  = 'promo_engine_user_source'; // Tracking de origen persistente

        public function __construct() {
            add_action('init',                            [$this, 'register_cpt']);
            add_action('init',                            [$this, 'maybe_seed_pack_lotteries'], 30);
            add_action('init',                            [$this, 'capture_incoming_source']); // Captura fuente de URL
            add_action('add_meta_boxes',                  [$this, 'add_metaboxes']);
            add_action('save_post',                       [$this, 'save_campaign_meta'], 10, 2);
            add_action('admin_enqueue_scripts',           [$this, 'enqueue_admin_assets']);
            add_action('add_meta_boxes',                  [$this, 'add_email_box']);
            add_action('admin_post_promo_send_email',     [$this, 'send_promo_email']);
            add_action('before_delete_post',              [$this, 'cleanup_on_delete']);

            add_filter('manage_' . self::CPT . '_posts_columns',       [$this, 'admin_columns']);
            add_action('manage_' . self::CPT . '_posts_custom_column', [$this, 'render_admin_column'], 10, 2);

            add_action('user_register',                    [$this, 'save_source_on_registration'], 10, 1);

            // Inyectar campo oculto en formularios de registro para persistir la fuente
            add_action('register_form',                   [$this, 'inject_source_in_registration_form']);
            add_action('woocommerce_register_form',       [$this, 'inject_source_in_registration_form']);
            add_action('woocommerce_checkout_before_terms_and_conditions', [$this, 'inject_source_in_registration_form']);

            add_action('woocommerce_cart_calculate_fees', [$this, 'apply_promotions_entrypoint'], 20, 1);
            add_action('woocommerce_checkout_order_processed', [$this, 'mark_redemption_on_order'], 10, 3);

            // Cupones % de WC se calculan sobre line_subtotal; la promo va como fee.
            // Escalamos el cupón para que equivalga a aplicarlo DESPUÉS de la promo.
            add_filter('woocommerce_coupon_get_discount_amount', [$this, 'scale_coupon_discount_after_promo'], 100, 5);
        }

        /**
         * Inyecta un campo oculto con la fuente capturada en los formularios de registro.
         * Esto es el mecanismo más confiable: la fuente viaja en $_POST junto al formulario.
         */
        public function inject_source_in_registration_form() {
            $source = $this->get_captured_source();
            if (!empty($source)) {
                echo '<input type="hidden" name="pe_source" value="' . esc_attr($source) . '" />';
            }
        }

        /** Obtiene la fuente capturada desde sesión WC o cookie, en ese orden. */
        private function get_captured_source() {
            $source = '';
            if (WC()->session) {
                $source = (string) WC()->session->get(self::SESSION_SOURCE);
            }
            if (empty($source) && isset($_COOKIE['pe_captured_source'])) {
                $source = sanitize_text_field($_COOKIE['pe_captured_source']);
            }
            return $source;
        }

        /**
         * Cuando un usuario se registra, persiste la fuente capturada en user meta.
         * Prioridad de lectura: 1) $_POST (campo oculto del form), 2) cookie, 3) sesión WC.
         */
        public function save_source_on_registration($user_id) {
            $source = '';

            // 1. Más confiable: el campo oculto inyectado en el formulario
            if (!empty($_POST['pe_source'])) {
                $source = sanitize_text_field(wp_unslash($_POST['pe_source']));
            }

            // 2. Fallback: cookie (disponible en todas las requests)
            if (empty($source) && isset($_COOKIE['pe_captured_source'])) {
                $source = sanitize_text_field($_COOKIE['pe_captured_source']);
            }

            // 3. Fallback: sesión WC (puede no estar disponible en contextos no-WC)
            if (empty($source) && WC()->session) {
                $source = (string) WC()->session->get(self::SESSION_SOURCE);
            }

            if (!empty($source)) {
                update_user_meta($user_id, 'pe_registration_source', $source);
            }
        }

        /** Captura ?source= de la URL y lo guarda en la sesión de WooCommerce y en una Cookie para persistencia */
        public function capture_incoming_source() {
            $source = '';
            if (isset($_GET['source']) && !empty($_GET['source'])) {
                $source = sanitize_text_field($_GET['source']);
                // Guardar en Cookie por 30 días para que si el usuario vuelve sin la URL, el sistema sepa su origen
                setcookie('pe_captured_source', $source, time() + (DAY_IN_SECONDS * 30), COOKIEPATH, COOKIE_DOMAIN);
            }

            if (WC()->session) {
                if (!empty($source)) {
                    WC()->session->set(self::SESSION_SOURCE, $source);
                } elseif (isset($_COOKIE['pe_captured_source'])) {
                    // Si no está en la URL pero sí en la cookie, restaurar a la sesión
                    WC()->session->set(self::SESSION_SOURCE, sanitize_text_field($_COOKIE['pe_captured_source']));
                }
            }
        }

        private function log($msg, $context = []) {
            if (!defined('PROMO_ENGINE_DEBUG') || !PROMO_ENGINE_DEBUG) return;
            if (!function_exists('wc_get_logger')) return;
            $logger = wc_get_logger();
            $entry  = is_string($msg) ? $msg : wp_json_encode($msg);
            if (!empty($context)) $entry .= ' | ' . wp_json_encode($context);
            $logger->info($entry, ['source' => 'promo-engine-stable']);
        }

        /** Parse helper en TZ de WordPress. */
        private static function parse_wp_datetime($str, $is_end = false){
            $str = trim((string)$str);
            if ($str === '') return 0;
            $norm = str_replace('/', '-', $str);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $norm)) {
                $norm .= ($is_end ? ' 23:59:59' : ' 00:00:00');
            }
            try {
                if (function_exists('wp_timezone')) {
                    $tz = wp_timezone();
                } else {
                    $tz = new DateTimeZone(get_option('timezone_string') ?: 'UTC');
                }
                $dt = new DateTimeImmutable($norm, $tz);
                $ts = $dt->getTimestamp();
                if ($is_end) {
                    if (preg_match('/\s00:00(:00)?$/', $norm)) {
                        $ts = $dt->setTime(23, 59, 59)->getTimestamp();
                    } else {
                        if (strpos($norm, ':') !== false && substr_count($norm, ':') === 1) {
                            $ts += 59;
                        }
                    }
                }
                return $ts;
            } catch (Exception $e) {
                $ts = strtotime($norm);
                if ($ts && $is_end) {
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $norm)) $ts += 86399;
                }
                return $ts ? $ts : 0;
            }
        }

        /* ------------------------------ Admin ------------------------------- */

        public function register_cpt() {
            register_post_type(self::CPT, [
                'label'         => 'Promociones',
                'public'        => true,
                'show_ui'       => true,
                'menu_icon'     => 'dashicons-megaphone',
                'supports'      => ['title', 'editor'],
                'capability_type' => 'post',
                'map_meta_cap'  => true,
                'show_in_rest'  => true,
            ]);
        }

        public function enqueue_admin_assets($hook) {
            global $post_type;
            if (('post.php' === $hook || 'post-new.php' === $hook) && self::CPT === $post_type) {
                wp_enqueue_script('wc-enhanced-select');
                wp_enqueue_style('wc-enhanced-select');
                wp_enqueue_script('jquery');
            }
        }

        public function add_metaboxes() {
            add_meta_box('promo_engine_settings', 'Configuración de la Promoción', [$this, 'render_settings_metabox'], self::CPT, 'normal', 'high');
        }

        public function render_settings_metabox($post) {
            wp_nonce_field('promo_engine_save', 'promo_engine_nonce');

            $get = function($key, $default = '') use ($post) {
                $v = get_post_meta($post->ID, self::META[$key], true);
                return $v === '' ? $default : $v;
            };

            $type              = $get('type', 'fixed');
            $stackable         = $get('stackable', 'yes');
            $amount            = $get('amount', '');
            $bxpy_buy          = (int) $get('bxpy_buy', '');
            $bxpy_pay          = (int) $get('bxpy_pay', '');
            $min_total         = $get('min_total', '');
            $start             = $get('start', '');
            $end               = $get('end', '');
            $limit_total       = $get('limit_total', '');
            $limit_per_user    = $get('limit_per_user', '1');
            $segment_type      = $get('segment_type', 'all');
            $segment_data      = $get('segment_data', '');
            $reg_days_limit    = $get('reg_days_limit', ''); // Agregado
            $reg_before_date   = $get('reg_before_date', ''); // Agregado
            $email_subject     = $get('email_subject', '');
            $email_body        = $get('email_body', '');
            $redemptions       = (int) get_post_meta($post->ID, self::META['redemptions'], true);
            $target_categories = $get('target_categories', []);
            $target_products   = $get('target_products', []);
            $show_on_product   = $get('show_on_product', 'no');
            $product_badge     = $get('product_badge', '');
            $product_sort      = $get('product_sort', '10');
            ?>
                <div class="promo-field">
                    <label>Lógica de Acumulación</label>
                    <select name="promo_stackable">
                        <option value="yes" <?php selected($stackable, 'yes'); ?>>Acumulable con todo</option>
                        <option value="no_coupons" <?php selected($stackable, 'no_coupons'); ?>>Acumulable con promos, pero NO con cupones</option>
                        <option value="no" <?php selected($stackable, 'no'); ?>>Exclusiva (No acumulable con nada)</option>
                    </select>
                    <p class="promo-hint">"Acumulable con todo": el cupón de WooCommerce se aplica sobre el precio ya con promo (ej. 20x8 = $80.000 → cupón 30% = $24.000). "No con cupones": la promo se apaga si hay un cupón en el carrito.</p>
                </div>
            <?php
            ?>
            <style>
                .promo-grid { display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom: 20px;}
                .promo-grid .full { grid-column: 1 / -1; }
                .promo-field label { font-weight: 600; display:block; margin-bottom:6px; }
                .promo-hint { color:#666; font-size:12px; margin-top: 4px; }
                .promo-separator { grid-column: 1 / -1; border-top: 1px solid #ddd; padding-top: 10px; margin-top: 10px; }
                .promo-box-special { background: #f0f6fb; padding: 12px; border-radius: 4px; border: 1px solid #c3d4e3; grid-column: 1 / -1; margin-top: 10px; }
                .promo-box-product { background: #f7faf4; padding: 12px; border-radius: 4px; border: 1px solid #c5d9b8; grid-column: 1 / -1; margin-top: 10px; }
            </style>

            <div class="promo-grid">
                <div class="promo-box-product">
                    <label style="color:#2f6b1a; display:block; margin-bottom:8px;">FICHA DE PRODUCTO (PACKS)</label>
                    <label style="font-weight:600; display:flex; align-items:center; gap:8px; margin-bottom:10px;">
                        <input type="checkbox" name="promo_show_on_product" value="yes" id="promo_show_on_product" <?php checked($show_on_product, 'yes'); ?> />
                        Mostrar en ficha de producto (packs)
                    </label>
                    <p class="promo-hint" style="margin-top:0;">Marca la campaña como pack y elige en qué sorteos se ve. Si no marcas ningún sorteo, no aparece en ninguna ficha.</p>
                    <?php
                    $lotteries = self::get_lottery_products_for_admin();
                    $raw_lotteries = get_post_meta($post->ID, self::META['target_lotteries'], true);
                    $lottery_configured = is_array($raw_lotteries);
                    $selected_lottery_ids = $lottery_configured ? array_map('absint', (array) $raw_lotteries) : null;
                    ?>
                    <input type="hidden" name="promo_target_lotteries_present" value="1" />
                    <div class="promo-lotteries" style="margin:10px 0 4px;">
                        <label style="margin-bottom:6px;">Visible en estos sorteos</label>
                        <p class="promo-hint" style="margin:0 0 8px;">Aplica a la ficha y al pack en el carrito de esos sorteos. El resto de promos no cambia.</p>
                        <p style="margin:0 0 8px;">
                            <button type="button" class="button promo-lotteries-all">Seleccionar todos</button>
                            <button type="button" class="button promo-lotteries-none">Ninguno</button>
                        </p>
                        <div class="promo-lotteries-list" style="max-height:240px; overflow:auto; border:1px solid #c5d9b8; background:#fff; padding:8px 10px; border-radius:4px;">
                            <?php if (empty($lotteries)) : ?>
                                <p class="promo-hint" style="margin:0;">No se encontraron sorteos (productos lottery).</p>
                            <?php else : ?>
                                <?php foreach ($lotteries as $lot) :
                                    $lid = (int) $lot['id'];
                                    $checked = ($selected_lottery_ids === null) || in_array($lid, $selected_lottery_ids, true);
                                    $status_label = ($lot['status'] !== 'publish') ? ' (' . $lot['status'] . ')' : '';
                                    ?>
                                    <label style="display:flex; align-items:flex-start; gap:8px; font-weight:500; margin:0 0 6px;">
                                        <input type="checkbox" class="promo-lottery-cb" name="promo_target_lotteries[]" value="<?php echo esc_attr((string) $lid); ?>" <?php checked($checked); ?> />
                                        <span><?php echo esc_html($lot['title'] . $status_label); ?> <span style="color:#888; font-weight:400;">#<?php echo (int) $lid; ?></span></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <script>
                    jQuery(function($){
                        var $box = $('.promo-lotteries');
                        function syncLotteryLock(){
                            $box.css('opacity', $('#promo_show_on_product').is(':checked') ? 1 : 0.55);
                        }
                        $('.promo-lotteries-all').on('click', function(e){
                            e.preventDefault();
                            $box.find('.promo-lottery-cb').prop('checked', true);
                        });
                        $('.promo-lotteries-none').on('click', function(e){
                            e.preventDefault();
                            $box.find('.promo-lottery-cb').prop('checked', false);
                        });
                        $('#promo_show_on_product').on('change', syncLotteryLock);
                        syncLotteryLock();
                    });
                    </script>
                    <div style="display:flex; gap:16px; margin-top:12px; flex-wrap:wrap;">
                        <div class="promo-field" style="flex:1; min-width:180px;">
                            <label>Badge en ficha (opcional)</label>
                            <input type="text" name="promo_product_badge" value="<?php echo esc_attr($product_badge); ?>" placeholder="Ej: MÁS POPULAR" style="width:100%;" />
                        </div>
                        <div class="promo-field" style="flex:0 0 120px;">
                            <label>Orden en ficha</label>
                            <input type="number" name="promo_product_sort" value="<?php echo esc_attr($product_sort); ?>" step="1" style="width:100%;" />
                            <p class="promo-hint">Menor = primero.</p>
                        </div>
                    </div>
                </div>

                <div class="promo-field">
                    <label>Tipo de promoción</label>
                    <select name="promo_type">
                        <option value="fixed"   <?php selected($type,'fixed');   ?>>Bono fijo ($)</option>
                        <option value="percent" <?php selected($type,'percent'); ?>>Descuento %</option>
                        <option value="2x1"     <?php selected($type,'2x1');      ?>>2x1</option>
                        <option value="3x2"     <?php selected($type,'3x2');      ?>>3x2</option>
                        <option value="bxpy"    <?php selected($type,'bxpy');     ?>>Personalizada (Lleva X / Paga Y)</option>
                    </select>
                </div>

                <div class="promo-field">
                    <label>Monto / %</label>
                    <input type="number" step="0.01" name="promo_amount" value="<?php echo esc_attr($amount); ?>" />
                </div>

                <div class="promo-field promo-bxpy-field">
                    <label>Lleva (X)</label>
                    <input type="number" min="2" step="1" name="promo_bxpy_buy" value="<?php echo esc_attr($bxpy_buy); ?>" />
                    <p class="promo-hint">Cantidad por bloque (debe ser > Paga Y).</p>
                </div>

                <div class="promo-field promo-bxpy-field">
                    <label>Paga (Y)</label>
                    <input type="number" min="1" step="1" name="promo_bxpy_pay" value="<?php echo esc_attr($bxpy_pay); ?>" />
                    <p class="promo-hint">Ej: 2x1 → Lleva 2, paga 1.</p>
                </div>

                <script>
                    jQuery(function($){
                        function toggleBxpy(){
                            var t = $('select[name="promo_type"]').val();
                            var showBxpy = (t === 'bxpy');
                            $('.promo-bxpy-field').toggle(showBxpy);
                            var showAmount = (t === 'fixed' || t === 'percent');
                            $('input[name="promo_amount"]').closest('.promo-field').toggle(showAmount);
                        }
                        toggleBxpy();
                        $('select[name="promo_type"]').on('change', toggleBxpy);
                    });
                </script>

                <div class="promo-field">
                    <label>Mínimo de compra (CLP)</label>
                    <input type="number" step="0.01" name="promo_min_total" value="<?php echo esc_attr($min_total); ?>" />
                    <p class="promo-hint">Se evalúa sobre el subtotal elegible (sin envío).</p>
                </div>

                <div class="promo-field">
                    <label>Un solo uso por cliente</label>
                    <select name="promo_limit_per_user">
                        <option value="0" <?php selected($limit_per_user,'0'); ?>>No</option>
                        <option value="1" <?php selected($limit_per_user,'1'); ?>>Sí (1 uso)</option>
                    </select>
                </div>

                <div class="promo-field">
                    <label>Límite total de redenciones</label>
                    <input type="number" name="promo_limit_total" value="<?php echo esc_attr($limit_total); ?>" />
                </div>

                <div class="promo-field">
                    <label>Redenciones realizadas</label>
                    <input type="number" value="<?php echo esc_attr($redemptions); ?>" disabled />
                </div>

                <div class="promo-field">
                    <label>Fecha inicio (Y-m-d H:i)</label>
                    <input type="text" name="promo_start" value="<?php echo esc_attr($start); ?>" placeholder="2025-10-15 00:00" />
                </div>

                <div class="promo-field">
                    <label>Fecha fin (Y-m-d H:i)</label>
                    <input type="text" name="promo_end" value="<?php echo esc_attr($end); ?>" placeholder="2025-12-31 23:59" />
                    <p class="promo-hint">Si escribes solo fecha, se considera hasta las 23:59:59 de ese día.</p>
                </div>

                <div class="promo-field full promo-separator">
                    <label>Aplicar solo a estas categorías</label>
                    <select name="promo_target_categories[]" multiple="multiple" class="wc-enhanced-select" style="width:100%;" data-placeholder="Buscar categorías...">
                        <?php
                        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
                        foreach ($categories as $cat) {
                            echo '<option value="' . esc_attr($cat->term_id) . '" ' . selected(in_array($cat->term_id, (array)$target_categories, true), true, false) . '>' . esc_html($cat->name) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="promo-field full">
                    <label>Aplicar solo a estos productos</label>
                    <select class="wc-product-search" multiple="multiple" style="width: 100%;" name="promo_target_products[]" data-placeholder="Buscar productos...">
                        <?php
                        if (!empty($target_products)) {
                            foreach ((array)$target_products as $product_id) {
                                $product = wc_get_product($product_id);
                                if (is_object($product)) {
                                    echo '<option value="' . esc_attr($product_id) . '" selected>' . wp_strip_all_tags($product->get_formatted_name()) . '</option>';
                                }
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="promo-field full promo-separator">
                    <label>Filtro de Segmentación Principal</label>
                    <select name="promo_segment_type" id="main_segment_selector">
                        <option value="all" <?php selected($segment_type,'all'); ?>>Todos los Clientes</option>
                        <option value="traffic_source" <?php selected($segment_type,'traffic_source'); ?>>Por Fuente de Tráfico (URL source)</option>
                        <option value="manual" <?php selected($segment_type,'manual'); ?>>Manual (IDs o Emails)</option>
                        <option value="comment_post" <?php selected($segment_type,'comment_post'); ?>>Comentó en un post</option>
                        <option value="abandoned_cart" <?php selected($segment_type,'abandoned_cart'); ?>>Carrito abandonado</option>
                        <option value="reg_before" <?php selected($segment_type,'reg_before'); ?>>Registrado antes de fecha</option>
                    </select>
                </div>

                <div class="promo-field full promo-data-container" style="display:none;">
                    <label id="segment_data_label">Datos del segmento</label>
                    <input type="text" name="promo_segment_data" value="<?php echo esc_attr($segment_data); ?>" style="width:100%;" />
                    <p class="promo-hint" id="segment_data_hint">Para manual, IDs y/o emails separados por comas.</p>
                </div>

                <div class="promo-field full promo-reg-before-container" style="display:none;">
                    <label>Fecha Límite de Registro (Y-m-d)</label>
                    <input type="text" name="promo_reg_before_date" value="<?php echo esc_attr($reg_before_date); ?>" placeholder="Ej: 2024-01-01" style="width:100%;" />
                    <p class="promo-hint">Solo usuarios cuya fecha de registro sea anterior a este día.</p>
                </div>

                <div class="promo-box-special">
                    <label style="color:#007cba; display:block; margin-bottom:8px;">VIGENCIA TRAS REGISTRO (Bono Dinámico)</label>
                    <div style="display:flex; gap:16px;">
                        <div class="promo-field" style="flex:1;">
                            <label>Días de validez desde el registro:</label>
                            <input type="number" name="promo_reg_days_limit" value="<?php echo esc_attr($reg_days_limit); ?>" placeholder="Ej: 3" style="width:100%"/>
                        </div>
                    </div>
                    <p class="promo-hint">Si pones 3, el beneficio expira 3 días después de que el usuario creó su cuenta.</p>
                </div>

                <script>
                    jQuery(function($){
                        function toggleSegmentFields(){
                            var s = $('#main_segment_selector').val();
                            $('.promo-data-container').toggle(s !== 'all' && s !== 'abandoned_cart' && s !== 'reg_before');
                            $('.promo-reg-before-container').toggle(s === 'reg_before');
                            
                            if(s === 'traffic_source'){
                                $('#segment_data_label').text('Nombre de la Fuente (slug)');
                                $('#segment_data_hint').text('Ej: mailing_abril. La URL debe contener ?source=mailing_abril');
                            } else {
                                $('#segment_data_label').text('Datos del segmento');
                                $('#segment_data_hint').text('IDs, emails o ID de post según corresponda.');
                            }
                        }
                        toggleSegmentFields();
                        $('#main_segment_selector').on('change', toggleSegmentFields);
                    });
                </script>

                <div class="promo-field full promo-separator">
                    <label>Asunto Email</label>
                    <input type="text" name="promo_email_subject" value="<?php echo esc_attr($email_subject); ?>" style="width:100%;" />
                </div>

                <div class="promo-field full">
                    <label>Cuerpo Email</label>
                    <?php
                    wp_editor($email_body, 'promo_email_body', [
                        'textarea_name' => 'promo_email_body',
                        'media_buttons' => true,
                        'textarea_rows' => 15,
                        'wpautop' => true,
                        'teeny' => false
                    ]);
                    ?>
                    <p class="promo-hint">Puedes usar {user_name} y {site_name}.</p>
                </div>
            </div>
            <?php
        }

        public function save_campaign_meta($post_id, $post) {
            if ($post->post_type !== self::CPT) return;
            if (!isset($_POST['promo_engine_nonce']) || !wp_verify_nonce($_POST['promo_engine_nonce'], 'promo_engine_save')) return;
            if (!current_user_can('edit_post', $post_id)) return;

            $update = function($key, $val) use ($post_id) {
                update_post_meta($post_id, self::META[$key], $val);
            };

            $update('type',             sanitize_text_field($_POST['promo_type'] ?? 'fixed'));
            $update('stackable',        sanitize_text_field($_POST['promo_stackable'] ?? 'yes'));
            $update('amount',           wc_format_decimal($_POST['promo_amount'] ?? ''));
            $update('min_total',        wc_format_decimal($_POST['promo_min_total'] ?? ''));
            $update('start',            sanitize_text_field($_POST['promo_start'] ?? ''));
            $update('end',              sanitize_text_field($_POST['promo_end'] ?? ''));
            $update('reg_days_limit',   sanitize_text_field($_POST['promo_reg_days_limit'] ?? ''));
            $update('reg_before_date',  sanitize_text_field($_POST['promo_reg_before_date'] ?? ''));
            $update('limit_total',      intval($_POST['promo_limit_total'] ?? 0));
            $update('limit_per_user',   intval($_POST['promo_limit_per_user'] ?? 1));
            $update('segment_type',     sanitize_text_field($_POST['promo_segment_type'] ?? 'all'));
            $update('segment_data',     sanitize_text_field($_POST['promo_segment_data'] ?? ''));
            $update('email_subject',    sanitize_text_field($_POST['promo_email_subject'] ?? ''));
            $update('email_body',       wp_kses_post($_POST['promo_email_body'] ?? ''));

            $bxpy_buy = isset($_POST['promo_bxpy_buy']) ? max(2, intval($_POST['promo_bxpy_buy'])) : 0;
            $bxpy_pay = isset($_POST['promo_bxpy_pay']) ? max(1, intval($_POST['promo_bxpy_pay'])) : 0;
            if ($bxpy_buy && $bxpy_pay && $bxpy_pay >= $bxpy_buy) {
                $bxpy_pay = $bxpy_buy - 1;
            }
            $update('bxpy_buy', $bxpy_buy);
            $update('bxpy_pay', $bxpy_pay);

            $target_categories = isset($_POST['promo_target_categories']) ? array_map('absint', (array)$_POST['promo_target_categories']) : [];
            $update('target_categories', $target_categories);

            $target_products = isset($_POST['promo_target_products']) ? array_map('absint', (array)$_POST['promo_target_products']) : [];
            $update('target_products', $target_products);

            $update('show_on_product', isset($_POST['promo_show_on_product']) && $_POST['promo_show_on_product'] === 'yes' ? 'yes' : 'no');
            $update('product_badge', sanitize_text_field($_POST['promo_product_badge'] ?? ''));
            $update('product_sort', intval($_POST['promo_product_sort'] ?? 10));

            if (isset($_POST['promo_target_lotteries_present'])) {
                $target_lotteries = isset($_POST['promo_target_lotteries']) ? array_values(array_unique(array_map('absint', (array) $_POST['promo_target_lotteries']))) : [];
                $target_lotteries = array_values(array_filter($target_lotteries));
                $update('target_lotteries', $target_lotteries);
            }
        }

        public function admin_columns($columns) {
            $new = [];
            foreach ($columns as $key => $label) {
                $new[$key] = $label;
                if ($key === 'title') {
                    $new['promo_show_on_product'] = 'Ficha producto';
                }
            }
            if (!isset($new['promo_show_on_product'])) {
                $new['promo_show_on_product'] = 'Ficha producto';
            }
            return $new;
        }

        public function render_admin_column($column, $post_id) {
            if ($column !== 'promo_show_on_product') {
                return;
            }
            $show = get_post_meta($post_id, self::META['show_on_product'], true);
            if ($show !== 'yes') {
                echo 'No';
                return;
            }
            $raw = get_post_meta($post_id, self::META['target_lotteries'], true);
            if (!is_array($raw)) {
                echo '<strong style="color:#2f6b1a;">Sí · todos</strong>';
                return;
            }
            $n = count(array_filter(array_map('absint', $raw)));
            if ($n === 0) {
                echo '<strong style="color:#a00;">Sí · 0 sorteos</strong>';
                return;
            }
            echo '<strong style="color:#2f6b1a;">Sí · ' . (int) $n . ' sorteo' . ($n === 1 ? '' : 's') . '</strong>';
        }

        /* ----------------------- Aplicación CORE INTEGRAL ----------------------- */

        public function apply_promotions_entrypoint($cart) {
            if (is_admin() && !defined('DOING_AJAX')) return;
            if (empty($cart) || !is_a($cart, 'WC_Cart')) return;

            static $running = false;
            if ($running) return;
            $running = true;

            $this->remove_previous_fees($cart);

            $user_id   = get_current_user_id();
            $campaigns = get_posts([
                'post_type'      => self::CPT,
                'post_status'    => 'publish',
                'numberposts'    => -1,
                'suppress_filters' => false,
            ]);

            if (empty($campaigns)) {
                $running = false;
                return;
            }

            $has_wc_coupons = (count($cart->get_applied_coupons()) > 0);
            $consumed_quantities = [];
            $applied_ids = [];

            // Cada línea de pack (ss_pack_campaign_id) recibe SU promo, aunque haya N packs
            // iguales, packs distintos o sorteos distintos. No se pisan entre sí.
            $this->apply_pack_line_promotions($cart, $user_id, $has_wc_coupons, $consumed_quantities, $applied_ids, true, false);

            $scored_campaigns = [];

            foreach ($campaigns as $c) {
                $stack_meta = get_post_meta($c->ID, self::META['stackable'], true);
                $stack_type = !empty($stack_meta) ? $stack_meta : 'no_coupons';

                if (($stack_type === 'no' || $stack_type === 'no_coupons') && $has_wc_coupons) continue;
                if (!$this->campaign_is_active_for_user($c->ID, $user_id)) continue;

                $calc = $this->calculate_discount_for_campaign($c->ID, $cart, []);
                $discount_value = isset($calc['discount']) ? (float)$calc['discount'] : 0.0;

                if ($discount_value > 0) {
                    $scored_campaigns[] = [
                        'id'         => $c->ID,
                        'post'       => $c,
                        'discount'   => $discount_value,
                        'stack_type' => $stack_type,
                        'calc'       => $calc
                    ];
                }
            }

            usort($scored_campaigns, function($a, $b) {
                if ($b['discount'] != $a['discount']) return $b['discount'] <=> $a['discount'];
                if ($a['stack_type'] !== $b['stack_type']) return ($a['stack_type'] === 'no') ? 1 : -1;
                return $b['id'] <=> $a['id'];
            });

            foreach ($scored_campaigns as $sc) {
                if (in_array((int) $sc['id'], $applied_ids, true)) {
                    continue;
                }
                if (!empty($applied_ids)) {
                    $first_applied_stack = get_post_meta($applied_ids[0], self::META['stackable'], true) ?: 'no_coupons';
                    if ($first_applied_stack === 'no') break;
                }
                if ($sc['stack_type'] === 'no' && !empty($applied_ids)) continue;

                $final_calc = $this->calculate_discount_for_campaign($sc['id'], $cart, $consumed_quantities);
                $final_disc = (float)$final_calc['discount'];

                if ($final_disc > 0) {
                    $this->add_promo_fees_for_calc($cart, $final_calc);
                    $applied_ids[] = (int)$sc['id'];
                    foreach ($final_calc['consumed'] as $key => $qty_used) {
                        $consumed_quantities[$key] = ($consumed_quantities[$key] ?? 0) + $qty_used;
                    }
                }
            }

            if (WC()->session) WC()->session->set(self::SESSION_APPLIED, !empty($applied_ids) ? $applied_ids : null);
            $running = false;
        }

        /**
         * Escala descuentos de cupones porcentuales para que se calculen sobre
         * (subtotal - descuento promo), no sobre el listado sin promo.
         *
         * @param float     $discount
         * @param float     $discounting_amount
         * @param array     $cart_item
         * @param bool      $single
         * @param WC_Coupon $coupon
         */
        public function scale_coupon_discount_after_promo($discount, $discounting_amount, $cart_item, $single, $coupon) {
            $discount = (float) $discount;
            if ($discount <= 0 || !is_a($coupon, 'WC_Coupon')) {
                return $discount;
            }
            // Cupones de monto fijo siguen siendo el monto fijo; solo % necesita base post-promo.
            if (!$coupon->is_type('percent')) {
                return $discount;
            }
            $ratio = $this->get_coupon_base_ratio_after_promo();
            if ($ratio >= 0.999999) {
                return $discount;
            }
            return (float) wc_format_decimal($discount * $ratio, wc_get_price_decimals());
        }

        /**
         * ratio = (subtotal - promo_stackable) / subtotal
         * Cacheado por cart hash durante el mismo calculate_totals.
         */
        private function get_coupon_base_ratio_after_promo() {
            if (!function_exists('WC') || !WC()->cart) {
                return 1.0;
            }
            $cart = WC()->cart;
            static $cached_key = null;
            static $cached_ratio = 1.0;

            $coupons = $cart->get_applied_coupons();
            if (empty($coupons)) {
                return 1.0;
            }

            $key = method_exists($cart, 'get_cart_hash') ? (string) $cart->get_cart_hash() : md5(wp_json_encode($cart->get_cart()));
            $key .= '|' . implode(',', $coupons);
            if ($cached_key === $key) {
                return $cached_ratio;
            }

            $subtotal = 0.0;
            foreach ($cart->get_cart() as $item) {
                $subtotal += (float) ($item['line_subtotal'] ?? 0);
            }
            if ($subtotal <= 0) {
                $cached_key = $key;
                $cached_ratio = 1.0;
                return 1.0;
            }

            $promo = $this->estimate_stackable_promo_discount($cart);
            if ($promo <= 0) {
                $cached_key = $key;
                $cached_ratio = 1.0;
                return 1.0;
            }

            $promo = min($promo, $subtotal);
            $ratio = max(0.0, ($subtotal - $promo) / $subtotal);
            $cached_key = $key;
            $cached_ratio = $ratio;
            return $ratio;
        }

        /**
         * Estima el descuento de campañas stackable=yes que se aplicarán con cupones presentes
         * (misma prioridad/consumo que apply_promotions_entrypoint, sin mutar fees).
         */
        private function estimate_stackable_promo_discount($cart) {
            if (empty($cart) || !is_a($cart, 'WC_Cart')) {
                return 0.0;
            }

            $user_id = get_current_user_id();
            $campaigns = get_posts([
                'post_type'         => self::CPT,
                'post_status'       => 'publish',
                'numberposts'       => -1,
                'suppress_filters'  => false,
            ]);
            if (empty($campaigns)) {
                return 0.0;
            }

            $scored = [];
            foreach ($campaigns as $c) {
                $stack_meta = get_post_meta($c->ID, self::META['stackable'], true);
                $stack_type = !empty($stack_meta) ? $stack_meta : 'no_coupons';
                // Solo campañas que conviven con cupones WC.
                if ($stack_type !== 'yes') {
                    continue;
                }
                if (!$this->campaign_is_active_for_user($c->ID, $user_id)) {
                    continue;
                }
                $calc = $this->calculate_discount_for_campaign($c->ID, $cart, []);
                $discount_value = isset($calc['discount']) ? (float) $calc['discount'] : 0.0;
                if ($discount_value > 0) {
                    $scored[] = [
                        'id'         => $c->ID,
                        'discount'   => $discount_value,
                        'stack_type' => $stack_type,
                    ];
                }
            }

            if (empty($scored)) {
                return 0.0;
            }

            usort($scored, function ($a, $b) {
                if ($b['discount'] != $a['discount']) {
                    return $b['discount'] <=> $a['discount'];
                }
                return $b['id'] <=> $a['id'];
            });

            $consumed = [];
            $total = 0.0;
            $applied_ids = [];
            $has_wc_coupons = (count($cart->get_applied_coupons()) > 0);
            $total += $this->apply_pack_line_promotions($cart, $user_id, $has_wc_coupons, $consumed, $applied_ids, false, true);
            foreach ($scored as $sc) {
                if (in_array((int) $sc['id'], $applied_ids, true)) {
                    continue;
                }
                if (!empty($applied_ids)) {
                    $first = get_post_meta($applied_ids[0], self::META['stackable'], true) ?: 'no_coupons';
                    if ($first === 'no') {
                        break;
                    }
                }
                if ($sc['stack_type'] === 'no' && !empty($applied_ids)) {
                    continue;
                }
                $final_calc = $this->calculate_discount_for_campaign($sc['id'], $cart, $consumed);
                $final_disc = (float) $final_calc['discount'];
                if ($final_disc > 0) {
                    $total += $final_disc;
                    $applied_ids[] = (int) $sc['id'];
                    foreach ($final_calc['consumed'] as $ckey => $qty_used) {
                        $consumed[$ckey] = ($consumed[$ckey] ?? 0) + $qty_used;
                    }
                }
            }

            return $total;
        }

        /**
         * Aplica la promo de cada línea agregada como pack (ss_pack_campaign_id).
         * Así 2× 10×5, 10×5+3×2 o packs de distintos sorteos no se bloquean entre sí.
         *
         * @param array<string,int> $consumed_quantities
         * @param array<int,int>    $applied_ids
         */
        private function apply_pack_line_promotions($cart, $user_id, $has_wc_coupons, array &$consumed_quantities, array &$applied_ids, $add_fees = true, $only_stackable_yes = false): float {
            $sum = 0.0;
            $campaign_ids = [];
            foreach ($cart->get_cart() as $item) {
                $cid = isset($item['ss_pack_campaign_id']) ? absint($item['ss_pack_campaign_id']) : 0;
                if ($cid > 0) {
                    $campaign_ids[$cid] = true;
                }
            }
            if (empty($campaign_ids)) {
                return 0.0;
            }

            foreach (array_keys($campaign_ids) as $cid) {
                $cid = (int) $cid;
                if (get_post_status($cid) !== 'publish') {
                    continue;
                }
                $stack_type = get_post_meta($cid, self::META['stackable'], true);
                $stack_type = !empty($stack_type) ? $stack_type : 'no_coupons';
                if ($only_stackable_yes && $stack_type !== 'yes') {
                    continue;
                }
                if (!$only_stackable_yes && ($stack_type === 'no' || $stack_type === 'no_coupons') && $has_wc_coupons) {
                    continue;
                }
                if (!$this->campaign_is_active_for_user($cid, $user_id)) {
                    continue;
                }

                $final_calc = $this->calculate_discount_for_campaign($cid, $cart, $consumed_quantities);
                $final_disc = (float) $final_calc['discount'];
                if ($final_disc <= 0) {
                    continue;
                }
                if ($add_fees) {
                    $this->add_promo_fees_for_calc($cart, $final_calc);
                }
                $sum += $final_disc;
                if (!in_array($cid, $applied_ids, true)) {
                    $applied_ids[] = $cid;
                }
                foreach ($final_calc['consumed'] as $key => $qty_used) {
                    $consumed_quantities[$key] = ($consumed_quantities[$key] ?? 0) + $qty_used;
                }
            }

            return $sum;
        }

        private function remove_previous_fees($cart) {
            $fees = method_exists($cart, 'get_fees') ? $cart->get_fees() : array();
            if (empty($fees)) {
                return;
            }
            $keep = array();
            foreach ($fees as $fee) {
                $name = isset($fee->name) ? (string) $fee->name : '';
                if (strpos($name, '[PROMO] ') !== 0) {
                    $keep[] = $fee;
                }
            }

            if (method_exists($cart, 'fees_api') && method_exists($cart->fees_api(), 'set_fees')) {
                $raw = array();
                foreach ($keep as $fee) {
                    $raw[] = array(
                        'id'        => isset($fee->id) ? (string) $fee->id : '',
                        'name'      => $fee->name,
                        'amount'    => $fee->amount,
                        'taxable'   => !empty($fee->taxable),
                        'tax_class' => isset($fee->tax_class) ? $fee->tax_class : '',
                    );
                }
                $cart->fees_api()->set_fees($raw);
                return;
            }

            $cart->fees = array();
            foreach ($keep as $fee) {
                $cart->add_fee($fee->name, $fee->amount, $fee->taxable, $fee->tax_class);
            }
        }

        /* ------------------ Validación / Segmentación ------------------ */

        public function campaign_is_active_for_user($campaign_id, $user_id) {
            $now = current_time('timestamp');
            $start_ts = self::parse_wp_datetime(get_post_meta($campaign_id, self::META['start'], true), false);
            $end_ts   = self::parse_wp_datetime(get_post_meta($campaign_id, self::META['end'], true),   true);

            if (($start_ts && $now < $start_ts) || ($end_ts && $now > $end_ts)) return false;

            $limit_total = (int) get_post_meta($campaign_id, self::META['limit_total'], true);
            $redemptions = (int) get_post_meta($campaign_id, self::META['redemptions'], true);
            if ($limit_total > 0 && $redemptions >= $limit_total) return false;

            if ($user_id && (int)get_post_meta($campaign_id, self::META['limit_per_user'], true)) {
                $used = get_user_meta($user_id, 'promo_engine_used_campaigns', true);
                if (is_array($used) && in_array($campaign_id, $used)) return false;
            }

            // --- FILTRO DINÁMICO: DÍAS TRAS REGISTRO ---
            $days_limit = get_post_meta($campaign_id, self::META['reg_days_limit'], true);
            if (!empty($days_limit)) {
                if (!$user_id) return false;
                $ud = get_userdata($user_id);
                if (!$ud) return false;
                $reg_ts = strtotime($ud->user_registered);
                if ($now > ($reg_ts + (absint($days_limit) * DAY_IN_SECONDS))) return false;
            }

            // --- FILTRO DE SEGMENTO / FUENTE ---
            $stype = get_post_meta($campaign_id, self::META['segment_type'], true) ?: 'all';
            $sdata = get_post_meta($campaign_id, self::META['segment_data'], true);

            if ($stype === 'all') return true;

            $user_in_segment = false;
            switch ($stype) {
                case 'traffic_source':
                    $captured_source = '';
                    if (WC()->session) {
                        $captured_source = (string) WC()->session->get(self::SESSION_SOURCE);
                    }
                    // Fallback: si el usuario ya estaba registrado y la sesión no tiene la fuente,
                    // consultar user meta guardado al momento del registro.
                    if (empty($captured_source) && $user_id) {
                        $captured_source = (string) get_user_meta($user_id, 'pe_registration_source', true);
                    }
                    if (!empty($captured_source) && strtolower($captured_source) === strtolower(trim($sdata))) {
                        $user_in_segment = true;
                    }
                    break;
                case 'manual':
                    if (empty($sdata)) break;
                    $items = array_map('trim', explode(',', (string)$sdata));
                    foreach ($items as $item) {
                        if (is_numeric($item) && (int)$user_id === (int)$item) { $user_in_segment = true; break; }
                        if (is_email($item) && $user_id) {
                            $ud = get_userdata($user_id);
                            if ($ud && strtolower($ud->user_email) === strtolower($item)) { $user_in_segment = true; break; }
                        }
                    }
                    break;
                case 'comment_post':
                    $post_id = absint($sdata);
                    if ($post_id && $user_id && get_comments(['post_id'=>$post_id,'user_id'=>$user_id,'count'=>true,'status'=>'approve'])) $user_in_segment = true;
                    break;
                case 'abandoned_cart':
                    if ($user_id && apply_filters('promo_engine_is_abandoned_cart_user', false, $user_id, $campaign_id)) $user_in_segment = true;
                    break;
                case 'reg_before':
                    if (!$user_id) break;
                    $before_date = get_post_meta($campaign_id, self::META['reg_before_date'], true);
                    if (empty($before_date)) break;
                    $limit_ts = self::parse_wp_datetime($before_date, false);
                    $ud = get_userdata($user_id);
                    if ($ud && strtotime($ud->user_registered) < $limit_ts) {
                        $user_in_segment = true;
                    }
                    break;
            }
            return $user_in_segment;
        }

        /* -------------------- Cálculo descuento -------------------- */

        /**
         * Packs (BxPy): un fee por línea con sufijo de sorteo (evita colisión WC).
         * Bonos fixed/%: un solo fee de carrito, sin nombre de producto
         * (el bono es de la compra, no de un DigiTicket concreto).
         */
        private function add_promo_fees_for_calc($cart, array $final_calc): void {
            $disc  = isset($final_calc['discount']) ? (float) $final_calc['discount'] : 0.0;
            $label = isset($final_calc['label']) ? (string) $final_calc['label'] : '';
            $type  = isset($final_calc['type']) ? (string) $final_calc['type'] : '';
            $cid   = isset($final_calc['campaign_id']) ? (int) $final_calc['campaign_id'] : 0;

            if ($disc <= 0 || $label === '') {
                return;
            }

            // Bono de carrito: una línea limpia "[PROMO] Bono $60.000".
            if (in_array($type, ['fixed', 'percent'], true)) {
                $this->add_promo_fee($cart, $label, -$disc, 'cart-' . $cid);
                return;
            }

            $lines = (isset($final_calc['lines']) && is_array($final_calc['lines'])) ? $final_calc['lines'] : [];
            if (empty($lines)) {
                $this->add_promo_fee($cart, $label, -$disc, 'cart-' . $cid);
                return;
            }
            foreach ($lines as $cart_key => $line) {
                $line_disc = isset($line['discount']) ? (float) $line['discount'] : 0.0;
                if ($line_disc <= 0) {
                    continue;
                }
                $item  = (isset($line['item']) && is_array($line['item'])) ? $line['item'] : [];
                $fline = $this->unique_promo_fee_label($label, $item, (string) $cart_key);
                $this->add_promo_fee($cart, $fline, -$line_disc, (string) $cart_key);
            }
        }

        /**
         * Total a pagar estimado ya con fees [PROMO] previos (packs) y cupones,
         * antes de agregar el bono actual. Sirve para min_total de campañas fixed/%.
         */
        private function cart_payable_after_existing_promos($cart): float {
            if (!is_object($cart) || !method_exists($cart, 'get_subtotal')) {
                return 0.0;
            }
            $payable = (float) $cart->get_subtotal();
            if (method_exists($cart, 'get_discount_total')) {
                $payable -= (float) $cart->get_discount_total();
            }
            $fees = method_exists($cart, 'get_fees') ? $cart->get_fees() : [];
            foreach ($fees as $fee) {
                $name = isset($fee->name) ? (string) $fee->name : '';
                if (strpos($name, '[PROMO] ') !== 0) {
                    continue;
                }
                $payable += (float) $fee->amount;
            }
            return (float) wc_format_decimal(max(0, $payable), wc_get_price_decimals());
        }

        private function add_promo_fee($cart, string $label, float $amount, string $uniq = ''): void {
            if (!is_object($cart) || $label === '' || $amount == 0.0) {
                return;
            }
            $id = sanitize_title($label);
            if ($uniq !== '') {
                $id .= '-' . substr(md5($uniq), 0, 10);
            }
            if (method_exists($cart, 'fees_api')) {
                $cart->fees_api()->add_fee([
                    'id'        => $id,
                    'name'      => $label,
                    'amount'    => $amount,
                    'taxable'   => true,
                    'tax_class' => '',
                ]);
                return;
            }
            $cart->add_fee($label, $amount, true);
        }

        private function unique_promo_fee_label($base, array $item, $cart_key) {
            $name = '';
            if (!empty($item['data']) && is_object($item['data']) && method_exists($item['data'], 'get_name')) {
                $name = (string) $item['data']->get_name();
            }
            $name = trim(preg_replace('/^DigiTickets?\s*\|\s*/iu', '', wp_strip_all_tags($name)));
            if ($name === '') {
                $name = $cart_key !== '' ? (string) $cart_key : 'item';
            }
            return rtrim((string) $base) . ' — ' . $name;
        }

        private function calculate_discount_for_campaign($campaign_id, $cart, $already_consumed_map = []) {
            $type      = get_post_meta($campaign_id, self::META['type'], true) ?: 'fixed';
            $amount    = (float) get_post_meta($campaign_id, self::META['amount'], true);
            $min_total = (float) get_post_meta($campaign_id, self::META['min_total'], true);
            $target_cats  = get_post_meta($campaign_id, self::META['target_categories'], true) ?: [];
            $target_prods = get_post_meta($campaign_id, self::META['target_products'], true) ?: [];

            $eligible_items    = [];
            $eligible_subtotal = 0.0;

            foreach ($cart->get_cart() as $key => $item) {
                $pid = $item['product_id'];
                $is_target = (empty($target_cats) && empty($target_prods));
                if (!$is_target) {
                    if (in_array($pid, $target_prods, true)) $is_target = true;
                    if (!$is_target && !empty($target_cats)) {
                        $product_cats = wc_get_product_term_ids($pid, 'product_cat');
                        if (!empty(array_intersect($product_cats, $target_cats))) $is_target = true;
                    }
                }
                if ($is_target && !self::campaign_targets_lottery($campaign_id, $pid)) {
                    $is_target = false;
                }

                if ($is_target) {
                    $line_pack = isset($item['ss_pack_campaign_id']) ? absint($item['ss_pack_campaign_id']) : 0;
                    // Aislar packs entre sí (3x2 no descuenta sobre línea 10x5).
                    // Los bonos fijos/% acumulables SÍ deben aplicar sobre líneas de pack.
                    if ($line_pack > 0 && $line_pack !== (int) $campaign_id) {
                        $is_pack_campaign = (get_post_meta($campaign_id, self::META['show_on_product'], true) === 'yes')
                            || in_array($type, ['2x1', '3x2', 'bxpy'], true);
                        if ($is_pack_campaign) {
                            continue;
                        }
                    }
                    $original_qty = (int) $item['quantity'];
                    // Inventario virtual solo para BxPy/2x1/3x2. fixed/percent se acumulan en $ sobre la misma línea.
                    $used_qty = 0;
                    if (in_array($type, ['2x1', '3x2', 'bxpy'], true)) {
                        $used_qty = isset($already_consumed_map[$key]) ? (int) $already_consumed_map[$key] : 0;
                    }
                    $available_qty = max(0, $original_qty - $used_qty);
                    if ($available_qty > 0) {
                        $sim = $item;
                        $sim['quantity'] = $available_qty;
                        $sim['line_subtotal'] = ((float)$item['line_subtotal'] / $original_qty) * $available_qty;
                        $eligible_items[$key] = $sim;
                        $eligible_subtotal += (float) $sim['line_subtotal'];
                    }
                }
            }

            if (empty($eligible_items)) {
                return ['discount' => 0, 'label' => '', 'consumed' => [], 'lines' => [], 'type' => $type, 'campaign_id' => (int) $campaign_id];
            }

            // Bonos de carrito: umbral = total a pagar tras packs/cupones ya aplicados.
            // Packs BxPy: umbral sigue siendo el subtotal elegible de esas líneas.
            if ($min_total > 0) {
                $base_for_min = in_array($type, ['fixed', 'percent'], true)
                    ? $this->cart_payable_after_existing_promos($cart)
                    : $eligible_subtotal;
                if ($base_for_min < $min_total) {
                    return ['discount' => 0, 'label' => '', 'consumed' => [], 'lines' => [], 'type' => $type, 'campaign_id' => (int) $campaign_id];
                }
            }

            $label = '[PROMO] ' . get_the_title($campaign_id);
            $discount = 0.0; $consumed_in_calc = []; $lines = [];

            if ($type === 'fixed') {
                $discount = min($amount, $eligible_subtotal);
                // Un solo fee de carrito: no hace falta repartir por línea en el label.
                foreach ($eligible_items as $k => $it) {
                    $consumed_in_calc[$k] = $it['quantity'];
                }
            }
            elseif ($type === 'percent') {
                foreach ($eligible_items as $k => $it) {
                    $line_d = round((float) $it['line_subtotal'] * ($amount / 100), wc_get_price_decimals());
                    $consumed_in_calc[$k] = $it['quantity'];
                    if ($line_d > 0) {
                        $lines[$k] = ['discount' => $line_d, 'item' => $it];
                        $discount += $line_d;
                    }
                }
                // Percent también se muestra como fee único de carrito (suma).
                $lines = [];
            }
            elseif (in_array($type, ['2x1', '3x2', 'bxpy'])) {
                $buy = ($type==='2x1')?2:(($type==='3x2')?3:(int)get_post_meta($campaign_id, self::META['bxpy_buy'], true));
                $pay = ($type==='2x1')?1:(($type==='3x2')?2:(int)get_post_meta($campaign_id, self::META['bxpy_pay'], true));
                if ($buy > 1 && $pay < $buy) {
                    foreach ($eligible_items as $k => $it) {
                        $quantity = (int)$it['quantity'];
                        if ($quantity >= $buy) {
                            $price = (float)wc_get_price_excluding_tax($it['data']);
                            $blocks = floor($quantity / $buy);
                            $line_d = ($blocks * ($buy - $pay)) * $price;
                            $consumed_in_calc[$k] = $blocks * $buy;
                            if ($line_d > 0) {
                                $lines[$k] = ['discount' => $line_d, 'item' => $it];
                                $discount += $line_d;
                            }
                        }
                    }
                }
            }
            return [
                'discount'    => max(0, (float) $discount),
                'label'       => $label,
                'consumed'    => $consumed_in_calc,
                'lines'       => $lines,
                'type'        => $type,
                'campaign_id' => (int) $campaign_id,
            ];
        }

        public function mark_redemption_on_order($order_id, $posted_data, $order) {
            if (!WC()->session || !($applied = WC()->session->get(self::SESSION_APPLIED))) return;
            $user_id = (int)$order->get_user_id(); if (!$user_id) return;
            $campaign_ids = is_array($applied) ? $applied : [ (int) $applied ];
            $used = get_user_meta($user_id, 'promo_engine_used_campaigns', true) ?: [];
            foreach ($campaign_ids as $cid) {
                if ((int) get_post_meta($cid, self::META['limit_per_user'], true) && !in_array($cid, $used, true)) $used[] = $cid;
                update_post_meta($cid, self::META['redemptions'], (int)get_post_meta($cid, self::META['redemptions'], true) + 1);
            }
            update_user_meta($user_id, 'promo_engine_used_campaigns', $used);
            $order->update_meta_data('_promo_engine_campaign_ids', implode(',', $campaign_ids));
            $order->save();
            WC()->session->set(self::SESSION_APPLIED, null);
        }

        /**
         * Campañas publicadas, vigentes y marcadas para ficha de producto.
         * Usado por la UI de packs (Fase C) y el flujo AJAX (Fase B).
         *
         * @param int $product_id Producto DigiTicket (0 = sin filtrar por producto).
         * @return array<int, array<string, mixed>>
         */
        public static function get_product_page_campaigns($product_id = 0) {
            $product_id = absint($product_id);
            $q = new WP_Query([
                'post_type'      => self::CPT,
                'post_status'    => 'publish',
                'posts_per_page' => 100,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => [
                    [
                        'key'   => self::META['show_on_product'],
                        'value' => 'yes',
                    ],
                ],
            ]);

            $now = current_time('timestamp');
            $rows = [];

            foreach ($q->posts as $campaign_id) {
                $campaign_id = (int) $campaign_id;
                $start_ts = self::parse_wp_datetime(get_post_meta($campaign_id, self::META['start'], true), false);
                $end_ts   = self::parse_wp_datetime(get_post_meta($campaign_id, self::META['end'], true), true);
                if (($start_ts && $now < $start_ts) || ($end_ts && $now > $end_ts)) {
                    continue;
                }

                $limit_total = (int) get_post_meta($campaign_id, self::META['limit_total'], true);
                $redemptions = (int) get_post_meta($campaign_id, self::META['redemptions'], true);
                if ($limit_total > 0 && $redemptions >= $limit_total) {
                    continue;
                }

                if ($product_id && !self::campaign_targets_product($campaign_id, $product_id)) {
                    continue;
                }

                $type = get_post_meta($campaign_id, self::META['type'], true) ?: 'fixed';
                $buy = 1;
                $pay = 1;
                if ($type === '2x1') {
                    $buy = 2;
                    $pay = 1;
                } elseif ($type === '3x2') {
                    $buy = 3;
                    $pay = 2;
                } elseif ($type === 'bxpy') {
                    $buy = max(1, (int) get_post_meta($campaign_id, self::META['bxpy_buy'], true));
                    $pay = max(1, (int) get_post_meta($campaign_id, self::META['bxpy_pay'], true));
                }

                $sort = (int) get_post_meta($campaign_id, self::META['product_sort'], true);
                $pricing = self::get_product_page_pack_pricing($campaign_id, $product_id);

                $rows[] = [
                    'id'         => $campaign_id,
                    'title'      => get_the_title($campaign_id),
                    'type'       => $type,
                    'buy'        => $buy,
                    'pay'        => $pay,
                    'badge'      => (string) get_post_meta($campaign_id, self::META['product_badge'], true),
                    'sort'       => $sort,
                    'unit_price' => $pricing['unit_price'],
                    'list_price' => $pricing['list_price'],
                    'pack_price' => $pricing['pack_price'],
                    'savings'    => $pricing['savings'],
                ];
            }

            usort($rows, function ($a, $b) {
                if ($a['sort'] === $b['sort']) {
                    return $a['id'] <=> $b['id'];
                }
                return $a['sort'] <=> $b['sort'];
            });

            return $rows;
        }

        /**
         * Precios de card para un pack (usa precio del producto DigiTicket).
         *
         * @return array{unit_price:float,list_price:float,pack_price:float,savings:float}
         */
        public static function get_product_page_pack_pricing($campaign_id, $product_id = 0) {
            $campaign_id = absint($campaign_id);
            $product_id  = absint($product_id);
            $empty = [
                'unit_price' => 0.0,
                'list_price' => 0.0,
                'pack_price' => 0.0,
                'savings'    => 0.0,
            ];

            $unit = 0.0;
            if ($product_id && function_exists('wc_get_product')) {
                $product = wc_get_product($product_id);
                if ($product) {
                    $unit = (float) wc_get_price_to_display($product);
                }
            }

            $type = get_post_meta($campaign_id, self::META['type'], true) ?: 'fixed';
            $buy = 1;
            $pay = 1;
            if ($type === '2x1') {
                $buy = 2;
                $pay = 1;
            } elseif ($type === '3x2') {
                $buy = 3;
                $pay = 2;
            } elseif ($type === 'bxpy') {
                $buy = max(1, (int) get_post_meta($campaign_id, self::META['bxpy_buy'], true));
                $pay = max(1, (int) get_post_meta($campaign_id, self::META['bxpy_pay'], true));
            }

            $list = $unit * $buy;
            $pack = $unit * $pay;
            return [
                'unit_price' => $unit,
                'list_price' => $list,
                'pack_price' => $pack,
                'savings'    => max(0, $list - $pack),
            ];
        }

        /** True si la campaña aplica al producto (targets vacíos = todos). */
        public static function campaign_targets_product($campaign_id, $product_id) {
            $campaign_id = absint($campaign_id);
            $product_id  = absint($product_id);
            if (!$campaign_id || !$product_id) {
                return false;
            }
            if (!self::campaign_targets_lottery($campaign_id, $product_id)) {
                return false;
            }

            $target_cats  = get_post_meta($campaign_id, self::META['target_categories'], true) ?: [];
            $target_prods = get_post_meta($campaign_id, self::META['target_products'], true) ?: [];
            if (empty($target_cats) && empty($target_prods)) {
                return true;
            }
            if (in_array($product_id, array_map('absint', (array) $target_prods), true)) {
                return true;
            }
            if (!empty($target_cats) && function_exists('wc_get_product_term_ids')) {
                $product_cats = wc_get_product_term_ids($product_id, 'product_cat');
                if (!empty(array_intersect(array_map('absint', (array) $target_cats), $product_cats))) {
                    return true;
                }
            }
            return false;
        }

        /**
         * Sorteos asignados al pack. Sin meta = todos (legado). Array vacío = ninguno.
         * Solo restringe campañas marcadas para ficha; el resto de promos no cambia.
         */
        public static function campaign_targets_lottery($campaign_id, $product_id) {
            $campaign_id = absint($campaign_id);
            $product_id  = absint($product_id);
            if (!$campaign_id || !$product_id) {
                return false;
            }
            $show = get_post_meta($campaign_id, self::META['show_on_product'], true);
            if ($show !== 'yes') {
                return true;
            }
            $raw = get_post_meta($campaign_id, self::META['target_lotteries'], true);
            if (!is_array($raw)) {
                return true;
            }
            $ids = array_values(array_filter(array_map('absint', $raw)));
            if (!$ids) {
                return false;
            }
            return in_array($product_id, $ids, true);
        }

        /**
         * @return array<int, array{id:int,title:string,status:string}>
         */
        public static function get_lottery_products_for_admin() {
            $ids = self::get_lottery_product_ids();
            $rows = [];
            foreach ($ids as $id) {
                $id = (int) $id;
                $title = get_the_title($id);
                $product = function_exists('wc_get_product') ? wc_get_product($id) : null;
                if ($product) {
                    $title = (string) $product->get_name();
                }
                $title = trim(preg_replace('/^DigiTickets?\s*\|\s*/iu', '', wp_strip_all_tags($title)) ?? $title);
                $rows[] = [
                    'id'     => $id,
                    'title'  => $title !== '' ? $title : ('Sorteo #' . $id),
                    'status' => (string) get_post_status($id),
                ];
            }
            usort($rows, static function ($a, $b) {
                return strcasecmp($a['title'], $b['title']);
            });
            return $rows;
        }

        /**
         * @return int[]
         */
        public static function get_lottery_product_ids() {
            $ids = [];
            $statuses = ['publish', 'draft', 'pending', 'private', 'future'];

            if (taxonomy_exists('product_type')) {
                $q = new WP_Query([
                    'post_type'      => 'product',
                    'post_status'    => $statuses,
                    'posts_per_page' => 200,
                    'fields'         => 'ids',
                    'no_found_rows'  => true,
                    'tax_query'      => [[
                        'taxonomy' => 'product_type',
                        'field'    => 'slug',
                        'terms'    => 'lottery',
                    ]],
                ]);
                $ids = array_map('absint', (array) $q->posts);
            }

            if (!$ids && function_exists('wc_get_products')) {
                $candidates = wc_get_products([
                    'status' => $statuses,
                    'limit'  => 200,
                    'return' => 'ids',
                ]);
                foreach ((array) $candidates as $pid) {
                    $pid = (int) $pid;
                    $product = wc_get_product($pid);
                    if (!$product) {
                        continue;
                    }
                    $is_lottery = ($product->get_type() === 'lottery');
                    if (!$is_lottery && function_exists('lty_is_lottery_product')) {
                        $is_lottery = (bool) lty_is_lottery_product($product);
                    }
                    if ($is_lottery) {
                        $ids[] = $pid;
                    }
                }
            }

            return array_values(array_unique(array_filter(array_map('absint', $ids))));
        }

        /** Asigna todos los sorteos actuales a los 4 packs de ficha, una sola vez. */
        public function maybe_seed_pack_lotteries() {
            if (get_option(self::PACK_LOTTERY_SEED_FLAG)) {
                return;
            }
            if (!function_exists('wc_get_product')) {
                return;
            }
            $lottery_ids = self::get_lottery_product_ids();
            if (!$lottery_ids) {
                return;
            }
            foreach (self::PACK_LOTTERY_SEED_IDS as $campaign_id) {
                $campaign_id = (int) $campaign_id;
                if (!get_post($campaign_id)) {
                    continue;
                }
                $existing = get_post_meta($campaign_id, self::META['target_lotteries'], true);
                if (is_array($existing) && $existing) {
                    continue;
                }
                update_post_meta($campaign_id, self::META['target_lotteries'], $lottery_ids);
            }
            update_option(self::PACK_LOTTERY_SEED_FLAG, '1', false);
        }

        public function add_email_box() { add_meta_box('promo_engine_email', 'Notificar por correo', [$this, 'render_email_box'], self::CPT, 'side', 'default'); }
        public function render_email_box($post) { echo '<p>Configura el email en el editor.</p>'; }
        public function send_promo_email() {}
        public function cleanup_on_delete($post_id) {}
    }
endif;

new Promo_Engine_Stable();

/** Status marked used on status changes **/
add_action('woocommerce_order_status_processing', 'promo_engine_mark_used_status', 10, 2);
add_action('woocommerce_order_status_completed', 'promo_engine_mark_used_status', 10, 2);
function promo_engine_mark_used_status($order_id, $order = null) {
    if (!$order instanceof WC_Order) $order = wc_get_order($order_id);
    if (!$order || !($user_id = (int)$order->get_user_id())) return;
    $ids = array_filter(array_map('intval', explode(',', (string)$order->get_meta('_promo_engine_campaign_ids'))));
    $used = get_user_meta($user_id, 'promo_engine_used_campaigns', true) ?: [];
    foreach ($ids as $cid) {
        if (!in_array($cid, $used)) $used[] = $cid;
        update_post_meta($cid, '_promo_redemptions', (int)get_post_meta($cid, '_promo_redemptions', true) + 1);
    }
    update_user_meta($user_id, 'promo_engine_used_campaigns', $used);
}

/** Mercado Pago Logic Integral **/
add_filter('woocommerce_mercadopago_checkout_preference', 'pe_mp_inject_promo_kensu', 10, 2);
add_filter('woocommerce_mercadopago_custom_checkout_preference', 'pe_mp_inject_promo_kensu', 10, 2);
function pe_mp_inject_promo_kensu($preference_data, $order = null) {
    $total = 0; $fees = $order ? $order->get_fees() : (WC()->cart ? WC()->cart->get_fees() : []);
    foreach ($fees as $fee) {
        $amt = $order ? $fee->get_total() : $fee->amount;
        if ($amt < 0) {
            $tax = $order ? $fee->get_total_tax() : (isset($fee->tax_data) ? array_sum($fee->tax_data) : 0);
            $total += abs((float)$amt) + abs((float)$tax);
        }
    }
    if ($total > 0) {
        $preference_data['coupon_amount'] = round($total, 0);
        if (empty($preference_data['coupon_code'])) $preference_data['coupon_code'] = 'PROMO-KENSU';
    }
    return $preference_data;
}