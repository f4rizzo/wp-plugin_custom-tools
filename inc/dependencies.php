<?php

/**
 * =======================================================
 * Controllo dipendenze del plugin
 * =======================================================
 *
 * Custom Tools si appoggia a:
 *
 *  - ACF PRO   → campi, Gallery/Repeater, sync ACF JSON, registrazione CPT
 *                (dipendenza OBBLIGATORIA: senza ACF le funzionalità non
 *                 vengono nemmeno caricate, vedi custom-tools.php)
 *  - Elementor + Elementor Pro → Loop Grid della home, lightbox nativa,
 *                 widget "Shortcode" nei Loop Item Template
 *                (dipendenza CONSIGLIATA: senza Elementor gli shortcode
 *                 restano innocui ma non producono l'effetto atteso)
 *
 * Se manca qualcosa viene mostrato un avviso nella bacheca di WordPress.
 */

// Impedisci l'accesso diretto al file
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ACF (free o PRO) è attivo?
 *
 * @return bool
 */
function custom_tools_has_acf()
{
    return class_exists('ACF') || function_exists('get_field');
}

/**
 * ACF PRO è attivo? (Gallery e Repeater esistono solo nella versione PRO)
 *
 * @return bool
 */
function custom_tools_has_acf_pro()
{
    if (function_exists('acf_is_pro')) {
        return (bool) acf_is_pro();
    }

    // Fallback per versioni ACF meno recenti
    return defined('ACF_PRO') && ACF_PRO;
}

/**
 * Elementor (core) è attivo?
 *
 * @return bool
 */
function custom_tools_has_elementor()
{
    return defined('ELEMENTOR_VERSION') || did_action('elementor/loaded');
}

/**
 * Elementor Pro è attivo?
 *
 * @return bool
 */
function custom_tools_has_elementor_pro()
{
    return defined('ELEMENTOR_PRO_VERSION');
}

/**
 * Elenco delle dipendenze non soddisfatte.
 *
 * Ogni voce: [ 'label' => string, 'required' => bool ]
 *
 * @return array
 */
function custom_tools_missing_dependencies()
{
    $missing = array();

    if (!custom_tools_has_acf()) {
        $missing[] = array(
            'label'    => 'Advanced Custom Fields PRO',
            'required' => true,
        );
    } elseif (!custom_tools_has_acf_pro()) {
        $missing[] = array(
            'label'    => 'Advanced Custom Fields PRO — rilevata la versione free: Gallery e Repeater non funzioneranno',
            'required' => true,
        );
    }

    if (!custom_tools_has_elementor()) {
        $missing[] = array(
            'label'    => 'Elementor',
            'required' => false,
        );
    } elseif (!custom_tools_has_elementor_pro()) {
        $missing[] = array(
            'label'    => 'Elementor Pro — necessario per Loop Grid e widget "Shortcode"',
            'required' => false,
        );
    }

    return $missing;
}

/**
 * Avviso in bacheca quando manca una dipendenza.
 */
add_action('admin_notices', 'custom_tools_dependencies_notice');
function custom_tools_dependencies_notice()
{
    // Solo per chi può gestire i plugin
    if (!current_user_can('activate_plugins')) {
        return;
    }

    $missing = custom_tools_missing_dependencies();
    if (empty($missing)) {
        return;
    }

    // Se manca almeno una dipendenza obbligatoria l'avviso è un errore (rosso),
    // altrimenti è un semplice warning (giallo).
    $has_required = false;
    foreach ($missing as $dep) {
        if ($dep['required']) {
            $has_required = true;
            break;
        }
    }

    $intro = $has_required
        ? 'alcune funzionalità sono disattivate perché mancano dipendenze obbligatorie:'
        : 'per sfruttare tutte le funzionalità servono anche questi plugin:';

    echo '<div class="notice ' . ($has_required ? 'notice-error' : 'notice-warning') . '">';
    echo '<p><strong>Custom Tools</strong> — ' . esc_html($intro) . '</p>';
    echo '<ul style="list-style:disc;margin-left:20px;">';

    foreach ($missing as $dep) {
        $suffix = $dep['required'] ? ' (obbligatorio)' : ' (consigliato)';
        echo '<li>' . esc_html($dep['label']) . '<em>' . esc_html($suffix) . '</em></li>';
    }

    echo '</ul>';
    echo '</div>';
}
