<?php

/**
 * =======================================================
 * Shortcode: modular_gallery
 * =======================================================
 * Mostra una galleria immagini con supporto lightbox.
 *
 * Sorgente delle foto (serve "field" oppure "ids"):
 * - field: nome di un campo ACF di tipo galleria (es. 'galleria_matrimonio_parte_1')
 * - ids: elenco di ID di immagini, nell'ordine voluto (es. "12,15,18"),
 *        per gallerie scelte a mano dentro articoli e pagine
 * - post: ID del post da cui leggere "field" (opzionale, default: post corrente)
 * - slideshow: nome del gruppo lightbox (opzionale, default: "gallery")
 *
 * Layout (tutti facoltativi):
 * - layout:     modulare (default) | colonna | coppia | trio | griglia | masonry
 *     modulare  ingombro scelto foto per foto (wide/tall, campo ACF dell'immagine),
 *               corretto perché non restino buchi (l'ordine non cambia):
 *               - una 'tall' senza foto accanto (prima di una 'wide', o in fondo)
 *                 è mostrata come standard
 *               - l'ultima 'tall' dopo due standard affiancate va a destra, con le
 *                 due standard impilate a sinistra
 *               - l'ultima foto, se resta da sola nella riga, diventa 'wide'
 *     colonna   una foto per riga, sempre
 *     coppia    due foto affiancate per riga, stessa altezza
 *     trio      una grande a sinistra (due righe) + due impilate a destra, a ripetere;
 *               in fondo due foto si affiancano, una sola va a tutta larghezza
 *     griglia   celle tutte uguali (vedi "proporzione")
 *     masonry   colonne con le foto nelle proporzioni naturali (ordine per colonna)
 * - colonne:        colonne su desktop (default 2; 1 per "colonna")
 * - colonne_tablet: colonne da 768px al breakpoint (default 1)
 * - colonne_mobile: colonne sotto i 768px (default 1)
 * - breakpoint:     larghezza da cui valgono le colonne desktop: 1024 | 1280 | 1440 (default)
 * - proporzione:    celle di "griglia"/"coppia": 3:2 | 4:3 | 1:1 | 4:5 | 2:3 | auto
 * - gap:            spazio tra le foto in px (default 10)
 * - larghezza:      contenuto (max 1138px, default) | piena (tutta la pagina)
 * - didascalie:     si = mostra la didascalia sotto la foto
 * - nitide:         si = una foto non viene mai mostrata più grande della
 *                   sua risoluzione (evita foto sgranate se il file è piccolo)
 *
 * Esempi:
 *   [modular_gallery field="galleria_matrimonio_parte_1" slideshow="matrimonio"]
 *   [modular_gallery ids="745,746,747" layout="trio"]
 *   [modular_gallery ids="…" layout="griglia" colonne="3" colonne_tablet="2" proporzione="4:5"]
 *   [modular_gallery field="galleria_matrimonio_parte_2" post="733" breakpoint="1024"]
 *
 * Ogni immagine esce con srcset e con "sizes" calcolato dal layout scelto
 * (colonne, gap, larghezza, breakpoint): il browser scarica il file della
 * misura giusta. Gli stili sono nel plugin (assets/scss/modular-gallery.scss);
 * larghezze, breakpoint e default stanno in assets/scss/_modular-gallery-settings.scss,
 * letto anche da qui (custom_tools_gallery_settings()). Colonne, gap e
 * proporzione della singola galleria arrivano al CSS come variabili (--mg-*).
 * =======================================================
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * =======================================================
 * Funzione Helper: custom_tools_gallery_settings
 * =======================================================
 * Impostazioni della galleria, lette da assets/scss/_modular-gallery-settings.scss:
 * è l'unica fonte dei valori, la stessa da cui si compila il CSS.
 * Legge le righe `$mg-nome: valore;`: px e numeri diventano interi,
 * le liste separate da virgola un array.
 *
 * @return array Es. ['max-width' => 1158, 'breakpoints' => [1024, 1280, 1440], …]
 */
