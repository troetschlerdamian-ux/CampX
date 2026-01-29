<?php
namespace CampX;
if ( ! defined( 'ABSPATH' ) ) exit;

if ( class_exists( __NAMESPACE__ . '\\Admin' ) ) {
    return;
}

class Admin {

    public static function init(){
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_init', [__CLASS__, 'handle_generate_token']);
        add_action('admin_head', [__CLASS__, 'admin_head']);
        add_action('add_meta_boxes', [__CLASS__, 'meta_boxes']);
        add_action('save_post_campx_resource', [__CLASS__, 'save_resource_meta']);
        add_action('save_post_campx_booking', [__CLASS__, 'save_booking_meta']);
        add_action('save_post_campx_booking', [__CLASS__, 'ensure_occupancy_for_booking'], 12);
        add_action('added_post_meta', [__CLASS__, 'maybe_sync_booking_occupancy'], 10, 4);
        add_action('updated_post_meta', [__CLASS__, 'maybe_sync_booking_occupancy'], 10, 4);
        add_action('deleted_post_meta', [__CLASS__, 'maybe_sync_booking_occupancy'], 10, 4);
        add_action('before_delete_post', [__CLASS__, 'handle_booking_delete']);
        add_action('trashed_post', [__CLASS__, 'handle_booking_delete']);
        add_action('untrashed_post', [__CLASS__, 'handle_booking_restore']);
        add_filter('manage_campx_booking_posts_columns', [__CLASS__, 'bookings_columns']);
        add_action('manage_campx_booking_posts_custom_column', [__CLASS__, 'bookings_columns_content'], 10, 2);
        add_action('transition_post_status', [__CLASS__, 'maybe_notify_on_status_change'], 10, 3);
        add_action('campx_expire_requests', [__CLASS__, 'expire_requests']);
        if ( ! wp_next_scheduled('campx_expire_requests') ) {
            wp_schedule_event(time()+HOUR_IN_SECONDS, 'hourly', 'campx_expire_requests');
        }
        add_filter('gettext', [__CLASS__, 'filter_gettext_labels'], 10, 3);
    }

    public static function menu(){
        add_menu_page(__('CampX', 'campx'), 'CampX', 'manage_options', 'campx', [__CLASS__, 'settings_page'], 'dashicons-calendar-alt', 26);
        add_submenu_page('campx', __('Einstellungen', 'campx'), __('Einstellungen', 'campx'), 'manage_options', 'campx', [__CLASS__, 'settings_page']);
        add_submenu_page('campx', __('Ressourcen', 'campx'), __('Ressourcen', 'campx'), 'edit_posts', 'edit.php?post_type=campx_resource');
        add_submenu_page('campx', __('Buchungen', 'campx'), __('Buchungen', 'campx'), 'edit_posts', 'edit.php?post_type=campx_booking');
        add_submenu_page('campx', __('Kalender', 'campx'), __('Kalender', 'campx'), 'edit_posts', 'campx_calendar', [__CLASS__, 'calendar_page']);
    }

    public static function register_settings(){
        register_setting('campx_settings_group', 'campx_settings');
        register_setting('campx_templates_group', 'campx_email_templates');
    }

    public static function handle_generate_token(){
        if ( ! is_admin() ) {
            return;
        }
        if ( ! isset($_GET['campx_generate_token']) || ! current_user_can('manage_options') ) {
            return;
        }
        check_admin_referer('campx_generate_token');
        $settings = Plugin::get_settings();
        $settings['ics_token'] = wp_generate_password(32, false, false);
        update_option('campx_settings', $settings);
        wp_safe_redirect(admin_url('admin.php?page=campx'));
        exit;
    }

