<?php
/**
 * Calculadora de plan personalizado (#calculadora).
 *
 * - Catálogo de servicios y precios: rp_calc_config() — es el único lugar donde se editan precios.
 * - Contactos y presupuestos: tipo de contenido privado "rp_presupuesto" (menú "Presupuestos" del panel).
 * - REST: /wp-json/reptilia/v1/lead, /presupuesto y /presupuesto/{id}/pdf.
 * - El total siempre se recalcula en el servidor; el navegador solo muestra una vista previa.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-rp-pdf.php';

/** Dónde llega el aviso de cada presupuesto: opción `rp_calc_owner_email`, o el email de administrador del sitio. */
function rp_calc_owner_email(): string
{
    $email = (string) get_option('rp_calc_owner_email', '');
    return is_email($email) ? $email : (string) get_option('admin_email');
}
const RP_CALC_WHATSAPP = '5493417539204';
const RP_CALC_USD_FALLBACK = 1545; // dólar oficial venta al 01/10/2026, por si falla la consulta

/* ------------------------------------------------------------------------- */
/* Catálogo                                                                  */
/* ------------------------------------------------------------------------- */

function rp_calc_config(): array
{
    return [
        // Multiplicador según el tipo de negocio (los precios base son "Pyme")
        'segments' => [
            ['id' => 'particular', 'label' => 'Particular', 'hint' => 'Emprendedores y profesionales', 'mult' => 0.85],
            ['id' => 'pyme', 'label' => 'Pyme', 'hint' => 'Comercios y empresas chicas', 'mult' => 1.0],
            ['id' => 'empresa', 'label' => 'Empresa', 'hint' => 'Equipos y marcas grandes', 'mult' => 1.35],
        ],
        // Descuento por cantidad total de ítems (no se acumulan: se aplica el mayor alcanzado)
        'discounts' => [['min' => 5, 'pct' => 5], ['min' => 10, 'pct' => 10], ['min' => 15, 'pct' => 15]],
        'categories' => [
            ['id' => 'diseno', 'label' => 'Diseño'],
            ['id' => 'video', 'label' => 'Video'],
            ['id' => 'redaccion', 'label' => 'Redacción'],
            ['id' => 'gestion', 'label' => 'Gestión'],
            ['id' => 'ads', 'label' => 'Publicidad'],
            ['id' => 'estrategia', 'label' => 'Estrategia'],
            ['id' => 'produccion', 'label' => 'Producción'],
        ],
        // Precios a $9.000 por hora (base Pyme, en ARS). "h" = horas estimadas de trabajo.
        // Los packs tienen entre 10% y 16% de descuento sobre la suma de las unidades.
        // "of" + "qty" = es un pack de otro servicio (sirve para mostrar el ahorro).
        'services' => [
            ['id' => 'post', 'cat' => 'diseno', 'name' => 'Posteo / placa', 'desc' => 'Diseño de una publicación para feed.', 'price' => 13500, 'h' => 1.5],
            ['id' => 'post4', 'cat' => 'diseno', 'name' => 'Pack 4 posteos', 'desc' => 'Cuatro placas con línea visual coherente.', 'price' => 48000, 'of' => 'post', 'qty' => 4],
            ['id' => 'post8', 'cat' => 'diseno', 'name' => 'Pack 8 posteos', 'desc' => 'Ocho placas para cubrir el mes.', 'price' => 92000, 'of' => 'post', 'qty' => 8],
            ['id' => 'carrusel', 'cat' => 'diseno', 'name' => 'Carrusel', 'desc' => 'Carrusel de hasta 5 placas.', 'price' => 22500, 'h' => 2.5],
            ['id' => 'carrusel3', 'cat' => 'diseno', 'name' => 'Pack 3 carruseles', 'desc' => 'Tres carruseles de hasta 5 placas.', 'price' => 60000, 'of' => 'carrusel', 'qty' => 3],
            ['id' => 'story', 'cat' => 'diseno', 'name' => 'Historia', 'desc' => 'Diseño de una historia.', 'price' => 4500, 'h' => 0.5],
            ['id' => 'story5', 'cat' => 'diseno', 'name' => 'Pack 5 historias', 'desc' => 'Cinco historias con estética de marca.', 'price' => 20000, 'of' => 'story', 'qty' => 5],
            ['id' => 'story10', 'cat' => 'diseno', 'name' => 'Pack 10 historias', 'desc' => 'Diez historias para sostener la presencia.', 'price' => 38000, 'of' => 'story', 'qty' => 10],
            ['id' => 'ad_piece', 'cat' => 'diseno', 'name' => 'Pieza publicitaria', 'desc' => 'Creatividad para anuncio adaptada a feed y stories.', 'price' => 18000, 'h' => 2],
            ['id' => 'highlights', 'cat' => 'diseno', 'name' => 'Portadas de destacadas', 'desc' => 'Set de íconos para las historias destacadas.', 'price' => 22500, 'h' => 2.5],
            ['id' => 'branding', 'cat' => 'diseno', 'name' => 'Branding práctico', 'desc' => 'Logo, paleta de colores y tipografías.', 'price' => 180000, 'h' => 20],
            ['id' => 'reel', 'cat' => 'video', 'name' => 'Reel editado', 'desc' => 'Edición de un reel de hasta 30 segundos.', 'price' => 27000, 'h' => 3],
            ['id' => 'reel3', 'cat' => 'video', 'name' => 'Pack 3 reels', 'desc' => 'Tres reels editados de hasta 30 segundos.', 'price' => 72000, 'of' => 'reel', 'qty' => 3],
            ['id' => 'reel5', 'cat' => 'video', 'name' => 'Pack 5 reels', 'desc' => 'Cinco reels editados de hasta 30 segundos.', 'price' => 115000, 'of' => 'reel', 'qty' => 5],
            ['id' => 'video_inst', 'cat' => 'video', 'name' => 'Video institucional', 'desc' => 'Pieza de hasta 60 segundos para presentar tu marca.', 'price' => 72000, 'h' => 8],
            ['id' => 'copy', 'cat' => 'redaccion', 'name' => 'Copy para publicación', 'desc' => 'Texto persuasivo con la voz de tu marca.', 'price' => 4500, 'h' => 0.5],
            ['id' => 'copy10', 'cat' => 'redaccion', 'name' => 'Pack 10 copys', 'desc' => 'Diez textos listos para publicar.', 'price' => 38000, 'of' => 'copy', 'qty' => 10],
            ['id' => 'script', 'cat' => 'redaccion', 'name' => 'Guion para reel', 'desc' => 'Guion con gancho, desarrollo y llamado a la acción.', 'price' => 9000, 'h' => 1],
            ['id' => 'calendar', 'cat' => 'gestion', 'name' => 'Calendario mensual', 'desc' => 'Planificación de contenidos del mes.', 'price' => 27000, 'h' => 3],
            ['id' => 'schedule', 'cat' => 'gestion', 'name' => 'Programación de contenido', 'desc' => 'Publicación y programación de todo el mes.', 'price' => 18000, 'h' => 2],
            ['id' => 'community', 'cat' => 'gestion', 'name' => 'Gestión de mensajes y comentarios', 'desc' => 'Atención de la comunidad durante el mes.', 'price' => 54000, 'h' => 6],
            ['id' => 'report', 'cat' => 'gestion', 'name' => 'Reporte mensual', 'desc' => 'Métricas, aprendizajes y próximos pasos.', 'price' => 18000, 'h' => 2],
            ['id' => 'profile', 'cat' => 'gestion', 'name' => 'Optimización de perfil', 'desc' => 'Biografía, destacadas y enlaces.', 'price' => 22500, 'h' => 2.5],
            ['id' => 'campaign', 'cat' => 'ads', 'name' => 'Campaña de ads', 'desc' => 'Configuración y gestión mensual. Sumá una por cada campaña.', 'price' => 45000, 'h' => 5],
            ['id' => 'bm_setup', 'cat' => 'ads', 'name' => 'Configuración publicitaria', 'desc' => 'Business Manager, cuenta publicitaria y píxel.', 'price' => 36000, 'h' => 4],
            ['id' => 'audit', 'cat' => 'estrategia', 'name' => 'Auditoría de redes', 'desc' => 'Diagnóstico de tus cuentas con recomendaciones.', 'price' => 54000, 'h' => 6],
            ['id' => 'content_strategy', 'cat' => 'estrategia', 'name' => 'Estrategia de contenidos', 'desc' => 'Pilares, formatos y tono para tus redes.', 'price' => 72000, 'h' => 8],
            ['id' => 'comm_plan', 'cat' => 'estrategia', 'name' => 'Plan de comunicación y marketing', 'desc' => 'Objetivos, públicos, canales y acciones.', 'price' => 144000, 'h' => 16],
            ['id' => 'consulting', 'cat' => 'estrategia', 'name' => 'Consultoría 1 a 1', 'desc' => 'Una hora de reunión, con preparación previa.', 'price' => 18000, 'h' => 2],
            ['id' => 'shoot', 'cat' => 'produccion', 'name' => 'Jornada de grabación con iPhone', 'desc' => 'Hasta 3 horas de rodaje más selección del material (solo Rosario).', 'price' => 45000, 'h' => 5],
        ],
    ];
}

