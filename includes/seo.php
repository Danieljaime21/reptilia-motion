<?php
/**
 * SEO local: sección "Marketing digital en Rosario" con preguntas frecuentes
 * y datos estructurados (negocio local + FAQ) para la portada.
 *
 * El texto de las preguntas vive en un solo lugar (rp_seo_faq) y alimenta tanto
 * lo que se ve en la página como el marcado FAQPage que lee Google, así nunca
 * pueden quedar desparejos.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Ciudades y zonas de cobertura (Rosario y Gran Rosario). */
function rp_seo_zones(): array
{
    return ['Rosario', 'Funes', 'Roldán', 'Granadero Baigorria', 'Villa Gobernador Gálvez', 'Pérez', 'Capitán Bermúdez', 'San Lorenzo'];
}

/** Servicios que se ofrecen (mismos nombres que la calculadora y los planes). */
function rp_seo_services(): array
{
    return [
        ['Gestión de redes sociales', 'Calendario de contenidos, diseño de publicaciones e historias, copys y programación, con reporte mensual.'],
        ['Publicidad en Meta Ads', 'Campañas en Instagram y Facebook para llegar a clientes de tu zona, con seguimiento y ajustes.'],
        ['Reels y edición de video', 'Contenido en video pensado para redes, con guion y edición.'],
        ['Diseño de piezas', 'Placas, carruseles y creatividades con la identidad de tu marca.'],
        ['Estrategia y plan de contenidos', 'Auditoría de tus redes y un plan claro de qué publicar y para quién.'],
    ];
}

/** Preguntas frecuentes (se muestran y se publican como FAQPage). */
function rp_seo_faq(): array
{
    return [
        [
            '¿Cuánto cuesta un community manager en Rosario?',
            'Mis planes mensuales de gestión de redes van desde $150.000 (Plan Nido) hasta $250.000 (Plan Evolución). Si necesitás algo puntual, como un pack de posteos o reels, podés armar tu presupuesto a medida en la calculadora de esta página y recibirlo en PDF.',
        ],
        [
            '¿Trabajan con negocios de Rosario y alrededores?',
            'Sí. Mi foco está en Rosario y Gran Rosario: Funes, Roldán, Granadero Baigorria, Villa Gobernador Gálvez, Pérez y zonas cercanas. Trabajar cerca me permite conocer mejor tu negocio y tu público. Si estás en otra ciudad, escribime y lo vemos.',
        ],
        [
            '¿Qué incluye la gestión de redes sociales?',
            'Según el plan: calendario mensual de contenidos, diseño de publicaciones e historias, reels, copys y programación, reporte mensual y rondas de ajustes. Cada plan detalla cantidades exactas para que sepas qué recibís.',
        ],
        [
            '¿Hacen publicidad en Instagram y Facebook?',
            'Sí. Configuro y gestiono campañas en Meta Ads (Instagram y Facebook). Los planes incluyen campañas de ads: 1 en el Plan Nido, hasta 2 en el Plan Muda y hasta 4 en el Plan Evolución. También se pueden contratar por separado.',
        ],
        [
            '¿Cómo empiezo a trabajar con Reptilia?',
            'Elegí uno de los planes o armá tu presupuesto a medida con la calculadora. Dejás tus datos, te llega el detalle en PDF a tu correo y seguimos la conversación por WhatsApp para ponerlo en marcha.',
        ],
    ];
}

/** HTML de la sección: cobertura, servicios y preguntas frecuentes. */
add_shortcode('reptilia_rosario', function () {
    $zones = '';
    foreach (rp_seo_zones() as $z) {
        $zones .= '<li>' . esc_html($z) . '</li>';
    }
    $services = '';
    foreach (rp_seo_services() as [$name, $desc]) {
        $services .= '<li><h3>' . esc_html($name) . '</h3><p>' . esc_html($desc) . '</p></li>';
    }
    $faq = '';
    foreach (rp_seo_faq() as [$q, $a]) {
        $faq .= '<details class="rp-faq__item"><summary>' . esc_html($q) . '</summary><p>' . esc_html($a) . '</p></details>';
    }
    return '<div class="rp-local">'
        . '<ul class="rp-local__zones" aria-label="Zonas donde trabajamos">' . $zones . '</ul>'
        . '<ul class="rp-local__services">' . $services . '</ul>'
        . '<div class="rp-faq"><h3 class="rp-faq__title">Preguntas frecuentes</h3>' . $faq . '</div>'
        . '</div>';
});

/** Datos estructurados: negocio local + preguntas frecuentes (solo en la portada). */
add_action('wp_head', function () {
    if (!is_front_page()) {
        return;
    }
    $areas = array_map(fn($z) => ['@type' => 'City', 'name' => $z], rp_seo_zones());
    $areas[] = ['@type' => 'AdministrativeArea', 'name' => 'Gran Rosario, Santa Fe, Argentina'];

    $business = [
        '@context' => 'https://schema.org',
        '@type' => 'ProfessionalService',
        '@id' => home_url('/#negocio'),
        'name' => 'Reptilia Marketing',
        'url' => home_url('/'),
        'description' => 'Marketing digital para pymes en Rosario y Gran Rosario: gestión de redes sociales, publicidad en Meta Ads, diseño y reels.',
        'telephone' => '+' . RP_CALC_WHATSAPP,
        'email' => 'reptiliamarketing@gmail.com',
        'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Rosario', 'addressRegion' => 'Santa Fe', 'addressCountry' => 'AR'],
        'areaServed' => $areas,
        'serviceType' => array_map(fn($s) => $s[0], rp_seo_services()),
        'sameAs' => [
            'https://www.instagram.com/reptilia.mkt/',
            'https://www.facebook.com/profile.php?id=61591590301124',
            'https://www.behance.net/danieljaime4',
        ],
    ];

    $faq = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn($f) => [
            '@type' => 'Question',
            'name' => $f[0],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
        ], rp_seo_faq()),
    ];

    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG;
    echo '<script type="application/ld+json" id="rp-local-business">' . wp_json_encode($business, $flags) . '</script>' . "\n";
    echo '<script type="application/ld+json" id="rp-faq">' . wp_json_encode($faq, $flags) . '</script>' . "\n";
}, 30);

/** Estilos de la sección (solo portada). */
add_action('wp_enqueue_scripts', function () {
    if (!rp_motion_enabled() || !is_front_page()) {
        return;
    }
    wp_enqueue_style('rp-seo', RP_MOTION_URL . 'assets/seo.css', ['rp-motion'], rp_motion_asset_version('assets/seo.css'));
});
