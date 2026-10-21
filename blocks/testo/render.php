<?php

/**
 * Blocco dlc/testo — resa (template ACF).
 * Valore grezzo del campo (come il template nativo): paragrafi con wpautop().
 *
 * @var array $block      Blocco
 * @var bool  $is_preview Resa nell'editor
 */

if (!defined('ABSPATH')) {
    exit;
}

$testo = (string) get_field('testo', false, false);

if (!empty($is_preview)) {
    echo $testo !== '' ? dlc_blocco_riepilogo('Testo', [dlc_blocco_estratto($testo)]) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_html() nella funzione
    return;
}

echo dlc_sezione_testo_html($testo); // phpcs:ignore WordPress.Security.EscapeOutput -- wp_kses_post() nella funzione
