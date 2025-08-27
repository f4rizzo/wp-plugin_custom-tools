<?php


/**
 * =======================================================
 * Shortcode: modular_gallery
 * =======================================================
 * Mostra una galleria immagini ACF con supporto lightbox.
 * Parametri:
 * - field: nome del campo ACF (obbligatorio)
 * - slideshow: nome del gruppo lightbox (opzionale, default: "gallery")
 * =======================================================
 */
add_shortcode('modular_gallery', function ($atts) {
    global $post;

    $atts = shortcode_atts([
        'field' => '',             // es. 'galleria_matrimonio_parte_1'
        'slideshow' => 'gallery',  // nome usato nella lightbox
    ], $atts);

    if (empty($atts['field'])) {
        return '<p>Campo ACF non specificato.</p>';
    }

    $gallery = get_field($atts['field'], $post->ID);

    if (empty($gallery)) {
        return '<p>Nessuna immagine disponibile.</p>';
    }

    ob_start();
    echo '<div class="modular-gallery">';

    foreach ($gallery as $image) {
        // URL ottimizzato della thumbnail
        $thumb_url = get_gallery_thumb_url($image['ID']);

        // URL originale per la lightbox
        $full_url = $image['url'];

        // ALT text dell'immagine
        $alt = esc_attr($image['alt']);

        // Classe CSS per gestire layout delle immagini
        $size = get_field('gallery_image_size', $image['ID']);

        $class = 'modular-gallery__item';
        switch ($size) {
            case 'wide':
                $class .= ' wide';
                break;
            case 'tall':
                $class .= ' tall';
                break;
            case 'standard':
            default:
                // Mantiene solo la classe base per disposizione standard
                break;
        }

        // Output HTML
        echo "<a href='{$full_url}' class='{$class}' data-elementor-open-lightbox='yes' data-elementor-lightbox-slideshow='{$atts['slideshow']}' rel='lightbox'>
            <img src='{$thumb_url}' alt='{$alt}' loading='lazy' />
        </a>";
    }

    echo '</div>';
    return ob_get_clean();
});



/**
 * =======================================================
 * Funzione Helper: get_gallery_thumb_url
 * =======================================================
 * Ritorna l'URL ottimizzato di un'immagine della Gallery
 * in base al campo ACF 'gallery_image_size'.
 * 
 * Vengono generate delle immagini ad-hoc per i formati gestiti:
 * - 'wide': usa 'gallery_wide' (1480x0px senza crop)
 * - 'tall': usa 'gallery_tall' (0x1200px senza crop)
 * - 'standard': usa 'large' (~1024px, proporzioni originali)
 *
 * @param int $image_id L'ID dell'immagine in WP.
 * @return string L'URL della thumb ottimizzata.
 */
function get_gallery_thumb_url($image_id)
{
    // Recupera il valore specifico del campo ACF
    $gallery_size = get_field('gallery_image_size', $image_id);

    // Mappa i valori ACF alle dimensioni WordPress
    switch ($gallery_size) {
        case 'wide':
            $size = 'gallery_wide';
            break;
        case 'tall':
            $size = 'gallery_tall';
            break;
        case 'standard':
        default:        // Per valori vuoti (disposizione standard) o non riconosciuti
            $size = 'large'; // Usa la dimensione standard di WordPress (~1024px)
            break;
    }

    // Recupera l'array con i dati del formato immagine
    $image_data = wp_get_attachment_image_src($image_id, $size);

    // Se il formato esiste, ritorna l'URL, altrimenti l'URL originale
    return $image_data ? $image_data[0] : wp_get_attachment_url($image_id);
}