/** Cotización del dólar oficial (venta), guardada 12 h; si falla, el valor de respaldo. */
function rp_calc_usd_rate(): array
{
    $cached = get_transient('rp_calc_usd');
    if (is_array($cached) && !empty($cached['rate'])) {
        return $cached;
    }
    $rate = ['rate' => RP_CALC_USD_FALLBACK, 'date' => '', 'source' => 'respaldo'];
    $res = wp_remote_get('https://dolarapi.com/v1/dolares/oficial', ['timeout' => 6]);
    if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) === 200) {
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (!empty($body['venta']) && is_numeric($body['venta']) && $body['venta'] > 100) {
            $rate = ['rate' => (float) $body['venta'], 'date' => (string) ($body['fechaActualizacion'] ?? ''), 'source' => 'dolarapi'];
        }
    }
    set_transient('rp_calc_usd', $rate, $rate['source'] === 'dolarapi' ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS);
    return $rate;
}

/** Precio unitario redondeado (misma fórmula que el navegador) */
function rp_calc_unit_price(int $base, float $mult, string $currency, float $rate): float
{
    $ars = round($base * $mult / 100) * 100;
    return $currency === 'USD' ? (float) round($ars / $rate) : (float) $ars;
}

/** Calcula el presupuesto completo a partir de {id: cantidad} */
function rp_calc_quote(array $qty, string $segment, string $currency): array
{
    $cfg = rp_calc_config();
    $mult = 1.0;
    $segLabel = 'Pyme';
    foreach ($cfg['segments'] as $s) {
        if ($s['id'] === $segment) {
            $mult = (float) $s['mult'];
            $segLabel = $s['label'];
        }
    }
    $currency = $currency === 'USD' ? 'USD' : 'ARS';
    $rate = rp_calc_usd_rate();
    $cats = array_column($cfg['categories'], 'label', 'id');
    $lines = [];
    $units = 0;
    $subtotal = 0;
    foreach ($cfg['services'] as $s) {
        $q = isset($qty[$s['id']]) ? max(0, min(50, (int) $qty[$s['id']])) : 0;
        if (!$q) {
            continue;
        }
        $unit = rp_calc_unit_price((int) $s['price'], $mult, $currency, (float) $rate['rate']);
        $lines[] = ['id' => $s['id'], 'name' => $s['name'], 'cat' => $cats[$s['cat']] ?? '', 'qty' => $q, 'unit' => $unit, 'total' => $unit * $q];
        $units += $q;
        $subtotal += $unit * $q;
    }
    $pct = 0;
    foreach ($cfg['discounts'] as $d) {
        if ($units >= $d['min']) {
            $pct = (int) $d['pct'];
        }
    }
    $discount = round($subtotal * $pct / 100);
    return [
        'lines' => $lines, 'units' => $units, 'subtotal' => $subtotal, 'discount_pct' => $pct, 'discount' => $discount,
        'total' => $subtotal - $discount, 'currency' => $currency, 'segment' => $segment, 'segment_label' => $segLabel,
        'rate' => (float) $rate['rate'],
    ];
}

