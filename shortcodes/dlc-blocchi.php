<?php

/**
 * =======================================================
 * Blocchi DLC (design Figma del rifacimento)
 * =======================================================
 *
 * Funzioni PHP usate dai template nativi del tema (hello-theme-child:
 * header-dlc.php, single-post.php, home.php, page-templates/dlc-*.php).
 *
 * Riusano gli elementi già esistenti, senza duplicarli:
 *  - titoli:     classi del tema .title-Literata-md / .title-Anton-lg e
 *                .header-title / .header-title__secondRow (come [matrimonio_titolo])
 *  - pulsanti:   .elementor-button, stilizzato dal Kit Elementor
 *  - colori:     colori globali del Kit Elementor (unica fonte di verità)
 *  - gallerie:   [modular_gallery] (field oppure ids)
 *
 * Unico shortcode nuovo, perché non esisteva nulla di simile:
 *   [dlc_instagram immagini="1,2,3,4,5,6" url="https://www.instagram.com/…/"]
 *
 * Lo stile è nel tema (sass/blocks/). Le immagini escono con srcset/sizes,
 * width/height e lazy-load (tranne l'hero, che è l'elemento LCP).
 * =======================================================
 */

if (!defined('ABSPATH')) {
    exit;
}


/**
 * =======================================================
 * Helper comuni
 * =======================================================
 */

/**
 * <img> responsive di un allegato con i default dei blocchi DLC.
 *
 * @param int    $id    ID dell'allegato
 * @param string $size  Formato di partenza (la srcset include gli altri)
 * @param string $sizes Attributo sizes
 * @param array  $attr  Attributi aggiuntivi (class, alt, loading, fetchpriority…)
 * @return string
 */
function dlc_img($id, $size, $sizes, $attr = [])
{
    if (!$id || !wp_attachment_is_image($id)) {
        return '';
    }

    $alt = trim((string) get_post_meta($id, '_wp_attachment_image_alt', true));

    return wp_get_attachment_image($id, $size, false, array_merge([
        'sizes'    => $sizes,
        'alt'      => $alt,
        'loading'  => 'lazy',
        'decoding' => 'async',
    ], $attr));
}

/**
 * Titolo a due font con le classi già esistenti nel tema
 * (sass/global/_typography.scss), le stesse dei titoli della home:
 *   - riga corsiva:  .title-Literata-md   (Literata italic)
 *   - riga maiuscola: .title-Anton-lg      (Anton uppercase)
 *   - hero:          .header-title + .header-title__secondRow, come [matrimonio_titolo]
 * Qui si aggiungono solo colore e allineamento (.dlc-titolo).
 *
 * @param string $corsivo   Prima parte (corsivo)
 * @param string $maiuscolo Seconda parte (maiuscolo)
 * @param array  $opts      tag (h1…h3, p), allinea (center|left|right),
 *                          layout (colonna|riga), stile (titolo|hero),
 *                          misura (lg|xl → .title-Anton-lg / -xl), class
 * @return string
 */
function dlc_titolo_html($corsivo, $maiuscolo, $opts = [])
{
    $opts = wp_parse_args($opts, [
        'tag'     => 'h2',
        'allinea' => 'center',
        'layout'  => 'colonna',
        'stile'   => 'titolo',
        'misura'  => 'lg',
        'class'   => '',
    ]);

    $tag  = in_array($opts['tag'], ['h1', 'h2', 'h3', 'h4', 'p', 'div'], true) ? $opts['tag'] : 'h2';
    $hero = $opts['stile'] === 'hero';

    $class = trim(sprintf(
        'dlc-titolo dlc-titolo--%s dlc-titolo--%s %s %s',
        sanitize_html_class($opts['allinea']),
        sanitize_html_class($opts['layout']),
        $hero ? 'header-title' : 'title-Literata-md',
        $opts['class']
    ));

    $second = $hero ? 'header-title__secondRow' : ($opts['misura'] === 'xl' ? 'title-Anton-xl' : 'title-Anton-lg');

    $parts = esc_html($corsivo);
    if ($maiuscolo !== '') {
        $parts .= ($corsivo !== '' ? ' ' : '') . '<span class="' . $second . '">' . esc_html($maiuscolo) . '</span>';
    }

    return sprintf('<%1$s class="%2$s">%3$s</%1$s>', $tag, esc_attr($class), $parts);
}