    public static function admin_head() {
        echo '<style>' . Plugin::color_css_vars() . '</style>';
    }

    
    public static function filter_gettext_labels($translated, $text, $domain){
        if ( ! is_admin() || ! function_exists('get_current_screen') ) return $translated;
        $scr = get_current_screen();
        if ( ! $scr || empty($scr->post_type) || ! in_array($scr->post_type, ['campx_resource','campx_booking'], true) ) return $translated;

        // IMPORTANT: never call __() inside gettext filter to avoid recursion.
        $map_resource = [
            'Beitrag hinzufügen' => 'Ressource hinzufügen',
            'Beitrag bearbeiten' => 'Ressource bearbeiten',
            'Add New Post'       => 'Ressource hinzufügen',
            'Edit Post'          => 'Ressource bearbeiten',
        ];
        $map_booking = [
            'Beitrag hinzufügen' => 'Buchung hinzufügen',
            'Beitrag bearbeiten' => 'Buchung bearbeiten',
            'Add New Post'       => 'Buchung hinzufügen',
            'Edit Post'          => 'Buchung bearbeiten',
        ];
        $map = ($scr->post_type==='campx_booking') ? $map_booking : $map_resource;
        return array_key_exists($text, $map) ? $map[$text] : $translated;
    }
    

    public static function meta_boxes(){
        add_meta_box('campx_resource_meta', __('Ressourcen-Einstellungen','campx'), [__CLASS__,'resource_meta_box'], 'campx_resource', 'normal', 'high');
        add_meta_box('campx_booking_meta', __('Buchungsdetails','campx'), [__CLASS__,'booking_meta_box'], 'campx_booking', 'normal', 'high');
    }

    public static function resource_meta_box($post){
        $meta = [
            'resource_type' => get_post_meta($post->ID, '_campx_resource_type', true) ?: 'room',
            'capacity'      => (int) get_post_meta($post->ID, '_campx_capacity', true),
            'unit_label'    => get_post_meta($post->ID, '_campx_unit_label', true) ?: __('Person','campx'),
            'min_stay'      => (int) get_post_meta($post->ID, '_campx_min_stay', true),
            'max_per_booking'=> (int) get_post_meta($post->ID, '_campx_max_per_booking', true),
            'max_persons'   => (int) get_post_meta($post->ID, '_campx_max_persons', true),
        ];
        wp_nonce_field('campx_resource_save','campx_resource_nonce');
        ?>
        <style>.campx-fields label{display:block;margin:.5rem 0 .25rem;font-weight:600}.campx-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.campx-fields input,.campx-fields select{width:100%;max-width:420px}</style>
        <div class="campx-fields">
          <div class="campx-grid">
            <div>
              <label><?php _e('Typ','campx');?></label>
              <select name="campx_resource_type">
                <option value="room"   <?php selected($meta['resource_type'],'room');?>><?php _e('Zimmer','campx');?></option>
                <option value="parcel" <?php selected($meta['resource_type'],'parcel');?>><?php _e('Parzelle / Zeltplatz','campx');?></option>
                <option value="other"  <?php selected($meta['resource_type'],'other');?>><?php _e('Sonstiges','campx');?></option>
              </select>
            </div>
            <div>
              <label><?php _e('Gesamtkapazität (Einheiten)','campx');?></label>
              <input type="number" name="campx_capacity" value="<?php echo esc_attr($meta['capacity']);?>" min="1" required />
            </div>
            <div>
              <label><?php _e('Einheiten-Label','campx');?></label>
              <input type="text" name="campx_unit_label" value="<?php echo esc_attr($meta['unit_label']);?>" />
            </div>
            <div>
              <label><?php _e('Min. Aufenthaltsdauer (Nächte)','campx');?></label>
              <input type="number" name="campx_min_stay" value="<?php echo esc_attr($meta['min_stay']);?>" min="1" />
            </div>
            <div>
              <label><?php _e('Max. Einheiten pro Buchung','campx');?></label>
              <input type="number" name="campx_max_per_booking" value="<?php echo esc_attr($meta['max_per_booking']);?>" min="1" />
            </div>
            <div>
              <label><?php _e('Max. Personen pro Buchung','campx');?></label>
              <input type="number" name="campx_max_persons" value="<?php echo esc_attr($meta['max_persons']);?>" min="0" />
            </div>
          </div>
        </div>
        <?php
    }

