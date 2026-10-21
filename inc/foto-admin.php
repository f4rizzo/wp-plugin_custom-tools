<?php

/**
 * =======================================================
 * Opzioni delle foto nella Libreria media
 * =======================================================
 *
 * Gruppo ACF "Foto nella galleria" (group_image-metadata), visibile nei
 * dettagli di ogni immagine: nella Libreria media e nel pannello che si
 * apre cliccando una foto dentro una galleria.
 *
 * Etichette corte: il pannello della galleria ACF (dettaglio di un evento
 * o di un matrimonio) è stretto. Lì i campi della foto salgono subito sotto
 * la miniatura, prima di titolo, didascalia e testo alternativo.
 *
 *  - Ingombro (gallery_image_size): pulsanti Standard · Larga · Alta.
 *    "Standard" in admin, vuoto nel database, come prima: i valori
 *    salvati e la pipeline dei ritagli (inc/images.php) non cambiano.
 *  - Inquadratura (gallery_crop_position): solo le scelte che hanno
 *    effetto su quella foto, in base all'orientamento:
 *      orizzontale o quadrata → Sinistra · Centro · Destra, solo se Alta
 *        (ritaglio 3:4);
 *      verticale → Alto · Centro · Basso, se Larga (ritaglio 3:2) o Alta
 *        (cella 3:4, la foto 2:3 si adatta tagliando un po' sopra e sotto).
 *  - Anteprima dal vivo: la foto nella proporzione della cella scelta,
 *    con la parte da tenere; si aggiorna cliccando i pulsanti
 *    (assets/js/foto-admin.js), prima di salvare.
 *  - Testo alternativo e didascalia: una riga che spiega dove compaiono.
 *    La descrizione, che il sito non usa, è nascosta.
 */

if (!defined('ABSPATH')) {
    exit;
}

const CUSTOM_TOOLS_CAMPO_INGOMBRO  = 'field_688794fa3b2bb';
const CUSTOM_TOOLS_CAMPO_POSIZIONE = 'field_bf54a05c6a726';

/**
 * Proporzione larghezza/altezza dell'originale (0 se non nota).
 *
 * @param int $attachment_id
 * @return float
 */
function custom_tools_foto_proporzione($attachment_id)
{
    $meta = wp_get_attachment_metadata($attachment_id);

    return !empty($meta['width']) && !empty($meta['height']) ? $meta['width'] / $meta['height'] : 0.0;
}

/**
 * Ingombro: "standard" nel form, vuoto nel database. La conversione è solo
 * nel form (prepare_field), così get_field() nel sito resta com'era.
 */
add_filter('acf/prepare_field/key=' . CUSTOM_TOOLS_CAMPO_INGOMBRO, function ($field) {
    if (!in_array($field['value'], ['wide', 'tall'], true)) {
        $field['value'] = 'standard';
    }

    return $field;
});

add_filter('acf/update_value/key=' . CUSTOM_TOOLS_CAMPO_INGOMBRO, function ($value) {
    return in_array($value, ['wide', 'tall'], true) ? $value : '';
});

/**
 * Parte da tenere: scelte e condizione in base all'orientamento della foto.
 */
add_filter('acf/prepare_field/key=' . CUSTOM_TOOLS_CAMPO_POSIZIONE, function ($field) {
    $id          = (int) acf_get_form_data('post_id');
    $proporzione = $id ? custom_tools_foto_proporzione($id) : 0.0;

    if (!$proporzione) {
        return $field;
    }

    $verticale = $proporzione < 1;
    $scelte    = $verticale ? ['top', 'center', 'bottom'] : ['left', 'center', 'right'];

    $field['choices'] = array_intersect_key(
        array_replace(array_flip($scelte), $field['choices']),
        array_flip($scelte)
    );

    if (!in_array($field['value'], $scelte, true)) {
        $field['value'] = 'center';
    }

    $field['conditional_logic'] = $verticale
        ? [[['field' => CUSTOM_TOOLS_CAMPO_INGOMBRO, 'operator' => '!=', 'value' => 'standard']]]
        : [[['field' => CUSTOM_TOOLS_CAMPO_INGOMBRO, 'operator' => '==', 'value' => 'tall']]];

    return $field;
});

/**
 * Anteprima sotto i pulsanti dell'ingombro: la foto nella proporzione
 * della cella (Alta 3:4, Larga 3:2) con la parte da tenere. Lo stato
 * iniziale è quello salvato; il resto lo fa foto-admin.js.
 */
