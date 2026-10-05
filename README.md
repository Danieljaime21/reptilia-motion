# Reptilia Motion

Plugin de WordPress hecho a medida para la web de [Reptilia Marketing](https://reptiliamarketing.online): animaciones de la portada, muro de portfolio y una calculadora de presupuestos con captación de contactos.

Pensado para el stack **WordPress + Astra + Elementor** (probado con Elementor 4.1.4). Estética cyber-noir, negro y violeta.

## Qué incluye

**Portada animada** (`assets/motion.css`, `assets/motion.js`)
- Intro con cortina, hero con efectos y revelado progresivo de secciones.
- Contadores animados, marquesina de logos con recorte automático y tarjetas con efecto de brillo.
- Sección de planes con adicionales y barra de plan personalizado.
- Respeta `prefers-reduced-motion` y tiene un modo de respaldo si el JavaScript falla.

**Muro de portfolio** (shortcode `[reptilia_portfolio]`)
- Columnas que se desplazan en sentidos opuestos, con imágenes y videos.
- Visor a pantalla completa (lightbox). Lee un `manifest.json` desde `uploads/reptilia-portfolio/`.

**Calculadora de presupuestos** (`includes/calculator.php`, `assets/calc.*`)
- Panel a pantalla completa que se abre desde cualquier enlace a `#calculadora`.
- Captación de nombre, WhatsApp y email antes de empezar, guardados en un tipo de contenido privado (menú **Presupuestos** del panel).
- Filtros por categoría, tipo de cliente (particular / pyme / empresa), pesos o dólares con cotización oficial en vivo (dolarapi.com, cacheada 12 h).
- Descuentos por volumen: 5, 10 y 15 ítems → 5 %, 10 % y 15 %.
- El precio se recalcula siempre en el servidor, nunca se confía en el navegador.
- PDF generado sin librerías externas (`includes/class-rp-pdf.php`, escritor mínimo de PDF 1.4).
- Correo con PDF adjunto al cliente y aviso al dueño; los correos salen en segundo plano para que la ventana de agradecimiento aparezca al instante.
- Exportación de todos los contactos a CSV (abre en Excel) desde el panel.

## Instalación

1. Copiar la carpeta a `wp-content/plugins/reptilia-motion/` y activarla.
2. Subir el material del portfolio a `wp-content/uploads/reptilia-portfolio/` con su `manifest.json` (no se incluye en este repositorio).
3. Configurar el envío de correos con un SMTP real (por ejemplo SureMail con una cuenta de Gmail y contraseña de aplicación); sin eso, los correos del hosting suelen no llegar.

## Configuración

El aviso de cada presupuesto llega a la opción de WordPress `rp_calc_owner_email` (si no existe, al email de administrador del sitio). Las demás constantes están al inicio de `includes/calculator.php`:

| Constante | Para qué sirve |
|---|---|
| `RP_CALC_WHATSAPP` | Número del botón "Continuar por WhatsApp" |
| `RP_CALC_USD_FALLBACK` | Cotización de respaldo si falla la consulta en vivo |

Los servicios y precios (base Pyme, en pesos) están en `rp_calc_config()`.

> Las animaciones apuntan a los IDs de elementos de Elementor de la portada de Reptilia, así que en otro sitio habría que adaptar esos selectores.

## Versiones

El historial está etiquetado: `v1.0` (animaciones) → `v1.1` (retrato y packs) → `v1.2` (planes) → `v1.3` (portfolio) → `v1.4` (calculadora) → `v1.5` (agradecimiento instantáneo y correos en segundo plano).

## Licencia

Código publicado como referencia. Todos los derechos reservados salvo que se indique lo contrario.
