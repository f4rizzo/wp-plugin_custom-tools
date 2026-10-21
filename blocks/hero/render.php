<?php

/**
 * Blocco dlc/hero — resa (template ACF).
 * Nomi dalla barra laterale dell'evento (nome_coniuge_1/2, gli stessi
 * campi dei matrimoni); foto: quella del blocco, se scelta, altrimenti
 * l'immagine in evidenza.
 *
 * @var array $block      Blocco
 * @var int   $post_id    Post che contiene il blocco
 * @var bool  $is_preview Resa nell'editor
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_id  = (int) ($post_id ?? get_the_ID());
$foto     = (int) get_field('immagine');
$immagine = $foto ?: (int) get_post_thumbnail_id($post_id);
$nome1    = (string) get_field('nome_coniuge_1', $post_id);
$nome2    = (string) get_field('nome_coniuge_2', $post_id);
$corsivo  = (string) get_field('corsivo');

if (!empty($is_preview)) {
    echo dlc_blocco_riepilogo('Hero con i nomi', [ // phpcs:ignore WordPress.Security.EscapeOutput -- esc_html() nella funzione
        ($nome1 && $nome2) ? trim($corsivo . ' ' . $nome1 . ' e ' . $nome2) : 'Titolo: ' . get_the_title($post_id) . ' (nomi da compilare nella barra laterale)',
        $immagine ? ($foto ? 'Foto scelta nel blocco' : 'Foto: immagine in evidenza') : 'Nessuna foto: scegli l\'immagine in evidenza o una foto nel blocco',
    ], $immagine ? [$immagine] : []);
    return;
}

echo dlc_sezione_hero_html($immagine, $nome1, $nome2, get_the_title($post_id), $corsivo); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML già pulito da dlc_hero_html()
