<?php
include 'conf/config.php';

$data = new Histogram('foo', 'bar', 'baz', 60);

function get_tsdb_data($metric, $range = 'hour', $time = '')
{
    $date_format = 'Y/m/d-H:i:s';
    $aggregator = 'sum';

    if (empty($time)) {
        $time = date('U');
    }

    //TODO: This section should be config driven.
    switch (strtolower($range)) {
        case 'hour':
            $start = date($date_format, strtotime('1 hour ago', $time));
            $end = date($date_format, $time);
            $downsample = '';
            $seconds = 60;
            break;
        case 'day':
            $start = date($date_format, strtotime('1 day ago', $time));
            $end = date($date_format, $time);
            $downsample = '5m-avg:';
            $seconds = 60 * 5;
            break;
        case 'week':
            $start = date($date_format, strtotime('1 week ago', $time));
            $end = date($date_format, $time);
            $downsample = '30m-avg:';
            $seconds = 60 * 30;
            break;
        case 'month':
            $start = date($date_format, strtotime('1 month ago', $time));
            $end = date($date_format, $time);
            $downsample = '2h-avg:';
            $seconds = 60 * 120;
            break;
        case 'year':
            $start = date($date_format, strtotime('1 year ago', $time));
            $end = date($date_format, $time);
            $downsample = '1d-avg:';
            $seconds = 60 * 60 * 24;
            break;
        default:
            break;
    }
    /*
     * Hour -> start-1h/start + start-25h/start-24h
     * Day -> (downsample 5m avg) start-1d/start + start-2d/start-1d
     * Week -> (downsample 30m avg) start-1w/start + start-2w/start-1w
     * Month -> (downsample 2h avg) start-1m/start
     * Year -> (downsample 1d avg) start-1y/start
     */

    $url = 'http://opentsdb.sv2.box.net:4242/q?start=' . $start . '&end=' . $end . '&ignore=2&m=' . $aggregator . ':' . $downsample . $metric . '&o=&yrange=[0:]&wxh=1660x761&ascii';
    $data = $ascii = file_get_contents($url);
    $data = preg_split('/$\R?^/m', $data);
    $stats = array();

    foreach ($data as $line) {
        $line = explode(' ', $line);
        $timestamp = $line[1];
        $value = $line[2];
        $minute = floor($timestamp / $seconds) * $seconds;
        if ($value != 0) {
            if (!array_key_exists("$minute", $stats)) {
                $stats[$minute] = new Histogram('foo', 'bar', $metric, 60);
            }
            $stats[$minute]->add_value(round($value, 3), $timestamp);
        }
    }
    foreach ($stats as $minute => $stat) {
        $stat->histogram();
        $ret[$minute] = round($stat->avg(), 3);
    }
    return $ret;
}

$points = get_tsdb_data('proc.loadavg.1min', 'day');

foreach ($points as $timestamp => $value) {
    $data->add_value($value, $timestamp);
}

$pts = $data->get_values();
$hw = $data->holt_winters('sum', 7, 0.01, 0.01, 0.01, 0.1);