<?php
namespace CampX;
if ( ! defined( 'ABSPATH' ) ) exit;

class Actions {
    const CUSTOMER_LOCK_DAYS = 14;

    public static function listen(){
        $action = sanitize_text_field( $_GET['campx_action'] ?? get_query_var('campx_action') ?? '' );
        if ( empty($action) ) {
            return;
        }
        $booking_id = absint( $_GET['booking'] ?? get_query_var('booking') ?? 0 );
        $token = sanitize_text_field( $_GET['action_token'] ?? get_query_var('action_token') ?? '' );
        if ( ! $booking_id || empty($token) ) {
            self::render_message( __('Ungültiger Link.','campx'), 400 );
        }

        $booking = get_post( $booking_id );
        if ( ! $booking || $booking->post_type !== 'campx_booking' ) {
            self::render_message( __('Buchung nicht gefunden.','campx'), 404 );
        }

        $is_owner_action = in_array($action, ['owner_accept','owner_decline','owner_edit'], true);
        $token_valid = $is_owner_action
            ? self::validate_token($booking_id, $token, 'owner')
            : self::validate_token($booking_id, $token, 'customer');

        if ( ! $token_valid ) {
            self::render_message( __('Ungültiger oder abgelaufener Link.','campx'), 403 );
        }

        switch ($action) {
            case 'owner_accept':
                self::update_status($booking_id, 'accepted');
                self::render_message( __('Buchung wurde bestätigt.','campx') );
                break;
            case 'owner_decline':
                self::update_status($booking_id, 'declined');
                self::render_message( __('Buchung wurde abgelehnt.','campx') );
                break;
            case 'owner_edit':
                $edit_url = admin_url('post.php?post=' . $booking_id . '&action=edit');
                if ( is_user_logged_in() && current_user_can('edit_post', $booking_id) ) {
                    wp_safe_redirect( $edit_url );
                    exit;
                }
                wp_safe_redirect( wp_login_url( $edit_url ) );
                exit;
                break;
            case 'customer_cancel':
                self::assert_customer_change_allowed($booking_id);
                self::update_status($booking_id, 'declined');
                self::render_message( __('Deine Buchung wurde storniert.','campx') );
                break;
            case 'customer_edit':
                self::assert_customer_change_allowed($booking_id);
                self::render_customer_edit_form($booking_id, $token);
                break;
            case 'customer_edit_submit':
                self::assert_customer_change_allowed($booking_id);
                self::handle_customer_edit_submit($booking_id, $token);
                break;
            default:
                self::render_message( __('Unbekannte Aktion.','campx'), 400 );
        }
    }

    public static function ensure_tokens($booking_id){
        if ( ! get_post_meta($booking_id, '_campx_owner_token', true) ) {
            update_post_meta($booking_id, '_campx_owner_token', wp_generate_password(32, false, false));
        }
        if ( ! get_post_meta($booking_id, '_campx_customer_token', true) ) {
            update_post_meta($booking_id, '_campx_customer_token', wp_generate_password(32, false, false));
        }
    }

    public static function get_owner_token($booking_id){
        return get_post_meta($booking_id, '_campx_owner_token', true);
    }

    public static function get_customer_token($booking_id){
        return get_post_meta($booking_id, '_campx_customer_token', true);
    }

    public static function get_action_url($booking_id, $action, $token){
        return add_query_arg([
            'campx_action' => $action,
            'booking' => $booking_id,
            'action_token' => $token,
        ], home_url('/'));
    }

    public static function get_customer_nonce($booking_id){
        return wp_create_nonce('campx_customer_edit_' . (int) $booking_id);
    }

