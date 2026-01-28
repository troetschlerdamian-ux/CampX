<?php
namespace CampX;
if ( ! defined( 'ABSPATH' ) ) exit;

class Admin {

    public static function init(){
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_head', [__CLASS__, 'admin_head']);
        add_action('add_meta_boxes', [__CLASS__, 'meta_boxes']);
        add_action('save_post_campx_resource', [__CLASS__, 'save_resource_meta']);
        add_action('save_post_campx_booking', [__CLASS__, 'save_booking_meta']);
        add_action('save_post_campx_booking', [__CLASS__, 'ensure_occupancy_for_booking'], 12);
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
        static $__running = false; if ($__running) return; $__running = true;

        if ( defined('CAMPX_SAVING_BOOKING') ) return;
        define('CAMPX_SAVING_BOOKING', true);

        if ( ! isset($_POST['campx_booking_nonce']) || ! wp_verify_nonce($_POST['campx_booking_nonce'], 'campx_booking_save') ) return;
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
        foreach ($rows as $row) {
            $days[$row['date']][] = $row;
            $booking_ids[] = (int) $row['booking_id'];
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
        require CAMPX_PATH . 'templates/admin/calendar-page.php';
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
        $ics_link = add_query_arg(['campx_ics'=>'booking','id'=>$booking_id], home_url('/'));

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
        if ( ! $res_id || ! $start || ! $end ) return;
        if ( in_array($status,['declined','expired'], true) ){
            \CampX\DB::free_occupancy($booking_id);
            return;
        }
        \CampX\DB::reserve_occupancy($res_id, $booking_id, $start, $end, $units, $status ?: 'requested');
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
