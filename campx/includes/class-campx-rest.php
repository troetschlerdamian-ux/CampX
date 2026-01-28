<?php
namespace CampX;
if ( ! defined( 'ABSPATH' ) ) exit;

class Rest {
    public static function register_routes(){
        register_rest_route( 'campx/v1', '/availability', [
            'methods' => 'GET',
            'callback' => [ __CLASS__, 'get_availability' ],
            'permission_callback' => '__return_true',
            'args' => [
                'resource' => ['required'=>true],
                'start'    => ['required'=>true],
                'end'      => ['required'=>true],
            ],
        ]);
        register_rest_route( 'campx/v1', '/book', [
            'methods' => 'POST',
            'callback' => [ __CLASS__, 'post_booking' ],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function get_availability( $req ){
        $res_id = absint( $req['resource'] );
        $start  = sanitize_text_field( $req['start'] );
        $end    = sanitize_text_field( $req['end'] );
        $capacity = (int) get_post_meta( $res_id, '_campx_capacity', true );
        $days = [];
        foreach( \CampX\Availability::date_range_nights($start,$end) as $d ){
            $booked = \CampX\Availability::booked_units_on($res_id,$d);
            $days[] = ['date'=>$d,'booked'=>$booked,'capacity'=>$capacity,'free'=>max(0,$capacity-$booked)];
        }
        return rest_ensure_response(['resource'=>$res_id,'days'=>$days]);
    }

    public static function post_booking( $req ){
        // honeypot & rate limit
        $hp = sanitize_text_field( $req['company'] ?? '' );
        if ( ! empty($hp) ) return new \WP_Error('campx_bot', __('Spam erkannt','campx'), ['status'=>400]);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = 'campx_rl_' . md5( strtolower( sanitize_email( $req['email'] ?? '' ) ) . '|' . $ip );
        $tries = (int) get_transient($key);
        if ( $tries > 5 ) return new \WP_Error('campx_rate', __('Zu viele Versuche, bitte später erneut','campx'), ['status'=>429]);
        set_transient($key, $tries+1, 15 * MINUTE_IN_SECONDS);

        $res_id = absint($req['resource_id'] ?? 0);
        $start  = sanitize_text_field($req['start_date'] ?? '');
        $end    = sanitize_text_field($req['end_date'] ?? '');
        $units  = max(1, intval($req['units'] ?? 1));
        $persons= max(0, intval($req['persons'] ?? 0));
        $name   = sanitize_text_field($req['name'] ?? '');
        $email  = sanitize_email($req['email'] ?? '');
        $phone  = sanitize_text_field($req['phone'] ?? '');
        $notes  = sanitize_textarea_field($req['notes'] ?? '');

        if ( ! $res_id || ! $start || ! $end || ! $name || ! $email ){
            return new \WP_Error('campx_missing', __('Fehlende Felder','campx'), ['status'=>400]);
        }
        $start_ts = strtotime($start); $end_ts = strtotime($end);
        if ( ! $start_ts || ! $end_ts || $end_ts <= $start_ts ){
            return new \WP_Error('campx_dates', __('Ungültiger Zeitraum','campx'), ['status'=>400]);
        }
        $min_stay = max(1, (int) get_post_meta($res_id,'_campx_min_stay',true));
        $nights = count(\CampX\Availability::date_range_nights($start,$end));
        if ( $nights < $min_stay ){
            return new \WP_Error('campx_min', sprintf(__('Mindestaufenthalt: %d Nächte','campx'),$min_stay), ['status'=>400]);
        }
        $max_per = max(1, (int) get_post_meta($res_id,'_campx_max_per_booking',true));
        if ( $units > $max_per ){
            return new \WP_Error('campx_max_units', sprintf(__('Max. %d Einheiten pro Buchung','campx'),$max_per), ['status'=>400]);
        }
        $max_p = max(0, (int) get_post_meta($res_id,'_campx_max_persons',true));
        if ( $max_p && $persons > $max_p ){
            return new \WP_Error('campx_max_persons', sprintf(__('Max. %d Personen pro Buchung','campx'),$max_p), ['status'=>400]);
        }
        // capacity check
        $capacity = (int) get_post_meta($res_id,'_campx_capacity',true);
        foreach(\CampX\DB::nights($start,$end) as $d){
            $booked = \CampX\DB::booked_units_on($res_id,$d);
            if ( ($booked + $units) > $capacity ){
                return new \WP_Error('campx_no_space', __('Für den Zeitraum ist nicht genug Kapazität frei.','campx'), ['status'=>409]);
            }
        }
        // create booking post
        $title = sprintf('%s – %s → %s', get_the_title($res_id), $start, $end);
        $booking_id = wp_insert_post([ 'post_type'=>'campx_booking', 'post_status'=>'publish', 'post_title'=>$title ]);
        if ( is_wp_error($booking_id) ) return $booking_id;

        update_post_meta($booking_id,'_campx_resource_id',$res_id);
        update_post_meta($booking_id,'_campx_start_date',$start);
        update_post_meta($booking_id,'_campx_end_date',$end);
        update_post_meta($booking_id,'_campx_units',$units);
        update_post_meta($booking_id,'_campx_persons',$persons);
        update_post_meta($booking_id,'_campx_customer_name',$name);
        update_post_meta($booking_id,'_campx_customer_email',$email);
        update_post_meta($booking_id,'_campx_customer_phone',$phone);
        update_post_meta($booking_id,'_campx_notes',$notes);
        update_post_meta($booking_id,'_campx_status','requested');

        if ( ! \CampX\DB::reserve_occupancy($res_id,$booking_id,$start,$end,$units,'requested') ){
            wp_delete_post($booking_id, true);
            return new \WP_Error('campx_occ', __('Reservierung fehlgeschlagen (Gleichzeitiger Zugriff)','campx'), ['status'=>409]);
        }

        \CampX\Admin::send_booking_email($booking_id,'requested');

        $s = \CampX\Plugin::get_settings();
        $thanks = '';
        $pid = intval($s['thankyou_page_id'] ?? 0);
        if ( $pid ) { $thanks = get_permalink($pid); }
        return rest_ensure_response([ 'ok'=>true, 'booking_id'=>$booking_id, 'thankyou'=>$thanks ]);
    }
}
