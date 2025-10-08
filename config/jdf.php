<?php
/**
 * تبدیل تاریخ شمسی - نسخه بهبود یافته
 * Based on jdf.php by Reza Gholampanahi
 */

function jdate($format, $timestamp = '', $none = '', $time_zone = 'Asia/Tehran', $tr_num = 'fa') {
    $T_sec = 0;
    
    if ($time_zone != 'local') {
        date_default_timezone_set($time_zone);
    }
    
    $ts = $T_sec + (($timestamp === '') ? time() : $timestamp);
    $date = explode('_', date('H_i_s_j_n_Y', $ts));
    
    list($j_y, $j_m, $j_d) = gregorian_to_jalali($date[5], $date[4], $date[3]);
    
    $doy = ($j_m < 7) ? (($j_m - 1) * 31) + $j_d : (($j_m - 7) * 30) + $j_d + 186;
    $kab = (((($j_y % 33) % 4) - 1) == (int)(($j_y % 33) * 0.05)) ? 1 : 0;
    $sl = strlen($format);
    $out = '';
    
    for ($i = 0; $i < $sl; $i++) {
        $sub = substr($format, $i, 1);
        
        if ($sub == '\\') {
            $out .= substr($format, ++$i, 1);
            continue;
        }
        
        switch ($sub) {
            case 'Y': $out .= $j_y; break;
            case 'y': $out .= substr($j_y, 2, 2); break;
            case 'm': $out .= sprintf('%02d', $j_m); break;
            case 'n': $out .= $j_m; break;
            case 'd': $out .= sprintf('%02d', $j_d); break;
            case 'j': $out .= $j_d; break;
            
            case 'H': $out .= $date[0]; break;
            case 'i': $out .= $date[1]; break;
            case 's': $out .= $date[2]; break;
            
            case 'U': $out .= $ts; break;
            
            case 'l':
                $day_of_week = date('w', $ts);
                $days = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه', 'شنبه'];
                $out .= $days[$day_of_week];
                break;
                
            case 'D':
                $day_of_week = date('w', $ts);
                $days = ['ی', 'د', 'س', 'چ', 'پ', 'ج', 'ش'];
                $out .= $days[$day_of_week];
                break;
                
            case 'F':
                $months = [
                    'فروردین', 'اردیبهشت', 'خرداد',
                    'تیر', 'مرداد', 'شهریور',
                    'مهر', 'آبان', 'آذر',
                    'دی', 'بهمن', 'اسفند'
                ];
                $out .= $months[$j_m - 1];
                break;
                
            case 'M':
                $months = [
                    'فرو', 'ارد', 'خرد',
                    'تیر', 'مرد', 'شهر',
                    'مهر', 'آبا', 'آذر',
                    'دی', 'بهم', 'اسف'
                ];
                $out .= $months[$j_m - 1];
                break;
                
            default:
                $out .= $sub;
        }
    }
    
    return ($tr_num != 'en') ? farsi_num($out, 'fa', '.') : $out;
}

function gregorian_to_jalali($g_y, $g_m, $g_d) {
    $g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
    
    $gy = $g_y - 1600;
    $gm = $g_m - 1;
    $gd = $g_d - 1;
    
    $g_day_no = 365 * $gy + floor(($gy + 3) / 4) - floor(($gy + 99) / 100) + floor(($gy + 399) / 400);
    
    for ($i = 0; $i < $gm; ++$i) {
        $g_day_no += $g_days_in_month[$i];
    }
    
    if ($gm > 1 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0))) {
        $g_day_no++;
    }
    
    $g_day_no += $gd;
    $j_day_no = $g_day_no - 79;
    $j_np = floor($j_day_no / 12053);
    $j_day_no = $j_day_no % 12053;
    $jy = 979 + 33 * $j_np + 4 * floor($j_day_no / 1461);
    $j_day_no %= 1461;
    
    if ($j_day_no >= 366) {
        $jy += floor(($j_day_no - 1) / 365);
        $j_day_no = ($j_day_no - 1) % 365;
    }
    
    $j_month = 0;
    for ($i = 0; $i < 11 && $j_day_no >= $j_days_in_month[$i]; ++$i) {
        $j_day_no -= $j_days_in_month[$i];
        $j_month++;
    }
    
    $jd = $j_day_no + 1;
    $jm = $j_month + 1;
    
    return [$jy, $jm, $jd];
}

function jalali_to_gregorian($j_y, $j_m, $j_d) {
    $j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
    $g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    
    $jy = $j_y - 979;
    $jm = $j_m - 1;
    $jd = $j_d - 1;
    
    $j_day_no = 365 * $jy + floor($jy / 33) * 8 + floor(($jy % 33 + 3) / 4);
    
    for ($i = 0; $i < $jm; ++$i) {
        $j_day_no += $j_days_in_month[$i];
    }
    
    $j_day_no += $jd;
    $g_day_no = $j_day_no + 79;
    $gy = 1600 + 400 * floor($g_day_no / 146097);
    $g_day_no = $g_day_no % 146097;
    
    $leap = true;
    if ($g_day_no >= 36525) {
        $g_day_no--;
        $gy += 100 * floor($g_day_no / 36524);
        $g_day_no = $g_day_no % 36524;
        
        if ($g_day_no >= 365) {
            $g_day_no++;
        }
        $leap = false;
    }
    
    $gy += 4 * floor($g_day_no / 1461);
    $g_day_no %= 1461;
    
    if ($g_day_no >= 366) {
        $leap = false;
        $g_day_no--;
        $gy += floor($g_day_no / 365);
        $g_day_no = $g_day_no % 365;
    }
    
    $g_month = 0;
    for ($i = 0; $g_day_no >= $g_days_in_month[$i] + ($i == 1 && $leap); $i++) {
        $g_day_no -= $g_days_in_month[$i] + ($i == 1 && $leap);
        $g_month++;
    }
    
    $gd = $g_day_no + 1;
    $gm = $g_month + 1;
    
    return [$gy, $gm, $gd];
}

function farsi_num($str, $mod = 'fa', $mf = '٫') {
    $num_a = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '.'];
    $key_a = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', $mf];
    
    return ($mod == 'fa') ? str_replace($num_a, $key_a, $str) : str_replace($key_a, $num_a, $str);
}

function jmktime($h = '', $m = '', $s = '', $jm = '', $jd = '', $jy = '', $is_dst = -1) {
    if ($h === '') {
        return time();
    } else {
        list($h, $m, $s, $jm, $jd, $jy) = explode('_', $h . '_' . $m . '_' . $s . '_' . $jm . '_' . $jd . '_' . $jy);
        if ($m === '') {
            return mktime($h);
        } else {
            list($gy, $gm, $gd) = jalali_to_gregorian($jy, $jm, $jd);
            return mktime($h, $m, $s, $gm, $gd, $gy, $is_dst);
        }
    }
}
?>
