<?php

function median($arr) {
    sort($arr);
    $count = count($arr);
    $middle = floor(($count-1)/2);
    if ($count % 2) {
        return $arr[$middle];
    } else {
        $low = $arr[$middle];
        $high = $arr[$middle+1];
        return (($low+$high)/2);
    }
}
function average($arr) {
    return round(array_sum($arr) / count($arr), 2);
}

function num_array_check_less_than_or_equal($arr, $num) {
    if (count($arr) === 0) return false;

    $filtered = array_filter($arr, function($value) use ($num) {
        return $value <= $num;
    });

    return empty($filtered);
}