function rp_calc_money($amount, string $currency): string
{
    return ($currency === 'USD' ? 'US$ ' : '$') . number_format((float) $amount, 0, ',', '.');
}

/* ------------------------------------------------------------------------- */
/* Contactos / presupuestos guardados                                         */
/* ------------------------------------------------------------------------- */

add_action('init', function () {
    register_post_type('rp_presupuesto', [
        'labels' => [
            'name' => 'Presupuestos', 'singular_name' => 'Presupuesto', 'menu_name' => 'Presupuestos',
            'all_items' => 'Todos los presupuestos', 'edit_item' => 'Ver presupuesto', 'search_items' => 'Buscar',
            'not_found' => 'Todavía no hay presupuestos',
        ],
        'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'show_in_rest' => false,
        'menu_icon' => 'dashicons-calculator', 'menu_position' => 26, 'supports' => ['title'],
        'capability_type' => 'post', 'map_meta_cap' => true,
        'capabilities' => ['create_posts' => 'do_not_allow'],
    ]);
});

add_filter('manage_rp_presupuesto_posts_columns', function ($cols) {
    return ['cb' => $cols['cb'], 'title' => 'Nombre', 'rp_email' => 'Email', 'rp_phone' => 'WhatsApp', 'rp_total' => 'Total', 'rp_status' => 'Estado', 'date' => 'Fecha'];
});
add_action('manage_rp_presupuesto_posts_custom_column', function ($col, $id) {
    $m = fn($k) => get_post_meta($id, $k, true);
    if ($col === 'rp_email') echo esc_html($m('rp_email'));
    if ($col === 'rp_phone') echo esc_html($m('rp_phone'));
    if ($col === 'rp_total') {
        $q = $m('rp_quote');
        echo $q ? esc_html(rp_calc_money($q['total'], $q['currency'])) : '—';
    }
    if ($col === 'rp_status') {
        echo $m('rp_number') ? '<strong>Presupuesto ' . esc_html($m('rp_number')) . '</strong>' : 'Solo contacto';
    }
}, 10, 2);

