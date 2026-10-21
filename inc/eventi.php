<?php

/**
 * =======================================================
 * Eventi: pagina da form ACF (prova per i matrimoni)
 * =======================================================
 *
 * Il post type "evento" (ACF JSON, come gli altri) è la prova della
 * nuova pagina dei matrimoni. Si compila solo con il gruppo ACF
 * "Dettagli evento", a tutta pagina (editor nascosto), diviso in sezioni:
 * Coppia, Foto principali, Intro, Informazioni, Racconto.
 *
 * I campi hanno gli stessi nomi dei matrimoni (nome_coniuge_1/2,
 * img_header_matrimonio, intro_matrimonio, img_hero_matrimonio,
 * matrimonio_location…, info_matrimonio), così card, shortcode e un futuro
 * passaggio dei matrimoni li trovano. Al posto delle due parti fisse c'è
 * il ripetitore "capitoli" (galleria + layout + testo). La foto della
 * card è un campo del form salvato come immagine in evidenza (_thumbnail_id).
 *
 * La resa è nel tema (single-evento.php). Il markup delle sezioni è
 * quello del template nativo dei matrimoni: funzioni dlc_sezione_*()
 * in shortcodes/dlc-blocchi.php.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Foto di copertina di un evento: il campo "Foto di copertina",
 * altrimenti l'immagine in evidenza.
 *
 * @param int $post_id
 * @return int 0 se non c'è
 */
function dlc_evento_copertina($post_id)
{
    return (int) get_post_meta($post_id, 'img_header_matrimonio', true) ?: (int) get_post_thumbnail_id($post_id);
}

/**
 * Un capitolo del racconto: galleria e testo dopo le foto.
 * Da chiamare dentro il ciclo have_rows('capitoli').
 * Tutte le gallerie della pagina condividono la lightbox ("evento").
 *
 * @return string
 */
function dlc_evento_capitolo_html()
{
    $html = '';
    $ids  = array_filter(array_map('intval', (array) get_sub_field('galleria', false)));

    if ($ids) {
        $atts   = ['ids' => implode(',', $ids), 'slideshow' => 'evento'];
        $layout = (string) get_sub_field('layout');

        if ($layout !== '' && $layout !== 'modulare') {
            $atts['layout'] = $layout;
            $colonne = (int) get_sub_field('colonne');
            if ($colonne && in_array($layout, ['griglia', 'masonry'], true)) {
                $atts['colonne'] = $colonne;
            }
        }

        if (get_sub_field('didascalie')) {
            $atts['didascalie'] = 'si';
        }

        $html .= dlc_sezione_galleria_html($atts);
    }

    // Valore grezzo, come il template nativo: paragrafi con wpautop()
    return $html . dlc_sezione_testo_html((string) get_sub_field('testo', false));
}

/**
 * Senza foto della card, l'immagine in evidenza (card, condivisioni)
 * è la copertina.
 */
add_action('acf/save_post', function ($post_id) {
    if (get_post_type($post_id) !== 'evento' || get_post_thumbnail_id($post_id)) {
        return;
    }

    $copertina = (int) get_post_meta($post_id, 'img_header_matrimonio', true);
    if ($copertina) {
        set_post_thumbnail($post_id, $copertina);
    }
}, 20);

/**
 * Preload della foto di copertina (elemento LCP). Stessi srcset e sizes
 * dell'<img> di dlc_hero_html(): il browser sceglie lo stesso file e lo
 * scarica una sola volta, prima ancora di leggere il CSS.
 */
add_action('wp_head', function () {
    if (!is_singular('evento')) {
        return;
    }

    $foto   = dlc_evento_copertina(get_queried_object_id());
    $src    = $foto ? wp_get_attachment_image_src($foto, 'full') : false;
    $srcset = $foto ? wp_get_attachment_image_srcset($foto, 'full') : false;

    if (!$src) {
        return;
    }

    printf(
        '<link rel="preload" as="image" href="%s"%s imagesizes="100vw" fetchpriority="high">' . "\n",
        esc_url($src[0]),
        $srcset ? ' imagesrcset="' . esc_attr($srcset) . '"' : ''
    );
}, 2);

