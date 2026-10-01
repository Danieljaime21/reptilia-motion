<?php
/**
 * Plugin Name: Reptilia Motion
 * Description: Capa de animaciones e interacciones "cyber-noir" para el sitio de Reptilia Marketing. No modifica el contenido de las páginas: se desactiva y todo vuelve a como estaba.
 * Version: 1.0.0
 * Author: Reptilia Marketing
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('RP_MOTION_DIR', plugin_dir_path(__FILE__));
define('RP_MOTION_URL', plugin_dir_url(__FILE__));

/**
 * No cargar nada dentro del editor de Elementor ni en el panel de administración.
 */
function rp_motion_enabled(): bool
{
    if (is_admin() || wp_doing_ajax()) {
        return false;
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['elementor-preview']) || isset($_GET['elementor_library'])) {
        return false;
    }
    return true;
}

function rp_motion_asset_version(string $file): string
{
    $path = RP_MOTION_DIR . $file;
    return file_exists($path) ? (string) filemtime($path) : '1.0.0';
}

/**
 * Script mínimo en <head>: marca el documento antes del primer pintado para evitar parpadeos.
 * - rp-motion: el visitante no pidió reducir el movimiento.
 * - rp-seen: la intro ya se mostró en esta sesión.
 * - rp-failed: si el script principal no arrancó en 3,5 s, todo se muestra sin animación.
 */
add_action('wp_head', function () {
    if (!rp_motion_enabled()) {
        return;
    }
    ?>
<script id="rp-motion-boot">
(function(d,w){var h=d.documentElement;h.classList.add('rp-js');
try{if(!(w.matchMedia&&w.matchMedia('(prefers-reduced-motion: reduce)').matches))h.classList.add('rp-motion');}catch(e){}
try{if(w.sessionStorage.getItem('rpSeen'))h.classList.add('rp-seen');}catch(e){h.classList.add('rp-seen');}
w.setTimeout(function(){if(!h.classList.contains('rp-booted'))h.classList.add('rp-failed');},3500);
})(document,window);
</script>
    <?php
}, 1);

add_action('wp_enqueue_scripts', function () {
    if (!rp_motion_enabled()) {
        return;
    }
    wp_enqueue_style('rp-motion', RP_MOTION_URL . 'assets/motion.css', [], rp_motion_asset_version('assets/motion.css'));
    wp_enqueue_script('rp-motion', RP_MOTION_URL . 'assets/motion.js', [], rp_motion_asset_version('assets/motion.js'), [
        'in_footer' => true,
        'strategy' => 'defer',
    ]);
}, 99);

/**
 * Intro breve con el logo (solo portada y solo la primera visita de la sesión).
 * Siempre se oculta sola por CSS aunque el JavaScript falle.
 */
add_action('wp_body_open', function () {
    if (!rp_motion_enabled() || !is_front_page()) {
        return;
    }
    $logo_id = (int) get_theme_mod('custom_logo');
    $logo = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
    ?>
<div class="rp-curtain" aria-hidden="true">
  <div class="rp-curtain__inner">
    <?php if ($logo) : ?><img class="rp-curtain__logo" src="<?php echo esc_url($logo); ?>" alt="" width="96" height="96"><?php endif; ?>
    <span class="rp-curtain__word">REPTILIA</span>
    <span class="rp-curtain__bar"><i></i></span>
  </div>
</div>
    <?php
}, 1);