add_action('add_meta_boxes', function () {
    add_meta_box('rp_presupuesto_detalle', 'Detalle', function ($post) {
        $m = fn($k) => get_post_meta($post->ID, $k, true);
        $q = $m('rp_quote');
        echo '<p><strong>Email:</strong> ' . esc_html($m('rp_email')) . '<br><strong>WhatsApp:</strong> ' . esc_html($m('rp_phone'))
            . '<br><strong>Consentimiento:</strong> ' . ($m('rp_consent') ? 'Sí (' . esc_html($m('rp_consent')) . ')' : 'No') . '</p>';
        if (!$q) {
            echo '<p>Dejó sus datos pero todavía no generó un presupuesto.</p>';
            return;
        }
        echo '<p><strong>' . esc_html($m('rp_number')) . '</strong> · ' . esc_html($q['segment_label']) . ' · ' . esc_html($q['currency']) . '</p><table class="widefat striped"><thead><tr><th>Servicio</th><th>Cant.</th><th>Subtotal</th></tr></thead><tbody>';
        foreach ($q['lines'] as $l) {
            echo '<tr><td>' . esc_html($l['name']) . '</td><td>' . (int) $l['qty'] . '</td><td>' . esc_html(rp_calc_money($l['total'], $q['currency'])) . '</td></tr>';
        }
        echo '</tbody></table><p>Subtotal: ' . esc_html(rp_calc_money($q['subtotal'], $q['currency']))
            . ($q['discount_pct'] ? '<br>Descuento ' . (int) $q['discount_pct'] . '%: −' . esc_html(rp_calc_money($q['discount'], $q['currency'])) : '')
            . '<br><strong>Total: ' . esc_html(rp_calc_money($q['total'], $q['currency'])) . '</strong></p>';
        $url = wp_nonce_url(admin_url('admin-post.php?action=rp_presupuesto_pdf&id=' . $post->ID), 'rp_pdf_' . $post->ID);
        echo '<p><a class="button button-primary" href="' . esc_url($url) . '">Descargar PDF</a></p>';
    }, 'rp_presupuesto', 'normal', 'high');
});

/* Exportar todos los contactos a un archivo que abre Excel (CSV con ; y UTF-8 con BOM) */
add_action('manage_posts_extra_tablenav', function ($which) {
    if ($which !== 'top' || get_current_screen()->post_type !== 'rp_presupuesto') {
        return;
    }
    $url = wp_nonce_url(admin_url('admin-post.php?action=rp_presupuestos_csv'), 'rp_csv');
    echo '<div class="alignleft actions"><a class="button button-primary" href="' . esc_url($url) . '">Descargar Excel (CSV)</a></div>';
});

add_action('admin_post_rp_presupuestos_csv', function () {
    if (!current_user_can('edit_posts') || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'rp_csv')) {
        wp_die('No autorizado');
    }
    $ids = get_posts(['post_type' => 'rp_presupuesto', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC']);
    nocache_headers();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="presupuestos-reptilia-' . current_time('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Fecha', 'Nombre', 'Email', 'WhatsApp', 'Presupuesto', 'Tipo de cliente', 'Moneda', 'Servicios', 'Subtotal', 'Descuento %', 'Total'], ';');
    foreach ($ids as $id) {
        $q = get_post_meta($id, 'rp_quote', true);
        $services = $q ? implode(' | ', array_map(fn($l) => (int) $l['qty'] . ' x ' . $l['name'], $q['lines'])) : '';
        fputcsv($out, [
            get_the_date('d/m/Y H:i', $id),
            rp_calc_name($id),
            get_post_meta($id, 'rp_email', true),
            get_post_meta($id, 'rp_phone', true),
            get_post_meta($id, 'rp_number', true) ?: 'Solo contacto',
            $q['segment_label'] ?? '',
            $q['currency'] ?? '',
            $services,
            $q ? round($q['subtotal'], 2) : '',
            $q ? (int) $q['discount_pct'] : '',
            $q ? round($q['total'], 2) : '',
        ], ';');
    }
    fclose($out);
    exit;
});

add_action('admin_post_rp_presupuesto_pdf', function () {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id || !current_user_can('edit_post', $id) || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'rp_pdf_' . $id)) {
        wp_die('No autorizado');
    }
    rp_calc_send_pdf($id);
});

/* ------------------------------------------------------------------------- */
/* REST                                                                       */
/* ------------------------------------------------------------------------- */

function rp_calc_rate_limited(string $bucket, int $max): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'x';
    $key = 'rp_rl_' . $bucket . '_' . md5($ip);
    $n = (int) get_transient($key);
    if ($n >= $max) {
        return true;
    }
    set_transient($key, $n + 1, HOUR_IN_SECONDS);
    return false;
}

function rp_calc_lead_from_request(WP_REST_Request $r)
{
    $id = (int) $r->get_param('lead');
    $token = (string) $r->get_param('token');
    if (!$id || get_post_type($id) !== 'rp_presupuesto' || !hash_equals((string) get_post_meta($id, 'rp_token', true), $token)) {
        return null;
    }
    return $id;
}

