<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$resource_title = $resource ? $resource->post_title : '';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title><?php echo esc_html(get_bloginfo('name')); ?></title>
  <link rel="stylesheet" href="<?php echo esc_url(CAMPX_URL . 'assets/css/frontend.css'); ?>">
  <style>
    <?php echo \CampX\Plugin::color_css_vars(); ?>
  </style>
  <style>
    body{font-family:Arial,sans-serif;background:#f7f7f7;margin:0;padding:0}
    .wrap{max-width:760px;margin:32px auto;background:#fff;border-radius:10px;padding:24px;box-shadow:0 8px 20px rgba(0,0,0,.08)}
    label{display:block;margin:.75rem 0 .25rem;font-weight:600}
    input,textarea{width:100%;padding:10px;border:1px solid #ccc;border-radius:6px}
    .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
    .full{grid-column:1/-1}
    button{background:#5b7fff;color:#fff;border:0;padding:12px 18px;border-radius:6px;font-weight:600;cursor:pointer}
  </style>
</head>
<body>
  <div class="wrap">
    <h2><?php echo esc_html__('Buchung anpassen','campx'); ?></h2>
    <p><?php echo esc_html__('Ressource:','campx'); ?> <strong><?php echo esc_html($resource_title); ?></strong></p>
    <form method="post" action="<?php echo esc_url($action_url); ?>">
      <input type="hidden" name="campx_customer_edit" value="1">
      <?php wp_nonce_field('campx_customer_edit_' . (int) $booking_id, 'campx_customer_nonce'); ?>
      <div class="grid">
        <div>
          <label><?php _e('Check-in','campx'); ?></label>
          <input type="date" name="start_date" value="<?php echo esc_attr($data['start_date']); ?>" required>
        </div>
        <div>
          <label><?php _e('Check-out','campx'); ?></label>
          <input type="date" name="end_date" value="<?php echo esc_attr($data['end_date']); ?>" required>
        </div>
        <div>
          <label><?php _e('Einheiten','campx'); ?></label>
          <input type="number" name="units" min="1" value="<?php echo esc_attr($data['units']); ?>" required>
        </div>
        <div>
          <label><?php _e('Personen','campx'); ?></label>
          <input type="number" name="persons" min="0" value="<?php echo esc_attr($data['persons']); ?>">
        </div>
        <div>
          <label><?php _e('Name','campx'); ?></label>
          <input type="text" name="name" value="<?php echo esc_attr($data['name']); ?>" required>
        </div>
        <div>
          <label><?php _e('E-Mail','campx'); ?></label>
          <input type="email" name="email" value="<?php echo esc_attr($data['email']); ?>" required>
        </div>
        <div class="full">
          <label><?php _e('Telefon','campx'); ?></label>
          <input type="text" name="phone" value="<?php echo esc_attr($data['phone']); ?>">
        </div>
        <div class="full">
          <label><?php _e('Nachricht (optional)','campx'); ?></label>
          <textarea name="notes" rows="3"><?php echo esc_textarea($data['notes']); ?></textarea>
        </div>
        <div class="full">
          <button type="submit"><?php echo esc_html__('Buchung speichern','campx'); ?></button>
        </div>
      </div>
    </form>
    <p style="margin-top:16px;">
      <a href="<?php echo esc_url(\CampX\Actions::get_action_url($booking_id, 'customer_cancel', $token)); ?>">
        <?php echo esc_html__('Buchung stornieren','campx'); ?>
      </a>
    </p>
  </div>
</body>
</html>