    public static function build_owner_action_links($booking_id){
        $owner_token = self::get_owner_token($booking_id);
        $accept = esc_url( self::get_action_url($booking_id, 'owner_accept', $owner_token) );
        $decline = esc_url( self::get_action_url($booking_id, 'owner_decline', $owner_token) );
        $edit = esc_url( self::get_action_url($booking_id, 'owner_edit', $owner_token) );
        return '<p><strong>' . esc_html__('Aktionen','campx') . '</strong></p>'
            . '<p><a href="' . $accept . '">' . esc_html__('Buchung bestätigen','campx') . '</a></p>'
            . '<p><a href="' . $decline . '">' . esc_html__('Buchung ablehnen','campx') . '</a></p>'
            . '<p><a href="' . $edit . '">' . esc_html__('Buchung bearbeiten','campx') . '</a></p>';
    }

    protected static function validate_token($booking_id, $token, $type){
        $meta_key = $type === 'owner' ? '_campx_owner_token' : '_campx_customer_token';
        $stored = get_post_meta($booking_id, $meta_key, true);
        if ( empty($stored) ) {
            return false;
        }
        return hash_equals((string) $stored, (string) $token);
    }

    protected static function assert_customer_change_allowed($booking_id){
        $start = get_post_meta($booking_id,'_campx_start_date',true);
        if ( ! $start ) {
            return;
        }
        try {
            $start_date = new \DateTime($start);
        } catch (\Exception $ex) {
            return;
        }
        $now = new \DateTime('now', wp_timezone());
        $limit = (clone $start_date)->modify('-' . self::CUSTOMER_LOCK_DAYS . ' days');
        if ( $now >= $limit ) {
            self::render_message(
                sprintf(__('Änderungen/Stornos sind nur bis %d Tage vor Anreise möglich.','campx'), self::CUSTOMER_LOCK_DAYS),
                403
            );
        }
    }

    protected static function update_status($booking_id, $status){
        update_post_meta($booking_id, '_campx_status', $status);
        \CampX\DB::update_occupancy_status($booking_id, $status);
        \CampX\Admin::send_booking_email($booking_id, $status);
    }

    protected static function render_customer_edit_form($booking_id, $token){
        $data = [
            'resource_id' => (int) get_post_meta($booking_id,'_campx_resource_id',true),
            'start_date' => get_post_meta($booking_id,'_campx_start_date',true),
            'end_date' => get_post_meta($booking_id,'_campx_end_date',true),
            'units' => (int) get_post_meta($booking_id,'_campx_units',true),
            'persons' => (int) get_post_meta($booking_id,'_campx_persons',true),
            'name' => get_post_meta($booking_id,'_campx_customer_name',true),
            'email' => get_post_meta($booking_id,'_campx_customer_email',true),
            'phone' => get_post_meta($booking_id,'_campx_customer_phone',true),
            'notes' => get_post_meta($booking_id,'_campx_notes',true),
        ];
        $resource = get_post( $data['resource_id'] );
        $action_url = esc_url( self::get_action_url($booking_id, 'customer_edit_submit', $token) );
        $nonce = self::get_customer_nonce($booking_id);

        status_header(200);
        nocache_headers();
        include CAMPX_PATH . 'templates/self-service.php';
        exit;
    }

