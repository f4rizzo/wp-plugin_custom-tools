/**
 * Anteprima del riquadro nei dettagli di una foto (inc/foto-admin.php):
 * cliccando i pulsanti di ingombro e parte da tenere l'anteprima cambia
 * proporzione e inquadratura, prima ancora di salvare.
 */
(function ($) {
  'use strict';

  var INGOMBRO = 'field_688794fa3b2bb';
  var POSIZIONE = 'field_bf54a05c6a726';
  var INQUADRATURA = {
    left: 'left center',
    right: 'right center',
    top: 'center top',
    bottom: 'center bottom',
    center: 'center'
  };

  function aggiorna(elemento) {
    var $box = $(elemento).closest('.compat-attachment-fields, form');
    var ingombro = $box.find('[data-key="' + INGOMBRO + '"] input:checked').val() || 'standard';
    var parte = $box.find('[data-key="' + POSIZIONE + '"] input:checked').val() || 'center';
    var standard = ingombro === 'standard';

    $box.find('.ct-anteprima, .ct-anteprima-nota').prop('hidden', standard);
    $box.find('.ct-anteprima')
      .css('aspect-ratio', ingombro === 'tall' ? '3 / 4' : '3 / 2')
      .find('img').css('object-position', INQUADRATURA[parte] || 'center');
  }

  $(document).on(
    'change',
    '[data-key="' + INGOMBRO + '"] input, [data-key="' + POSIZIONE + '"] input',
    function () {
      aggiorna(this);
    }
  );
})(jQuery);
