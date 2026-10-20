<?php

/**
 * =======================================================
 * Pipeline immagini: formati, WebP, crop per la gallery
 * =======================================================
 *
 * Tutto quello che succede a un'immagine dal caricamento alla pagina:
 *
 *  1. Originali ridimensionati a 2048px (big_image_size_threshold).
 *     L'originale resta sul server, WordPress usa la copia "-scaled".
 *  2. Formati intermedi salvati in WebP, se il server lo supporta.
 *  3. Formati registrati: solo quelli che servono davvero al sito.
 *  4. Ritagli "art direction" per la Modular Gallery, generati AL CARICAMENTO
 *     per ogni fotografia. La cliente sceglie l'ingombro (campo ACF
 *     'gallery_image_size') quando vuole e può cambiarlo in ogni momento:
 *     il formato giusto deve esistere già.
 *       - foto orizzontale → anche un ritaglio verticale 3:4 (ingombro 'tall')
 *       - foto verticale   → anche un ritaglio orizzontale 3:2 (ingombro 'wide')
 *     L'orientamento naturale non serve ritagliarlo: c'è già nei formati standard.
 *     La cliente può scegliere quale parte tenere (campo ACF 'gallery_crop_position':
 *     centro, sinistra/destra per le orizzontali, alto/basso per le verticali).
 *
 * Il modulo non dipende da ACF: il valore del campo si legge con get_post_meta().
 */

if (!defined('ABSPATH')) {
    exit;
}


/**
 * =======================================================
 * 1. Soglia "big image": 2048px
 * =======================================================
 * Le foto arrivano spesso direttamente dalla fotocamera (5472px, 7MB).
 * Oltre i 2048px sul lato lungo WordPress crea la copia "-scaled" e la usa
 * come immagine "piena" (lightbox, sfondi). 2048px bastano anche su schermi retina.
 */
add_filter('big_image_size_threshold', function () {
    return 2048;
});


/**
 * =======================================================
 * 2. Output in WebP
 * =======================================================
 * JPEG e PNG caricati vengono salvati in WebP (formati intermedi e copia
 * "-scaled"); l'originale resta nel formato di partenza. WebP gestisce anche
 * la trasparenza, quindi vale anche per i loghi PNG.
 * Se l'editor immagini del server non supporta WebP non cambia nulla.
 */
add_filter('image_editor_output_format', function ($formats) {
    if (!custom_tools_supports_webp()) {
        return $formats;
    }

    $formats['image/jpeg'] = 'image/webp';
    $formats['image/png']  = 'image/webp';

    return $formats;
});

/**
 * L'editor immagini del server sa scrivere WebP? (risultato in cache per richiesta)
 *
 * @return bool
 */
function custom_tools_supports_webp()
{
    static $supported = null;

    if ($supported === null) {
        $supported = wp_image_editor_supports(['mime_type' => 'image/webp']);
    }

    return $supported;
}

/**
 * Qualità WebP: 82 (default WordPress 86). Su fotografie la differenza non si
 * vede, il peso scende di circa il 15%.
 */
add_filter('wp_editor_set_quality', function ($quality, $mime_type) {
    return $mime_type === 'image/webp' ? 82 : $quality;
}, 10, 2);

/**
 * Metadati: i formati generati perdono EXIF/XMP (fino a 21KB a file),
 * vedi inc/class-image-editor-imagick.php. Se Imagick non c'è, WordPress
 * usa GD, che i metadati non li copia proprio.
 */
add_filter('wp_image_editors', function ($editors) {
    if (!class_exists('WP_Image_Editor_Imagick', false)) {
        return $editors;
    }

    require_once __DIR__ . '/class-image-editor-imagick.php';

    return array_map(function ($editor) {
        return $editor === 'WP_Image_Editor_Imagick' ? 'Custom_Tools_Image_Editor_Imagick' : $editor;
    }, $editors);
});


