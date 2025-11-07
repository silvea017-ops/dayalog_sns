<?php
// functions/date_helper.php

function formatPostDate($datetime) {
    $timestamp = strtotime($datetime);
    
    $date = date('Y.m.d', $timestamp);
    $hour = date('h', $timestamp);
    $minute = date('i', $timestamp);
    $ampm = date('A', $timestamp);
    
    return "{$date} {$ampm}{$hour}:{$minute}";
}

/**
 * 상대 시간 표시 함수 (방금, 5분, 2시간, 3일 등)
 */
if (!function_exists('getRelativeTime')) {
    function getRelativeTime($datetime) {
        $now = new DateTime();
        $past = new DateTime($datetime);
        $diff = $now->getTimestamp() - $past->getTimestamp();
        
        if ($diff < 60) {
            return '방금';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . '분';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . '시간';
        } elseif ($diff < 604800) {
            return floor($diff / 86400) . '일';
        } else {
            // 7일 이상이면 절대 시간으로 표시
            return formatPostDate($datetime);
        }
    }
}