add_action('rest_api_init', function () {
    register_rest_route('reptilia/v1', '/lead', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'callback' => function (WP_REST_Request $r) {
            // Chequeo anti-bots: el navegador marca el envío y mide cuánto tardó en completarse el formulario.
            if ((string) $r->get_param('h') !== 'rp1' || (int) $r->get_param('t') < 1500) {
                return new WP_REST_Response(['ok' => false, 'error' => 'Esperá un segundo y volvé a tocar el botón.'], 400);
            }
            if (rp_calc_rate_limited('lead', 15)) {
                return new WP_REST_Response(['ok' => false, 'error' => 'Demasiados intentos. Probá de nuevo en un rato.'], 429);
            }
            $name = sanitize_text_field((string) $r->get_param('name'));
            $email = sanitize_email((string) $r->get_param('email'));
            $phone = preg_replace('/[^0-9+ ()-]/', '', (string) $r->get_param('phone'));
            $consent = (bool) $r->get_param('consent');
            $digits = preg_replace('/\D/', '', $phone);
            if (mb_strlen($name) < 2 || !is_email($email) || strlen($digits) < 8 || !$consent) {
                return new WP_REST_Response(['ok' => false, 'error' => 'Revisá tus datos: nombre, email válido, WhatsApp y la casilla de consentimiento.'], 400);
            }
            $id = wp_insert_post(['post_type' => 'rp_presupuesto', 'post_status' => 'private', 'post_title' => $name], true);
            if (is_wp_error($id)) {
                return new WP_REST_Response(['ok' => false, 'error' => 'No pudimos guardar tus datos.'], 500);
            }
            $token = bin2hex(random_bytes(16));
            update_post_meta($id, 'rp_email', $email);
            update_post_meta($id, 'rp_phone', $phone);
            update_post_meta($id, 'rp_consent', current_time('mysql'));
            update_post_meta($id, 'rp_token', $token);
            return new WP_REST_Response(['ok' => true, 'lead' => $id, 'token' => $token, 'name' => $name], 200);
        },
    ]);

    register_rest_route('reptilia/v1', '/presupuesto', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'callback' => function (WP_REST_Request $r) {
            $id = rp_calc_lead_from_request($r);
            if (!$id) {
                return new WP_REST_Response(['ok' => false, 'error' => 'Tu sesión venció. Volvé a cargar tus datos.'], 403);
            }
            if (rp_calc_rate_limited('quote', 20)) {
                return new WP_REST_Response(['ok' => false, 'error' => 'Demasiados intentos. Probá de nuevo en un rato.'], 429);
            }
            $items = (array) $r->get_param('items');
            $quote = rp_calc_quote($items, (string) $r->get_param('segment'), (string) $r->get_param('currency'));
            if (!$quote['lines']) {
                return new WP_REST_Response(['ok' => false, 'error' => 'Sumá al menos un servicio.'], 400);
            }
            $number = get_post_meta($id, 'rp_number', true);
            if (!$number) {
                $n = (int) get_option('rp_calc_counter', 0) + 1;
                update_option('rp_calc_counter', $n, false);
                $number = sprintf('RP-%04d', $n);
                update_post_meta($id, 'rp_number', $number);
            }
            $quote['number'] = $number;
            $quote['date'] = current_time('d/m/Y');
            update_post_meta($id, 'rp_quote', $quote);
            wp_update_post(['ID' => $id]); // actualiza la fecha de modificación

            rp_calc_send_emails_after_response($id);
            $name = rp_calc_name($id);
            $wa = sprintf(
                "¡Hola Dani! Soy %s. Armé mi plan personalizado en la web por %s y quiero seguir con la gestión.",
                $name,
                rp_calc_money($quote['total'], $quote['currency'])
            );
            return new WP_REST_Response([
                'ok' => true,
                'quote' => $quote,
                'whatsapp' => 'https://wa.me/' . RP_CALC_WHATSAPP . '?text=' . rawurlencode($wa),
                'pdf' => rest_url('reptilia/v1/presupuesto/' . $id . '/pdf') . '?token=' . get_post_meta($id, 'rp_token', true),
            ], 200);
        },
    ]);

    register_rest_route('reptilia/v1', '/presupuesto/(?P<id>\d+)/pdf', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function (WP_REST_Request $r) {
            $id = (int) $r['id'];
            $r->set_param('lead', $id);
            if (!rp_calc_lead_from_request($r) || !get_post_meta($id, 'rp_quote', true)) {
                return new WP_REST_Response(['ok' => false], 403);
            }
            rp_calc_send_pdf($id);
        },
    ]);
});

/* ------------------------------------------------------------------------- */
/* PDF                                                                        */
/* ------------------------------------------------------------------------- */

/** Logo del sitio convertido a JPEG sobre fondo negro (el PDF no maneja transparencias) */
function rp_calc_logo_jpeg(): string
{
    $cached = get_transient('rp_calc_logo_jpg');
    if (is_string($cached) && $cached !== '') {
        return base64_decode($cached);
    }
    $logo_id = (int) get_theme_mod('custom_logo');
    $path = $logo_id ? get_attached_file($logo_id) : '';
    if (!$path || !file_exists($path) || !function_exists('imagecreatefromstring')) {
        return '';
    }
    $src = @imagecreatefromstring((string) file_get_contents($path));
    if (!$src) {
        return '';
    }
    $size = 240;
    $dst = imagecreatetruecolor($size, $size);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 0, 0, 0));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $size, $size, imagesx($src), imagesy($src));
    ob_start();
    imagejpeg($dst, null, 90);
    $jpg = (string) ob_get_clean();
    imagedestroy($src);
    imagedestroy($dst);
    set_transient('rp_calc_logo_jpg', base64_encode($jpg), DAY_IN_SECONDS);
    return $jpg;
}