/**
 * =======================================================
 * 3. Formati immagine registrati
 * =======================================================
 * - portfolio_thumb: anteprime del portfolio in home (454px di larghezza).
 * - gallery_1200:    tappa della srcset tra 1024 e 1536. È la misura esatta per
 *                    la galleria a colonna singola (1138px) su desktop e per
 *                    gli smartphone a densità 3x (370px x 3 = 1110px).
 *
 * 'gallery_wide' (1480px) non viene più registrato: era a 4% da '1536x1536',
 * un file in più senza beneficio. I file già generati restano nella srcset.
 *
 * 'gallery_tall' (0x1200) non viene più registrato: su una foto orizzontale
 * produceva un file 1800x1200 da cui il CSS mostrava solo una striscia verticale.
 * Ora c'è il ritaglio 3:4 dedicato (punto 4). I file già generati restano validi.
 */
add_action('after_setup_theme', function () {
    add_image_size('portfolio_thumb', 454, 0, false);
    add_image_size('gallery_1200', 1200, 0, false);
});

/**
 * Il formato '2048x2048' di WordPress non serve: con la soglia a 2048px
 * l'immagine "piena" (-scaled) ha già quella dimensione, e il formato
 * sarebbe un secondo file identico.
 */
add_filter('intermediate_image_sizes_advanced', function ($sizes) {
    unset($sizes['2048x2048']);

    return $sizes;
});

// Formati selezionabili nel Media Picker
add_filter('image_size_names_choose', function ($sizes) {
    return array_merge($sizes, [
        'portfolio_thumb' => 'Portfolio Thumb - 454px',
        'gallery_1200'    => 'Gallery - 1200px',
    ]);
});


/**
 * =======================================================
 * 4. Ritagli per la Modular Gallery
 * =======================================================
 */

/**
 * Definizione dei ritagli.
 *
 * - ratio:   proporzione larghezza/altezza della cella
 * - widths:  larghezze generate (srcset): coprono mobile (370px a 2x/3x)
 *            e desktop (564px a 1x/2x per 'tall', 1138px per 'wide');
 *            quelle più grandi dell'originale vengono saltate
 * - applies: per quali foto serve, in base alla proporzione dell'originale
 *
 * @return array
 */
function custom_tools_gallery_crop_specs()
{
    return [
        // Cella su due righe: 564x762 sul desktop (≈ 3:4). Serve alle foto
        // orizzontali e quadrate; le verticali (2:3) si adattano senza ritaglio.
        'tall' => [
            'ratio'   => 3 / 4,
            'widths'  => [600, 900, 1200],
            'applies' => function ($ratio) {
                return $ratio > 0.8;
            },
        ],
        // Cella su due colonne: una foto verticale diventerebbe alta 1700px,
        // quindi serve un ritaglio orizzontale 3:2.
        'wide' => [
            'ratio'   => 3 / 2,
            'widths'  => [768, 1200, 1800],
            'applies' => function ($ratio) {
                return $ratio < 1;
            },
        ],
    ];
}

/**
 * Nome del formato di ritaglio nei metadati dell'immagine.
 * Es. custom_tools_gallery_crop_name('tall', 900) → 'gallery_tall_crop_900'
 *
 * @param string $layout 'tall' | 'wide'
 * @param int    $width
 * @return string
 */
function custom_tools_gallery_crop_name($layout, $width)
{
    return 'gallery_' . $layout . '_crop_' . (int) $width;
}

/**
 * Ingombro scelto per l'immagine nella gallery ('', 'wide', 'tall').
 *
 * @param int $attachment_id
 * @return string
 */
function custom_tools_gallery_layout($attachment_id)
{
    $layout = get_post_meta($attachment_id, 'gallery_image_size', true);

    return in_array($layout, ['wide', 'tall'], true) ? $layout : '';
}

/**
 * L'immagine è una fotografia da gallery? Esclusi PNG, GIF e SVG (loghi,
 * grafiche, icone), per cui i ritagli non hanno senso.
 *
 * @param int $attachment_id
 * @return bool
 */
function custom_tools_gallery_is_photo($attachment_id)
{
    return in_array(get_post_mime_type($attachment_id), ['image/jpeg', 'image/webp', 'image/avif', 'image/heic'], true);
}

/**
 * Nomi dei formati di ritaglio presenti nei metadati per un ingombro,
 * ordinati per larghezza crescente.
 *
 * @param array  $metadata
 * @param string $layout
 * @return string[]
 */
function custom_tools_gallery_crop_sizes($metadata, $layout)
{
    $prefix = 'gallery_' . $layout . '_crop_';
    $names  = [];

    foreach (array_keys($metadata['sizes'] ?? []) as $name) {
        if (strpos($name, $prefix) === 0) {
            $names[(int) substr($name, strlen($prefix))] = $name;
        }
    }

    ksort($names);

    return array_values($names);
}

