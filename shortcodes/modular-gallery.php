<?php

/**
 * =======================================================
 * Shortcode: modular_gallery
 * =======================================================
 * Mostra una galleria immagini con supporto lightbox.
 * Parametri (serve "field" oppure "ids"):
 * - field: nome di un campo ACF di tipo galleria (es. 'galleria_matrimonio_parte_1')
 * - ids: elenco di ID di immagini, nell'ordine voluto (es. "12,15,18"),
 *        per gallerie scelte a mano dentro articoli e pagine
 * - post: ID del post da cui leggere "field" (opzionale, default: post corrente)
 * - slideshow: nome del gruppo lightbox (opzionale, default: "gallery")
 *
 * Esempi:
 *   [modular_gallery field="galleria_matrimonio_parte_1" slideshow="matrimonio"]
 *   [modular_gallery ids="745,746,747" slideshow="articolo"]
 *   [modular_gallery field="galleria_matrimonio_parte_2" post="733"]
 *
 * Layout (vedi sass/shortcodes/_modular-gallery.scss nel tema):
 * - sotto i 1440px: una colonna, larga al massimo 1138px
 * - dai 1440px:     due colonne da 564px; 'wide' occupa due colonne,
 *                   'tall' due righe (cella ≈ 3:4)
 *
 * Ogni immagine esce con srcset/sizes, quindi il browser sceglie il file
 * adatto allo schermo. Quando l'ingombro scelto dalla cliente ('tall' o
 * 'wide') ha un orientamento diverso dalla foto, c'è un <picture> che usa
 * il ritaglio generato al caricamento (inc/images.php):
 *
 *   sotto i 1440px  foto intera, nel suo orientamento originale
 *                   (una colonna: l'ingombro non si applica)
 *   da 1440px       ritaglio, nella cella a due righe/colonne
 * =======================================================
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Breakpoint e larghezze della galleria, allineati al CSS del tema.
 */
const CUSTOM_TOOLS_GALLERY_BREAKPOINT = 1440; // due colonne da qui in su
const CUSTOM_TOOLS_GALLERY_WIDTH      = 1138; // larghezza utile (max-width 1158 - padding 2x10)
const CUSTOM_TOOLS_GALLERY_COLUMN     = 564;  // (1138 - gap 10) / 2

add_shortcode('modular_gallery', function ($atts) {
    $atts = shortcode_atts([
        'field'     => '',        // es. 'galleria_matrimonio_parte_1'
        'ids'       => '',        // es. '12,15,18' (alternativa a field)
        'post'      => 0,         // post da cui leggere field (default: corrente)
        'slideshow' => 'gallery', // nome usato nella lightbox
    ], $atts);

    if (empty($atts['field']) && empty($atts['ids'])) {
        return '<p>Campo ACF non specificato.</p>';
    }

    if (!empty($atts['ids'])) {
        // Foto scelte a mano: solo immagini esistenti, nell'ordine indicato
        $gallery = array_filter(array_map('intval', explode(',', (string) $atts['ids'])), 'wp_attachment_is_image');
    } else {
        $post_id = (int) $atts['post'] ?: get_the_ID();
        $gallery = get_field($atts['field'], $post_id, false); // solo gli ID, senza formattare 50 array immagine
    }

    if (empty($gallery) || !is_array($gallery)) {
        return '<p>Nessuna immagine disponibile.</p>';
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $gallery))));

    // Prime della cache: una query per i post e una per i meta di tutti gli allegati,
    // invece di due query per ogni immagine.
    _prime_post_caches($ids, false, true);

    $html = '';
    foreach ($ids as $id) {
        $html .= custom_tools_gallery_item($id, $atts['slideshow']);
    }

    return $html ? '<div class="modular-gallery">' . $html . '</div>' : '';
});


/**
 * =======================================================
 * Funzione Helper: custom_tools_gallery_item
 * =======================================================
 * Markup di una singola immagine della galleria.
 *
 * @param int    $id        ID dell'allegato.
 * @param string $slideshow Gruppo della lightbox.
 * @return string
 */