function custom_tools_gallery_settings()
{
    static $settings = null;

    if ($settings !== null) {
        return $settings;
    }

    $settings = [];
    $file     = plugin_dir_path(__DIR__) . 'assets/scss/_modular-gallery-settings.scss';
    $scss     = is_readable($file) ? (string) file_get_contents($file) : '';

    preg_match_all('/^\$mg-([a-z0-9-]+)\s*:\s*([^;]+);/m', $scss, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $values = array_map(function ($value) {
            $value = trim($value);
            return preg_match('/^-?\d+(px)?$/', $value) ? (int) $value : $value;
        }, explode(',', $match[2]));

        $settings[$match[1]] = count($values) > 1 ? $values : $values[0];
    }

    if (!$settings) {
        error_log('custom-tools: impostazioni della modular_gallery non trovate in ' . $file);
    }

    return $settings;
}

/**
 * Valori della galleria, calcolati dalle impostazioni (vedi sopra).
 */
$custom_tools_gallery = custom_tools_gallery_settings();
$custom_tools_gallery_width = (int) ($custom_tools_gallery['max-width'] ?? 0) - 2 * (int) ($custom_tools_gallery['padding'] ?? 0);
$custom_tools_gallery_cols  = max(1, (int) ($custom_tools_gallery['colonne'] ?? 1));

define('CUSTOM_TOOLS_GALLERY_BREAKPOINT', (int) ($custom_tools_gallery['breakpoint'] ?? 0)); // colonne desktop da qui in su
define('CUSTOM_TOOLS_GALLERY_TABLET', (int) ($custom_tools_gallery['tablet'] ?? 0));         // colonne tablet da qui in su
define('CUSTOM_TOOLS_GALLERY_WIDTH', $custom_tools_gallery_width);                           // larghezza utile (max-width - padding 2 lati)
define('CUSTOM_TOOLS_GALLERY_GAP', (int) ($custom_tools_gallery['gap'] ?? 0));
define('CUSTOM_TOOLS_GALLERY_PADDING', 2 * (int) ($custom_tools_gallery['padding'] ?? 0));  // padding laterale totale
define('CUSTOM_TOOLS_GALLERY_COLUMN', (int) (($custom_tools_gallery_width - ($custom_tools_gallery_cols - 1) * CUSTOM_TOOLS_GALLERY_GAP) / $custom_tools_gallery_cols)); // una colonna desktop
define('CUSTOM_TOOLS_GALLERY_BREAKPOINTS', array_map('intval', (array) ($custom_tools_gallery['breakpoints'] ?? [])));
define('CUSTOM_TOOLS_GALLERY_COLS', $custom_tools_gallery_cols);
define('CUSTOM_TOOLS_GALLERY_COLS_TABLET', max(1, (int) ($custom_tools_gallery['colonne-tablet'] ?? 1)));
define('CUSTOM_TOOLS_GALLERY_COLS_MOBILE', max(1, (int) ($custom_tools_gallery['colonne-mobile'] ?? 1)));

unset($custom_tools_gallery, $custom_tools_gallery_width, $custom_tools_gallery_cols);

/**
 * Stili della galleria (assets/scss/modular-gallery.scss → assets/css/).
 * Priorità 15: prima del CSS del tema (20), che può così cambiare le variabili --mg-*.
 */
add_action('wp_enqueue_scripts', function () {
    $file = plugin_dir_path(__DIR__) . 'assets/css/modular-gallery.css';

    if (!file_exists($file)) {
        return;
    }

    wp_enqueue_style(
        'custom-tools-modular-gallery',
        plugin_dir_url(__DIR__) . 'assets/css/modular-gallery.css',
        [],
        (string) filemtime($file)
    );
}, 15);

add_shortcode('modular_gallery', 'custom_tools_modular_gallery_shortcode');

/**
 * Callback dello shortcode, richiamabile anche da PHP
 * (es. dlc_sezione_galleria_html() per i blocchi e il template nativo).
 *
 * @param array|string $atts Attributi dello shortcode
 * @return string
 */
