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