function custom_tools_gallery_item($id, $slideshow)
{
    $metadata = wp_get_attachment_metadata($id);

    if (empty($metadata['width']) || empty($metadata['height'])) {
        return '';
    }

    $layout = custom_tools_gallery_layout($id);
    $bp     = CUSTOM_TOOLS_GALLERY_BREAKPOINT;
    $full   = CUSTOM_TOOLS_GALLERY_WIDTH;
    $col    = CUSTOM_TOOLS_GALLERY_COLUMN;

    // Larghezza mostrata (sizes): 'wide' resta a tutta larghezza anche su due colonne
    $sizes = $layout === 'wide'
        ? sprintf('(min-width: %1$dpx) %1$dpx, calc(100vw - 20px)', $full)
        : sprintf('(min-width: %dpx) %dpx, (min-width: %dpx) %dpx, calc(100vw - 20px)', $bp, $col, $full + 20, $full);

    // Testo alternativo: quello dell'immagine; se la cliente non l'ha compilato,
    // il titolo del matrimonio (meglio di un alt vuoto, per accessibilità e SEO)
    $alt = trim((string) get_post_meta($id, '_wp_attachment_image_alt', true)) ?: get_the_title();

    $attr = [
        'class'    => 'modular-gallery__img',
        'sizes'    => $sizes,
        'alt'      => $alt,
        'loading'  => 'lazy',
        'decoding' => 'async',
    ];

    // Allineamento scelto dalla cliente: vale quando il CSS adatta la foto alla
    // cella senza un ritaglio dedicato (es. foto verticale in una cella 'tall')
    $object_position = custom_tools_gallery_object_position(custom_tools_gallery_crop_position($id));
    if ($layout && $object_position) {
        $attr['style'] = 'object-position: ' . $object_position;
    }

    $img = wp_get_attachment_image($id, 'large', false, $attr);

    if (!$img) {
        return '';
    }

    $source = custom_tools_gallery_crop_source($id, $metadata, $layout);
    $media  = $source ? '<picture>' . $source . $img . '</picture>' : $img;

    $class = 'modular-gallery__item' . ($layout ? ' ' . $layout : '');

    return sprintf(
        '<a href="%s" class="%s" data-elementor-open-lightbox="yes" data-elementor-lightbox-slideshow="%s" rel="lightbox">%s</a>',
        esc_url(custom_tools_large_image_url($id)),
        esc_attr($class),
        esc_attr($slideshow),
        $media
    );
}


/**
 * =======================================================
 * Funzione Helper: custom_tools_gallery_crop_source
 * =======================================================
 * <source> con il ritaglio per l'ingombro scelto, valido dai 1440px (dove la
 * cella 'tall' o 'wide' ha davvero quella forma). Stringa vuota se l'immagine non ha
 * ritagli per quell'ingombro (la foto ha già l'orientamento giusto): in quel
 * caso il CSS adatta la foto con object-fit.
 *
 * @param int    $id
 * @param array  $metadata
 * @param string $layout
 * @return string
 */
function custom_tools_gallery_crop_source($id, $metadata, $layout)
{
    if (!$layout) {
        return '';
    }

    $names = custom_tools_gallery_crop_sizes($metadata, $layout);

    if (!$names) {
        return '';
    }

    $base   = trailingslashit(dirname(wp_get_attachment_url($id)));
    $srcset = [];
    foreach ($names as $name) {
        $size     = $metadata['sizes'][$name];
        $srcset[] = $base . rawurlencode($size['file']) . ' ' . (int) $size['width'] . 'w';
    }
    $srcset = implode(', ', $srcset);

    $largest = $metadata['sizes'][end($names)];
    $desktop = $layout === 'tall' ? CUSTOM_TOOLS_GALLERY_COLUMN : CUSTOM_TOOLS_GALLERY_WIDTH;

    return sprintf(
        '<source media="%s" srcset="%s" sizes="%s" type="%s" width="%d" height="%d">',
        esc_attr('(min-width: ' . CUSTOM_TOOLS_GALLERY_BREAKPOINT . 'px)'),
        esc_attr($srcset),
        esc_attr($desktop . 'px'),
        esc_attr($largest['mime-type'] ?? 'image/jpeg'),
        (int) $largest['width'],
        (int) $largest['height']
    );
}


/**
 * =======================================================
 * Funzione Helper: get_gallery_thumb_url
 * =======================================================
 * Ritorna l'URL di un'immagine della Gallery nel formato adatto al suo
 * ingombro (campo ACF 'gallery_image_size'). Non più usata dallo shortcode,
 * che ora stampa <img> con srcset; resta per compatibilità con eventuali
 * template o snippet che la chiamano.
 *
 * - 'wide':     formato 'gallery_1200' (prima 'gallery_wide', 1480px)
 * - 'tall':     ritaglio verticale 900x1200 se esiste (prima 'gallery_tall', 0x1200)
 * - 'standard': 'large' (~1024px)
 *
 * @param int         $image_id L'ID dell'immagine in WP.
 * @param string|null $size     Valore già noto di 'gallery_image_size'
 *                              ('wide'|'tall'|'standard'|''). Se null viene letto qui.
 * @return string L'URL della thumb ottimizzata.
 */
function get_gallery_thumb_url($image_id, $size = null)
{
    if ($size === null) {
        $size = get_post_meta($image_id, 'gallery_image_size', true);
    }

    $metadata = wp_get_attachment_metadata($image_id);

    switch ($size) {
        case 'wide':
            $candidates = ['gallery_1200', 'gallery_wide', 'large'];
            break;
        case 'tall':
            $crops      = custom_tools_gallery_crop_sizes(is_array($metadata) ? $metadata : [], 'tall');
            $candidates = array_merge(array_reverse(array_slice($crops, 0, 2)), ['gallery_tall', 'large']);
            break;
        case 'standard':
        default:
            $candidates = ['large'];
            break;
    }

    foreach ($candidates as $wp_size) {
        if (!empty($metadata['sizes'][$wp_size])) {
            $image_data = wp_get_attachment_image_src($image_id, $wp_size);
            if ($image_data) {
                return $image_data[0];
            }
        }
    }

    return (string) wp_get_attachment_url($image_id);
}
