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

    // Prime della meta cache: una sola query per tutti gli attachment della
    // galleria, così get_field() e wp_get_attachment_image_src() qui sotto
    // leggono dalla cache invece di interrogare il DB immagine per immagine.
    update_meta_cache('post', wp_list_pluck($gallery, 'ID'));

    ob_start();
    echo '<div class="modular-gallery">';

    foreach ($gallery as $image) {
        // Valore ACF letto una sola volta e riusato per classe + size
        $size = get_field('gallery_image_size', $image['ID']);

        $thumb_url = get_gallery_thumb_url($image['ID'], $size);
        $full_url  = $image['url'];
        $alt       = esc_attr($image['alt']);

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
 * @param int         $image_id L'ID dell'immagine in WP.
 * @param string|null $size     Valore già noto di 'gallery_image_size'
 *                              ('wide'|'tall'|'standard'|''). Se null viene
 *                              letto qui (retro-compatibilità).
 * @return string L'URL della thumb ottimizzata.
 */
function get_gallery_thumb_url($image_id, $size = null)
{
    // Recupera il valore del campo ACF solo se non è già stato passato
    if ($size === null) {
        $size = get_field('gallery_image_size', $image_id);
    }

    // Mappa i valori ACF alle dimensioni WordPress
    switch ($size) {
        case 'wide':
            $wp_size = 'gallery_wide';
            break;
        case 'tall':
            $wp_size = 'gallery_tall';
            break;
        case 'standard':
        default:        // Per valori vuoti (disposizione standard) o non riconosciuti
            $wp_size = 'large'; // Usa la dimensione standard di WordPress (~1024px)
            break;
    }

    // Recupera l'array con i dati del formato immagine
    $image_data = wp_get_attachment_image_src($image_id, $wp_size);

    // Se il formato esiste, ritorna l'URL, altrimenti l'URL originale
    return $image_data ? $image_data[0] : wp_get_attachment_url($image_id);
}
