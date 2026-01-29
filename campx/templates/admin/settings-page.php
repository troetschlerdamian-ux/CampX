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
      <tr><th scope="row"><?php _e('Anfragen verfallen nach (Stunden)','campx');?></th><td><input type="number" min="1" name="campx_settings[request_expires_hours]" value="<?php echo esc_attr($s['request_expires_hours']);?>" style="width:120px"></td></tr>
      <tr><th scope="row"><?php _e('Kalender – Anzahl Monate','campx');?></th><td><select name="campx_settings[calendar_months]"><?php foreach([1,2,3] as $m){ echo '<option value="'.$m.'"'.selected(($s['calendar_months']??2),$m,false).'>'.$m.'</option>'; } ?></select></td></tr>
      <tr><th scope="row"><?php _e('Danke-Seite','campx');?></th><td><?php wp_dropdown_pages(['name'=>'campx_settings[thankyou_page_id]','show_option_none'=>__('— keine —','campx'),'option_none_value'=>'0','selected'=>intval($s['thankyou_page_id']??0)]); ?></td></tr>
      <tr><th scope="row"><?php _e('Beim Löschen alle Daten entfernen','campx');?></th><td><label><input type="checkbox" name="campx_settings[wipe_on_uninstall]" value="1" <?php checked(!empty($s['wipe_on_uninstall']));?>> <?php _e('Achtung: löscht Tabellen, Ressourcen & Buchungen beim Plugin-Löschen.','campx');?></label></td></tr>
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
  <h2><?php _e('E-Mail-Vorlagen','campx');?></h2>
  <p class="description"><?php _e('Platzhalter: {{name}}, {{resource}}, {{start}}, {{end}}, {{ics_link}}, {{site_name}}, {{site_url}}','campx');?></p>
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
  $resource_url = home_url('/campx-resource-RESOURCE_ID.ics');
  $all_url = home_url('/campx.ics');
  if ( ! empty($token) ) {
      $resource_url = add_query_arg(['token' => $token], $resource_url);
      $all_url = add_query_arg(['token' => $token], $all_url);
  }
  $resource_webcal = preg_replace('#^https?://#', 'webcal://', $resource_url);
  $all_webcal = preg_replace('#^https?://#', 'webcal://', $all_url);
  ?>
  <p><strong>ICS:</strong> <?php _e('Ressourcen-Kalender','campx');?> <code><?php echo esc_html( $resource_url ); ?></code></p>
  <p><strong>ICS:</strong> <?php _e('Alle Buchungen','campx');?> <code><?php echo esc_html( $all_url ); ?></code></p>
</div>
