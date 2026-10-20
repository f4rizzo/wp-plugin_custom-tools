<?php

/**
 * ======================================================================================
 * Shortcode per mostrare una thumb cliccabile che apre l'immagine originale in lightbox
 * ======================================================================================
 *
 * Questo shortcode viene utilizzato all'interno del Loop Item Template di Elementor Pro
 * (via widget "Shortcode") e genera il markup HTML necessario per:
 *
 *  - Mostrare l'immagine 'immagine_anteprima_portfolio' (formato 'portfolio_thumb' + srcset)
 *  - Aprire nella lightbox nativa di Elementor l'immagine 'immagine_originale_portfolio'
 *  - Consentire la navigazione tra le immagini con lo stesso gruppo lightbox
 *
 * Requisiti:
 *  - Elementor Pro (per Loop Grid e supporto lightbox nativo)
 *  - ACF Pro ('immagine_anteprima_portfolio' restituisce un ARRAY,
 *    'immagine_originale_portfolio' restituisce un URL)
 *  - Le immagini devono essere filtrate tramite la query Elementor con meta_query su 'mostra_in_home'
 *
 * Come usare:
 *  - Inserisci lo shortcode [portfolio_lightbox_item] nel widget "Shortcode" all'interno del Loop Item Template
 *  - Elementor eseguirà la funzione PHP e inietterà il markup dinamico nel DOM
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Larghezza mostrata delle anteprime nel Loop Grid della home:
 * 3 colonne da 454px sul desktop, 2 colonne su tablet e mobile.
 */
const CUSTOM_TOOLS_PORTFOLIO_SIZES = '(min-width: 1025px) 454px, 50vw';

function portfolio_lightbox_shortcode()
{
    // Recupera i campi ACF immagine
    $img_anteprima = get_field('immagine_anteprima_portfolio');
    $img_originale = get_field('immagine_originale_portfolio');

    // Verifica che entrambi i campi siano presenti
    if (!$img_anteprima || !$img_originale) {
        return ''; // Se uno dei due è vuoto, non genera nulla
    }

    // === 1. Anteprima: <img> responsive con srcset, width/height e alt dell'immagine ===
    $preview_id = is_array($img_anteprima) ? (int) ($img_anteprima['ID'] ?? 0) : (int) $img_anteprima;
    $title      = get_the_title();

    $img = $preview_id ? wp_get_attachment_image($preview_id, 'portfolio_thumb', false, [
        'sizes'    => CUSTOM_TOOLS_PORTFOLIO_SIZES,
        'alt'      => trim((string) get_post_meta($preview_id, '_wp_attachment_image_alt', true)) ?: $title,
        'loading'  => 'lazy',
        'decoding' => 'async',
    ]) : '';

    if (!$img) {
        return '';
    }

    // === 2. Lightbox: formato 2048 dell'immagine originale (l'originale può pesare diversi MB) ===
    $url_originale = is_array($img_originale) ? ($img_originale['url'] ?? '') : (string) $img_originale;
    $original_id   = is_array($img_originale) ? (int) ($img_originale['ID'] ?? 0) : attachment_url_to_postid($url_originale);

    if ($original_id) {
        $url_originale = custom_tools_large_image_url($original_id);
    }

    // === 3. Output: link alla lightbox con l'anteprima ===
    return sprintf(
        '<a href="%s" data-elementor-open-lightbox="yes" data-elementor-lightbox-slideshow="portfolio" rel="lightbox">%s</a>',
        esc_url($url_originale),
        $img
    );
}

// === 4. Registra lo shortcode ===
// Questo rende utilizzabile [portfolio_lightbox_item] ovunque nel sito
add_shortcode('portfolio_lightbox_item', 'portfolio_lightbox_shortcode');