    public static function save_resource_meta($post_id){
        if ( ! isset($_POST['campx_resource_nonce']) || ! wp_verify_nonce($_POST['campx_resource_nonce'],'campx_resource_save') ) return;
        update_post_meta($post_id, '_campx_resource_type', sanitize_text_field($_POST['campx_resource_type'] ?? 'room'));
        update_post_meta($post_id, '_campx_capacity', max(1, intval($_POST['campx_capacity'] ?? 1)));
        update_post_meta($post_id, '_campx_unit_label', sanitize_text_field($_POST['campx_unit_label'] ?? __('Person','campx')));
        update_post_meta($post_id, '_campx_min_stay', max(1, intval($_POST['campx_min_stay'] ?? 1)));
        update_post_meta($post_id, '_campx_max_per_booking', max(1, intval($_POST['campx_max_per_booking'] ?? 1)));
        update_post_meta($post_id, '_campx_max_persons', max(0, intval($_POST['campx_max_persons'] ?? 0)));
    }

    public static function booking_meta_box($post){
        $meta = [
            'resource_id' => (int) get_post_meta($post->ID, '_campx_resource_id', true),
            'start_date'  => get_post_meta($post->ID, '_campx_start_date', true),
            'end_date'    => get_post_meta($post->ID, '_campx_end_date', true),
            'units'       => (int) get_post_meta($post->ID, '_campx_units', true),
            'persons'     => (int) get_post_meta($post->ID, '_campx_persons', true),
            'customer_name'  => get_post_meta($post->ID, '_campx_customer_name', true),
            'customer_email' => get_post_meta($post->ID, '_campx_customer_email', true),
            'customer_phone' => get_post_meta($post->ID, '_campx_customer_phone', true),
            'status'      => get_post_meta($post->ID, '_campx_status', true) ?: 'requested',
            'notes'       => get_post_meta($post->ID, '_campx_notes', true),
        ];
        wp_nonce_field('campx_booking_save','campx_booking_nonce');
        $resources = get_posts(['post_type'=>'campx_resource','numberposts'=>-1]);
        ?>
        <style>.campx-fields label{display:block;margin:.5rem 0 .25rem;font-weight:600}.campx-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.campx-fields input,.campx-fields select,.campx-fields textarea{width:100%;max-width:420px}</style>
        <div class="campx-fields">
          <div class="campx-grid">
            <div>
              <label><?php _e('Ressource','campx');?></label>
              <select name="campx_resource_id" required>
                <option value=""><?php _e('— bitte wählen —','campx');?></option>
                <?php foreach($resources as $res): ?>
                  <option value="<?php echo esc_attr($res->ID);?>" <?php selected($meta['resource_id'],$res->ID);?>><?php echo esc_html($res->post_title);?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label><?php _e('Von (Check-in)','campx');?></label>
              <input type="date" name="campx_start_date" value="<?php echo esc_attr($meta['start_date']);?>" required>
            </div>
            <div>
              <label><?php _e('Bis (Check-out)','campx');?></label>
              <input type="date" name="campx_end_date" value="<?php echo esc_attr($meta['end_date']);?>" required>
            </div>
            <div>
              <label><?php _e('Einheiten','campx');?></label>
              <input type="number" name="campx_units" value="<?php echo esc_attr($meta['units']);?>" min="1" required>
            </div>
            <div>
              <label><?php _e('Personen','campx');?></label>
              <input type="number" name="campx_persons" value="<?php echo esc_attr($meta['persons']);?>" min="1">
            </div>
            <div>
              <label><?php _e('Status','campx');?></label>
              <select name="campx_status">
                <option value="requested" <?php selected($meta['status'],'requested');?>><?php _e('Angefragt','campx');?></option>
                <option value="accepted" <?php selected($meta['status'],'accepted');?>><?php _e('Bestätigt','campx');?></option>
                <option value="declined" <?php selected($meta['status'],'declined');?>><?php _e('Abgelehnt','campx');?></option>
                <option value="expired"  <?php selected($meta['status'],'expired');?>><?php _e('Abgelaufen','campx');?></option>
              </select>
            </div>
            <div>
              <label><?php _e('Kunde – Name','campx');?></label>
              <input type="text" name="campx_customer_name" value="<?php echo esc_attr($meta['customer_name']);?>">
            </div>
            <div>
              <label><?php _e('Kunde – E-Mail','campx');?></label>
              <input type="email" name="campx_customer_email" value="<?php echo esc_attr($meta['customer_email']);?>">
            </div>
            <div>
              <label><?php _e('Kunde – Telefon','campx');?></label>
              <input type="text" name="campx_customer_phone" value="<?php echo esc_attr($meta['customer_phone']);?>">
            </div>
            <div style="grid-column:1 / -1">
              <label><?php _e('Notizen (intern)','campx');?></label>
              <textarea name="campx_notes" rows="4"><?php echo esc_textarea($meta['notes']);?></textarea>
            </div>
          </div>
        </div>
        <?php
    }