/**
 * Allineamento del ritaglio scelto dalla cliente (campo ACF 'gallery_crop_position').
 *
 * @param int $attachment_id
 * @return string 'center' | 'left' | 'right' | 'top' | 'bottom'
 */
function custom_tools_gallery_crop_position($attachment_id)
{
    $position = get_post_meta($attachment_id, 'gallery_crop_position', true);

    return in_array($position, ['left', 'right', 'top', 'bottom'], true) ? $position : 'center';
}

/**
 * Parametro 'crop' di WordPress per un ingombro e un allineamento.
 * 'tall' ritaglia in larghezza (conta sinistra/destra), 'wide' in altezza (conta alto/basso).
 *
 * @param string $layout
 * @param string $position
 * @return array [x, y]
 */
function custom_tools_gallery_crop_anchor($layout, $position)
{
    if ($layout === 'tall' && in_array($position, ['left', 'right'], true)) {
        return [$position, 'center'];
    }

    if ($layout === 'wide' && in_array($position, ['top', 'bottom'], true)) {
        return ['center', $position];
    }

    return ['center', 'center'];
}

/**
 * Valore CSS object-position per un allineamento (foto adattate senza ritaglio).
 *
 * @param string $position
 * @return string '' se centrato
 */
function custom_tools_gallery_object_position($position)
{
    $map = [
        'left'   => 'left center',
        'right'  => 'right center',
        'top'    => 'center top',
        'bottom' => 'center bottom',
    ];

    return $map[$position] ?? '';
}

/**
 * Genera i ritagli di una fotografia e li aggiunge ai metadati: così entrano
 * nella srcset e vengono cancellati insieme all'immagine.
 *
 * - Partono dall'originale caricato (non dalla copia "-scaled"), per avere la
 *   massima risoluzione disponibile.
 * - Seguono l'allineamento scelto (custom_tools_gallery_crop_position()).
 *   Se l'allineamento cambia, i ritagli vecchi vengono cancellati e rifatti.
 *   I file non centrati hanno l'allineamento nel nome (es. foto-600x800-left.webp),
 *   così browser e CDN non mostrano la versione precedente dalla cache.
 *
 * @param int        $attachment_id
 * @param array|null $metadata Metadati già in memoria (dal filtro di generazione).
 *                             Se null vengono letti e, se cambiano, salvati.
 * @return array Metadati aggiornati.
 */
