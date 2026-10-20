# Custom Tools

Plugin WordPress custom per il sito, orientato a:

- gestione contenuti custom (Portfolio, Matrimoni e altri CPT via ACF JSON)
- integrazione ACF + Elementor
- shortcode per rendering dinamico
- supporto lightbox e gallerie modulari
- pipeline immagini ottimizzata (WebP, soglia 2048px, ritagli per la gallery, srcset)
- miglioramenti UX nell'admin del CPT Portfolio

NOTA:
Per funzionare, è necessario che siano installati e attivi i Plugin:
- ACF Pro
- Elementor Pro 

## Informazioni Plugin

- Nome: `Custom Tools`
- File bootstrap: `custom-tools.php`
- Versione dichiarata: `1.0`
- Autore dichiarato: `Fabrizio Rizzo`

## Cosa fa il plugin (in dettaglio)

### 1. Bootstrap delle funzionalita

Il file `custom-tools.php` carica i moduli PHP:

- `inc/dependencies.php` e `inc/images.php` (sempre: non usano ACF)
- `inc/register-cpt.php`
- `inc/admin-columns.php`
- `shortcodes/matrimonio.php`
- `shortcodes/portfolio.php`
- `shortcodes/modular-gallery.php`

I moduli che usano `get_field()` vengono caricati su `plugins_loaded` solo se ACF
è attivo (vedi anche `inc/dependencies.php`).

### 2. Sync ACF JSON dentro la repo

Il plugin intercetta:

- `acf/settings/save_json`
- `acf/settings/load_json`

e imposta come cartella JSON locale:

- `assets/acf-json`

Comportamento:

- se la cartella non esiste, viene creata
- i gruppi campi e i post type ACF vengono versionati in repo
- ACF puo caricare automaticamente queste definizioni da qui

### 3. Pipeline immagini (`inc/images.php`)

Cosa succede a ogni immagine caricata, dalla Libreria media o dai campi ACF:

| Regola | Hook | Effetto |
|---|---|---|
| Soglia 2048px | `big_image_size_threshold` | oltre i 2048px WordPress crea la copia `-scaled` e la usa come immagine "piena" (lightbox, sfondi). L'originale resta sul server. |
| Output WebP | `image_editor_output_format` | JPEG e PNG vengono salvati in WebP (formati intermedi e `-scaled`), solo se il server lo supporta. Qualità 82 (`wp_editor_set_quality`). |
| Metadati ridotti | `wp_image_editors` → `inc/class-image-editor-imagick.php` | i file generati perdono EXIF/XMP (fino a 21KB a file, 10 volte una miniatura). Il profilo colore resta se non è sRGB. L'originale è intatto. |
| Niente `2048x2048` | `intermediate_image_sizes_advanced` | sarebbe identico alla copia `-scaled`. |
| Ritagli gallery | `wp_generate_attachment_metadata` | vedi sotto. |

Formati registrati (`after_setup_theme`, visibili anche nel Media Picker):

- `portfolio_thumb`: larghezza 454, altezza libera, no crop (anteprime portfolio in home)
- `gallery_1200`: larghezza 1200, altezza libera, no crop. Misura esatta per la
  galleria a colonna singola (1138px) e per gli smartphone a densità 3x.

`gallery_wide` (1480) e `gallery_tall` (0x1200) non vengono più generati: il primo
era a 4% da `1536x1536`, il secondo su una foto orizzontale creava un 1800x1200 di
cui si vedeva solo una striscia. I file già esistenti restano validi.

#### Ritagli per la Modular Gallery

La cliente sceglie l'ingombro di ogni foto (campo `gallery_image_size`) e può
cambiarlo quando vuole: per questo **ogni fotografia** (JPEG/WebP/AVIF/HEIC; PNG,
GIF e SVG esclusi) riceve al caricamento anche il formato con l'orientamento opposto:

| Foto | Ritaglio generato | Per l'ingombro | Larghezze |
|---|---|---|---|
| orizzontale o quadrata | verticale 3:4 | `tall` (cella 564x762) | 600, 900, 1200 |
| verticale o quadrata | orizzontale 3:2 | `wide` (due colonne) | 768, 1200, 1800 |

I ritagli si vedono solo dai 1440px, dove la galleria ha due colonne. Sotto, la
foto viene mostrata intera, nel suo orientamento originale.

- i ritagli partono dall'originale caricato, con taglio centrato;
- sono registrati nei metadati (`gallery_tall_crop_600`, …): entrano nella srcset,
  vengono cancellati con l'immagine e rigenerati da `wp media regenerate`;
- costo: 3 file WebP in più per foto (circa 150KB).

#### Testata del matrimonio

