<?php

/**
 * =======================================================
 * Eventi: pagina a blocchi (prova per i matrimoni)
 * =======================================================
 *
 * Il post type "evento" (ACF JSON, come gli altri) si compila con
 * l'editor a blocchi invece che con un gruppo di campi fisso:
 *
 *  - barra laterale: nomi (gruppo ACF "Dettagli evento", stessi campi
 *    nome_coniuge_1/2 dei matrimoni, così card e shortcode li trovano)
 *    e immagine in evidenza (foto dell'hero);
 *  - pagina: blocchi ACF dlc/* (cartella blocks/), riordinabili.
 *    Ogni evento nuovo parte con la struttura di un matrimonio:
 *    hero, intro, foto larga, informazioni, galleria, racconto,
 *    galleria, racconto. Cambiare la struttura qui vale solo per gli
 *    eventi nuovi: quelli esistenti restano come sono.
 *
 * La resa è nel tema (single-evento.php). Il markup delle sezioni è
 * quello del template nativo dei matrimoni: funzioni dlc_sezione_*()
 * in shortcodes/dlc-blocchi.php.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Blocchi dlc/*: uno per cartella in blocks/ (block.json + render.php).
 * I campi stanno nei gruppi ACF con location "Blocco" (acf-json/).
 */
add_action('init', function () {
    foreach (glob(plugin_dir_path(__DIR__) . 'blocks/*/block.json') ?: [] as $file) {
        register_block_type(dirname($file));
    }
});

/**
 * Riepilogo di un blocco dlc/* nell'editor, al posto della resa del sito
 * (che nell'editor non ha lo stile della pagina): titolo, qualche riga
 * di dettaglio e le miniature delle foto. Serve a riconoscere i blocchi
 * mentre si riordinano. Un blocco vuoto non usa il riepilogo: ACF mostra
 * il suo riquadro con il pulsante per compilarlo.
 *
 * @param string $titolo    Nome del blocco
 * @param array  $righe     Righe di testo (già brevi)
 * @param int[]  $miniature ID delle immagini da mostrare
 * @return string
 */
function dlc_blocco_riepilogo($titolo, $righe = [], $miniature = [])
{
    $html = '<div style="border:1px solid #dcdcde;border-radius:2px;padding:12px 16px;background:#fff;color:#1e1e1e;font:13px/1.5 -apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif">';
    $html .= '<div style="font-weight:600;text-transform:uppercase;letter-spacing:.04em;font-size:11px;color:#757575;margin-bottom:4px">' . esc_html($titolo) . '</div>';

    foreach (array_filter($righe) as $riga) {
        $html .= '<div>' . esc_html($riga) . '</div>';
    }

    if ($miniature) {
        $html .= '<div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:8px">';
        foreach (array_slice($miniature, 0, 12) as $id) {
            $html .= wp_get_attachment_image((int) $id, 'thumbnail', false, [
                'style'   => 'width:56px;height:56px;object-fit:cover;display:block',
                'loading' => 'lazy',
                'alt'     => '',
            ]);
        }
        if (count($miniature) > 12) {
            $html .= '<span style="align-self:center;color:#757575">+' . (count($miniature) - 12) . '</span>';
        }
        $html .= '</div>';
    }

    return $html . '</div>';
}

/**
 * Prime parole di un testo HTML, per il riepilogo.
 *
 * @param string $html
 * @param int    $parole
 * @return string
 */
function dlc_blocco_estratto($html, $parole = 24)
{
    // Paragrafi e a capo diventano uno spazio, così non si attaccano ("fine.Inizio")
    $html = (string) preg_replace('#</?(p|br|div|li|h[1-6])\b[^>]*>#i', ' ', (string) $html);

    return wp_trim_words(wp_strip_all_tags($html), $parole, '…');
}

/**
 * Categoria dei blocchi nell'inseritore.
 */
add_filter('block_categories_all', function ($categories) {
    array_unshift($categories, [
        'slug'  => 'dlc',
        'title' => 'Deborah Lo Castro',
        'icon'  => null,
    ]);

    return $categories;
});

/**
 * Struttura proposta a ogni evento nuovo. Non è bloccata: si possono
 * spostare, aggiungere e togliere blocchi. L'hero si può spostare ma
 * non eliminare (contiene l'H1 della pagina).
 */
add_filter('register_post_type_args', function ($args, $post_type) {
    if ($post_type !== 'evento') {
        return $args;
    }

    $args['template'] = [
        ['dlc/hero', ['lock' => ['remove' => true]]],
        ['dlc/testo'],
        ['dlc/foto-larga'],
        ['dlc/info'],
        ['dlc/galleria'],
        ['dlc/testo'],
        ['dlc/galleria'],
        ['dlc/testo'],
    ];
    $args['template_lock'] = false;

    return $args;
}, 10, 2);

/**
 * Foto dell'hero di un evento: quella scelta nel blocco dlc/hero,
 * altrimenti l'immagine in evidenza.
 *
 * @param int $post_id
 * @return int 0 se non c'è
 */
function dlc_evento_foto_hero($post_id)
{
    foreach (parse_blocks((string) get_post_field('post_content', $post_id)) as $block) {
        if ($block['blockName'] === 'dlc/hero') {
            $foto = (int) ($block['attrs']['data']['immagine'] ?? 0);
            return $foto ?: (int) get_post_thumbnail_id($post_id);
        }
    }

    return 0;
}

/**
 * Preload della foto dell'hero (elemento LCP). Stessi srcset e sizes
 * dell'<img> di dlc_hero_html(): il browser sceglie lo stesso file e lo
 * scarica una sola volta, prima ancora di leggere il CSS.
 */
add_action('wp_head', function () {
    if (!is_singular('evento')) {
        return;
    }

    $foto   = dlc_evento_foto_hero(get_queried_object_id());
    $src    = $foto ? wp_get_attachment_image_src($foto, 'full') : false;
    $srcset = $foto ? wp_get_attachment_image_srcset($foto, 'full') : false;

    if (!$src) {
        return;
    }

    printf(
        '<link rel="preload" as="image" href="%s"%s imagesizes="100vw" fetchpriority="high">' . "\n",
        esc_url($src[0]),
        $srcset ? ' imagesrcset="' . esc_attr($srcset) . '"' : ''
    );
}, 2);

/**
 * Negli eventi l'inseritore propone solo i blocchi dlc/*: il template
 * della pagina li impagina, un paragrafo o un'immagine core uscirebbero
 * senza lo stile del sito.
 */
add_filter('allowed_block_types_all', function ($allowed, $context) {
    if (empty($context->post) || $context->post->post_type !== 'evento') {
        return $allowed;
    }

    return array_values(array_filter(
        array_keys(WP_Block_Type_Registry::get_instance()->get_all_registered()),
        function ($name) {
            return strpos($name, 'dlc/') === 0;
        }
    ));
}, 10, 2);
