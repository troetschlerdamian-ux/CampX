<?php
namespace CampX;
if ( ! defined( 'ABSPATH' ) ) exit;

class Ajax {
    public static function init(){
        add_action('wp_ajax_campx_check_availability', [__CLASS__, 'check_availability']);
        add_action('wp_ajax_nopriv_campx_check_availability', [__CLASS__, 'check_availability']);
    }

    public static function check_availability(){
        $start = sanitize_text_field( $_POST['start'] ?? $_POST['start_date'] ?? '' );
        $end   = sanitize_text_field( $_POST['end'] ?? $_POST['end_date'] ?? '' );
        $ids   = self::parse_ids( $_POST );
        $map   = [];

        if ( empty($ids) || ! $start || ! $end ){
            wp_send_json([ 'availability' => $map ]);
        }

        $dates = \CampX\Availability::date_range_nights( $start, $end );
        if ( empty($dates) ){
            wp_send_json([ 'availability' => $map ]);
        }

        foreach ( $ids as $res_id ){
            $capacity = (int) get_post_meta( $res_id, '_campx_capacity', true );
            if ( $capacity < 1 ){
                $map[ $res_id ] = [ 'available' => false, 'units_left' => 0, 'capacity' => $capacity ];
                continue;
            }
            $min_left = $capacity;
            foreach ( $dates as $d ){
                $booked = \CampX\DB::booked_units_on( $res_id, $d );
                $left = $capacity - $booked;
                if ( $left < $min_left ) { $min_left = $left; }
                if ( $min_left <= 0 ) { break; }
            }
            $map[ $res_id ] = [
                'available'  => $min_left > 0,
                'units_left' => max(0, $min_left),
                'capacity'   => $capacity,
            ];
        }

        wp_send_json([ 'availability' => $map ]);
    }

    private static function parse_ids( $data ){
        $ids = [];
        if ( isset($data['ids']) && is_string($data['ids']) ){
            $decoded = json_decode( wp_unslash($data['ids']), true );
            if ( is_array($decoded) ){
                $ids = $decoded;
            }
        }
        if ( empty($ids) && isset($data['ids']) && is_array($data['ids']) ){
            $ids = $data['ids'];
        }
        if ( empty($ids) && isset($data['ids[]']) ){
            $ids = (array) $data['ids[]'];
        }
        $ids = array_filter( array_map( 'absint', (array) $ids ) );
        return array_values( array_unique( $ids ) );
    }
}
