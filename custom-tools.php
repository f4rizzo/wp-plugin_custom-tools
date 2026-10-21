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

// Pipeline immagini: soglia 2048px, WebP, formati, crop della gallery.
// Non usa funzioni ACF, quindi si carica sempre.
require_once plugin_dir_path(__FILE__) . 'inc/images.php';

// Comandi WP-CLI (wp custom-tools …): caricati solo da riga di comando
require_once plugin_dir_path(__FILE__) . 'inc/cli.php';

// Funzioni dei template classici del nuovo design e shortcode [dlc_instagram].
// Non usano funzioni ACF.
require_once plugin_dir_path(__FILE__) . 'shortcodes/dlc-blocchi.php';

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

    // Eventi: post type a blocchi, blocchi ACF dlc/* (blocks/) e struttura iniziale
    require_once $base . 'inc/eventi.php';

    // Correttivo temporaneo ACF 6.8 / ACFE 0.9.2.3 (checkbox), vedi file
    require_once $base . 'inc/compat-acfe.php';

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
