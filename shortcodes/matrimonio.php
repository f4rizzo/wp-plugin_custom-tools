<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * =======================================================
 * Shortcode: Title Matrimonio
 * =======================================================
 * Mostra un titolo che dinamicamente inserisce il nome
 * dei coniugi preceduto da Il matrimonio di
 * 
 * Shortcode = [matrimonio_titolo]
 * 
 */

function matrimonio_titolo_shortcode()
{
    $nome1 = get_field('nome_coniuge_1', get_the_ID());
    $nome2 = get_field('nome_coniuge_2', get_the_ID());

    if ($nome1 && $nome2) {
        return 'Il matrimonio di<br>' .
            '<span class="header-title__secondRow">' .
            esc_html($nome1) . ' e ' . esc_html($nome2) .
            '</span>';
    } else {
        return 'Il matrimonio';
    }
}
add_shortcode('matrimonio_titolo', 'matrimonio_titolo_shortcode');


/**
 * =======================================================
 * Shortcode: [nomi_coniugi]
 * =======================================================
 * Mostra il nome dei coniugi
 * Campi ACF:
 * - nome_coniuge_1
 * - nome_coniuge_2
 * =======================================================
 */

function mostra_nomi_coniugi_shortcode()
{
    // Recupera i valori dai campi ACF
    $nome1 = get_field('nome_coniuge_1');
    $nome2 = get_field('nome_coniuge_2');

    // Controlla che entrambi i campi abbiano un valore
    if ($nome1 && $nome2) {
        // Restituisce la stringa formattata
        return esc_html($nome1) . ' e ' . esc_html($nome2);
    }

    // Se uno dei due manca, non restituisce nulla
    return '';
}
add_shortcode('nomi_coniugi', 'mostra_nomi_coniugi_shortcode');


/**
 * =======================================================
 * Shortcode: Grid Dettagli Matrimonio
 * =======================================================
 * Struttura una griglia che mostra i dettagli del matrimonio
 * 
 * Shortcode = [info_matrimonio]
 * 
 */
function mostra_info_matrimonio($atts)
{
    // Voci libere del repeater (etichetta + valore)
    $altre = [];
    if (have_rows('info_matrimonio')) {
        while (have_rows('info_matrimonio')) {
            the_row();
            $altre[] = [get_sub_field('etichetta_info_matrimonio'), get_sub_field('valore_info_matrimonio')];
        }
    }

    // Markup condiviso con il blocco dlc/info (shortcodes/dlc-blocchi.php)
    return dlc_info_griglia_html(dlc_info_voci(
        get_field('matrimonio_location'),
        get_field('matrimonio_luogo'),
        get_field('matrimonio_catering'),
        $altre
    ));
}
// Il file viene caricato solo con ACF attivo (vedi custom-tools.php)
add_shortcode('info_matrimonio', 'mostra_info_matrimonio');