    public static function save_booking_meta($post_id){
        static $__running = false;
        if ($__running) return;
        if ( defined('CAMPX_SAVING_BOOKING') ) return;
        if ( ! isset($_POST['campx_booking_nonce']) || ! wp_verify_nonce($_POST['campx_booking_nonce'], 'campx_booking_save') ) return;

        $__running = true;
        define('CAMPX_SAVING_BOOKING', true);
        $fields = [
            '_campx_resource_id' => intval($_POST['campx_resource_id'] ?? 0),
            '_campx_start_date'  => sanitize_text_field($_POST['campx_start_date'] ?? ''),
            '_campx_end_date'    => sanitize_text_field($_POST['campx_end_date'] ?? ''),
            '_campx_units'       => max(1, intval($_POST['campx_units'] ?? 1)),
            '_campx_persons'     => max(0, intval($_POST['campx_persons'] ?? 0)),
            '_campx_customer_name'  => sanitize_text_field($_POST['campx_customer_name'] ?? ''),
            '_campx_customer_email' => sanitize_email($_POST['campx_customer_email'] ?? ''),
            '_campx_customer_phone' => sanitize_text_field($_POST['campx_customer_phone'] ?? ''),
            '_campx_status'         => sanitize_text_field($_POST['campx_status'] ?? 'requested'),
            '_campx_notes'          => sanitize_textarea_field($_POST['campx_notes'] ?? ''),
        ];
        foreach($fields as $k=>$v) update_post_meta($post_id, $k, $v);
        self::auto_title_booking($post_id);
        $__running = false;
    }

    protected static function auto_title_booking($post_id){
        $rid = (int) get_post_meta($post_id,'_campx_resource_id',true);
        $s   = get_post_meta($post_id,'_campx_start_date',true);
        $e   = get_post_meta($post_id,'_campx_end_date',true);
        if ($rid && $s && $e){
            $new_title = sprintf('%s – %s → %s', get_the_title($rid), $s, $e);
            $current = get_post_field('post_title', $post_id);
            if ($current === $new_title) { return; }
            // Temporarily unhook save handlers to prevent recursion
            remove_action('save_post_campx_booking', [__CLASS__, 'save_booking_meta']);
            remove_action('save_post_campx_booking', [__CLASS__, 'ensure_occupancy_for_booking'], 12);
            wp_update_post(['ID'=>$post_id,'post_title'=>$new_title]);
            // Re-hook
            add_action('save_post_campx_booking', [__CLASS__, 'save_booking_meta'], 10, 3);
            add_action('save_post_campx_booking', [__CLASS__, 'ensure_occupancy_for_booking'], 12, 3);
        }
    }

    public static function settings_page(){
        $s = Plugin::get_settings();
        $tpl = get_option('campx_email_templates', []);
        $tpl_requested = $tpl['requested'] ?? '<p>Hallo {{name}},</p><p>Wir haben deine Buchungsanfrage für <strong>{{resource}}</strong> vom <strong>{{start}}</strong> bis <strong>{{end}}</strong> erhalten und prüfen diese.</p><p>Liebe Grüsse<br>{{site_name}}</p>';
        $tpl_accepted  = $tpl['accepted']  ?? '<p>Hallo {{name}},</p><p>Deine Buchung für <strong>{{resource}}</strong> vom <strong>{{start}}</strong> bis <strong>{{end}}</strong> ist bestätigt.</p><p><a href="{{ics_link}}">Termin als ICS herunterladen</a></p><p>Liebe Grüsse<br>{{site_name}}</p>';
        $tpl_declined  = $tpl['declined']  ?? '<p>Hallo {{name}},</p><p>Leider konnten wir deine Buchung für <strong>{{resource}}</strong> nicht bestätigen.</p><p>Liebe Grüsse<br>{{site_name}}</p>';
        require CAMPX_PATH . 'templates/admin/settings-page.php';
    }