function rp_calc_pdf(int $id): string
{
    $q = get_post_meta($id, 'rp_quote', true);
    $name = rp_calc_name($id);
    $email = get_post_meta($id, 'rp_email', true);
    $phone = get_post_meta($id, 'rp_phone', true);
    $cur = $q['currency'];
    $money = fn($v) => rp_calc_money($v, $cur);

    $pdf = new RP_PDF();
    $W = $pdf->w;
    $M = 42;
    $purple = '#7B2CBF';
    $ink = '#15101C';
    $muted = '#6E6878';

    $header = function () use ($pdf, $W, $M, $q) {
        $pdf->rect(0, 0, $W, 128, '#000000');
        $pdf->rect(0, 128, $W, 4, '#8A2BE2');
        $logo = rp_calc_logo_jpeg();
        if ($logo) {
            $pdf->jpeg($logo, $M, 30, 66, 66);
        }
        $x = $logo ? $M + 82 : $M;
        $pdf->text($x, 40, 'REPTILIA', 22, '#FFFFFF', true);
        $pdf->text($x, 70, 'Marketing para pymes con estrategia e IA', 10, '#C9A7FF');
        $pdf->text($W - $M, 38, 'PRESUPUESTO', 11, '#C9A7FF', true, 'R');
        $pdf->text($W - $M, 58, 'Plan a medida', 17, '#FFFFFF', true, 'R');
        $pdf->text($W - $M, 86, 'Fecha: ' . $q['date'], 9, '#BDB6C8', false, 'R');
    };

    $footer = function ($page) use ($pdf, $W, $M, $muted) {
        $y = $pdf->h - 46;
        $pdf->line($M, $y, $W - $M, $y, '#E4DDF0', 0.8);
        $pdf->text($M, $y + 12, 'WhatsApp +54 9 341 753-9204  ·  reptiliamarketing@gmail.com  ·  reptiliamarketing.online', 8.5, $muted);
        $pdf->text($W - $M, $y + 12, 'Página ' . $page, 8.5, $muted, false, 'R');
    };

    $pdf->addPage();
    $header();

    // Datos del cliente
    $y = 158;
    $pdf->roundRect($M, $y, $W - 2 * $M, 78, 10, '#F5F0FC');
    $pdf->text($M + 18, $y + 14, 'PREPARADO PARA', 8, $purple, true);
    $pdf->text($M + 18, $y + 30, $name, 15, $ink, true);
    $pdf->text($M + 18, $y + 52, $email . '   ·   ' . $phone, 9.5, $muted);
    $pdf->text($W - $M - 18, $y + 14, 'TIPO DE NEGOCIO', 8, $purple, true, 'R');
    $pdf->text($W - $M - 18, $y + 30, $q['segment_label'], 13, $ink, true, 'R');
    $pdf->text($W - $M - 18, $y + 52, 'Moneda: ' . ($cur === 'USD' ? 'Dólares (USD)' : 'Pesos argentinos (ARS)'), 9.5, $muted, false, 'R');

    // Tabla
    $cols = ['name' => $M + 14, 'qty' => $W - $M - 190, 'unit' => $W - $M - 100, 'total' => $W - $M - 14];
    $tableHead = function ($y) use ($pdf, $W, $M, $cols) {
        $pdf->roundRect($M, $y, $W - 2 * $M, 26, 6, '#15101C');
        $pdf->text($cols['name'], $y + 8, 'SERVICIO', 8.5, '#FFFFFF', true);
        $pdf->text($cols['qty'], $y + 8, 'CANT.', 8.5, '#FFFFFF', true, 'C');
        $pdf->text($cols['unit'], $y + 8, 'PRECIO UNIT.', 8.5, '#FFFFFF', true, 'R');
        $pdf->text($cols['total'], $y + 8, 'SUBTOTAL', 8.5, '#FFFFFF', true, 'R');
        return $y + 26;
    };
    $y = $tableHead(262);
    $page = 1;
    foreach ($q['lines'] as $i => $l) {
        if ($y > $pdf->h - 150) {
            $footer($page++);
            $pdf->addPage();
            $header();
            $y = $tableHead(158);
        }
        if ($i % 2 === 0) {
            $pdf->rect($M, $y, $W - 2 * $M, 34, '#FAF8FD');
        }
        $pdf->text($cols['name'], $y + 7, $l['name'], 10.5, $ink, true);
        $pdf->text($cols['name'], $y + 21, $l['cat'], 8, $muted);
        $pdf->text($cols['qty'], $y + 12, (string) $l['qty'], 10.5, $ink, false, 'C');
        $pdf->text($cols['unit'], $y + 12, $money($l['unit']), 10, $ink, false, 'R');
        $pdf->text($cols['total'], $y + 12, $money($l['total']), 10.5, $ink, true, 'R');
        $y += 34;
    }
    $pdf->line($M, $y + 4, $W - $M, $y + 4, '#E4DDF0', 0.8);

    // Totales (derecha) y próximos pasos (izquierda), en el mismo bloque: ~130 pt de alto
    if ($y > $pdf->h - 200) {
        $footer($page++);
        $pdf->addPage();
        $header();
        $y = 150;
    }
    $y += 22;
    $top = $y;
    $bx = $W - $M - 240;
    $pdf->text($bx, $y, 'Subtotal (' . $q['units'] . ' ítems)', 10, $muted);
    $pdf->text($W - $M - 14, $y, $money($q['subtotal']), 10, $ink, false, 'R');
    if ($q['discount_pct']) {
        $y += 20;
        $pdf->text($bx, $y, 'Descuento por volumen (' . $q['discount_pct'] . '%)', 10, $purple, true);
        $pdf->text($W - $M - 14, $y, '-' . $money($q['discount']), 10, $purple, true, 'R');
    }
    $y += 28;
    $pdf->roundRect($bx - 14, $y, 254, 48, 10, '#7B2CBF');
    $pdf->text($bx, $y + 17, 'TOTAL', 10, '#E9DDFF', true);
    $pdf->text($W - $M - 14, $y + 12, $money($q['total']), 20, '#FFFFFF', true, 'R');

    $notes = [
        'Valores de referencia, sujetos a confirmación según el alcance final del trabajo.',
        $cur === 'USD' ? 'Conversión al dólar oficial del día: US$ 1 = $' . number_format($q['rate'], 0, ',', '.') . '.' : '',
        'Para avanzar, escribinos por WhatsApp y seguimos con la gestión.',
    ];
    $ny = $top;
    $nw = $bx - 14 - $M - 28;
    $pdf->text($M, $ny, 'PRÓXIMOS PASOS', 8.5, $purple, true);
    $ny += 16;
    foreach (array_filter($notes) as $n) {
        foreach ($pdf->wrap('•  ' . $n, 9, $nw) as $ln) {
            $pdf->text($M, $ny, $ln, 9, $muted);
            $ny += 13;
        }
        $ny += 3;
    }
    $footer($page);
    return $pdf->output();
}

