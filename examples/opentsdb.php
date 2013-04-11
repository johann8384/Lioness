<?php
include '/Users/jcreasy/code/Lioness/conf/config.php';

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

function get_points($metric = 'proc.loadavg.1m', $range = 'day', $start = '')
{
    //echo "<pre> get_points |$metric| |$range| |$start|</pre>";
    if (empty($start))
    {
        $time = date('U');
    } else {
        $time = strtotime($start);
    }

    $points = get_tsdb_data($metric, $range, $time);
    $stat = new Histogram('foo', 'bar', 'baz', 60);

    foreach ($points as $timestamp => $value) {
        $stat->add_value($value, $timestamp);
    }
    return array('points'=>$stat->get_values(), 'holt_winters'=>$stat->holt_winters());
}

$metric = $_GET['metric'];
$range = $_GET['range'];

//$metric = 'proc.loadavg.1min';
//$range = 'day';

$points1 = get_points($metric, $range);
$points2 = get_points($metric, $range, '2 ' . $range . 's ago');

$hw1 = $points1['holt_winters'];
$hw2 = $points2['holt_winters'];

$pts1 = $points1['points'];
$pts2 = $points2['points'];

?>
<!doctype html>
<script src="/js/d3.v2.min.js"></script>
<script src="http://code.shutterstock.com/rickshaw/rickshaw.min.js"></script>

<div id="chart"></div>

<script>
    <?php
    $diff = array();
    foreach ($hw['deviations'] as $x => $y) {
        $diff[$x] = abs($pts[$x] - $hw['deviations'][$x]);
    }
    ?>
    var graph = new Rickshaw.Graph({
        element:document.querySelector("#chart"),
        width:1280,
        height:720,
        renderer:'line',
        onData: function(d) {
               	  Rickshaw.Series.zeroFill(d);
               	  return d;
               	},
        series:[
            {
                name:'data',
                color:'steelblue',
                data:[
                <?php
                foreach ($pts1 as $x => $y) {
                    echo "\t\t\t{ x: $x, y: $y },\n";
                }
                ?>
                ]
            },
            {
                name:'data',
                color:'green',
                data:[
                <?php
                foreach ($pts2 as $x => $y) {
                    echo "\t\t\t{ x: $x, y: $y },\n";
                }
                ?>
                ]
            },
            {
                name:'deviations',
                color:'red',
                data:[
                <?php
                foreach ($hw1['deviations'] as $x => $y) {
                    echo "\t\t\t{ x: $x, y: $y },\n";
                }
                ?>
                ]
            },
            {
                name:'deviations',
                color:'yellow',
                data:[
                <?php
                foreach ($hw2['deviations'] as $x => $y) {
                    echo "\t\t\t{ x: $x, y: $y },\n";
                }
                ?>
                ]
            }
        ]
    });

    var legend = new Rickshaw.Graph.Legend({
        graph:graph,
        element:document.querySelector('#chart')
    });

    var highlighter = new Rickshaw.Graph.Behavior.Series.Highlight({
        graph:graph,
        legend:legend
    });

    //graph.renderer.unstack = true;
    graph.render();

</script>