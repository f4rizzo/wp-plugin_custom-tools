<?php

/**
 * Blocco dlc/foto-larga — resa (template ACF).
 *
 * @var array $block      Blocco
 * @var bool  $is_preview Resa nell'editor
 */

if (!defined('ABSPATH')) {
    exit;
}

$immagine = (int) get_field('immagine');

if (!empty($is_preview)) {
    echo $immagine ? dlc_blocco_riepilogo('Foto larga', [get_the_title($immagine)], [$immagine]) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_html() nella funzione
    return;
}

echo dlc_sezione_foto_larga_html($immagine); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML di wp_get_attachment_image()
