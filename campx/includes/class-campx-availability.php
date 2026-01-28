<?php
namespace CampX;
if ( ! defined( 'ABSPATH' ) ) exit;

class Availability {
    public static function is_available( $resource_id, $start_date, $end_date, $units_needed ){
        $capacity = (int) get_post_meta( $resource_id, '_campx_capacity', true );
        if ( $capacity < 1 ) return false;
        $dates = self::date_range_nights( $start_date, $end_date );
        foreach( $dates as $d ){
            $booked = self::booked_units_on( $resource_id, $d );
            if ( ($booked + $units_needed) > $capacity ) return false;
        }
        return true;
    }

    public static function booked_units_on( $resource_id, $date ){
        return \CampX\DB::booked_units_on( $resource_id, $date );
    }

    public static function date_range_nights( $start, $end ){
        $out = [];
        try { $s = new \DateTime($start); $e = new \DateTime($end); } catch(\Exception $ex){ return $out; }
        while( $s < $e ){ $out[] = $s->format('Y-m-d'); $s->modify('+1 day'); }
        return $out;
    }
}