function custom_tools_gallery_ensure_crops($attachment_id, $metadata = null)
{
    $save = $metadata === null;

    if ($save) {
        $metadata = wp_get_attachment_metadata($attachment_id);
    }

    if (!is_array($metadata) || empty($metadata['width']) || empty($metadata['height'])) {
        return is_array($metadata) ? $metadata : [];
    }

    if (!custom_tools_gallery_is_photo($attachment_id)) {
        return $metadata;
    }

    $source = wp_get_original_image_path($attachment_id);
    $size   = $source && file_exists($source) ? wp_getimagesize($source) : false;

    if (!$size) {
        return $metadata;
    }

    [$src_w, $src_h] = $size;

    $position = custom_tools_gallery_crop_position($attachment_id);
    $changed  = false;

    // Allineamento cambiato rispetto ai ritagli esistenti: via i vecchi file
    if (($metadata['custom_tools_crop_position'] ?? 'center') !== $position) {
        $dir = dirname(get_attached_file($attachment_id));
        foreach (array_keys(custom_tools_gallery_crop_specs()) as $layout) {
            foreach (custom_tools_gallery_crop_sizes($metadata, $layout) as $name) {
                wp_delete_file(path_join($dir, $metadata['sizes'][$name]['file']));
                unset($metadata['sizes'][$name]);
            }
        }
        $changed = true;
    }

    $metadata['custom_tools_crop_position'] = $position;

    // Elenco dei ritagli da creare, per tutti gli ingombri che servono a questa foto
    $todo = [];
    foreach (custom_tools_gallery_crop_specs() as $layout => $spec) {
        if (!call_user_func($spec['applies'], $src_w / $src_h)) {
            continue;
        }

        // Ritaglio massimo con la proporzione richiesta
        $max_w  = (int) floor(min($src_w, $src_h * $spec['ratio']));
        $widths = array_filter($spec['widths'], function ($w) use ($max_w) {
            return $w <= $max_w;
        });

        // Originale più piccolo di tutti i formati previsti: un solo ritaglio alla massima risoluzione
        if (!$widths && $max_w > 0) {
            $widths = [$max_w];
        }

        foreach ($widths as $width) {
            $name = custom_tools_gallery_crop_name($layout, $width);

            if (!empty($metadata['sizes'][$name])) {
                continue;
            }

            $todo[$name] = [
                'width'  => $width,
                'height' => (int) round($width / $spec['ratio']),
                'crop'   => custom_tools_gallery_crop_anchor($layout, $position),
            ];
        }
    }

    if ($todo) {
        $editor = wp_get_image_editor($source);

        if (!is_wp_error($editor)) {
            $dir = dirname($source);

            foreach ($todo as $name => $spec) {
                // make_subsize() lavora su una copia: la sorgente resta intatta per il formato successivo
                $data = $editor->make_subsize($spec);

                if (is_wp_error($data)) {
                    continue;
                }

                unset($data['path']); // come multi_resize(): nei metadati solo il nome del file

                // Allineamento nel nome del file, se non centrato
                if ($position !== 'center') {
                    $info    = pathinfo($data['file']);
                    $renamed = wp_unique_filename($dir, $info['filename'] . '-' . $position . '.' . $info['extension']);
                    if (@rename(path_join($dir, $data['file']), path_join($dir, $renamed))) {
                        $data['file'] = $renamed;
                    }
                }

                $metadata['sizes'][$name] = $data;
                $changed = true;
            }
        }
    }

    if ($save && $changed) {
        wp_update_attachment_metadata($attachment_id, $metadata);
    }

    return $metadata;
}

/**
 * I ritagli nascono insieme ai formati standard: al caricamento, con
 * "wp media regenerate" e con Regenerate Thumbnails (che altrimenti li
 * toglierebbe dai metadati).
 */
add_filter('wp_generate_attachment_metadata', function ($metadata, $attachment_id) {
    return custom_tools_gallery_ensure_crops($attachment_id, $metadata);
}, 20, 2);

/**
 * Quando la cliente cambia l'allineamento dalla Libreria media, ACF salva i
 * campi dell'immagine e lancia acf/save_post con il suo ID: i ritagli vengono
 * rifatti subito (circa un secondo).
 */
add_action('acf/save_post', function ($post_id) {
    if (is_numeric($post_id) && get_post_type($post_id) === 'attachment') {
        custom_tools_gallery_ensure_crops((int) $post_id);
    }
}, 20);


/**
 * =======================================================
 * 5. Immagine di testata del matrimonio
 * =======================================================
 * La testata è lo sfondo di un container Elementor collegato al campo ACF
 * 'img_header_matrimonio': Elementor usa l'URL "pieno" dell'immagine.
 * Per le foto caricate prima della soglia a 2048px l'URL pieno è l'originale
 * (2500px, 2,5MB): lo sostituiamo con il formato 2048 se esiste.
 * Poi lo precarichiamo, perché è l'elemento più grande della pagina (LCP).
 */

/**
 * URL dell'immagine "grande" da usare a tutta larghezza: il formato
 * 2048x2048 se esiste, altrimenti l'immagine piena (già ≤ 2048px).
 *
 * @param int $attachment_id
 * @return string
 */
function custom_tools_large_image_url($attachment_id)
{
    $src = wp_get_attachment_image_src($attachment_id, '2048x2048');

    return $src ? $src[0] : (string) wp_get_attachment_url($attachment_id);
}

add_filter('acf/format_value/name=img_header_matrimonio', function ($value) {
    if (is_array($value) && !empty($value['ID'])) {
        $value['url'] = custom_tools_large_image_url($value['ID']);
    }

    return $value;
}, 20);

add_action('wp_head', function () {
    if (!is_singular('matrimonio')) {
        return;
    }

    $image_id = (int) get_post_meta(get_queried_object_id(), 'img_header_matrimonio', true);

    if (!$image_id) {
        return;
    }

    printf(
        '<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n",
        esc_url(custom_tools_large_image_url($image_id))
    );
}, 2);
