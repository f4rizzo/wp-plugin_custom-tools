<?php

/**
 * =======================================================
 * Compatibilità temporanea ACF Pro 6.8 / ACF Extended Pro 0.9.2.3
 * =======================================================
 *
 * ACF Pro 6.8 ha reso privata la proprietà acf_field_checkbox::$_values.
 * ACFE Pro 0.9.2.3 sostituisce il render del campo checkbox e scrive quella
 * proprietà: ogni campo checkbox in admin (es. ACF → Strumenti → Esporta)
 * genera "Cannot access private property acf_field_checkbox::$_values".
 *
 * Finché ACFE non viene aggiornato, stacchiamo il suo render del checkbox e
 * rimettiamo quello originale di ACF. Si perde solo la personalizzazione
 * grafica di ACFE sul checkbox; valori e salvataggio non cambiano.
 *
 * Il correttivo si attiva solo se l'incompatibilità c'è davvero: dopo
 * l'aggiornamento di ACFE questo file si può eliminare (e il require in
 * custom-tools.php).
 */

// Impedisci l'accesso diretto al file
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_loaded', function () {
    if (!class_exists('acfe_pro_field_checkbox') || !class_exists('acf_field_checkbox')) {
        return;
    }

    // Serve solo se $_values è privata (ACF 6.8+)
    try {
        $property = new ReflectionProperty('acf_field_checkbox', '_values');
    } catch (ReflectionException $e) {
        return;
    }
    if (!$property->isPrivate()) {
        return;
    }

    $acfe = acf_get_instance('acfe_pro_field_checkbox');
    $acf  = acf_get_field_type('checkbox');
    if (!$acfe || !$acf) {
        return;
    }

    // ACFE aggancia il suo render con priorità 9 al posto di quello di ACF
    if (remove_action('acf/render_field/type=checkbox', array($acfe, 'render_field'), 9)) {
        add_action('acf/render_field/type=checkbox', array($acf, 'render_field'), 9, 1);
    }
});
