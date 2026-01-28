<?php
$s = \CampX\Plugin::get_settings();
$rid = isset($rid) ? $rid : 0;
?>
<div class="campx-card">
  <h3><?php _e('Buchungsformular','campx');?></h3>
  <form class="campx-form" data-campx-form <?php if($rid):?>data-res-id="<?php echo esc_attr($rid);?>"<?php endif; ?>>
    <div>
      <label><?php _e('Check-in','campx'); ?></label>
      <input type="date" name="start_date" required>
    </div>
    <div>
      <label><?php _e('Check-out','campx'); ?></label>
      <input type="date" name="end_date" required>
    </div>
    <div>
      <label><?php _e('Einheiten','campx'); ?></label>
      <input type="number" name="units" min="1" value="1" required>
    </div>
    <div>
      <label><?php _e('Personen','campx'); ?></label>
      <input type="number" name="persons" min="1" value="1">
    </div>
    <div class="full"><hr/></div>
    <div>
      <label><?php _e('Name','campx'); ?></label>
      <input type="text" name="name" required>
      <input type="text" name="company" style="display:none" tabindex="-1" autocomplete="off">
    </div>
    <div>
      <label><?php _e('E-Mail','campx'); ?></label>
      <input type="email" name="email" required>
    </div>
    <div>
      <label><?php _e('Telefon (optional)','campx'); ?></label>
      <input type="text" name="phone">
    </div>
    <div class="full">
      <label><?php _e('Nachricht (optional)','campx'); ?></label>
      <textarea name="notes" rows="3"></textarea>
    </div>
    <div class="full">
      <button class="campx-btn" data-campx-submit><?php _e('Anfrage senden','campx'); ?></button>
    </div>
    <div class="full" data-campx-out></div>
  </form>
</div>