/**
 * Divide un titolo nelle due righe del design.
 * Convenzione: "Il miglior reportage di | Nozze del 2021" → [corsivo, maiuscolo].
 * Senza "|" tutto il titolo va nella riga maiuscola.
 *
 * @param string $title
 * @return array [corsivo, maiuscolo]
 */
function dlc_split_title($title)
{
    $title = wp_strip_all_tags(html_entity_decode((string) $title, ENT_QUOTES, 'UTF-8'));

    if (strpos($title, '|') !== false) {
        [$a, $b] = array_map('trim', explode('|', $title, 2));
        return [$a, $b];
    }

    return ['', trim($title)];
}

/**
 * Il titolo del post senza il separatore "|" (per <title>, card, breadcrumb).
 */
add_filter('the_title', function ($title) {
    return is_admin() ? $title : str_replace(' | ', ' ', (string) $title);
}, 5);

// Niente "Privato:" davanti ai titoli nel front-end (contenuti privati in revisione)
add_filter('private_title_format', function ($format) {
    return is_admin() ? $format : '%s';
});

add_filter('document_title_parts', function ($parts) {
    if (isset($parts['title'])) {
        $parts['title'] = str_replace(' | ', ' ', $parts['title']);
    }
    return $parts;
});

/**
 * Titolo "grezzo" (con il separatore) di un post, per i template.
 *
 * @param int|null $post_id
 * @return string
 */
function dlc_raw_title($post_id = null)
{
    $post = get_post($post_id);
    return $post ? $post->post_title : '';
}

/**
 * Pulsante: .elementor-button, stilizzato dal Kit Elementor (unica fonte
 * di verità per ora), lo stesso dei pulsanti della home.
 * tema "chiaro" = bianco, per i fondi scuri.
 *
 * @param string $text
 * @param string $url
 * @param string $tema scuro | chiaro
 * @return string
 */
function dlc_bottone_html($text, $url, $tema = 'scuro')
{
    if ($text === '' || $url === '') {
        return '';
    }

    return sprintf(
        '<a class="elementor-button%s" href="%s"><span class="elementor-button-text">%s</span></a>',
        $tema === 'chiaro' ? ' dlc-chiaro' : '',
        esc_url($url),
        esc_html($text)
    );
}

/**
 * Estratto con l'enfasi del design: le parti in *asterischi* o già in
 * <em> diventano corsivo rosso (.dlc-enfasi).
 *
 * @param string $text
 * @return string HTML sicuro
 */
function dlc_testo_enfasi($text)
{
    $text = wp_kses((string) $text, ['em' => [], 'strong' => [], 'br' => []]);
    $text = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $text);

    return str_replace('<em>', '<em class="dlc-enfasi">', $text);
}


/**
 * =======================================================
 * Hero
 * =======================================================
 * Foto a tutta larghezza con titolo sovrapposto. È l'elemento più grande
 * della pagina (LCP): <img> vero (non uno sfondo CSS), caricato subito,
 * con srcset per servire il file giusto anche su smartphone.
 */
function dlc_hero_html($image_id, $corsivo = '', $maiuscolo = '', $opts = [])
{
    $opts = wp_parse_args($opts, [
        'allinea' => 'center',
        'altezza' => 'media',   // media (≈ 600px desktop) | piena (100svh)
        'tag'     => 'h1',
        'velo'    => '0.35',    // opacità del velo scuro sotto il titolo
    ]);

    $img = $image_id ? dlc_img($image_id, 'full', '100vw', [
        'class'         => 'dlc-hero__img',
        'loading'       => 'eager',
        'fetchpriority' => 'high',
        'decoding'      => 'auto',
    ]) : '';

    $title = ($corsivo !== '' || $maiuscolo !== '')
        ? dlc_titolo_html($corsivo, $maiuscolo, ['tag' => $opts['tag'], 'allinea' => $opts['allinea'], 'stile' => 'hero'])
        : '';

    return sprintf(
        '<section class="dlc-hero dlc-hero--%s dlc-hero--%s" style="--dlc-velo:%s">%s<div class="dlc-hero__contenuto">%s</div></section>',
        esc_attr(sanitize_html_class($opts['altezza'])),
        esc_attr(sanitize_html_class($opts['allinea'])),
        esc_attr((string) (float) $opts['velo']),
        $img,
        $title
    );
}



