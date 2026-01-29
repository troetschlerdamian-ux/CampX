<?php
namespace CampX;
if ( ! defined( 'ABSPATH' ) ) exit;

class ICS {
    public static function listen(){
        $type = get_query_var('campx_ics');
        if ( empty($type) && isset($_GET['campx_ics']) ) {
            $type = sanitize_text_field($_GET['campx_ics']);
        }
        if ( empty($type) ) {
            $request_path = isset($_SERVER['REQUEST_URI']) ? wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
            if ( $request_path && preg_match('#/campx\.ics/?$#', $request_path) ) {
                $type = 'all';
            }
            if ( empty($type) && $request_path && preg_match('#/campx-([A-Za-z0-9]+)\.ics/?$#', $request_path, $matches) ) {
                $type = 'all';
                if ( ! empty($matches[1]) ) {
                    $_GET['token'] = $matches[1];
                    if ( function_exists('set_query_var') ) {
                        set_query_var('token', $matches[1]);
                    } elseif ( isset($GLOBALS['wp_query']) && is_object($GLOBALS['wp_query']) ) {
                        $GLOBALS['wp_query']->query_vars['token'] = $matches[1];
                    }
                }
            }
        }
        if ( empty($type) ) {
            return;
        }
        $raw_id = get_query_var('id');
        if ( empty($raw_id) && isset($_GET['id']) ) {
            $raw_id = sanitize_text_field($_GET['id']);
        }
        $id = absint($raw_id);
        if ( ! $id && $type === 'resource' && ! empty($raw_id) ) {
            $resource = get_page_by_path(sanitize_title($raw_id), OBJECT, 'campx_resource');
            if ( $resource ) {
                $id = (int) $resource->ID;
            }
        }
        self::assert_token();
        if ( $type==='resource' && $id ) self::output_resource_ics($id);
        if ( $type==='booking'  && $id ) self::output_booking_ics($id);
        if ( $type==='all' ) self::output_all_ics();
        if ( $type === 'resource' || $type === 'booking' ) {
            status_header(400);
            echo 'Invalid ICS request';
            exit;
        }
    }