function custom_tools_modular_gallery_shortcode($atts)
{
    $atts = shortcode_atts([
        'field'          => '',        // es. 'galleria_matrimonio_parte_1'
        'ids'            => '',        // es. '12,15,18' (alternativa a field)
        'post'           => 0,         // post da cui leggere field (default: corrente)
        'slideshow'      => 'gallery', // nome usato nella lightbox
        'layout'         => 'modulare',
        'colonne'        => '',
        'colonne_tablet' => '',
        'colonne_mobile' => '',
        'breakpoint'     => '',
        'proporzione'    => '',
        'gap'            => '',
        'larghezza'      => 'contenuto',
        'didascalie'     => '',
        'nitide'         => '',
    ], $atts);

    if (empty($atts['field']) && empty($atts['ids'])) {
        return '<p>Campo ACF non specificato.</p>';
    }

    if (!empty($atts['ids'])) {
        // Foto scelte a mano: solo immagini esistenti, nell'ordine indicato
        $gallery = array_filter(array_map('intval', explode(',', (string) $atts['ids'])), 'wp_attachment_is_image');
    } else {
        $post_id = (int) $atts['post'] ?: get_the_ID();
        $gallery = get_field($atts['field'], $post_id, false); // solo gli ID, senza formattare 50 array immagine
    }

    if (empty($gallery) || !is_array($gallery)) {
        return '<p>Nessuna immagine disponibile.</p>';
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $gallery))));

    // Prime della cache: una query per i post e una per i meta di tutti gli allegati,
    // invece di due query per ogni immagine.
    _prime_post_caches($ids, false, true);

    $cfg = custom_tools_gallery_config($atts);

    // Ingombri: modulare = scelti dalla cliente, corretti perché non restino
    // buchi; trio = schema fisso. Nessun buco nemmeno in fondo alla galleria.
    if ($cfg['layout'] === 'modulare') {
        $resolved        = custom_tools_gallery_resolve_orphans(array_map('custom_tools_gallery_layout', $ids), $cfg['cols']);
        $cfg['ingombri'] = $resolved['ingombri'];
        $cfg['righe']    = $resolved['righe'];
    } elseif ($cfg['layout'] === 'trio') {
        $cfg['ingombri'] = custom_tools_gallery_trio(count($ids));
    }

    $html = '';
    foreach ($ids as $i => $id) {
        $html .= custom_tools_gallery_item($id, $atts['slideshow'], $cfg, $i);
    }

    if ($html === '') {
        return '';
    }

    return sprintf('<div class="%s"%s>%s</div>', esc_attr($cfg['class']), $cfg['style'], $html);
}


/**
 * =======================================================
 * Funzione Helper: custom_tools_gallery_config
 * =======================================================
 * Normalizza i parametri di layout e calcola classi, variabili CSS e
 * "sizes". Con i parametri di default restituisce esattamente la
 * configurazione storica (nessuna classe o stile in più).
 *
 * @param array $atts Attributi dello shortcode
 * @return array
 */
