# Custom Tools

Plugin WordPress custom per il sito, orientato a:

- gestione contenuti custom (Portfolio, Matrimoni e altri CPT via ACF JSON)
- integrazione ACF + Elementor
- shortcode per rendering dinamico
- supporto lightbox e gallerie modulari
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

### 3. Formati immagine custom + Media Picker

Hook usato: `after_setup_theme`.

Image size registrati:

- `portfolio_thumb`: larghezza 454, altezza libera, no crop
- `gallery_wide`: larghezza 1480, altezza libera, no crop
- `gallery_tall`: altezza 1200, larghezza libera, no crop

Hook usato: `image_size_names_choose`.

Effetto:

- i 3 formati compaiono anche nel selettore dimensioni del Media Picker.

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
- `<img>` preview con size `portfolio_thumb` (fallback su URL immagine base)
- apertura lightbox dell'immagine originale

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

Attributi:

- `field` (obbligatorio): nome campo gallery ACF (es. `galleria_matrimonio_parte_1`)
- `slideshow` (opzionale): gruppo lightbox Elementor, default `gallery`

Comportamento:

- legge immagini dal campo gallery del post corrente
- per ogni immagine sceglie miniatura via helper `get_gallery_thumb_url()`
- aggiunge classe base `.modular-gallery__item`
- aggiunge modificatore:
  - `.wide` se `gallery_image_size = wide`
  - `.tall` se `gallery_image_size = tall`
  - nessun modificatore se valore nullo/non previsto
- output con `loading="lazy"` e lightbox attiva via attributi Elementor

Messaggi di fallback:

- `Campo ACF non specificato.` se manca `field`
- `Nessuna immagine disponibile.` se la gallery e vuota

### 7. Helper immagini per la galleria modulare

Funzione: `get_gallery_thumb_url($image_id, $size = null)`.
Se `$size` (valore di `gallery_image_size`) è già noto va passato, per evitare
una seconda lettura del campo ACF.

Mappatura campo ACF `gallery_image_size` -> size WP:

- `wide` -> `gallery_wide`
- `tall` -> `gallery_tall`
- default -> `large`

Fallback finale:

- se la size non e disponibile, usa URL originale attachment.

Lo shortcode fa il prime della meta cache (`update_meta_cache`) su tutti gli
attachment della galleria prima del loop: una query invece di N.

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
7. Se le nuove size non esistono su media vecchi, rigenerare thumbnails.

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
|  `- dependencies.php
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
5. Testare `[modular_gallery]` su galleria con immagini marcate wide/tall.
6. Verificare fallback quando campi ACF sono vuoti.