    protected static function headers($filename='campx.ics'){
        status_header(200);
        nocache_headers();
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="'.$filename.'"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    public static function output_resource_ics($resource_id){
        $events = [];
        $q = new \WP_Query([
            'post_type'=>'campx_booking',
            'posts_per_page'=>-1,
            'post_status'=>['publish', 'private', 'pending', 'draft', 'future'],
            'meta_query'=>[
                'relation' => 'AND',
                ['key'=>'_campx_resource_id','value'=>$resource_id,'compare'=>'='],
                self::status_meta_query(),
            ]
        ]);
        foreach($q->posts as $p){
            $event = self::booking_to_vevent($p->ID);
            if ( $event ) $events[] = $event;
        }
        self::headers('campx-resource-'.$resource_id.'.ics');
        $calendar_name = sprintf('%s – %s', get_bloginfo('name'), get_the_title($resource_id));
        echo self::fold_lines( self::wrap( implode("\r\n", $events), $calendar_name ) );
        exit;
    }

    public static function output_booking_ics($booking_id){
        self::headers('campx-booking-'.$booking_id.'.ics');
        $calendar_name = sprintf('%s – %s', get_bloginfo('name'), __('Buchung', 'campx'));
        echo self::fold_lines( self::wrap( self::booking_to_vevent($booking_id), $calendar_name ) );
        exit;
    }

    public static function output_all_ics(){
        $events = [];
        $q = new \WP_Query([
            'post_type'=>'campx_booking',
            'posts_per_page'=>-1,
            'post_status'=>['publish', 'private', 'pending', 'draft', 'future'],
            'meta_query'=>self::status_meta_query(),
        ]);
        foreach($q->posts as $p){
            $event = self::booking_to_vevent($p->ID);
            if ( $event ) $events[] = $event;
        }
        self::headers('campx-bookings.ics');
        $calendar_name = sprintf('%s – %s', get_bloginfo('name'), __('Buchungen', 'campx'));
        echo self::fold_lines( self::wrap( implode("\r\n", $events), $calendar_name ) );
        exit;
    }

    protected static function booking_to_vevent($booking_id){
        $start = get_post_meta($booking_id,'_campx_start_date',true);
        $end   = get_post_meta($booking_id,'_campx_end_date',true);
        if ( ! $start || ! $end ) {
            return '';
        }
        $name   = get_post_meta($booking_id,'_campx_customer_name',true);
        $email  = get_post_meta($booking_id,'_campx_customer_email',true);
        $phone  = get_post_meta($booking_id,'_campx_customer_phone',true);
        $units  = get_post_meta($booking_id,'_campx_units',true);
        $persons= get_post_meta($booking_id,'_campx_persons',true);
        $notes  = get_post_meta($booking_id,'_campx_notes',true);
        $status = get_post_meta($booking_id,'_campx_status',true) ?: 'requested';
        $res_id = (int) get_post_meta($booking_id,'_campx_resource_id',true);
        $res    = get_the_title($res_id);
        $uid    = $booking_id . '@' . parse_url(home_url(), PHP_URL_HOST);
        $dtstamp = get_post_modified_time('Ymd\THis\Z', true, $booking_id);
        if ( ! $dtstamp ) {
            $dtstamp = gmdate('Ymd\THis\Z');
        }
        $dtstart = date('Ymd', strtotime($start));
        $dtend   = date('Ymd', strtotime($end));
        $summary = self::esc( sprintf('%s – %s', $res, $name) );
        $status_label = [
            'accepted' => __('Bestätigt', 'campx'),
            'requested' => __('Angefragt', 'campx'),
            'declined' => __('Abgelehnt', 'campx'),
            'expired' => __('Abgelaufen', 'campx'),
        ][$status] ?? $status;
        $desc    = self::esc( self::build_description([
            __('Status', 'campx') => $status_label,
            __('Name', 'campx') => $name,
            __('E-Mail', 'campx') => $email,
            __('Telefon', 'campx') => $phone,
            __('Ressource', 'campx') => $res,
            __('Anreise', 'campx') => $start,
            __('Abreise', 'campx') => $end,
            __('Einheiten', 'campx') => $units,
            __('Personen', 'campx') => $persons,
            __('Notizen', 'campx') => $notes,
        ]) );
        $loc     = self::esc( get_bloginfo('name').' – '.home_url('/') );
        $url     = self::esc( admin_url('post.php?post=' . $booking_id . '&action=edit') );
        $event_status = ($status === 'accepted') ? 'CONFIRMED' : 'TENTATIVE';
        return "BEGIN:VEVENT\r\nUID:$uid\r\nDTSTAMP:$dtstamp\r\nLAST-MODIFIED:$dtstamp\r\nDTSTART;VALUE=DATE:$dtstart\r\nDTEND;VALUE=DATE:$dtend\r\nSUMMARY:$summary\r\nDESCRIPTION:$desc\r\nLOCATION:$loc\r\nURL:$url\r\nSTATUS:$event_status\r\nEND:VEVENT";
    }

    protected static function wrap($vevents, $calendar_name=''){
        $prodid='-//CampX Booking//EN';
        $timezone = wp_timezone_string();
        $calname = $calendar_name ? self::esc($calendar_name) : self::esc(get_bloginfo('name'));
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            "PRODID:$prodid",
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            "X-WR-CALNAME:$calname",
            'X-WR-CALDESC:' . $calname,
            'X-PUBLISHED-TTL:PT15M',
            'REFRESH-INTERVAL;VALUE=DURATION:PT15M',
        ];
        if ( $timezone ) {
            $lines[] = 'X-WR-TIMEZONE:' . self::esc($timezone);
        }
        $prefix = implode("\r\n", $lines);
        return $prefix . "\r\n" . $vevents . "\r\nEND:VCALENDAR";
    }

    protected static function fold_lines($content){
        $lines = explode("\r\n", $content);
        $folded = [];
        foreach ( $lines as $line ) {
            $line = (string) $line;
            while ( strlen($line) > 75 ) {
                $folded[] = substr($line, 0, 75);
                $line = ' ' . substr($line, 75);
            }
            $folded[] = $line;
        }
        return implode("\r\n", $folded);
    }

    protected static function status_meta_query(){
        return [
            'relation' => 'OR',
            ['key'=>'_campx_status','value'=>'accepted','compare'=>'='],
            ['key'=>'_campx_status','value'=>'requested','compare'=>'='],
        ];
    }

    protected static function build_description(array $fields){
        $lines = [];
        foreach ( $fields as $label => $value ) {
            $value = trim((string) $value);
            if ( $value === '' ) {
                continue;
            }
            $lines[] = $label . ': ' . $value;
        }
        return implode("\n", $lines);
    }

    protected static function assert_token(){
        $settings = \CampX\Plugin::get_settings();
        $token = $settings['ics_token'] ?? '';
        if ( empty($token) ) {
            return;
        }
        $provided = get_query_var('token');
        if ( empty($provided) && isset($_GET['token']) ) {
            $provided = sanitize_text_field($_GET['token']);
        }
        $provided = sanitize_text_field((string) $provided);
        if ( ! $provided || ! hash_equals($token, $provided) ) {
            status_header(403);
            echo 'Forbidden';
            exit;
        }
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
