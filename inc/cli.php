<?php

/**
 * =======================================================
 * Comandi WP-CLI di Custom Tools
 * =======================================================
 *
 *   wp custom-tools ottimizza-media [--dry-run] [--solo=<immagini|elementor>]
 *
 * Porta tutto il sito alle regole della pipeline immagini (inc/images.php):
 *
 *  1. Immagini: rigenera formati, WebP e ritagli SOLO per le immagini che ne
 *     hanno bisogno (file pieno non ancora WebP, ritagli mancanti o con un
 *     allineamento diverso). I vecchi file non vengono cancellati, come con
 *     "wp media regenerate --skip-delete": le copie HTML di riserva di
 *     Elementor in post_content possono ancora puntarci.
 *
 *  2. Elementor: nei documenti (pagine, template, kit) le immagini scelte
 *     dalla Libreria media sono salvate come {id, url}. Elementor usa l'URL
 *     così com'è per gli sfondi: dopo la conversione resta quello del JPEG
 *     originale (anche 1MB contro 120KB del WebP). Il comando aggiorna l'URL
 *     a quello attuale dell'immagine e salva il documento con l'API di
 *     Elementor (Document::save, la stessa dell'editor): revisione, CSS
 *     rigenerato, nessuna modifica a mano di _elementor_data.
 *
 * Da lanciare dopo un backup del DB (export/export-db.sh), sempre prima con --dry-run.
 * =======================================================
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

class Custom_Tools_CLI
{
    /**
     * Porta immagini e documenti Elementor alle regole della pipeline immagini.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Mostra cosa farebbe senza modificare nulla.
     *
     * [--solo=<parte>]
     * : Esegue solo una parte.
     * ---
     * options:
     *   - immagini
     *   - elementor
     * ---
     *
     * ## EXAMPLES
     *
     *     wp custom-tools ottimizza-media --dry-run
     *     wp custom-tools ottimizza-media
     *     wp custom-tools ottimizza-media --solo=elementor
     *
     * @subcommand ottimizza-media
     */
    public function ottimizza_media($args, $assoc_args)
    {
        $dry_run = !empty($assoc_args['dry-run']);
        $solo    = $assoc_args['solo'] ?? '';

        // Document::save() di Elementor richiede un utente che possa modificare
        if (!get_current_user_id()) {
            $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
            if ($admins) {
                wp_set_current_user((int) $admins[0]);
            }
        }

        if ($solo !== 'elementor') {
            $this->immagini($dry_run);
        }

        if ($solo !== 'immagini') {
            $this->elementor($dry_run);
        }

        WP_CLI::success($dry_run ? 'Dry run completato: nessuna modifica.' : 'Ottimizzazione completata.');
    }


    /**
     * =======================================================
     * 1. Immagini da rigenerare
     * =======================================================
     */
    private function immagini($dry_run)
    {
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $ids  = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        $webp = custom_tools_supports_webp();
        $todo = [];

        foreach ($ids as $id) {
            $reason = $this->motivo_rigenerazione($id, $webp);
            if ($reason) {
                $todo[$id] = $reason;
            }
        }

        WP_CLI::log(sprintf('Immagini: %d in libreria, %d da rigenerare.', count($ids), count($todo)));

        foreach ($todo as $id => $reason) {
            WP_CLI::log(sprintf('  %d %s — %s', $id, basename((string) get_attached_file($id)), $reason));
        }

        if ($dry_run || !$todo) {
            return;
        }

        $progress = \WP_CLI\Utils\make_progress_bar('Rigenerazione', count($todo));
        foreach (array_keys($todo) as $id) {
            $source = wp_get_original_image_path($id);
            if ($source && file_exists($source)) {
                wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $source));
            }
            $progress->tick();
        }
        $progress->finish();
    }

    /**
     * Perché un'immagine va rigenerata (stringa vuota se è già a posto).
     *
     * @param int  $id
     * @param bool $webp Il server sa scrivere WebP?
     * @return string
     */
    private function motivo_rigenerazione($id, $webp)
    {
        $metadata = wp_get_attachment_metadata($id);
        $file     = (string) get_attached_file($id);

        if (!is_array($metadata) || empty($metadata['width'])) {
            return '';
        }

        // GIF e SVG restano come sono (animazioni, vettoriali)
        if (!in_array(get_post_mime_type($id), ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/heic'], true)) {
            return '';
        }

        if ($webp && !preg_match('/\.webp$/i', $file)) {
            return 'file pieno non ancora in WebP';
        }

        if (!empty($metadata['sizes']['2048x2048']) || !empty($metadata['sizes']['gallery_wide'])) {
            return 'formati vecchi (2048x2048 / gallery_wide)';
        }

        if (custom_tools_gallery_is_photo($id)) {
            $position = custom_tools_gallery_crop_position($id);
            if (($metadata['custom_tools_crop_position'] ?? 'center') !== $position) {
                return 'allineamento del ritaglio cambiato';
            }

            $ratio = $metadata['width'] / $metadata['height'];
            foreach (custom_tools_gallery_crop_specs() as $layout => $spec) {
                if (call_user_func($spec['applies'], $ratio) && !custom_tools_gallery_crop_sizes($metadata, $layout)) {
                    return "ritaglio '$layout' mancante";
                }
            }
        }

        return '';
    }


    /**
     * =======================================================
     * 2. Riferimenti alle immagini nei documenti Elementor
     * =======================================================
     */
    private function elementor($dry_run)
    {
        if (!class_exists('\Elementor\Plugin')) {
            WP_CLI::warning('Elementor non è attivo: salto i documenti.');
            return;
        }

        global $wpdb;
        $post_ids = $wpdb->get_col(
            "SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_elementor_data' AND p.post_type <> 'revision' AND p.post_status <> 'trash'"
        );

        $total = 0;
        foreach ($post_ids as $post_id) {
            $document = \Elementor\Plugin::$instance->documents->get((int) $post_id);
            if (!$document) {
                continue;
            }

            $elements = $document->get_elements_data();
            $changes  = [];
            $elements = $this->aggiorna_url($elements, $changes);

            if (!$changes) {
                continue;
            }

            $total += count($changes);
            WP_CLI::log(sprintf('Documento %d "%s" (%s): %d immagini', $post_id, get_the_title($post_id), get_post_type($post_id), count($changes)));
            foreach (array_unique($changes) as $change) {
                WP_CLI::log('  ' . $change);
            }

            if (!$dry_run) {
                $document->save(['elements' => $elements]);

                // Verifica sui dati salvati: il valore restituito da save() non è
                // affidabile per tutti i tipi di documento (es. loop-item)
                $check = [];
                $this->aggiorna_url(\Elementor\Plugin::$instance->documents->get((int) $post_id, false)->get_elements_data(), $check);
                if ($check) {
                    WP_CLI::warning("Documento $post_id: " . count($check) . ' riferimenti ancora da aggiornare.');
                }
            }
        }

        WP_CLI::log(sprintf('Elementor: %d riferimenti da aggiornare in %d documenti esaminati.', $total, count($post_ids)));
    }

    /**
     * Percorre ricorsivamente i dati Elementor e aggiorna ogni coppia {id, url}
     * che punta a un'immagine della libreria con un URL non più attuale.
     *
     * @param mixed $data
     * @param array $changes Elenco delle sostituzioni (per il report)
     * @return mixed
     */
    private function aggiorna_url($data, array &$changes)
    {
        if (!is_array($data)) {
            return $data;
        }

        if (isset($data['id'], $data['url']) && is_numeric($data['id']) && (int) $data['id'] > 0 && is_string($data['url']) && $data['url'] !== '') {
            $id = (int) $data['id'];

            if (wp_attachment_is_image($id)) {
                $current = (string) wp_get_attachment_url($id);

                // Confronto sul percorso: il dominio può cambiare tra un ambiente e l'altro
                $old_path = wp_parse_url($data['url'], PHP_URL_PATH);
                $new_path = wp_parse_url($current, PHP_URL_PATH);

                if ($current && $old_path !== $new_path) {
                    $changes[]   = basename((string) $old_path) . ' → ' . basename((string) $new_path);
                    $data['url'] = $current;
                }
            }
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->aggiorna_url($value, $changes);
            }
        }

        return $data;
    }
}

WP_CLI::add_command('custom-tools', 'Custom_Tools_CLI');
