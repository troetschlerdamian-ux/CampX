<?php
namespace CampX;
if ( ! defined( 'ABSPATH' ) ) exit;

class PDF {
    public static function generate_booking_pdf($booking_id, $is_admin_copy = false){
        $booking = get_post($booking_id);
        if ( ! $booking || $booking->post_type !== 'campx_booking' ) {
            return '';
        }
        $resource_id = (int) get_post_meta($booking_id,'_campx_resource_id',true);
        $resource = get_post($resource_id);
        $start = get_post_meta($booking_id,'_campx_start_date',true);
        $end = get_post_meta($booking_id,'_campx_end_date',true);
        $units = (int) get_post_meta($booking_id,'_campx_units',true);
        $persons = (int) get_post_meta($booking_id,'_campx_persons',true);
        $name = get_post_meta($booking_id,'_campx_customer_name',true);
        $email = get_post_meta($booking_id,'_campx_customer_email',true);
        $phone = get_post_meta($booking_id,'_campx_customer_phone',true);
        $status = get_post_meta($booking_id,'_campx_status',true) ?: 'requested';
        $price_per_night = (float) get_post_meta($resource_id,'_campx_price_per_night',true);
        $currency = \CampX\Plugin::get_settings()['currency'] ?? 'CHF';
        $nights = count(\CampX\Availability::date_range_nights($start, $end));
        $total = $price_per_night > 0 ? $price_per_night * $units * $nights : 0;

        $lines = [
            ['text' => get_bloginfo('name')],
            ['text' => __('Buchungsbestätigung','campx')],
            ['text' => ''],
            ['text' => __('Buchung','campx') . ' #' . $booking_id],
            ['text' => __('Ressource','campx') . ': ' . ($resource ? $resource->post_title : '')],
            ['text' => __('Anreise','campx') . ': ' . $start],
            ['text' => __('Abreise','campx') . ': ' . $end],
            ['text' => __('Nächte','campx') . ': ' . $nights],
            ['text' => __('Einheiten','campx') . ': ' . $units],
            ['text' => __('Personen','campx') . ': ' . $persons],
            ['text' => ''],
            ['text' => __('Kunde','campx') . ': ' . $name],
            ['text' => __('E-Mail','campx') . ': ' . $email],
            ['text' => __('Telefon','campx') . ': ' . $phone],
            ['text' => ''],
            ['text' => __('Preis pro Einheit/Nacht','campx') . ': ' . self::format_price($price_per_night, $currency)],
            ['text' => __('Gesamtbetrag','campx') . ': ' . self::format_price($total, $currency)],
            ['text' => ''],
            ['text' => __('Status','campx') . ': ' . ucfirst($status)],
            ['text' => __('NICHT BEZAHLT – Zahlung vor Ort','campx'), 'color' => [1, 0, 0], 'size' => 16],
        ];

        $pdf = self::build_pdf($lines);
        if ( ! $pdf ) {
            return '';
        }

        $upload_dir = wp_upload_dir();
        $folder = trailingslashit($upload_dir['basedir']) . 'campx';
        if ( ! file_exists($folder) ) {
            wp_mkdir_p($folder);
        }
        $filename = sprintf('campx-booking-%d-%s.pdf', $booking_id, $is_admin_copy ? 'admin' : 'customer');
        $path = trailingslashit($folder) . $filename;
        file_put_contents($path, $pdf);
        return $path;
    }

    protected static function build_pdf(array $lines){
        $content_lines = [];
        $content_lines[] = 'BT';
        $font_size = 12;
        $content_lines[] = '/F1 ' . $font_size . ' Tf';
        $content_lines[] = '50 780 Td';
        foreach ($lines as $line) {
            $text = $line['text'] ?? '';
            $color = $line['color'] ?? [0, 0, 0];
            $size = (int) ($line['size'] ?? $font_size);
            if ( $size !== $font_size && $size > 0 ) {
                $font_size = $size;
                $content_lines[] = '/F1 ' . $font_size . ' Tf';
            }
            $content_lines[] = sprintf('%.3f %.3f %.3f rg', $color[0], $color[1], $color[2]);
            $content_lines[] = '(' . self::escape_pdf_text($text) . ') Tj';
            $content_lines[] = 'T*';
        }
        $content_lines[] = 'ET';
        $content = implode("\n", $content_lines);

        $objects = [];
        $objects[] = '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj';
        $objects[] = '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj';
        $objects[] = '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj';
        $objects[] = '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj';
        $objects[] = '5 0 obj << /Length ' . strlen($content) . ' >> stream' . "\n" . $content . "\n" . 'endstream endobj';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= $obj . "\n";
        }
        $xref_offset = strlen($pdf);
        $pdf .= "xref\n0 " . count($offsets) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < count($offsets); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer << /Size " . count($offsets) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xref_offset . "\n%%EOF";
        return $pdf;
    }

    protected static function escape_pdf_text($text){
        $text = (string) $text;
        if ( function_exists('iconv') ) {
            $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
            if ( $converted !== false ) {
                $text = $converted;
            }
        }
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace('(', '\\(', $text);
        $text = str_replace(')', '\\)', $text);
        $text = str_replace(["\r", "\n"], ' ', $text);
        return $text;
    }

    protected static function format_price($amount, $currency){
        $amount = number_format((float) $amount, 2, '.', "'");
        return $amount . ' ' . $currency;
    }
}