- `acf/format_value/name=img_header_matrimonio`: lo sfondo della testata usa il
  formato 2048 invece dell'originale (per le foto caricate prima della soglia).
- `wp_head`: sulle pagine matrimonio l'immagine di testata viene precaricata
  (`<link rel="preload" fetchpriority="high">`), perché è l'elemento LCP.

Helper: `custom_tools_large_image_url($id)` restituisce l'URL a 2048px
(formato `2048x2048` se esiste, altrimenti l'immagine piena).

### 4. Query personalizzata Elementor per Home Portfolio

Nel file `inc/register-cpt.php` viene agganciata la action:

- `elementor/query/home_portfolio_query`

La query imposta una `meta_query` su campo ACF:

- key: `mostra_in_home`
- value: `1`
- compare: `=`
- type: `BOOLEAN`

Uso atteso:

- nel widget Loop Grid Elementor va impostato `Query ID = home_portfolio_query`
- in output passano solo i portfolio marcati come visibili in home

### 5. Colonne custom in admin per CPT Portfolio

File: `inc/admin-columns.php`.

Funzionalita:

- aggiunge colonna `Thumbs` subito dopo la checkbox
- renderizza thumb da ACF `immagine_anteprima_portfolio` (size `thumbnail`)
- aggiunge colonna `Visibile in Home`
- mostra `check` testuale (`Si`) se `mostra_in_home` e true, trattino altrimenti
- applica CSS inline in `admin_head` per larghezza/centratura colonne

Risultato:

- gestione editoriale piu rapida dalla lista Portfolio in backend.

### 6. Shortcode disponibili

#### `[portfolio_lightbox_item]`

File: `shortcodes/portfolio.php`.

Input impliciti (campi ACF sul post corrente):

- `immagine_anteprima_portfolio`
- `immagine_originale_portfolio`

Output:

- link `<a>` con attributi `data-elementor-open-lightbox="yes"` e slideshow `portfolio`
- `<img>` preview generato da `wp_get_attachment_image()`: formato `portfolio_thumb`
  con `srcset`, `sizes` (`(min-width: 1025px) 454px, 50vw`), `width`/`height`,
  `loading="lazy"`; `alt` dell'immagine, se vuoto il titolo del portfolio
- apertura lightbox dell'immagine originale nel formato 2048 (non l'originale da diversi MB)

Se manca uno dei due campi immagine:

- ritorna stringa vuota (nessun output).

#### `[matrimonio_titolo]`

File: `shortcodes/matrimonio.php`.

Input:

- `nome_coniuge_1`
- `nome_coniuge_2`

Output:

- `Il matrimonio di` + seconda riga con i due nomi
- fallback: `Il matrimonio` se uno dei nomi manca

#### `[nomi_coniugi]`

File: `shortcodes/matrimonio.php`.

Input:

- `nome_coniuge_1`
- `nome_coniuge_2`

Output:

- stringa `Nome1 e Nome2`
- stringa vuota se manca uno dei due

#### `[info_matrimonio]`

File: `shortcodes/matrimonio.php`.

Compone una griglia `.matrimonio-info` con:

- campi fissi:
  - `matrimonio_location` (label `Venue`)
  - `matrimonio_luogo` (label `Luogo`)
  - `matrimonio_catering` (label `Catering`)
- elementi dinamici dal repeater `info_matrimonio`:
  - `etichetta_info_matrimonio`
  - `valore_info_matrimonio`

Output:

- markup solo se ci sono item valorizzati.

#### `[modular_gallery field="..." slideshow="gallery"]`

File: `shortcodes/modular-gallery.php`.

Attributi (serve `field` oppure `ids`):

- `field`: nome di un campo ACF di tipo galleria (es. `galleria_matrimonio_parte_1`); qualsiasi campo galleria, non solo quelli del matrimonio
- `ids`: elenco di ID di immagini nell'ordine voluto (es. `745,746,747`), per gallerie scelte a mano in articoli e pagine; gli ID che non sono immagini vengono ignorati
- `post` (opzionale): ID del post da cui leggere `field`, default il post corrente
- `slideshow` (opzionale): gruppo lightbox Elementor, default `gallery`

```text
[modular_gallery field="galleria_matrimonio_parte_1" slideshow="matrimonio"]
[modular_gallery ids="745,746,747" slideshow="articolo"]
[modular_gallery field="galleria_matrimonio_parte_2" post="733"]
```

Comportamento:

- legge gli ID delle immagini dal campo gallery del post corrente e fa il prime
  della cache di post e meta (due query in tutto, non due per immagine)
- aggiunge classe base `.modular-gallery__item` e il modificatore:
  - `.wide` se `gallery_image_size = wide`
  - `.tall` se `gallery_image_size = tall`
  - nessun modificatore se valore nullo/non previsto