function custom_tools_gallery_config($atts)
{
    $layouts = ['modulare', 'colonna', 'coppia', 'trio', 'griglia', 'masonry'];
    $layout  = in_array($atts['layout'], $layouts, true) ? $atts['layout'] : 'modulare';

    $int = function ($value, $default, $min, $max) {
        return $value === '' ? $default : max($min, min($max, (int) $value));
    };

    // Colonne per dispositivo: alcuni layout le fissano
    $default_cols = $layout === 'colonna' ? 1 : CUSTOM_TOOLS_GALLERY_COLS;
    $cols         = $int($atts['colonne'], $default_cols, 1, 6);
    $cols_tablet  = $int($atts['colonne_tablet'], CUSTOM_TOOLS_GALLERY_COLS_TABLET, 1, 6);
    $cols_mobile  = $int($atts['colonne_mobile'], CUSTOM_TOOLS_GALLERY_COLS_MOBILE, 1, 4);

    if ($layout === 'colonna') {
        $cols = $cols_tablet = $cols_mobile = 1;
    } elseif (in_array($layout, ['coppia', 'trio'], true)) {
        $cols = 2;
    }

    // Breakpoint: solo i valori previsti dal CSS (le media query non accettano variabili)
    $breakpoint = in_array((int) $atts['breakpoint'], CUSTOM_TOOLS_GALLERY_BREAKPOINTS, true) ? (int) $atts['breakpoint'] : CUSTOM_TOOLS_GALLERY_BREAKPOINT;
    $gap        = $int($atts['gap'], CUSTOM_TOOLS_GALLERY_GAP, 0, 80);
    $full       = $atts['larghezza'] === 'piena';

    // Proporzione delle celle: "3:2" → 1.5
    $ratio = null;
    if (preg_match('/^(\d+(?:\.\d+)?)[:\/](\d+(?:\.\d+)?)$/', trim((string) $atts['proporzione']), $m) && (float) $m[2] > 0) {
        $ratio = (float) $m[1] / (float) $m[2];
    } elseif ($layout === 'griglia' || ($layout === 'coppia' && $atts['proporzione'] !== 'auto')) {
        $ratio = $layout === 'coppia' ? 4 / 5 : 3 / 2;
    }

    $is_default = $layout === 'modulare'
        && $cols === CUSTOM_TOOLS_GALLERY_COLS
        && $cols_tablet === CUSTOM_TOOLS_GALLERY_COLS_TABLET
        && $cols_mobile === CUSTOM_TOOLS_GALLERY_COLS_MOBILE
        && $breakpoint === CUSTOM_TOOLS_GALLERY_BREAKPOINT
        && $gap === CUSTOM_TOOLS_GALLERY_GAP
        && !$full && $ratio === null
        && $atts['didascalie'] !== 'si' && $atts['nitide'] !== 'si';

    // Classi: con la configurazione di default solo "modular-gallery", come sempre
    $class = 'modular-gallery';
    $style = '';

    if (!$is_default) {
        $class .= ' modular-gallery--' . $layout . ' modular-gallery--parametrica';
        $class .= $full ? ' modular-gallery--piena' : '';
        $class .= $atts['didascalie'] === 'si' ? ' modular-gallery--didascalie' : '';
        $class .= $atts['nitide'] === 'si' ? ' modular-gallery--nitide' : '';
        $class .= $ratio ? ' modular-gallery--proporzione' : '';
        $class .= ' modular-gallery--bp-' . $breakpoint;

        $vars = [
            '--mg-colonne'   => $cols,
            '--mg-colonne-t' => $cols_tablet,
            '--mg-colonne-m' => $cols_mobile,
            '--mg-gap'       => $gap . 'px',
        ];
        if ($ratio) {
            $vars['--mg-proporzione'] = round($ratio, 4);
        }

        $style = ' style="' . esc_attr(implode(';', array_map(function ($k, $v) {
            return $k . ':' . $v;
        }, array_keys($vars), $vars))) . '"';
    }

    return [
        'layout'      => $layout,
        'default'     => $is_default,
        'cols'        => $cols,
        'cols_tablet' => $cols_tablet,
        'cols_mobile' => $cols_mobile,
        'breakpoint'  => $breakpoint,
        'gap'         => $gap,
        'full'        => $full,
        'ratio'       => $ratio,
        'captions'    => $atts['didascalie'] === 'si',
        'sharp'       => $atts['nitide'] === 'si',
        'class'       => $class,
        'style'       => $style,
    ];
}


/**
 * =======================================================
 * Funzione Helper: custom_tools_gallery_place
 * =======================================================
 * Simula il posizionamento della griglia su desktop, come fa il browser
 * con grid-auto-flow: row (il cursore va solo avanti, senza tornare a
 * riempire i buchi).
 *
 * @param string[] $layouts Ingombri nell'ordine della galleria ('', 'wide', 'tall')
 * @param int      $cols    Colonne su desktop
 * @return array [griglia [riga][colonna] => indice, posizioni indice => [riga, colonna]]
 */
function custom_tools_gallery_place($layouts, $cols)
{
    $grid   = [];
    $placed = [];
    $row    = 0;
    $col    = 0;

    foreach ($layouts as $i => $layout) {
        $w = $layout === 'wide' ? $cols : 1;
        $h = $layout === 'tall' ? 2 : 1;

        // Primo posto libero dal cursore in avanti
        while (true) {
            if ($col + $w > $cols) {
                $row++;
                $col = 0;
                continue;
            }
            $free = true;
            for ($r = $row; $r < $row + $h && $free; $r++) {
                for ($c = $col; $c < $col + $w; $c++) {
                    if (isset($grid[$r][$c])) {
                        $free = false;
                        break;
                    }
                }
            }
            if ($free) {
                break;
            }
            $col++;
        }

        for ($r = $row; $r < $row + $h; $r++) {
            for ($c = $col; $c < $col + $w; $c++) {
                $grid[$r][$c] = $i;
            }
        }
        $placed[$i] = [$row, $col];
        $col += $w;
    }

    return [$grid, $placed];
}


