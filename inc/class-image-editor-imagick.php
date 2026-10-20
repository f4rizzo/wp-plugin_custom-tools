<?php

/**
 * =======================================================
 * Editor Imagick con metadati ridotti
 * =======================================================
 *
 * WordPress, quando genera i formati intermedi, conserva di proposito i
 * profili EXIF, XMP, IPTC e ICC. Le foto esportate da Lightroom ne portano
 * circa 21KB: su una miniatura da 2KB pesano dieci volte l'immagine, su un
 * formato 'large' da 48KB quasi la metà.
 *
 * Questo editor toglie tutti i metadati dai file GENERATI e tiene solo il
 * profilo colore quando non è sRGB (Adobe RGB, Display P3…), perché senza i
 * colori cambierebbero. Un profilo sRGB si può togliere: i browser assumono
 * già sRGB. L'originale caricato resta intatto, con tutti i suoi metadati.
 *
 * Caricato da inc/images.php tramite il filtro 'wp_image_editors', quando
 * WordPress ha già incluso WP_Image_Editor_Imagick.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Custom_Tools_Image_Editor_Imagick extends WP_Image_Editor_Imagick
{
    /**
     * Rimuove i metadati dall'immagine in lavorazione, salvo un profilo
     * colore non sRGB.
     *
     * @return true|WP_Error
     */
    protected function strip_meta()
    {
        if (!is_callable([$this->image, 'stripImage'])) {
            return parent::strip_meta();
        }

        try {
            $icc = '';
            $description = (string) $this->image->getImageProperty('icc:description');

            if ($description !== '' && stripos($description, 'srgb') === false) {
                $profiles = $this->image->getImageProfiles('icc', true);
                $icc      = $profiles['icc'] ?? '';
            }

            $this->image->stripImage();

            if ($icc !== '') {
                $this->image->profileImage('icc', $icc);
            }
        } catch (Exception $e) {
            return new WP_Error('image_strip_meta_error', $e->getMessage());
        }

        return true;
    }
}