- ogni `<img>` (classe `.modular-gallery__img`) esce da `wp_get_attachment_image()`
  con `srcset`, `sizes` calcolati sul layout, `width`/`height` (niente salti di
  layout), `loading="lazy"`, `decoding="async"`
- `alt`: quello dell'immagine, se vuoto il titolo del matrimonio
- link della lightbox: formato 2048 (`custom_tools_large_image_url()`)

Layout e file scelti (larghezze replicate nelle costanti `CUSTOM_TOOLS_GALLERY_*`
e nel tema in `sass/shortcodes/_modular-gallery.scss`):

| Schermo | Layout | Ingombro con orientamento diverso dalla foto |
|---|---|---|
| sotto i 1440px (smartphone, tablet, laptop) | una colonna (max 1138px) | foto intera nel suo orientamento originale: una `tall` orizzontale resta orizzontale |
| da 1440px | due colonne da 564px | `<picture>` con il ritaglio nella cella |

Se l'orientamento della foto coincide già con l'ingombro non c'è `<picture>`:
il CSS adatta la foto con `object-fit`.

Messaggi di fallback:

- `Campo ACF non specificato.` se mancano sia `field` sia `ids`
- `Nessuna immagine disponibile.` se la gallery e vuota

#### `[dlc_instagram immagini="1,2,3" url="…" testo="…"]`

File: `shortcodes/dlc-blocchi.php`.

Striscia di 6 foto che rimandano al profilo Instagram, con pulsante. Nessuna API
esterna. Senza `immagini` usa le immagini in evidenza degli ultimi contenuti.

Lo stesso file contiene le funzioni PHP dei template classici del tema
(`dlc_titolo_html()`, `dlc_bottone_html()`, `dlc_hero_html()`, card e lista blog):
non sono shortcode e riusano gli elementi esistenti (classi `title-Literata-md` /
`title-Anton-lg` / `header-title`, `[modular_gallery]`) e, per pulsanti e colori,
il Kit Elementor (`.elementor-button`, colori globali): per ora è l'unica fonte di
verità, da spostare nel tema in un'ottimizzazione futura.

### 7. Helper immagini per la galleria modulare

Funzione: `get_gallery_thumb_url($image_id, $size = null)`.

Non più usata dallo shortcode (che stampa `<img>` con srcset), resta per
compatibilità con template o snippet che la chiamano. Stessa firma di prima.

Mappatura campo ACF `gallery_image_size` -> formato:

- `wide` -> `gallery_1200` (fallback `gallery_wide`, `large`)
- `tall` -> ritaglio verticale 900x1200 (fallback `gallery_tall`, `large`)
- default -> `large`

Fallback finale: URL dell'immagine piena.

## Modello dati incluso (ACF JSON)

### Post Type ACF definiti in `assets/acf-json`

1. `portfolio`
- public: true
- has_archive: true
- supports: `title`, `thumbnail`, `custom-fields`
- icona admin: `dashicons-format-image`

2. `matrimonio`
- public: true
- hierarchical: true
- rewrite slug: `real-wedding`
- supports: `title`, `author`, `editor`, `excerpt`, `revisions`, `page-attributes`, `thumbnail`, `custom-fields`, `post-formats`
- icona admin: `dashicons-heart`

3. `side-project`
- public: true
- hierarchical: true
- supports estesi (simili a `matrimonio`)
- icona admin: `dashicons-palmtree`

4. `real-life`
- public: true
- hierarchical: true
- supports estesi (simili a `matrimonio`)
- icona admin: `dashicons-welcome-learn-more`

Nota tecnica:

- nel codice non ci sono chiamate dirette a `register_post_type()`.
- i CPT risultano gestiti tramite definizioni ACF JSON (post type export).

### Gruppi campi ACF principali

1. `Dettagli Elemento Portfolio` (post type `portfolio`)
- `immagine_anteprima_portfolio` (image, return `array`)
- `immagine_originale_portfolio` (image, return `url`)
- `descrizione_portfolio` (textarea)
- `mostra_in_home` (true/false)

2. `Dettagli Matrimonio` (post type `matrimonio`)
- anagrafica/contenuto:
  - `nome_coniuge_1`
  - `nome_coniuge_2`
  - `img_header_matrimonio`
  - `intro_matrimonio`
  - `img_hero_matrimonio`
- dettagli evento:
  - `matrimonio_location`
  - `matrimonio_luogo`
  - `matrimonio_catering`
  - repeater `info_matrimonio` (`etichetta_info_matrimonio`, `valore_info_matrimonio`)
