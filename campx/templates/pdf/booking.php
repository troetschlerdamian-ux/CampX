<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/** @var array $data */
$settings = $data['settings'] ?? [];
$brand = $settings['brand'] ?? get_bloginfo('name');
$primary = $settings['primary_hex'] ?? '#111111';
$accent = $settings['accent_hex'] ?? '#00c2a8';
$notice = $settings['notice_hex'] ?? '#e74c3c';
$logo_url = $settings['logo_url'] ?? '';
$logo_max_width = (int) ($settings['logo_max_width'] ?? 200);
$logo_align = $settings['logo_align'] ?? 'left';
$logo_align = in_array($logo_align, ['left', 'center', 'right'], true) ? $logo_align : 'left';
$header_html = $data['header_html'] ?? '';
$body_html = $data['body_html'] ?? '';
$footer_html = $data['footer_html'] ?? '';
$show_notice = ! empty($data['status']) && stripos($data['status'], __('bezahlt','campx')) === false;
?>
<!doctype html>
<html lang="de">
  <head>
    <meta charset="utf-8">
    <style>
      * { box-sizing: border-box; }
      body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #1f2933; margin: 0; padding: 32px; }
      h1 { font-size: 20px; margin: 0 0 4px; color: <?php echo esc_html($primary); ?>; }
      h2 { font-size: 14px; margin: 0 0 24px; color: <?php echo esc_html($accent); ?>; }
      .muted { color: #6b7280; }
      .header { margin-bottom: 24px; }
      .header-table { width: 100%; border-collapse: collapse; }
      .header-table td { vertical-align: top; }
      .header-table td:last-child { text-align: right; }
      .logo { max-width: <?php echo esc_html((string) $logo_max_width); ?>px; height: auto; margin-bottom: 8px; }
      .logo-align-left { text-align: left; }
      .logo-align-center { text-align: center; }
      .logo-align-right { text-align: right; }
      .panel { border: 1px solid #e5e7eb; padding: 16px; margin-bottom: 16px; }
      .panel h3 { margin: 0 0 12px; font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em; color: <?php echo esc_html($primary); ?>; }
      .grid { width: 100%; border-collapse: collapse; }
      .grid td { padding: 6px 0; vertical-align: top; }
      .grid td:first-child { width: 160px; color: #6b7280; }
      .total { font-size: 14px; font-weight: 700; color: <?php echo esc_html($primary); ?>; }
      .notice { margin-top: 12px; font-size: 13px; font-weight: 600; color: <?php echo esc_html($notice); ?>; }
      .footer { margin-top: 28px; font-size: 11px; color: #6b7280; }
    </style>
  </head>
  <body>
    <div class="header">
      <table class="header-table">
        <tr>
          <td>
            <?php if ( $logo_url ) : ?>
              <div class="logo-wrap logo-align-<?php echo esc_attr($logo_align); ?>">
                <img class="logo" src="<?php echo esc_url($logo_url); ?>" alt="">
              </div>
            <?php endif; ?>
            <h1><?php echo esc_html($brand); ?></h1>
            <h2><?php echo esc_html(__('Buchungsbestätigung','campx')); ?></h2>
          </td>
          <td class="muted">
            <?php echo esc_html(__('Buchung','campx')); ?> #<?php echo esc_html((string) ($data['booking_id'] ?? '')); ?>
          </td>
        </tr>
      </table>
    </div>

    <?php if ( $header_html !== '' ) : ?>
      <div class="panel">
        <?php echo $header_html; ?>
      </div>
    <?php endif; ?>

    <?php if ( $body_html !== '' ) : ?>
      <div class="panel">
        <?php echo $body_html; ?>
      </div>
    <?php endif; ?>

    <div class="panel">
      <h3><?php echo esc_html(__('Buchungsdetails','campx')); ?></h3>
      <table class="grid">
        <tr>
          <td><?php echo esc_html(__('Ressource','campx')); ?></td>
          <td><?php echo esc_html((string) ($data['resource_name'] ?? '')); ?></td>
        </tr>
        <tr>
          <td><?php echo esc_html(__('Anreise','campx')); ?></td>
          <td><?php echo esc_html((string) ($data['start'] ?? '')); ?></td>
        </tr>
        <tr>
          <td><?php echo esc_html(__('Abreise','campx')); ?></td>
          <td><?php echo esc_html((string) ($data['end'] ?? '')); ?></td>
        </tr>
        <tr>
          <td><?php echo esc_html(__('Nächte','campx')); ?></td>
          <td><?php echo esc_html((string) ($data['nights'] ?? '')); ?></td>
        </tr>
        <tr>
          <td><?php echo esc_html(__('Einheiten','campx')); ?></td>
          <td><?php echo esc_html((string) ($data['units'] ?? '')); ?></td>
        </tr>
        <tr>
          <td><?php echo esc_html(__('Personen','campx')); ?></td>
          <td><?php echo esc_html((string) ($data['persons'] ?? '')); ?></td>
        </tr>
        <tr>
          <td><?php echo esc_html(__('Status','campx')); ?></td>
          <td><?php echo esc_html((string) ($data['status'] ?? '')); ?></td>
        </tr>
      </table>
    </div>

    <div class="panel">
      <h3><?php echo esc_html(__('Kundendaten','campx')); ?></h3>
      <table class="grid">
        <tr>
          <td><?php echo esc_html(__('Name','campx')); ?></td>
          <td><?php echo esc_html((string) ($data['customer_name'] ?? '')); ?></td>
        </tr>
        <tr>
          <td><?php echo esc_html(__('E-Mail','campx')); ?></td>
          <td><?php echo esc_html((string) ($data['customer_email'] ?? '')); ?></td>
        </tr>
        <tr>
          <td><?php echo esc_html(__('Telefon','campx')); ?></td>
          <td><?php echo esc_html((string) ($data['customer_phone'] ?? '')); ?></td>
        </tr>
      </table>
    </div>

    <div class="panel">
      <h3><?php echo esc_html(__('Preisübersicht','campx')); ?></h3>
      <table class="grid">
        <tr>
          <td><?php echo esc_html(__('Preis pro Einheit/Nacht','campx')); ?></td>
          <td><?php echo esc_html((string) ($data['price_per_night'] ?? '')); ?></td>
        </tr>
        <tr>
          <td class="total"><?php echo esc_html(__('Gesamtbetrag','campx')); ?></td>
          <td class="total"><?php echo esc_html((string) ($data['total'] ?? '')); ?></td>
        </tr>
      </table>
      <?php if ( $show_notice ) : ?>
        <div class="notice"><?php echo esc_html(__('NICHT BEZAHLT – Zahlung vor Ort','campx')); ?></div>
      <?php endif; ?>
    </div>

    <?php if ( $footer_html !== '' ) : ?>
      <div class="footer">
        <?php echo $footer_html; ?>
      </div>
    <?php endif; ?>
  </body>
</html>