/**
 * =======================================================
 * Funzione Helper: custom_tools_gallery_resolve_orphans
 * =======================================================
 * Corregge gli ingombri del layout modulare perché su desktop non
 * restino buchi. L'ordine delle foto non cambia.
 *
 * 1. Una 'tall' che lascerebbe celle vuote accanto a sé (prima di una
 *    'wide', con una sola foto accanto, in fondo alla galleria) diventa
 *    standard. Eccezione, solo con 2 colonne: se è l'ultima foto e le due
 *    prima sono standard affiancate, la 'tall' va a destra e le due
 *    standard si impilano a sinistra (riga in "righe").
 * 2. L'ultima foto, se resta da sola nella sua riga, diventa 'wide'.
 *
 * @param string[] $layouts Ingombri nell'ordine della galleria ('', 'wide', 'tall')
 * @param int      $cols    Colonne su desktop
 * @return array ['ingombri' => string[], 'righe' => [indice => riga (da 0)]]
 */
function custom_tools_gallery_resolve_orphans($layouts, $cols)
{
    $layouts = array_values($layouts);
    $rows    = [];
    $last    = count($layouts) - 1;

    if ($cols < 2 || $last < 0) {
        return ['ingombri' => $layouts, 'righe' => $rows];
    }

    // 1. 'tall' orfane: ogni giro corregge la prima e rifà la simulazione
    for ($guard = 0; $guard <= $last; $guard++) {
        [$grid, $placed] = custom_tools_gallery_place($layouts, $cols);

        $orphan = null;
        foreach ($layouts as $i => $layout) {
            if ($layout !== 'tall') {
                continue;
            }
            [$r0] = $placed[$i];
            for ($r = $r0; $r < $r0 + 2 && $orphan === null; $r++) {
                for ($c = 0; $c < $cols; $c++) {
                    if (!isset($grid[$r][$c])) {
                        $orphan = $i;
                        break;
                    }
                }
            }
            if ($orphan !== null) {
                break;
            }
        }

        if ($orphan === null) {
            break;
        }

        // Ultima 'tall' finita a sinistra sotto due standard affiancate:
        // va a destra, accanto alle due standard impilate
        $row = $placed[$orphan][0] - 1;
        if ($orphan === $last && $cols === 2 && $last >= 2
            && $layouts[$last - 1] === '' && $layouts[$last - 2] === ''
            && $placed[$last] === [$row + 1, 0]
            && $placed[$last - 2] === [$row, 0] && $placed[$last - 1] === [$row, 1]) {
            $rows[$last] = $row;
            break;
        }

        $layouts[$orphan] = '';
    }

    // 2. Ultima foto da sola nella sua riga: a tutta larghezza
    if (!isset($rows[$last]) && $layouts[$last] === '') {
        [$grid, $placed] = custom_tools_gallery_place($layouts, $cols);
        if (count($grid[$placed[$last][0]]) === 1) {
            $layouts[$last] = 'wide';
        }
    }

    return ['ingombri' => $layouts, 'righe' => $rows];
}


/**
 * =======================================================
 * Funzione Helper: custom_tools_gallery_trio
 * =======================================================
 * Ingombri del layout "trio": la prima di ogni tre foto è la grande
 * ('tall'). In fondo: se restano due foto si affiancano, se ne resta
 * una sola va a tutta larghezza ('wide').
 *
 * @param int $count Numero di foto
 * @return string[]
 */
function custom_tools_gallery_trio($count)
{
    $layouts = [];
    for ($i = 0; $i < $count; $i++) {
        $layouts[] = $i % 3 === 0 ? 'tall' : '';
    }

    if ($count % 3 === 2) {
        $layouts[$count - 2] = '';
    } elseif ($count % 3 === 1) {
        $layouts[$count - 1] = 'wide';
    }

    return $layouts;
}