- gallery/story:
  - `galleria_matrimonio_parte_1`
  - `storia_matrimonio_parte_1`
  - `galleria_matrimonio_parte_2`
  - `storia_matrimonio_parte_2`

3. `Image - Meta data` (location attachment `image/jpeg`)
- `gallery_image_size` (select: `wide`, `tall`, nullable)
- usato da shortcode modular gallery per il layout.

## Dipendenze operative

Dipendenze richieste per funzionalita complete:

- ACF Pro:
  - funzioni `get_field`, `have_rows`, `get_sub_field`
  - gestione field groups da JSON
  - gestione post type da JSON ACF
- Elementor (in pratica Elementor Pro per loop/query custom e lightbox):
  - hook `elementor/query/home_portfolio_query`
  - attributi lightbox `data-elementor-open-lightbox`

Dipendenze non strettamente bloccanti ma coerenti con i JSON:

- ACF Extended (diverse chiavi `acfe_*` nei file JSON).

### Controllo automatico (`inc/dependencies.php`)

All'avvio il plugin verifica le dipendenze e mostra un avviso nella bacheca di
WordPress se manca qualcosa:

- **ACF PRO** mancante o versione free → avviso rosso (`notice-error`); i moduli
  che usano `get_field()` (CPT, colonne admin, shortcode) **non vengono caricati**,
  per evitare errori fatali.
- **Elementor / Elementor Pro** mancante → avviso giallo (`notice-warning`); gli
  shortcode restano attivi ma senza l'effetto atteso (loop, lightbox).

Rilevamento: `acf_is_pro()` / `ACF_PRO` per ACF, `ELEMENTOR_VERSION` e
`ELEMENTOR_PRO_VERSION` per Elementor.

## Installazione e setup consigliato

1. Posizionare il plugin in `wp-content/plugins/custom-tools`.
2. Attivare il plugin da backend WordPress.
3. Verificare che ACF sia attivo (e preferibilmente Elementor).
4. Controllare che `assets/acf-json` sia leggibile/scrivibile.
5. In Elementor Loop Grid per home portfolio, impostare `Query ID` a `home_portfolio_query`.
6. Inserire shortcode nei template Elementor dove serve.
7. Sui media caricati prima di queste regole rigenerare formati, WebP e ritagli
   (dopo un backup del DB):
   `wp media regenerate --skip-delete --yes`.
   `--skip-delete` lascia anche i vecchi file JPEG, ancora citati nelle copie HTML
   di riserva che Elementor salva in `post_content`.

## Esempi d'uso shortcode

Portfolio item in loop:

```text
[portfolio_lightbox_item]
```

Titolo pagina matrimonio:

```text
[matrimonio_titolo]
```

Nomi sposi:

```text
[nomi_coniugi]
```

Dettagli matrimonio:

```text
[info_matrimonio]
```

Gallery parte 1 con gruppo lightbox dedicato:

```text
[modular_gallery field="galleria_matrimonio_parte_1" slideshow="matrimonio-parte-1"]
```

## Struttura directory

```text
custom-tools/
|- custom-tools.php
|- inc/
|  |- register-cpt.php
|  |- admin-columns.php
|  |- dependencies.php
|  |- images.php
|  `- class-image-editor-imagick.php
|- shortcodes/
|  |- portfolio.php
|  |- matrimonio.php
|  `- modular-gallery.php
`- assets/
   |- acf-json/
   `- js/custom-tools.js
```

## Limiti attuali / note di manutenzione

- `assets/js/custom-tools.js` e vuoto (nessuna logica JS attiva).
- il file `inc/register-cpt.php` non registra CPT in PHP: contiene solo query Elementor.
- non ci sono CSS front-end nel plugin per `.modular-gallery` o `.matrimonio-info`: lo styling e demandato a tema/template.
- il campo attachment `gallery_image_size` e limitato alla location `image/jpeg` nel JSON corrente.

## Checklist QA rapida post deploy

1. Verificare presenza menu CPT (`Portfolio`, `Matrimoni`, eventuali altri CPT ACF).
2. Aprire lista Portfolio in admin e controllare colonne `Thumbs` + `Visibile in Home`.
3. Testare Loop Grid home con Query ID `home_portfolio_query`.
4. Testare `[portfolio_lightbox_item]` su item con entrambe le immagini.
5. Testare `[modular_gallery]` su galleria con immagini marcate wide/tall, su
   smartphone, tablet e desktop ≥1440px; aprire la lightbox.
6. Verificare fallback quando campi ACF sono vuoti.
7. Caricare una foto e controllare in `wp post meta get <id> _wp_attachment_metadata`
   che ci siano i formati WebP e i ritagli `gallery_*_crop_*`.