/**
 * =======================================================
 * Card dei Real Weddings
 * =======================================================
 * Griglia a due colonne: foto verticale, nomi degli sposi (link) e
 * frase di presentazione (estratto, con *enfasi* in corsivo rosso).
 */
function dlc_matrimoni_html($args = [])
{
    $args = wp_parse_args($args, ['numero' => -1, 'escludi' => '']);

    $query = new WP_Query([
        'post_type'           => 'matrimonio',
        'posts_per_page'      => (int) $args['numero'],
        'post__not_in'        => array_filter(array_map('intval', explode(',', (string) $args['escludi']))),
        'orderby'             => ['menu_order' => 'ASC', 'date' => 'DESC'],
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
    ]);

    if (!$query->have_posts()) {
        return '';
    }

    // Un'unica query per i meta di tutte le immagini in evidenza
    update_post_thumbnail_cache($query);

    $html = '<div class="dlc-matrimoni">';
    while ($query->have_posts()) {
        $query->the_post();

        $link  = get_permalink();
        $nome1 = get_post_meta(get_the_ID(), 'nome_coniuge_1', true);
        $nome2 = get_post_meta(get_the_ID(), 'nome_coniuge_2', true);
        $nomi  = ($nome1 && $nome2) ? $nome1 . ' e ' . $nome2 : get_the_title();
        $frase = has_excerpt() ? get_the_excerpt() : wp_trim_words(wp_strip_all_tags((string) get_post_meta(get_the_ID(), 'intro_matrimonio', true)), 22);

        $img = dlc_img(get_post_thumbnail_id(), 'large', '(min-width: 1200px) 571px, (min-width: 768px) 46vw, calc(100vw - 32px)', [
            'class' => 'dlc-card__img',
        ]);

        $html .= '<article class="dlc-card dlc-card--matrimonio">';
        $html .= '<a class="dlc-card__media" href="' . esc_url($link) . '" tabindex="-1" aria-hidden="true">' . $img . '</a>';
        $html .= '<div class="dlc-card__testo">';
        $html .= '<a class="dlc-card__etichetta" href="' . esc_url($link) . '">' . esc_html($nomi) . '</a>';
        $html .= '<p class="dlc-card__frase">' . dlc_testo_enfasi($frase) . '</p>';
        $html .= '</div></article>';
    }
    wp_reset_postdata();

    return $html . '</div>';
}



/**
 * =======================================================
 * Card Side Projects / Real Life
 * =======================================================
 */
function dlc_progetti_html($args = [])
{
    $args = wp_parse_args($args, ['tipo' => 'side-project', 'numero' => -1]);

    if (!post_type_exists($args['tipo'])) {
        return '';
    }

    $query = new WP_Query([
        'post_type'           => $args['tipo'],
        'posts_per_page'      => (int) $args['numero'],
        'orderby'             => ['menu_order' => 'ASC', 'date' => 'DESC'],
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
    ]);

    if (!$query->have_posts()) {
        return '';
    }

    update_post_thumbnail_cache($query);

    $html = '<div class="dlc-progetti">';
    while ($query->have_posts()) {
        $query->the_post();

        $img = dlc_img(get_post_thumbnail_id(), 'large', '(min-width: 1200px) 662px, (min-width: 768px) 46vw, calc(100vw - 32px)', [
            'class' => 'dlc-card__img',
        ]);

        $html .= '<article class="dlc-card dlc-card--progetto"><a class="dlc-card__link" href="' . esc_url(get_permalink()) . '">';
        $html .= '<span class="dlc-card__media">' . $img . '</span>';
        $html .= '<h3 class="dlc-card__titolo">' . esc_html(get_the_title()) . '</h3>';
        $html .= '</a></article>';
    }
    wp_reset_postdata();

    return $html . '</div>';
}



