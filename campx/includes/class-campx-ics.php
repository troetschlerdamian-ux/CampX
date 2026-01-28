<?php
namespace CampX;
if ( ! defined( 'ABSPATH' ) ) exit;

class ICS {
    public static function listen(){
        if ( isset($_GET['campx_ics']) ){
            $type = sanitize_text_field($_GET['campx_ics']);
            $id = absint($_GET['id'] ?? 0);
            if ( $type==='resource' && $id ) self::output_resource_ics($id);
            if ( $type==='booking'  && $id ) self::output_booking_ics($id);
        }
    }

    protected static function headers($filename='campx.ics'){
        nocache_headers();
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
    }

    public static function output_resource_ics($resource_id){
        $events = [];
        $q = new \WP_Query([
            'post_type'=>'campx_booking',
            'posts_per_page'=>-1,
            'post_status'=>'any',
            'meta_query'=>[
                ['key'=>'_campx_resource_id','value'=>$resource_id,'compare'=>'='],
                ['key'=>'_campx_status','value'=>'accepted','compare'=>'=']
            ]
        ]);
        foreach($q->posts as $p){ $events[] = self::booking_to_vevent($p->ID); }
        self::headers('campx-resource-'.$resource_id.'.ics');
        echo self::wrap( implode("\r\n", $events) );
        exit;
    }

    public static function output_booking_ics($booking_id){
        self::headers('campx-booking-'.$booking_id.'.ics');
        echo self::wrap( self::booking_to_vevent($booking_id) );
        exit;
    }

    protected static function booking_to_vevent($booking_id){
        $start = get_post_meta($booking_id,'_campx_start_date',true);
        $end   = get_post_meta($booking_id,'_campx_end_date',true);
        $name  = get_post_meta($booking_id,'_campx_customer_name',true);
        $res_id= (int) get_post_meta($booking_id,'_campx_resource_id',true);
        $res   = get_the_title($res_id);
        $uid   = $booking_id . '@' . parse_url(home_url(), PHP_URL_HOST);
        $dtstamp = gmdate('Ymd\THis\Z');
        $dtstart = date('Ymd', strtotime($start));
        $dtend   = date('Ymd', strtotime($end));
        $summary = self::esc( sprintf('%s – %s', $res, $name) );
        $desc    = self::esc( get_post_meta($booking_id,'_campx_notes',true) );
        $loc     = self::esc( get_bloginfo('name').' – '.home_url('/') );
        return "BEGIN:VEVENT\r\nUID:$uid\r\nDTSTAMP:$dtstamp\r\nDTSTART;VALUE=DATE:$dtstart\r\nDTEND;VALUE=DATE:$dtend\r\nSUMMARY:$summary\r\nDESCRIPTION:$desc\r\nLOCATION:$loc\r\nEND:VEVENT";
    }

    protected static function wrap($vevents){
        $prodid='-//CampX Booking//EN';
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:$prodid\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\n$vevents\r\nEND:VCALENDAR";
    }
    protected static function esc($s){
        $s = (string) $s;
        $s = str_replace('\\', '\\\\', $s);
        $s = str_replace("\n", "\\n", $s);
        $s = str_replace("\r", "", $s);
        $s = str_replace(",", "\\,", $s);
        $s = str_replace(";", "\\;", $s);
        return $s;
    }
}