add_action('acf/render_field/key=' . CUSTOM_TOOLS_CAMPO_INGOMBRO, function ($field) {
    $id  = (int) acf_get_form_data('post_id');
    $src = $id ? wp_get_attachment_image_src($id, 'medium_large') : false;

    if (!$src) {
        return;
    }

    $ingombro = in_array($field['value'], ['wide', 'tall'], true) ? $field['value'] : 'standard';
    $mappa    = ['left' => 'left center', 'right' => 'right center', 'top' => 'center top', 'bottom' => 'center bottom'];
    $parte    = $mappa[custom_tools_gallery_crop_position($id)] ?? 'center';

    printf(
        '<figure class="ct-anteprima"%s style="aspect-ratio:%s"><img src="%s" alt="" style="object-position:%s"></figure>'
        . '<p class="description ct-anteprima-nota"%s>Anteprima su desktop. Il ritaglio si aggiorna al salvataggio.</p>',
        $ingombro === 'standard' ? ' hidden' : '',
        $ingombro === 'tall' ? '3 / 4' : '3 / 2',
        esc_url($src[0]),
        esc_attr($parte),
        $ingombro === 'standard' ? ' hidden' : ''
    );
}, 20);

/**
 * Script dell'anteprima e stili del pannello, in tutto l'admin: la
 * Libreria media si apre anche dentro i form (gallerie dei capitoli).
 */
add_action('admin_enqueue_scripts', function () {
    $base = plugin_dir_path(__DIR__);

    wp_enqueue_script(
        'custom-tools-foto-admin',
        plugin_dir_url(__DIR__) . 'assets/js/foto-admin.js',
        ['jquery'],
        (string) filemtime($base . 'assets/js/foto-admin.js'),
        true
    );

    wp_register_style('custom-tools-foto-admin', false, [], '1.0');
    wp_enqueue_style('custom-tools-foto-admin');
    wp_add_inline_style('custom-tools-foto-admin', <<<'CSS'
/* Pannello della galleria ACF: i campi della foto subito sotto la miniatura */
.acf-gallery-side-data { display: flex; flex-direction: column; }
.acf-gallery-side-data > .acf-gallery-side-info { order: 0; }
.acf-gallery-side-data > .compat-attachment-fields { order: 1; margin-bottom: 8px; }
.acf-gallery-side-data > .form-table { order: 2; }
.acf-gallery-side-data tr[data-name="description"] { display: none; }
.acf-gallery-side-data > .media-types-required-info { display: none; }

/* Campi della foto nel pannello: etichetta sopra */
.compat-attachment-fields tr.acf-field[data-key="field_688794fa3b2bb"],
.compat-attachment-fields tr.acf-field[data-key="field_bf54a05c6a726"] { display: block; }
.compat-attachment-fields tr.acf-field[data-key="field_688794fa3b2bb"] > td,
.compat-attachment-fields tr.acf-field[data-key="field_bf54a05c6a726"] > td { display: block; width: auto; float: none; text-align: left; padding: 0 0 4px; }
.compat-attachment-fields .acf-button-group { flex-wrap: wrap; }

/* Anteprima del riquadro */
.ct-anteprima { display: block; width: 100%; max-width: 240px; margin: 10px 0 0; overflow: hidden; background: #f0f0f1; border-radius: 2px; }
.ct-anteprima[hidden], .ct-anteprima-nota[hidden] { display: none; }
.ct-anteprima img { display: block; width: 100%; height: 100%; object-fit: cover; transition: object-position .2s; }
.ct-anteprima-nota { margin-top: 4px !important; }

/* Descrizione: il sito non la usa */
.attachment-details .setting[data-setting="description"],
.post-type-attachment .attachment-content-description,
.post-type-attachment #wp-attachment_content-wrap { display: none !important; }

/* Dove compaiono testo alternativo e didascalia */
.attachment-details .setting[data-setting="alt"]::after,
.post-type-attachment p:has(> #attachment_alt)::after,
.acf-gallery-side-data tr[data-name="alt"] .acf-input::after {
  content: "Descrive la foto a Google e ai lettori di schermo. Vuoto: si usa il titolo.";
}
.attachment-details .setting[data-setting="caption"]::after,
.post-type-attachment p:has(> #attachment_caption)::after,
.acf-gallery-side-data tr[data-name="caption"] .acf-input::after {
  content: "Visibile se nel capitolo le didascalie sono attive.";
}
.attachment-details .setting[data-setting="alt"]::after,
.attachment-details .setting[data-setting="caption"]::after,
.post-type-attachment p:has(> #attachment_alt)::after,
.post-type-attachment p:has(> #attachment_caption)::after,
.acf-gallery-side-data tr[data-name="alt"] .acf-input::after,
.acf-gallery-side-data tr[data-name="caption"] .acf-input::after {
  display: block; flex-basis: 100%; margin-top: 4px; color: #646970; font-size: 12px; font-style: normal; line-height: 1.4;
}
CSS);
});
