<?php


/**
 * ======================================================================================
 * 2. Shortcode per mostrare una thumb cliccabile che apre l'immagine originale in Fancybox
 * ======================================================================================
 *
 * Questo shortcode viene utilizzato all'interno del Loop Item Template di Elementor Pro
 * (via widget "Shortcode") e genera il markup HTML necessario per:
 *
 *  - Mostrare l'immagine 'immagine_anteprima_portfolio' (in formato custom, es. 'portfolio_thumb')
 *  - Aprire in lightbox nativa (Fancybox 3) l'immagine 'immagine_originale_portfolio'
 *  - Consentire la navigazione tra le immagini con lo stesso gruppo Fancybox
 *
 * Requisiti:
 *  - Elementor Pro (per Loop Grid e supporto lightbox nativo)
 *  - ACF Pro (con i campi immagine impostati per restituire un ARRAY)
 *  - Le immagini devono essere filtrate tramite la query Elementor con meta_query su 'mostra_in_home'
 *
 * Come usare:
 *  - Inserisci lo shortcode [portfolio_lightbox_item] nel widget "Shortcode" all'interno del Loop Item Template
 *  - Elementor eseguirà la funzione PHP e inietterà il markup dinamico nel DOM
 */

function portfolio_lightbox_shortcode()
{
    // Recupera i campi ACF immagine come ARRAY
    $img_anteprima = get_field('immagine_anteprima_portfolio');
    $img_originale = get_field('immagine_originale_portfolio');

    // Verifica che entrambi i campi siano presenti
    if (!$img_anteprima || !$img_originale) {
        return ''; // Se uno dei due è vuoto, non genera nulla
    }

    // === 1. Specifica la size registrata via add_image_size (thumb personalizzata) ===
    $thumb_size = 'portfolio_thumb'; // Cambia con la tua size, es: 'portfolio_thumb_due'

    // === 2. Estrai l’URL corretto dal campo immagine (array) ===
    $url_anteprima = is_array($img_anteprima) && isset($img_anteprima['sizes'][$thumb_size])
        ? $img_anteprima['sizes'][$thumb_size]
        : (is_array($img_anteprima) ? $img_anteprima['url'] : $img_anteprima);

    $url_originale = is_array($img_originale) ? $img_originale['url'] : $img_originale;

    // === 3. Crea l'output HTML con link in lightbox e immagine preview ===
    ob_start();
?>
    <a href="<?php echo esc_url($url_originale); ?>"
        data-elementor-open-lightbox="yes"
        data-elementor-lightbox-slideshow="portfolio"
        rel="lightbox">
        <img src="<?php echo esc_url($url_anteprima); ?>" alt="Anteprima Portfolio" />
    </a>
<?php
    return ob_get_clean();
}

// === 4. Registra lo shortcode ===
// Questo rende utilizzabile [portfolio_lightbox_item] ovunque nel sito
add_shortcode('portfolio_lightbox_item', 'portfolio_lightbox_shortcode');