/**
 * =======================================================
 * Funzione Helper: custom_tools_gallery_sizes
 * =======================================================
 * Attributo "sizes" per una cella che occupa $span colonne, calcolato
 * dai parametri: è ciò che decide quale file scarica il browser, quindi
 * la nitidezza e il peso. Larghezza utile: 1138px (contenuto) o 100vw
 * (piena), meno il padding e i gap tra le colonne.
 *
 * Nel layout "contenuto" la galleria smette di crescere a 1158px di
 * schermo: da lì in su la larghezza è un valore fisso, anche quando il
 * breakpoint desktop è più basso (1024, 1280) o più alto (1440, per le
 * colonne tablet).
 *
 * @param array $cfg          Configurazione (custom_tools_gallery_config)
 * @param int   $span         Colonne occupate su desktop (1 o tutte)
 * @param bool  $desktop_only Solo la parte dal breakpoint in su (per <source>)
 * @return string
 */
function custom_tools_gallery_sizes($cfg, $span = 1, $desktop_only = false)
{
    $pad    = CUSTOM_TOOLS_GALLERY_PADDING;
    $max    = CUSTOM_TOOLS_GALLERY_WIDTH;
    $capped = $max + $pad; // schermo da cui la galleria "contenuto" è ferma a $max
    $bp     = $cfg['breakpoint'];
    $full   = $cfg['full'];

    // Larghezza fissa (galleria al massimo) e fluida (in proporzione allo schermo)
    $fixed = function ($cols, $span) use ($cfg, $max) {
        $gaps = ($cols - 1) * $cfg['gap'];
        return (int) round((($max - $gaps) / $cols) * $span + ($span - 1) * $cfg['gap']) . 'px';
    };
    $fluid = function ($cols, $span) use ($cfg, $pad) {
        if ($span >= $cols) {
            return 'calc(100vw - ' . $pad . 'px)';
        }
        $gaps = ($cols - 1) * $cfg['gap'];
        return sprintf('calc((100vw - %dpx) * %s)', $pad + $gaps, rtrim(rtrim(number_format($span / $cols, 4, '.', ''), '0'), '.'));
    };

    $cols = $cfg['cols'];
    $span = min($span, $cols);
    $list = []; // [larghezza minima dello schermo, larghezza della foto], dalla più grande

    // Desktop: dal breakpoint in su
    if (!$full && $bp < $capped) {
        $list[] = [$capped, $fixed($cols, $span)];
        $list[] = [$bp, $fluid($cols, $span)];
    } else {
        $list[] = [$bp, $full ? $fluid($cols, $span) : $fixed($cols, $span)];
    }

    if ($desktop_only) {
        $default = array_pop($list)[1];
    } else {
        // Tablet: dai 768px al breakpoint; mobile: sotto
        $tablet = $cfg['cols_tablet'];
        if (!$full && $bp > $capped) {
            $list[] = [$capped, $fixed($tablet, 1)];
        }
        $list[] = [CUSTOM_TOOLS_GALLERY_TABLET, $fluid($tablet, 1)];
        $default = $fluid($cfg['cols_mobile'], 1);
    }

    // Via le condizioni inutili: stessa larghezza della fascia sotto
    $parts = [];
    $next  = $default;
    foreach (array_reverse($list) as [$min, $value]) {
        if ($value !== $next) {
            array_unshift($parts, sprintf('(min-width: %dpx) %s', $min, $value));
        }
        $next = $value;
    }
    $parts[] = $default;

    return implode(', ', $parts);
}


/**
 * =======================================================
 * Funzione Helper: custom_tools_gallery_item
 * =======================================================
 * Markup di una singola immagine della galleria.
 *
 * @param int        $id        ID dell'allegato.
 * @param string     $slideshow Gruppo della lightbox.
 * @param array|null $cfg       Configurazione (default: quella storica)
 * @param int        $index     Posizione nella galleria (per "trio")
 * @return string
 */
