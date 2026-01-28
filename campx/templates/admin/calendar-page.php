<div class="wrap campx-calendar-admin">
  <h1><?php _e('Kalender', 'campx'); ?></h1>
  <div class="campx-cal-toolbar">
    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=campx_calendar&campx_month=' . $prev)); ?>">‹ <?php _e('Vorheriger Monat', 'campx'); ?></a>
    <strong class="campx-cal-month"><?php echo esc_html($month_label); ?></strong>
    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=campx_calendar&campx_month=' . $next)); ?>"><?php _e('Nächster Monat', 'campx'); ?> ›</a>
  </div>
  <div class="campx-cal-grid">
    <?php
    $weekday_labels = [__('Mo','campx'), __('Di','campx'), __('Mi','campx'), __('Do','campx'), __('Fr','campx'), __('Sa','campx'), __('So','campx')];
    foreach ($weekday_labels as $label) {
        echo '<div class="campx-cal-head">' . esc_html($label) . '</div>';
    }

    $first_day = (int) $start->format('N');
    for ($i = 1; $i < $first_day; $i++) {
        echo '<div class="campx-cal-cell is-empty"></div>';
    }

    $days_in_month = (int) $start->format('t');
    for ($day = 1; $day <= $days_in_month; $day++) {
        $date = $start->format('Y-m-') . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
        echo '<div class="campx-cal-cell">';
        echo '<div class="campx-cal-date">' . esc_html($day) . '</div>';
        if (!empty($days[$date])) {
            foreach ($days[$date] as $entry) {
                $bid = (int) $entry['booking_id'];
                $rid = (int) $entry['resource_id'];
                $resource_title = $rid ? get_the_title($rid) : __('Unbekannt', 'campx');
                $meta = $booking_meta[$bid] ?? [];
                $customer = $meta['name'] ?? '';
                $start_date = $meta['start'] ?? '';
                $end_date = $meta['end'] ?? '';
                $label = trim(sprintf('%s – %s', $resource_title, $customer));
                $range = ($start_date && $end_date) ? sprintf('%s → %s', $start_date, $end_date) : '';
                $link = get_edit_post_link($bid);
                echo '<a class="campx-cal-entry" href="' . esc_url($link) . '">';
                echo '<span class="campx-cal-entry-title">' . esc_html($label) . '</span>';
                if ($range) {
                    echo '<span class="campx-cal-entry-range">' . esc_html($range) . '</span>';
                }
                echo '</a>';
            }
        } else {
            echo '<span class="campx-cal-empty">' . esc_html__('Keine Buchungen', 'campx') . '</span>';
        }
        echo '</div>';
    }
    ?>
  </div>
</div>
<style>
  .campx-calendar-admin .campx-cal-toolbar{display:flex;align-items:center;gap:12px;margin:12px 0 18px}
  .campx-calendar-admin .campx-cal-month{font-size:18px}
  .campx-calendar-admin .campx-cal-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:8px}
  .campx-calendar-admin .campx-cal-head{font-weight:600;color:#4b5563;text-align:center}
  .campx-calendar-admin .campx-cal-cell{min-height:120px;border:1px solid #e5e7eb;border-radius:10px;padding:8px;background:#fff;display:flex;flex-direction:column;gap:6px}
  .campx-calendar-admin .campx-cal-cell.is-empty{background:transparent;border:0}
  .campx-calendar-admin .campx-cal-date{font-weight:600;color:#111827}
  .campx-calendar-admin .campx-cal-entry{display:block;padding:6px 8px;border-radius:8px;background:#eef2ff;color:#1f2937;text-decoration:none}
  .campx-calendar-admin .campx-cal-entry:hover{background:#e0e7ff}
  .campx-calendar-admin .campx-cal-entry-title{display:block;font-weight:600;font-size:12px}
  .campx-calendar-admin .campx-cal-entry-range{display:block;font-size:11px;color:#6b7280}
  .campx-calendar-admin .campx-cal-empty{font-size:11px;color:#9ca3af}
</style>