/**
 * =======================================================
 * Lista articoli
 * =======================================================
 * Riga per articolo: foto a sinistra, a destra categoria, frase di
 * apertura (titolo, con *enfasi*), estratto e pulsante "Leggi l'articolo".
 * Usa la query principale nelle pagine archivio (home.php, category.php),
 * altrimenti una query propria.
 */
function dlc_blog_riga_html()
{
    $link = get_permalink();
    $cats = get_the_category();
    $cat  = $cats ? $cats[0] : null;

    $img = dlc_img(get_post_thumbnail_id(), 'large', '(min-width: 1200px) 805px, (min-width: 768px) 55vw, calc(100vw - 32px)', [
        'class' => 'dlc-riga__img',
    ]);

    $excerpt = has_excerpt() ? get_the_excerpt() : wp_trim_words(wp_strip_all_tags(strip_shortcodes(get_the_content())), 40);

    $html  = '<article class="dlc-riga">';
    $html .= '<a class="dlc-riga__media" href="' . esc_url($link) . '" tabindex="-1" aria-hidden="true">' . $img . '</a>';
    $html .= '<div class="dlc-riga__testo">';
    if ($cat) {
        $html .= '<a class="dlc-riga__categoria" href="' . esc_url(get_category_link($cat)) . '">' . esc_html($cat->name) . '</a>';
    }
    $html .= '<h2 class="dlc-riga__titolo"><a href="' . esc_url($link) . '">' . dlc_testo_enfasi(get_the_title()) . '</a></h2>';
    $html .= '<p class="dlc-riga__estratto">' . esc_html($excerpt) . '</p>';
    $html .= dlc_bottone_html(__('Leggi l’articolo', 'custom-tools'), $link);
    $html .= '</div></article>';

    return $html;
}

function dlc_blog_html($args = [])
{
    $args = wp_parse_args($args, ['numero' => 10, 'categoria' => '', 'query' => null]);

    $query = $args['query'] instanceof WP_Query ? $args['query'] : new WP_Query([
        'post_type'           => 'post',
        'posts_per_page'      => (int) $args['numero'],
        'category_name'       => sanitize_title($args['categoria']),
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
    ]);

    if (!$query->have_posts()) {
        return '';
    }

    update_post_thumbnail_cache($query);

    $html = '<div class="dlc-blog">';
    while ($query->have_posts()) {
        $query->the_post();
        $html .= dlc_blog_riga_html();
    }

    if (!$args['query'] instanceof WP_Query) {
        wp_reset_postdata();
    }

    return $html . '</div>';
}



/**
 * =======================================================
 * [dlc_instagram] — striscia di 6 foto + pulsante
 * =======================================================
 * Nessuna API esterna (niente token, niente script di terze parti):
 * foto scelte dalla Libreria media che rimandano al profilo.
 * Senza "immagini" usa le immagini in evidenza degli ultimi matrimoni.
 */
add_shortcode('dlc_instagram', function ($atts) {
    $atts = shortcode_atts([
        'immagini' => '',
        'url'      => 'https://www.instagram.com/deborahlocastro/',
        'testo'    => 'Seguimi su Instagram',
    ], $atts);

    $ids = array_filter(array_map('intval', explode(',', (string) $atts['immagini'])));

    if (!$ids) {
        $ids = array_filter(array_map('get_post_thumbnail_id', get_posts([
            'post_type'      => ['matrimonio', 'post', 'side-project', 'real-life'],
            'posts_per_page' => 6,
            'fields'         => 'ids',
            'meta_key'       => '_thumbnail_id',
        ])));
    }

    $ids = array_slice($ids, 0, 6);

    if (!$ids) {
        return '';
    }

    $html = '<section class="dlc-instagram"><ul class="dlc-instagram__griglia" style="--dlc-foto:' . count($ids) . '">';
    foreach ($ids as $id) {
        $html .= '<li><a href="' . esc_url($atts['url']) . '" target="_blank" rel="noopener">'
            . dlc_img($id, 'medium_large', '(min-width: 768px) 17vw, 50vw', ['class' => 'dlc-instagram__img', 'alt' => ''])
            . '<span class="screen-reader-text">Instagram</span></a></li>';
    }
    $html .= '</ul>' . dlc_bottone_html($atts['testo'], $atts['url'], 'chiaro') . '</section>';

    return $html;
});