function custom_tools_gallery_item($id, $slideshow, $cfg = null, $index = 0)
{
    $metadata = wp_get_attachment_metadata($id);

    if (empty($metadata['width']) || empty($metadata['height'])) {
        return '';
    }

    $cfg = $cfg ?: custom_tools_gallery_config([
        'layout' => 'modulare', 'colonne' => '', 'colonne_tablet' => '', 'colonne_mobile' => '',
        'breakpoint' => '', 'proporzione' => '', 'gap' => '', 'larghezza' => 'contenuto',
        'didascalie' => '', 'nitide' => '',
    ]);

    // Ingombro: nel layout modulare lo sceglie la cliente (campo ACF);
    // nel "trio" la prima di ogni tre foto è la grande (come 'tall').
    // Di solito arriva già corretto dallo shortcode ($cfg['ingombri']).
    if ($cfg['layout'] === 'modulare') {
        $layout = $cfg['ingombri'][$index] ?? custom_tools_gallery_layout($id);
    } elseif ($cfg['layout'] === 'trio') {
        $layout = $cfg['ingombri'][$index] ?? ($index % 3 === 0 ? 'tall' : '');
    } else {
        $layout = '';
    }

    if ($cfg['default']) {
        // Configurazione storica: stessi "sizes" di sempre
        $bp   = CUSTOM_TOOLS_GALLERY_BREAKPOINT;
        $full = CUSTOM_TOOLS_GALLERY_WIDTH;
        $col  = CUSTOM_TOOLS_GALLERY_COLUMN;

        // Larghezza mostrata (sizes): 'wide' resta a tutta larghezza anche su due colonne
        $pad  = CUSTOM_TOOLS_GALLERY_PADDING;
        $sizes = $layout === 'wide'
            ? sprintf('(min-width: %1$dpx) %1$dpx, calc(100vw - %2$dpx)', $full, $pad)
            : sprintf('(min-width: %dpx) %dpx, (min-width: %dpx) %dpx, calc(100vw - %dpx)', $bp, $col, $full + $pad, $full, $pad);
    } else {
        $sizes = custom_tools_gallery_sizes($cfg, $layout === 'wide' ? $cfg['cols'] : 1);
    }

    // Testo alternativo: quello dell'immagine; se la cliente non l'ha compilato,
    // il titolo del matrimonio (meglio di un alt vuoto, per accessibilità e SEO)
    $alt = trim((string) get_post_meta($id, '_wp_attachment_image_alt', true)) ?: get_the_title();

    $attr = [
        'class'    => 'modular-gallery__img',
        'sizes'    => $sizes,
        'alt'      => $alt,
        'loading'  => 'lazy',
        'decoding' => 'async',
    ];

    // Allineamento scelto dalla cliente: vale quando il CSS adatta la foto alla
    // cella senza un ritaglio dedicato (es. foto verticale in una cella 'tall')
    $object_position = custom_tools_gallery_object_position(custom_tools_gallery_crop_position($id));
    if (($layout || $cfg['ratio']) && $object_position) {
        $attr['style'] = 'object-position: ' . $object_position;
    }

    // Foto nitide: mai più larga della sua risoluzione
    if ($cfg['sharp']) {
        $attr['style'] = trim(($attr['style'] ?? '') . '; max-width: ' . (int) $metadata['width'] . 'px', '; ');
    }

    $img = wp_get_attachment_image($id, 'large', false, $attr);

    if (!$img) {
        return '';
    }

    $source = custom_tools_gallery_crop_source($id, $metadata, $layout, $cfg);
    $media  = $source ? '<picture>' . $source . $img . '</picture>' : $img;

    if ($cfg['captions']) {
        $caption = wp_get_attachment_caption($id);
        if ($caption) {
            $media .= '<span class="modular-gallery__didascalia">' . esc_html($caption) . '</span>';
        }
    }

    $class = 'modular-gallery__item' . ($layout ? ' ' . $layout : '');
    $style = '';

    // Ultima 'tall' spostata a destra: riga calcolata dallo shortcode (grid-row parte da 1)
    if (isset($cfg['righe'][$index])) {
        $class .= ' modular-gallery__item--destra';
        $style  = sprintf(' style="--mg-riga: %d"', (int) $cfg['righe'][$index] + 1);
    }

    return sprintf(
        '<a href="%s" class="%s"%s data-elementor-open-lightbox="yes" data-elementor-lightbox-slideshow="%s" rel="lightbox">%s</a>',
        esc_url(custom_tools_large_image_url($id)),
        esc_attr($class),
        $style,
        esc_attr($slideshow),
        $media
    );
}


