<div class="wrap">
  <h1>CampX – <?php _e('Einstellungen','campx');?></h1>
  <form method="post" action="options.php">
    <?php settings_fields('campx_settings_group'); ?>
    <table class="form-table" role="presentation">
      <tr><th scope="row"><?php _e('Primärfarbe','campx');?></th><td><input type="color" name="campx_settings[primary]" value="<?php echo esc_attr($s['primary']);?>"></td></tr>
      <tr><th scope="row"><?php _e('Akzentfarbe','campx');?></th><td><input type="color" name="campx_settings[accent]" value="<?php echo esc_attr($s['accent']);?>"></td></tr>
      <tr><th scope="row"><?php _e('Success','campx');?></th><td><input type="color" name="campx_settings[success]" value="<?php echo esc_attr($s['success']);?>"></td></tr>
      <tr><th scope="row"><?php _e('Danger','campx');?></th><td><input type="color" name="campx_settings[danger]" value="<?php echo esc_attr($s['danger']);?>"></td></tr>
      <tr><th scope="row"><?php _e('Hintergrund','campx');?></th><td><input type="color" name="campx_settings[bg]" value="<?php echo esc_attr($s['bg']);?>"></td></tr>
      <tr><th scope="row"><?php _e('Admin-E-Mail','campx');?></th><td><input type="email" name="campx_settings[admin_email]" value="<?php echo esc_attr($s['admin_email']);?>" style="min-width:280px"></td></tr>
      <tr><th scope="row"><?php _e('Währung','campx');?></th><td><input type="text" name="campx_settings[currency]" value="<?php echo esc_attr($s['currency']);?>" style="width:120px"></td></tr>
      <tr><th scope="row"><?php _e('Anfragen verfallen nach (Stunden)','campx');?></th><td><input type="number" min="1" name="campx_settings[request_expires_hours]" value="<?php echo esc_attr($s['request_expires_hours']);?>" style="width:120px"></td></tr>
      <tr><th scope="row"><?php _e('Kalender – Anzahl Monate','campx');?></th><td><select name="campx_settings[calendar_months]"><?php foreach([1,2,3] as $m){ echo '<option value="'.$m.'"'.selected(($s['calendar_months']??2),$m,false).'>'.$m.'</option>'; } ?></select></td></tr>
      <tr><th scope="row"><?php _e('Danke-Seite','campx');?></th><td><?php wp_dropdown_pages(['name'=>'campx_settings[thankyou_page_id]','show_option_none'=>__('— keine —','campx'),'option_none_value'=>'0','selected'=>intval($s['thankyou_page_id']??0)]); ?></td></tr>
      <tr><th scope="row"><?php _e('Beim Löschen alle Daten entfernen','campx');?></th><td><label><input type="checkbox" name="campx_settings[wipe_on_uninstall]" value="1" <?php checked(!empty($s['wipe_on_uninstall']));?>> <?php _e('Achtung: löscht Tabellen, Ressourcen & Buchungen beim Plugin-Löschen.','campx');?></label></td></tr>
      <tr>
        <th scope="row"><?php _e('ICS Refresh-Intervall (Minuten)','campx');?></th>
        <td>
          <select name="campx_settings[ics_refresh_minutes]">
            <?php foreach([15,30,60,120] as $m){ echo '<option value="'.$m.'"'.selected(($s['ics_refresh_minutes'] ?? 60),$m,false).'>'.$m.'</option>'; } ?>
          </select>
          <p class="description"><?php _e('Empfohlen für Outlook sind 60 Minuten oder mehr.','campx');?></p>
        </td>
      </tr>
      <tr>
        <th scope="row"><?php _e('ICS-Token','campx');?></th>
        <td>
          <input type="text" name="campx_settings[ics_token]" value="<?php echo esc_attr($s['ics_token']);?>" style="min-width:280px">
          <?php
          $token_link = wp_nonce_url(add_query_arg('campx_generate_token', '1'), 'campx_generate_token');
          ?>
          <p class="description">
            <?php _e('Wenn gesetzt, muss der Token in der ICS-URL enthalten sein.','campx');?>
            <a href="<?php echo esc_url($token_link);?>"><?php _e('Neuen Token erzeugen','campx');?></a>
          </p>
        </td>
      </tr>
    </table>
    <?php submit_button(); ?>
  </form>

  <hr/>
  <h2><?php _e('PDF-Design','campx');?></h2>
  <form method="post" action="options.php">
    <?php wp_enqueue_media(); ?>
    <?php settings_fields('campx_settings_group'); ?>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row"><?php _e('PDF Brand-Name','campx');?></th>
        <td>
          <input type="text" name="campx_settings[pdf_brand_name]" value="<?php echo esc_attr($s['pdf_brand_name']);?>" style="min-width:280px">
          <p class="description"><?php _e('Leer lassen, um den Seitennamen zu verwenden.','campx');?></p>
        </td>
      </tr>
      <tr><th scope="row"><?php _e('PDF Primärfarbe','campx');?></th><td><input type="color" name="campx_settings[pdf_primary]" value="<?php echo esc_attr($s['pdf_primary']);?>"></td></tr>
      <tr><th scope="row"><?php _e('PDF Akzentfarbe','campx');?></th><td><input type="color" name="campx_settings[pdf_accent]" value="<?php echo esc_attr($s['pdf_accent']);?>"></td></tr>
      <tr><th scope="row"><?php _e('PDF Hinweisfarbe','campx');?></th><td><input type="color" name="campx_settings[pdf_notice]" value="<?php echo esc_attr($s['pdf_notice']);?>"></td></tr>
      <tr>
        <th scope="row"><?php _e('PDF Logo','campx');?></th>
        <td>
          <?php
          $logo_id = (int) ($s['pdf_logo_id'] ?? 0);
          $logo_url = $logo_id ? wp_get_attachment_url($logo_id) : '';
          $logo_max_width = (int) ($s['pdf_logo_max_width'] ?? 200);
          ?>
          <input type="hidden" id="campx_pdf_logo_id" name="campx_settings[pdf_logo_id]" value="<?php echo esc_attr($logo_id); ?>">
          <div id="campx_pdf_logo_preview" style="margin-bottom:8px;">
            <?php if ( $logo_url ) : ?>
              <img src="<?php echo esc_url($logo_url); ?>" alt="" style="max-width:<?php echo esc_attr($logo_max_width); ?>px;height:auto;">
            <?php endif; ?>
          </div>
          <button type="button" class="button" id="campx_pdf_logo_select"><?php _e('Logo auswählen','campx'); ?></button>
          <button type="button" class="button" id="campx_pdf_logo_remove"><?php _e('Logo entfernen','campx'); ?></button>
          <p class="description"><?php _e('Logo wird oben in der PDF angezeigt.','campx');?></p>
        </td>
      </tr>
      <tr>
        <th scope="row"><?php _e('Logo-Breite (px)','campx');?></th>
        <td>
          <input type="number" min="0" name="campx_settings[pdf_logo_max_width]" value="<?php echo esc_attr($s['pdf_logo_max_width'] ?? 200); ?>" style="width:120px;" id="campx_pdf_logo_max_width">
          <p class="description"><?php _e('Maximale Breite des Logos in Pixeln.','campx');?></p>
        </td>
      </tr>
      <tr>
        <th scope="row"><?php _e('Logo-Ausrichtung','campx');?></th>
        <td>
          <?php $logo_align = $s['pdf_logo_align'] ?? 'left'; ?>
          <select name="campx_settings[pdf_logo_align]">
            <option value="left" <?php selected($logo_align, 'left'); ?>><?php _e('Links','campx');?></option>
            <option value="center" <?php selected($logo_align, 'center'); ?>><?php _e('Zentriert','campx');?></option>
            <option value="right" <?php selected($logo_align, 'right'); ?>><?php _e('Rechts','campx');?></option>
          </select>
        </td>
      </tr>
      <tr>
        <th scope="row"><?php _e('PDF Header-Text','campx');?></th>
        <td>
          <?php wp_editor($s['pdf_header_text'], 'campx_pdf_header_text', ['textarea_name'=>'campx_settings[pdf_header_text]','textarea_rows'=>4,'media_buttons'=>false]); ?>
          <p class="description"><?php _e('Optionaler Text oberhalb der Buchungsdetails. HTML erlaubt. Platzhalter: {{booking_id}}, {{customer_name}}, {{resource_name}}, {{start}}, {{end}}, {{total}}, {{site_name}}, {{site_url}}.','campx');?></p>
        </td>
      </tr>
      <tr>
        <th scope="row"><?php _e('PDF Freitext','campx');?></th>
        <td>
          <?php wp_editor($s['pdf_body_html'], 'campx_pdf_body_html', ['textarea_name'=>'campx_settings[pdf_body_html]','textarea_rows'=>6,'media_buttons'=>false]); ?>
          <p class="description"><?php _e('Optionaler HTML-Block zwischen Header und Buchungsdetails. Platzhalter wie oben möglich.','campx');?></p>
        </td>
      </tr>
      <tr>
        <th scope="row"><?php _e('PDF Template (vollständig)','campx');?></th>
        <td>
          <?php wp_editor($s['pdf_template_html'], 'campx_pdf_template_html', ['textarea_name'=>'campx_settings[pdf_template_html]','textarea_rows'=>10,'media_buttons'=>false]); ?>
          <p class="description"><?php _e('Optional: eigenes HTML-Dokument für die komplette PDF. Wenn gesetzt, überschreibt es das Standard-Template. Platzhalter wie oben möglich.','campx');?></p>
        </td>
      </tr>
      <tr>
        <th scope="row"><?php _e('PDF Footer-Text','campx');?></th>
        <td>
          <?php wp_editor($s['pdf_footer_text'], 'campx_pdf_footer_text', ['textarea_name'=>'campx_settings[pdf_footer_text]','textarea_rows'=>4,'media_buttons'=>false]); ?>
          <p class="description"><?php _e('Optionaler Text unterhalb der Preisübersicht. HTML erlaubt. Platzhalter wie oben möglich.','campx');?></p>
        </td>
      </tr>
    </table>
    <?php submit_button(); ?>
  </form>
  <script>
    (function($){
      var frame;
      function updateLogoPreviewWidth() {
        var width = parseInt($('#campx_pdf_logo_max_width').val(), 10);
        if (isNaN(width) || width < 0) {
          width = 0;
        }
        $('#campx_pdf_logo_preview img').css('max-width', width ? width + 'px' : '');
      }
      $('#campx_pdf_logo_select').on('click', function(e){
        e.preventDefault();
        if ( frame ) {
          frame.open();
          return;
        }
        frame = wp.media({
          title: '<?php echo esc_js(__('Logo auswählen','campx')); ?>',
          button: { text: '<?php echo esc_js(__('Logo verwenden','campx')); ?>' },
          multiple: false
        });
        frame.on('select', function(){
          var attachment = frame.state().get('selection').first().toJSON();
          $('#campx_pdf_logo_id').val(attachment.id);
          $('#campx_pdf_logo_preview').html('<img src=\"' + attachment.url + '\" style=\"height:auto;\" alt=\"\">');
          updateLogoPreviewWidth();
        });
        frame.open();
      });
      $('#campx_pdf_logo_max_width').on('input', updateLogoPreviewWidth);
      $('#campx_pdf_logo_remove').on('click', function(e){
        e.preventDefault();
        $('#campx_pdf_logo_id').val('');
        $('#campx_pdf_logo_preview').empty();
      });
      updateLogoPreviewWidth();
    })(jQuery);
  </script>

  <hr/>
  <h2><?php _e('E-Mail-Vorlagen','campx');?></h2>
  <p class="description"><?php _e('Platzhalter: {{name}}, {{resource}}, {{start}}, {{end}}, {{ics_link}}, {{self_service_link}}, {{site_name}}, {{site_url}}','campx');?></p>
  <form method="post" action="options.php">
    <?php
    settings_fields('campx_templates_group');
    wp_editor($tpl_requested, 'campx_tpl_requested', ['textarea_name'=>'campx_email_templates[requested]','textarea_rows'=>8,'media_buttons'=>false]);
    echo '<br/><h3>'.__('Bestätigt','campx').'</h3>';
    wp_editor($tpl_accepted, 'campx_tpl_accepted', ['textarea_name'=>'campx_email_templates[accepted]','textarea_rows'=>8,'media_buttons'=>false]);
    echo '<br/><h3>'.__('Abgelehnt','campx').'</h3>';
    wp_editor($tpl_declined, 'campx_tpl_declined', ['textarea_name'=>'campx_email_templates[declined]','textarea_rows'=>8,'media_buttons'=>false]);
    submit_button();
    ?>
  </form>

  <?php
  $token = $s['ics_token'] ?? '';
  if ( ! empty($token) ) {
      if ( preg_match('/^[A-Za-z0-9_-]+$/', $token) ) {
          $all_url = home_url('/campx-' . rawurlencode($token) . '.ics');
      } else {
          $all_url = add_query_arg(['campx_ics' => 'all', 'token' => $token], home_url('/'));
      }
  } else {
      $all_url = home_url('/campx.ics');
  }
  $all_webcal = preg_replace('#^https?://#', 'webcal://', $all_url);
  ?>
  <p><strong>ICS (webcal):</strong> <?php _e('Alle Buchungen','campx');?> <code><?php echo esc_html( $all_webcal ); ?></code></p>
</div>