function rp_calc_send_pdf(int $id)
{
    $q = get_post_meta($id, 'rp_quote', true);
    $pdf = rp_calc_pdf($id);
    nocache_headers();
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="Presupuesto-Reptilia.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf; // phpcs:ignore
    exit;
}

/* ------------------------------------------------------------------------- */
/* Emails                                                                     */
/* ------------------------------------------------------------------------- */

function rp_calc_email_html(int $id, bool $forOwner): string
{
    $q = get_post_meta($id, 'rp_quote', true);
    $name = esc_html(rp_calc_name($id));
    $rows = '';
    foreach ($q['lines'] as $l) {
        $rows .= '<tr><td style="padding:10px 0;border-bottom:1px solid #eee;color:#15101c">' . esc_html($l['name']) . ' <span style="color:#8a8496">× ' . (int) $l['qty'] . '</span></td>'
            . '<td style="padding:10px 0;border-bottom:1px solid #eee;text-align:right;color:#15101c">' . esc_html(rp_calc_money($l['total'], $q['currency'])) . '</td></tr>';
    }
    $disc = $q['discount_pct'] ? '<tr><td style="padding:8px 0;color:#7b2cbf">Descuento por volumen (' . (int) $q['discount_pct'] . '%)</td><td style="padding:8px 0;text-align:right;color:#7b2cbf">−' . esc_html(rp_calc_money($q['discount'], $q['currency'])) . '</td></tr>' : '';
    $intro = $forOwner
        ? '<p style="margin:0 0 6px;font-size:15px;color:#15101c"><strong>Nuevo presupuesto desde la web</strong></p><p style="margin:0 0 18px;color:#5c5668">' . $name . ' · ' . esc_html(get_post_meta($id, 'rp_email', true)) . ' · ' . esc_html(get_post_meta($id, 'rp_phone', true)) . ' · ' . esc_html($q['segment_label']) . '</p>'
        : '<p style="margin:0 0 18px;font-size:16px;color:#15101c">¡Hola ' . $name . '! Esta es la copia de tu presupuesto.</p>';
    $wa = 'https://wa.me/' . RP_CALC_WHATSAPP . '?text=' . rawurlencode('¡Hola Dani! Quiero seguir con el presupuesto que armé en la web.');
    $cta = $forOwner ? '' : '<p style="margin:26px 0 0;text-align:center"><a href="' . esc_url($wa) . '" style="display:inline-block;background:#7b2cbf;color:#fff;text-decoration:none;font-weight:bold;padding:14px 28px;border-radius:999px">Seguir por WhatsApp</a></p>';
    return '<div style="background:#f3eff9;padding:28px 12px;font-family:Arial,Helvetica,sans-serif">'
        . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden">'
        . '<div style="background:#000;padding:24px 28px;border-bottom:4px solid #8a2be2"><div style="color:#fff;font-size:20px;font-weight:bold;letter-spacing:4px">REPTILIA</div>'
        . '<div style="color:#c9a7ff;font-size:12px;margin-top:4px">' . ($forOwner ? 'Presupuesto ' . esc_html($q['number']) . ' · ' : '') . esc_html($q['date']) . '</div></div>'
        . '<div style="padding:26px 28px">' . $intro
        . '<table style="width:100%;border-collapse:collapse;font-size:14px">' . $rows
        . '<tr><td style="padding:12px 0 4px;color:#5c5668">Subtotal (' . (int) $q['units'] . ' ítems)</td><td style="padding:12px 0 4px;text-align:right;color:#5c5668">' . esc_html(rp_calc_money($q['subtotal'], $q['currency'])) . '</td></tr>'
        . $disc
        . '<tr><td style="padding:14px 0 0;font-size:18px;font-weight:bold;color:#15101c">Total</td><td style="padding:14px 0 0;text-align:right;font-size:22px;font-weight:bold;color:#7b2cbf">' . esc_html(rp_calc_money($q['total'], $q['currency'])) . '</td></tr>'
        . '</table>' . $cta
        . '<p style="margin:26px 0 0;font-size:12px;color:#8a8496">Valores de referencia, sujetos a confirmación según el alcance final del trabajo.</p>'
        . '</div></div></div>';
}

