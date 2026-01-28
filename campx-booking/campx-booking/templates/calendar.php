<?php
$capacity = (int) get_post_meta($rid,'_campx_capacity',true);
?>
<div class="campx-card">
  <h3><?php echo sprintf(__('Kalender – %s','campx'), esc_html(get_the_title($rid)));?></h3>
  <div class="campx-calendar" data-campx-calendar data-res-id="<?php echo esc_attr($rid);?>" data-capacity="<?php echo esc_attr($capacity);?>"></div>
</div>
