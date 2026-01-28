<?php
$ids = isset($ids) ? $ids : [];
$months = intval( \CampX\Plugin::get_settings()['calendar_months'] ?? 2 );
?>
<div class="campx-card">
  <h3 style="margin:.5rem 0 1rem;"><?php _e('Verfügbarkeit prüfen & Ressource wählen','campx'); ?></h3>

  <!-- Single inline range picker -->
  <div class="campx-range" data-campx-range data-months="<?php echo $months; ?>"></div>

  <!-- Resource cards -->
  <div class="campx-grid-catalog" data-campx-catalog data-ids="<?php echo esc_attr( implode(',', $ids) ); ?>">
    <?php foreach($ids as $rid): $post = get_post($rid); if(!$post) continue; ?>
      <div class="campx-card item" data-res-id="<?php echo esc_attr($rid); ?>">
        <div style="display:flex; gap:12px; align-items:flex-start;">
          <div class="thumb" style="width:180px;height:120px;background:#f4f4f6;border-radius:10px; overflow:hidden; flex:0 0 auto;">
            <?php if (has_post_thumbnail($rid)) { echo get_the_post_thumbnail($rid, 'medium', ['style'=>'width:100%;height:100%;object-fit:cover']); } ?>
          </div>
          <div style="flex:1;">
            <div class="campx-badge">
              <?php $t=get_post_meta($rid,'_campx_resource_type',true);
                    echo esc_html($t==='room'?__('Zimmer','campx'):($t==='parcel'?__('Parzelle / Zeltplatz','campx'):__('Sonstiges','campx'))); ?>
            </div>
            <h4 style="margin:.25rem 0;"><?php echo esc_html($post->post_title); ?></h4>
            <p style="margin:.25rem 0 .5rem; opacity:.85;"><?php echo esc_html( $post->post_excerpt ); ?></p>
            <small><?php _e('Kapazität','campx'); ?>: <?php echo (int) get_post_meta($rid,'_campx_capacity',true); ?>
              • <?php _e('Min. Nächte','campx'); ?>: <?php echo (int) get_post_meta($rid,'_campx_min_stay',true); ?></small>
          </div>
          <div style="text-align:right;">
            <label class="campx-badge" data-status><?php _e('—','campx'); ?></label><br>
            <label style="display:inline-flex;gap:8px;align-items:center;margin-top:8px;">
              <input type="radio" name="campx_res_select" value="<?php echo esc_attr($rid); ?>" disabled>
              <?php _e('Auswählen','campx'); ?>
            </label>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Hidden booking form (revealed after selection) -->
  <div class="campx-form-wrapper hidden" data-form-wrapper>
    <?php $rid = 0; include CAMPX_PATH . 'templates/form.php'; ?>
  </div>
</div>
