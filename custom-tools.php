<?php

/**
 * Plugin Name: Custom Tools
 * Description: Plugin personalizzato con CPT, colonne admin, e funzionalità extra per il sito.
 * Author: Fabrizio
 * Version: 1.0
 */

// Sicurezza: impedisce l'accesso diretto al file
if (!defined('ABSPATH')) exit;

// Controllo dipendenze (ACF PRO / Elementor) + avviso in bacheca
require_once plugin_dir_path(__FILE__) . 'inc/dependencies.php';

/**
 * =======================================================
 * Caricamento delle funzionalità
 * =======================================================
 * CPT, colonne admin e shortcode usano get_field() di ACF: li carichiamo
 * solo se ACF è attivo, così un ambiente senza ACF non genera errori fatali.
 * L'avviso all'utente è gestito da inc/dependencies.php.
 *
 * Hook su plugins_loaded per non dipendere dall'ordine di caricamento dei plugin.
 */
add_action('plugins_loaded', function () {
    if (!custom_tools_has_acf()) {
        return;
    }

    $base = plugin_dir_path(__FILE__);
    require_once $base . 'inc/register-cpt.php';
    require_once $base . 'inc/admin-columns.php';
    require_once $base . 'shortcodes/matrimonio.php';
    require_once $base . 'shortcodes/portfolio.php';
    require_once $base . 'shortcodes/modular-gallery.php';

    // require_once $base . 'inc/acf-fields.php'; // se ti serve in futuro
});


/**
 * =======================================================
 * ACF Export in Json
 * =======================================================
 * Esporta le impostazioni ACF in un file JSON così da
 * archiviare tutto nella repo del progetto
 */
add_filter('acf/settings/save_json', function () {
    $path = plugin_dir_path(__FILE__) . 'assets/acf-json';

    if (!file_exists($path)) {
        mkdir($path, 0755, true);
    }

    return $path;
});

add_filter('acf/settings/load_json', function ($paths) {
    $paths[] = plugin_dir_path(__FILE__) . 'assets/acf-json';
    return $paths;
});


/**
 * ======================================================================================
 * 1. Registra nuovi formati immagine
 * ======================================================================================
 *
 * Questi formati vengono usati per ottimizzare il caricamento
 * delle immagini nelle gallerie:
 * - portfolio_thumb: thumbnail da 454px per anteprime piccole.
 * - gallery_wide: formato wide 800x400px con crop.
 * - gallery_tall: formato tall 400x800px con crop.
 */

add_action('after_setup_theme', function () {

    // Thumbnail per piccole anteprime (mantiene proporzioni, larghezza max 454px)
    add_image_size('portfolio_thumb', 454, 0, false); // true = crop / false = proporzioni originali

    // Formati specifici per modular gallery
    add_image_size('gallery_wide', 1480, 0, false);  // Wide format - immagine su due colonne
    add_image_size('gallery_tall', 0, 1200, false);  // Tall format - immagine su due righe

    // Formati futuri (commentati per ora)
    // add_image_size('gallery_square', 400, 400, true); // Quadrato
    // add_image_size('gallery_portrait', 400, 600, true); // Ritratto
    // add_image_size('gallery_landscape', 600, 400, true); // Paesaggio
});


// Mostrare i formati nel Media Picker
add_filter('image_size_names_choose', 'aggiungi_dimensioni_personalizzate');
function aggiungi_dimensioni_personalizzate($sizes)
{
    return array_merge($sizes, [
        'portfolio_thumb'   => 'Portfolio Thumb - 454px',
        'gallery_wide'      => 'Gallery Wide - 1480',
        'gallery_tall'      => 'Gallery Tall - 1200h',
    ]);
}
