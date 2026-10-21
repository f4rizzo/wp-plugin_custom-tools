<?php

/**
 * Blocco dlc/info — resa (template ACF).
 * Stessa griglia di [info_matrimonio]; senza voci non stampa nulla.
 *
 * @var array $block      Blocco
 * @var bool  $is_preview Resa nell'editor
 */

if (!defined('ABSPATH')) {
    exit;
}

$altre = [];
if (have_rows('voci')) {
    while (have_rows('voci')) {
        the_row();
        $altre[] = [get_sub_field('etichetta'), get_sub_field('valore')];
    }
}

$voci = dlc_info_voci(get_field('location'), get_field('luogo'), get_field('catering'), $altre);

if (!empty($is_preview)) {
    echo $voci ? dlc_blocco_riepilogo('Informazioni', [implode(' · ', array_map(function ($voce) { // phpcs:ignore WordPress.Security.EscapeOutput -- esc_html() nella funzione
        return $voce['label'] . ': ' . $voce['value'];
    }, $voci))]) : '';
    return;
}

if ($voci) {
    echo '<div class="dlc-contenitore">' . dlc_info_griglia_html($voci) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_html() nella funzione
}