/**
 * Manda los correos cuando la respuesta ya le llegó al navegador, así la ventana de gracias
 * aparece al instante en vez de esperar ~8 s a Gmail. Si el servidor no permite cerrar la
 * conexión antes, se mandan igual (solo que la respuesta tarda más).
 */
function rp_calc_send_emails_after_response(int $id): void
{
    add_action('shutdown', function () use ($id) {
        ignore_user_abort(true);
        if (function_exists('litespeed_finish_request') || function_exists('fastcgi_finish_request')) {
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
            function_exists('litespeed_finish_request') ? litespeed_finish_request() : fastcgi_finish_request();
        }
        @set_time_limit(120);
        rp_calc_send_emails($id);
    }, 0);
}

/** Envía el PDF al cliente y a Reptilia. Devuelve true si salieron los dos correos. */
function rp_calc_send_emails(int $id): bool
{
    $q = get_post_meta($id, 'rp_quote', true);
    $dir = trailingslashit(get_temp_dir()) . 'rp-' . wp_generate_password(10, false);
    wp_mkdir_p($dir);
    $pdf = rp_calc_pdf($id);
    $file = $dir . '/Presupuesto-Reptilia.pdf';
    $ownerFile = $dir . '/Presupuesto-Reptilia-' . $q['number'] . '.pdf';
    file_put_contents($file, $pdf);
    file_put_contents($ownerFile, $pdf);

    $clientEmail = get_post_meta($id, 'rp_email', true);
    $name = rp_calc_name($id);
    $html = ['Content-Type: text/html; charset=UTF-8'];
    $fromName = fn() => 'Reptilia Marketing';
    add_filter('wp_mail_from_name', $fromName, 20);

    $okClient = wp_mail(
        $clientEmail,
        'Tu recibo de Reptilia 🐍',
        rp_calc_email_html($id, false),
        array_merge($html, ['Reply-To: Reptilia Marketing <' . rp_calc_owner_email() . '>']),
        [$file]
    );
    $okOwner = wp_mail(
        rp_calc_owner_email(),
        'Nuevo presupuesto web ' . $q['number'] . ' · ' . $name . ' · ' . rp_calc_money($q['total'], $q['currency']),
        rp_calc_email_html($id, true),
        array_merge($html, ['Reply-To: ' . $name . ' <' . $clientEmail . '>']),
        [$ownerFile]
    );
    remove_filter('wp_mail_from_name', $fromName, 20);
    @unlink($file);
    @unlink($ownerFile);
    @rmdir($dir);
    update_post_meta($id, 'rp_mail', ['client' => (bool) $okClient, 'owner' => (bool) $okOwner, 'at' => current_time('mysql')]);
    return $okClient && $okOwner;
}

/* ------------------------------------------------------------------------- */
/* Front: panel de la calculadora                                             */
/* ------------------------------------------------------------------------- */

add_action('wp_enqueue_scripts', function () {
    if (!rp_motion_enabled() || !is_front_page()) {
        return;
    }
    wp_enqueue_style('rp-calc', RP_MOTION_URL . 'assets/calc.css', ['rp-motion'], rp_motion_asset_version('assets/calc.css'));
    wp_enqueue_script('rp-calc', RP_MOTION_URL . 'assets/calc.js', [], rp_motion_asset_version('assets/calc.js'), ['in_footer' => true, 'strategy' => 'defer']);
    $cfg = rp_calc_config();
    $rate = rp_calc_usd_rate();
    wp_localize_script('rp-calc', 'RP_CALC', [
        'api' => esc_url_raw(rest_url('reptilia/v1/')),
        'segments' => $cfg['segments'],
        'discounts' => $cfg['discounts'],
        'categories' => $cfg['categories'],
        'services' => $cfg['services'],
        'rate' => $rate['rate'],
        'logo' => wp_get_attachment_image_url((int) get_theme_mod('custom_logo'), 'thumbnail') ?: '',
    ]);
}, 100);

/** Nombre tal como lo cargó la persona (get_the_title() le antepone "Privado:" a los registros privados) */
function rp_calc_name(int $id): string
{
    return (string) get_post_field('post_title', $id, 'raw');
}
