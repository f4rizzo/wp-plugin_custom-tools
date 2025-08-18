<?php


// Debug - mostra gallery_image_size per TUTTE le immagini

add_action('wp_footer', function () {
  if (current_user_can('administrator')) {
    // Recupera tutte le immagini dalla libreria media
    $images = get_posts([
      'post_type' => 'attachment',
      'post_mime_type' => 'image',
      'posts_per_page' => -1, // Tutte le immagini
      'post_status' => 'inherit'
    ]);

    if ($images) {
      echo '<div style="position:fixed;bottom:0;left:0;background:black;color:white;padding:10px;z-index:9999;max-height:300px;overflow-y:scroll;width:max-content;">';
      echo '<strong>DEBUG: Tutte le immagini nella libreria media</strong><br><br>';

      $count_with_field = 0;
      $count_total = count($images);

      foreach ($images as $image) {
        $size_value = get_field('gallery_image_size', $image->ID);
        $image_title = $image->post_title ?: 'Senza titolo';

        // Mostra solo se ha un valore nel campo (per ridurre rumore)
        if ($size_value !== false && $size_value !== '' && $size_value !== null) {
          // echo "ID: {$image->ID} - '{$image_title}' - Size: '{$size_value}'<br>";
          // Mostra TUTTE le immagini (anche senza campo)
          echo "ID: {$image->ID} - '{$image_title}' - Size: " . var_export($size_value, true) . "<br>";
          $count_with_field++;
        }
      }

      if ($count_with_field === 0) {
        echo "Nessuna immagine ha il campo 'gallery_image_size' impostato.<br>";
      }

      echo "<br><strong>Totale immagini: {$count_total} | Con campo impostato: {$count_with_field}</strong>";
      echo '</div>';
    }
  }
});