/**
 * =======================================================
 * Funzione Helper: custom_tools_gallery_crop_source
 * =======================================================
 * <source> con il ritaglio per l'ingombro scelto, valido dal breakpoint
 * delle colonne desktop (dove la cella 'tall' o 'wide' ha davvero quella
 * forma). Stringa vuota se l'immagine non ha ritagli per quell'ingombro
 * (la foto ha già l'orientamento giusto): in quel caso il CSS adatta la
 * foto con object-fit.
 *
 * @param int        $id
 * @param array      $metadata
 * @param string     $layout
 * @param array|null $cfg
 * @return string
 */
function custom_tools_gallery_crop_source($id, $metadata, $layout, $cfg = null)
{
    if (!$layout) {
        return '';
    }

    $names = custom_tools_gallery_crop_sizes($metadata, $layout);

    if (!$names) {
        return '';
    }

    $base   = trailingslashit(dirname(wp_get_attachment_url($id)));
    $srcset = [];
    foreach ($names as $name) {
        $size     = $metadata['sizes'][$name];
        $srcset[] = $base . rawurlencode($size['file']) . ' ' . (int) $size['width'] . 'w';
    }
    $srcset = implode(', ', $srcset);

    $largest = $metadata['sizes'][end($names)];

    if (!$cfg || $cfg['default']) {
        $breakpoint = CUSTOM_TOOLS_GALLERY_BREAKPOINT;
        $desktop    = ($layout === 'tall' ? CUSTOM_TOOLS_GALLERY_COLUMN : CUSTOM_TOOLS_GALLERY_WIDTH) . 'px';
    } else {
        $breakpoint = $cfg['breakpoint'];
        // Basta la parte desktop: <source> vale solo dal breakpoint
        $desktop    = custom_tools_gallery_sizes($cfg, $layout === 'wide' ? $cfg['cols'] : 1, true);
    }

    return sprintf(
        '<source media="%s" srcset="%s" sizes="%s" type="%s" width="%d" height="%d">',
        esc_attr('(min-width: ' . $breakpoint . 'px)'),
        esc_attr($srcset),
        esc_attr($desktop),
        esc_attr($largest['mime-type'] ?? 'image/jpeg'),
        (int) $largest['width'],
        (int) $largest['height']
    );
}


/**
 * =======================================================
 * Funzione Helper: get_gallery_thumb_url
 * =======================================================
 * Ritorna l'URL di un'immagine della Gallery nel formato adatto al suo
 * ingombro (campo ACF 'gallery_image_size'). Non più usata dallo shortcode,
 * che ora stampa <img> con srcset; resta per compatibilità con eventuali
 * template o snippet che la chiamano.
 *
 * - 'wide':     formato 'gallery_1200' (prima 'gallery_wide', 1480px)
 * - 'tall':     ritaglio verticale 900x1200 se esiste (prima 'gallery_tall', 0x1200)
 * - 'standard': 'large' (~1024px)
 *
 * @param int         $image_id L'ID dell'immagine in WP.
 * @param string|null $size     Valore già noto di 'gallery_image_size'
 *                              ('wide'|'tall'|'standard'|''). Se null viene letto qui.
 * @return string L'URL della thumb ottimizzata.
 */
function get_gallery_thumb_url($image_id, $size = null)
{
    if ($size === null) {
        $size = get_post_meta($image_id, 'gallery_image_size', true);
    }

    $metadata = wp_get_attachment_metadata($image_id);

    switch ($size) {
        case 'wide':
            $candidates = ['gallery_1200', 'gallery_wide', 'large'];
            break;
        case 'tall':
            $crops      = custom_tools_gallery_crop_sizes(is_array($metadata) ? $metadata : [], 'tall');
            $candidates = array_merge(array_reverse(array_slice($crops, 0, 2)), ['gallery_tall', 'large']);
            break;
        case 'standard':
        default:
            $candidates = ['large'];
            break;
    }

    foreach ($candidates as $wp_size) {
        if (!empty($metadata['sizes'][$wp_size])) {
            $image_data = wp_get_attachment_image_src($image_id, $wp_size);
            if ($image_data) {
                return $image_data[0];
            }
        }
    }

    return (string) wp_get_attachment_url($image_id);
}
