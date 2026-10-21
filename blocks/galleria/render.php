<?php

/**
 * Blocco dlc/galleria — resa (template ACF).
 * Le foto scelte e ordinate nel blocco vanno a [modular_gallery ids="…"];
 * il layout di default (modulare) dà la stessa galleria dei matrimoni.
 * Tutte le gallerie della pagina condividono la lightbox ("evento").
 *
 * @var array $block      Blocco
 * @var bool  $is_preview Resa nell'editor
 */

if (!defined('ABSPATH')) {
    exit;
}

$ids = array_filter(array_map('intval', (array) get_field('galleria', false, false)));

if (!$ids) {
    return;
}

$atts   = ['ids' => implode(',', $ids), 'slideshow' => 'evento'];
$layout = (string) get_field('layout');

if ($layout !== '' && $layout !== 'modulare') {
    $atts['layout'] = $layout;

    $colonne = (int) get_field('colonne');
    if ($colonne && in_array($layout, ['griglia', 'masonry'], true)) {
        $atts['colonne'] = $colonne;
    }
}

if (get_field('didascalie')) {
    $atts['didascalie'] = 'si';
}

if (!empty($is_preview)) {
    $layout = $atts['layout'] ?? 'modulare';
    echo dlc_blocco_riepilogo('Galleria', [ // phpcs:ignore WordPress.Security.EscapeOutput -- esc_html() nella funzione
        sprintf('%d foto · layout %s%s%s', count($ids), $layout, isset($atts['colonne']) ? ' · ' . $atts['colonne'] . ' colonne' : '', isset($atts['didascalie']) ? ' · didascalie' : ''),
    ], $ids);
    return;
}

echo dlc_sezione_galleria_html($atts); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML di modular_gallery