    protected static function handle_customer_edit_submit($booking_id, $token){
        if ( ! isset($_POST['campx_customer_edit']) ) {
            self::render_message( __('Ungültige Anfrage.','campx'), 400 );
        }
        $nonce = sanitize_text_field($_POST['campx_customer_nonce'] ?? '');
        if ( ! wp_verify_nonce($nonce, 'campx_customer_edit_' . (int) $booking_id) ) {
            self::render_message( __('Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden.','campx'), 403 );
        }
        $res_id = (int) get_post_meta($booking_id,'_campx_resource_id',true);
        $start  = sanitize_text_field($_POST['start_date'] ?? '');
        $end    = sanitize_text_field($_POST['end_date'] ?? '');
        $units  = max(1, intval($_POST['units'] ?? 1));
        $persons= max(0, intval($_POST['persons'] ?? 0));
        $name   = sanitize_text_field($_POST['name'] ?? '');
        $email  = sanitize_email($_POST['email'] ?? '');
        $phone  = sanitize_text_field($_POST['phone'] ?? '');
        $notes  = sanitize_textarea_field($_POST['notes'] ?? '');

        if ( ! $res_id || ! $start || ! $end || ! $name || ! $email ) {
            self::render_message( __('Bitte alle Pflichtfelder ausfüllen.','campx'), 400 );
        }

        $start_ts = strtotime($start);
        $end_ts = strtotime($end);
        if ( ! $start_ts || ! $end_ts || $end_ts <= $start_ts ) {
            self::render_message( __('Ungültiger Zeitraum.','campx'), 400 );
        }

        $min_stay = max(1, (int) get_post_meta($res_id,'_campx_min_stay',true));
        $nights = count(\CampX\Availability::date_range_nights($start,$end));
        if ( $nights < $min_stay ) {
            self::render_message( sprintf(__('Mindestaufenthalt: %d Nächte','campx'),$min_stay), 400 );
        }
        $max_per = max(1, (int) get_post_meta($res_id,'_campx_max_per_booking',true));
        if ( $units > $max_per ) {
            self::render_message( sprintf(__('Max. %d Einheiten pro Buchung','campx'),$max_per), 400 );
        }
        $max_p = max(0, (int) get_post_meta($res_id,'_campx_max_persons',true));
        if ( $max_p && $persons > $max_p ) {
            self::render_message( sprintf(__('Max. %d Personen pro Buchung','campx'),$max_p), 400 );
        }

        if ( ! self::check_capacity($booking_id, $res_id, $start, $end, $units) ) {
            self::render_message( __('Für den Zeitraum ist nicht genug Kapazität frei.','campx'), 409 );
        }

        update_post_meta($booking_id,'_campx_resource_id',$res_id);
        update_post_meta($booking_id,'_campx_start_date',$start);
        update_post_meta($booking_id,'_campx_end_date',$end);
        update_post_meta($booking_id,'_campx_units',$units);
        update_post_meta($booking_id,'_campx_persons',$persons);
        update_post_meta($booking_id,'_campx_customer_name',$name);
        update_post_meta($booking_id,'_campx_customer_email',$email);
        update_post_meta($booking_id,'_campx_customer_phone',$phone);
        update_post_meta($booking_id,'_campx_notes',$notes);

        self::update_booking_title($booking_id, $res_id, $start, $end);
        \CampX\Admin::ensure_occupancy_for_booking($booking_id);

        self::render_message( __('Deine Buchung wurde aktualisiert.','campx') );
    }

    protected static function check_capacity($booking_id, $resource_id, $start, $end, $units){
        global $wpdb;
        $capacity = (int) get_post_meta($resource_id,'_campx_capacity',true);
        if ( $capacity < 1 ) {
            return false;
        }
        $occ = $wpdb->prefix . 'campx_occupancy';
        foreach(\CampX\DB::nights($start,$end) as $d){
            $booked = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(SUM(units),0) FROM $occ WHERE resource_id=%d AND date=%s AND booking_id != %d AND status IN ('requested','accepted')",
                $resource_id,
                $d,
                $booking_id
            ) );
            if ( ($booked + $units) > $capacity ) {
                return false;
            }
        }
        return true;
    }

    protected static function update_booking_title($booking_id, $resource_id, $start, $end){
        $title = sprintf('%s – %s → %s', get_the_title($resource_id), $start, $end);
        $current = get_post_field('post_title', $booking_id);
        if ( $current !== $title ) {
            wp_update_post(['ID'=>$booking_id, 'post_title'=>$title]);
        }
    }

    protected static function render_message($message, $status = 200){
        status_header($status);
        nocache_headers();
        $title = get_bloginfo('name');
        echo '<!doctype html><html><head><meta charset="utf-8"><title>' . esc_html($title) . '</title></head><body>';
        echo '<div style="max-width:640px;margin:32px auto;font-family:Arial,sans-serif;">';
        echo '<h2 style="margin-bottom:16px;">' . esc_html($title) . '</h2>';
        echo '<p>' . esc_html($message) . '</p>';
        echo '</div></body></html>';
        exit;
    }
}
