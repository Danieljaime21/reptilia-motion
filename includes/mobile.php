<?php
/**
 * Ajustes solo para celular: encabezado con accesos rápidos, hero a pantalla completa
 * y planes deslizables. Los estilos se cargan con media query, así que en escritorio
 * ni siquiera se aplican.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', function () {
    if (!rp_motion_enabled() || !is_front_page()) {
        return;
    }
    wp_enqueue_style('rp-mobile', RP_MOTION_URL . 'assets/mobile.css', ['rp-motion'], rp_motion_asset_version('assets/mobile.css'), '(max-width: 921px)');
    wp_enqueue_script('rp-mobile', RP_MOTION_URL . 'assets/mobile.js', ['rp-motion'], rp_motion_asset_version('assets/mobile.js'), ['in_footer' => true, 'strategy' => 'defer']);
});