    public static function calendar_page(){
        $raw_month = isset($_GET['campx_month']) ? sanitize_text_field($_GET['campx_month']) : '';
        $month = preg_match('/^\d{4}-\d{2}$/', $raw_month) ? $raw_month : gmdate('Y-m');
        try {
            $start = new \DateTime($month . '-01');
        } catch (\Exception $e) {
            $start = new \DateTime(gmdate('Y-m-01'));
        }
        $end = clone $start;
        $end->modify('+1 month');

        $prev = (clone $start)->modify('-1 month')->format('Y-m');
        $next = (clone $start)->modify('+1 month')->format('Y-m');

        global $wpdb;
        $occ = $wpdb->prefix . 'campx_occupancy';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT date, booking_id, resource_id, units, status
                 FROM $occ
                 WHERE date >= %s AND date < %s
                   AND status IN ('requested','accepted')
                 ORDER BY date ASC",
                $start->format('Y-m-d'),
                $end->format('Y-m-d')
            ),
            ARRAY_A
        );

        $days = [];
        $booking_ids = [];
        $skipped_booking_ids = [];
        foreach ($rows as $row) {
            $booking_id = (int) $row['booking_id'];
            if ( isset($skipped_booking_ids[$booking_id]) ) {
                continue;
            }
            $status = get_post_status($booking_id);
            if ( ! $status || $status === 'trash' ) {
                $skipped_booking_ids[$booking_id] = true;
                \CampX\DB::free_occupancy($booking_id);
                continue;
            }
            $days[$row['date']][] = $row;
            $booking_ids[] = $booking_id;
        }
        $booking_ids = array_values(array_unique($booking_ids));
        $booking_meta = [];
        foreach ($booking_ids as $bid) {
            $booking_meta[$bid] = [
                'name' => get_post_meta($bid, '_campx_customer_name', true),
                'start' => get_post_meta($bid, '_campx_start_date', true),
                'end' => get_post_meta($bid, '_campx_end_date', true),
            ];
        }

        $month_label = function_exists('wp_date') ? wp_date('F Y', $start->getTimestamp()) : $start->format('F Y');
        ?>
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
                        $colors = self::resource_color($rid);
                        $resource_title = $rid ? get_the_title($rid) : __('Unbekannt', 'campx');
                        $meta = $booking_meta[$bid] ?? [];
                        $customer = $meta['name'] ?? '';
                        $start_date = $meta['start'] ?? '';
                        $end_date = $meta['end'] ?? '';
                        $label = trim(sprintf('%s – %s', $resource_title, $customer));
                        $range = ($start_date && $end_date) ? sprintf('%s → %s', $start_date, $end_date) : '';
                        $link = get_edit_post_link($bid);
                        $style = sprintf(
                            '--campx-entry-bg:%s;--campx-entry-text:%s;--campx-entry-border:%s;',
                            esc_attr($colors['bg']),
                            esc_attr($colors['text']),
                            esc_attr($colors['border'])
                        );
                        echo '<a class="campx-cal-entry" style="' . $style . '" href="' . esc_url($link) . '">';
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
          .campx-calendar-admin .campx-cal-entry{display:block;padding:6px 8px;border-radius:8px;background:var(--campx-entry-bg,#eef2ff);color:var(--campx-entry-text,#1f2937);text-decoration:none;border:1px solid var(--campx-entry-border,#e0e7ff)}
          .campx-calendar-admin .campx-cal-entry:hover{filter:brightness(0.96)}
          .campx-calendar-admin .campx-cal-entry-title{display:block;font-weight:600;font-size:12px}
          .campx-calendar-admin .campx-cal-entry-range{display:block;font-size:11px;color:#6b7280}
          .campx-calendar-admin .campx-cal-empty{font-size:11px;color:#9ca3af}
        </style>
        <?php
    }

    protected static function resource_color($resource_id){
        $palette = [
            ['bg' => '#e0f2fe', 'text' => '#0c4a6e', 'border' => '#7dd3fc'],
            ['bg' => '#fef3c7', 'text' => '#92400e', 'border' => '#fcd34d'],
            ['bg' => '#ecfccb', 'text' => '#365314', 'border' => '#bef264'],
            ['bg' => '#fce7f3', 'text' => '#9d174d', 'border' => '#f9a8d4'],
            ['bg' => '#ede9fe', 'text' => '#4c1d95', 'border' => '#c4b5fd'],
            ['bg' => '#fee2e2', 'text' => '#991b1b', 'border' => '#fecaca'],
            ['bg' => '#cffafe', 'text' => '#0e7490', 'border' => '#67e8f9'],
            ['bg' => '#dcfce7', 'text' => '#166534', 'border' => '#86efac'],
        ];
        if ( empty($resource_id) ) {
            return $palette[0];
        }
        $index = absint($resource_id) % count($palette);
        return $palette[$index];
    }

    public static function bookings_columns($cols){
        return [
            'cb'=>'<input type="checkbox" />',
            'title'=>__('Buchung','campx'),
            'resource'=>__('Ressource','campx'),
            'dates'=>__('Zeitraum','campx'),
            'units'=>__('Einheiten/Personen','campx'),
            'status'=>__('Status','campx'),
            'date'=>__('Erstellt','campx'),
        ];
    }

    public static function bookings_columns_content($col, $post_id){
        if ($col==='resource'){
            $rid = (int) get_post_meta($post_id,'_campx_resource_id',true);
            echo esc_html( get_the_title($rid) ?: '—' );
        } elseif ($col==='dates'){
            $s = get_post_meta($post_id,'_campx_start_date',true);
            $e = get_post_meta($post_id,'_campx_end_date',true);
            echo esc_html($s.' → '.$e);
        } elseif ($col==='units'){
            $u = (int) get_post_meta($post_id,'_campx_units',true);
            $p = (int) get_post_meta($post_id,'_campx_persons',true);
            echo esc_html($u.' / '.$p);
        } elseif ($col==='status'){
            $st = get_post_meta($post_id,'_campx_status',true) ?: 'requested';
            $cls = ($st==='accepted')?'success':(($st==='declined')?'danger':'');
            echo '<span class="campx-badge '.esc_attr($cls).'">'.esc_html(ucfirst($st)).'</span>';
        }
    }

    public static function maybe_notify_on_status_change($new, $old, $post){
        if ( $post->post_type !== 'campx_booking' ) return;
        if ( $new === $old ) return;
        $status = get_post_meta($post->ID,'_campx_status',true);
        if ( in_array($status,['accepted','declined','requested','expired'], true) ){
            self::send_booking_email($post->ID, $status);
            \CampX\DB::update_occupancy_status($post->ID, $status);
        }
    }

    protected static function render_template($html, $vars){
        foreach($vars as $k=>$v){ $html = str_replace('{{'.$k.'}}', $v, $html); }
        return $html;
    }

    public static function send_booking_email($booking_id, $status){
        $email = get_post_meta($booking_id,'_campx_customer_email',true);
        $name  = get_post_meta($booking_id,'_campx_customer_name',true);
        $res_id= (int) get_post_meta($booking_id,'_campx_resource_id',true);
        $res   = get_the_title($res_id);
        $start = get_post_meta($booking_id,'_campx_start_date',true);
        $end   = get_post_meta($booking_id,'_campx_end_date',true);
        $admin_email = Plugin::get_settings()['admin_email'] ?? get_option('admin_email');
        $settings = Plugin::get_settings();
        $ics_args = ['campx_ics'=>'booking','id'=>$booking_id];
        if ( ! empty($settings['ics_token']) ) {
            $ics_args['token'] = $settings['ics_token'];
        }
        $ics_link = add_query_arg($ics_args, home_url('/'));

        $subject = sprintf(__('Buchung %s – %s','campx'), $status, $res);
        $headers = ['Content-Type: text/html; charset=UTF-8','From: '.get_bloginfo('name').' <'.$admin_email.'>'];

        $site = get_bloginfo('name'); $site_url = home_url('/');
        $tpl = get_option('campx_email_templates', []);
        $vars = ['name'=>esc_html($name),'resource'=>esc_html($res),'start'=>esc_html($start),'end'=>esc_html($end),'ics_link'=>esc_url($ics_link),'site_name'=>esc_html($site),'site_url'=>esc_url($site_url)];
        $body = ($status==='requested')?($tpl['requested']??''):($status==='accepted'?($tpl['accepted']??''):($tpl['declined']??''));
        if ( ! $body ){ $body = '<p>'.sprintf(__('Hallo %s,','campx'), esc_html($name)).'</p>'; }
        $msg = self::render_template($body, $vars);

        if ($email) wp_mail($email, $subject, $msg, $headers);
        wp_mail($admin_email, '[CampX] '.$subject, $msg, $headers);
    }

    public static function ensure_occupancy_for_booking($booking_id){
        static $__running = false; if ($__running) return; $__running = true;

        if ( defined('CAMPX_ENSURE_OCCUPANCY') ) return;
        define('CAMPX_ENSURE_OCCUPANCY', true);

        $res_id = (int) get_post_meta($booking_id,'_campx_resource_id',true);
        $start  = get_post_meta($booking_id,'_campx_start_date',true);
        $end    = get_post_meta($booking_id,'_campx_end_date',true);
        $units  = max(1,(int) get_post_meta($booking_id,'_campx_units',true));
        $status = get_post_meta($booking_id,'_campx_status',true);
        \CampX\DB::free_occupancy($booking_id);
        if ( ! $res_id || ! $start || ! $end ) return;
        if ( in_array($status,['declined','expired'], true) ){
            return;
        }
        \CampX\DB::reserve_occupancy($res_id, $booking_id, $start, $end, $units, $status ?: 'requested');
    }

    public static function maybe_sync_booking_occupancy($meta_id, $post_id, $meta_key, $_meta_value){
        if ( get_post_type($post_id) !== 'campx_booking' ) {
            return;
        }
        if ( defined('CAMPX_SAVING_BOOKING') ) {
            return;
        }
        $keys = [
            '_campx_resource_id',
            '_campx_start_date',
            '_campx_end_date',
            '_campx_units',
            '_campx_status',
        ];
        if ( ! in_array($meta_key, $keys, true) ) {
            return;
        }
        self::ensure_occupancy_for_booking($post_id);
    }

    public static function handle_booking_delete($post_id){
        if ( get_post_type($post_id) !== 'campx_booking' ) {
            return;
        }
        \CampX\DB::free_occupancy($post_id);
    }

    public static function handle_booking_restore($post_id){
        if ( get_post_type($post_id) !== 'campx_booking' ) {
            return;
        }
        self::ensure_occupancy_for_booking($post_id);
    }

    public static function expire_requests(){
        $s = Plugin::get_settings();
        $hrs = max(1, intval($s['request_expires_hours'] ?? 48));
        $cut = gmdate('Y-m-d H:i:s', time() - $hrs * HOUR_IN_SECONDS);
        $q = new \WP_Query([
            'post_type'=>'campx_booking','post_status'=>'any','posts_per_page'=>-1,
            'meta_query'=>[['key'=>'_campx_status','value'=>'requested','compare'=>'=']],
            'date_query'=>[['column'=>'post_date_gmt','before'=>$cut,'inclusive'=>true]]
        ]);
        foreach($q->posts as $p){
            update_post_meta($p->ID,'_campx_status','expired');
            \CampX\DB::update_occupancy_status($p->ID,'expired');
            $admin_email = Plugin::get_settings()['admin_email'] ?? get_option('admin_email');
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            wp_mail($admin_email, '[CampX] Anfrage abgelaufen', 'Buchung #'.$p->ID.' ist abgelaufen.', $headers);
        }
    }
}
