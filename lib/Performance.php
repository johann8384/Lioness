<?phpclass Performance
{
    private
    static $downsamplers = array('sum', 'avg', 'min', 'max', 'count');
    private
    static $events = array();
    public
    static $sleep_time = 60;
    public
    static $max = 0;
    // end to config

    private
    static $metrics = array(
        'count_db_queries' => array('counters', 'db_queries'),
        'count_memcache_queries' => array('counters', 'memcache_queries'),
        'count_memcache_get_hits' => array('counters', 'memcache_hits'),
        'count_memcache_get_misses' => array('counters', 'memcache_misses'),
        'count_memcache_sets' => array('counters', 'memcache_sets'),
        'count_sql_count' => array('counters', 'sql'),
        'timer_php_ms' => array('timers', 'php'),
        'timer_db_ms' => array('timers', 'db'),
        'timer_memcache_ms' => array('timers', 'memcache'),
        'timer_unidb_encode_ms' => array('timers', 'unidb_encode'),
        'timer_total_time_ms' => array('timers', 'total'),
        'memory_get_peak_usage' => array('memory', 'peak_usage'),
        'latency_db' => array('latency', 'db'),
        'latency_memcache' => array('latency', 'memcache'),
    );

    private
    static $params = array(
        'host' => 'host_name',
        'user' => 'user_id',
        'tarriff' => 'tariff_id',
        'enterprise' => 'enteprise_id',
        'requested_host' => 'requested_host_name',
        'rm' => 'current_rm',
    );

    private static function metric_factory($message)
{
    $second = $message['created'];

    $latency_pairs = array(
        'latency_db' => array('timer_db_ms', 'count_db_queries'),
        'latency_memcache' => array('timer_memcache_ms', 'count_memcache_queries')
    );

    foreach ($latency_pairs as $new_metric => $old_metric_pair) {
        if (array_key_exists($old_metric_pair[0], $message) && array_key_exists($old_metric_pair[1], $message)) {
            //echo "adding \$message[$new_metric]\n";
            $message[$new_metric] = $message[$old_metric_pair[0]] / $message[$old_metric_pair[1]];
        }
    }

    return $message;
}

	private static function init_events($second, $minute, $runmode, $class, $type)
{
    //echo "initializeing $second $minute $runmode $class $type\n";
    if (empty(self::$events) || !is_array(self::$events)) {
        self::$events = array();
    }

    self::init_event_section('minutes', "$minute", $runmode, $class, $type);
    self::init_event_section('seconds', "$second", $runmode, $class, $type);
    return;
}

	private static function init_event_section($section, $created, $runmode, $class, $type)
{
    if (!array_key_exists($section, self::$events)) {
        self::$events[$section] = array();
    }

    if (!array_key_exists(intval($created), self::$events[$section]) || !is_array(self::$events[$section]["$created"])) {
        self::$events[$section]["$created"] = array();
    }

    if (!array_key_exists($runmode, self::$events[$section]["$created"])) {
        self::$events[$section]["$created"][$runmode] = array();
    }

    if (!array_key_exists($class, self::$events[$section]["$created"][$runmode])) {
        self::$events[$section]["$created"][$runmode][$class] = array();
    }

    if (!array_key_exists($type, self::$events[$section]["$created"][$runmode][$class])) {
        self::$events[$section]["$created"][$runmode][$class][$type] = new Histogram($runmode, $class, $type, self::$sleep_time);
    }
}

	private static function get_host_info()
{
    $hostname = gethostname();
    $parts = explode('.', $hostname);
    $dc = $parts[1];
    $host = $parts[0];

    if (strpos($host, 'content') !== false) {
        $type = 'content';
    } else if (strpos($host, 'upload') !== false) {
        $type = 'upload';
    } else if (strpos($host, 'download') !== false) {
        $type = 'download';
    } else {
        $type = 'other';
    }
    return array('hostname' => $host, 'type' => $type, 'dc' => $dc);
}

	private static function get_minute_from_second($second)
{
    return floor($second / 60) * 60;
}

	private static function detect_out_of_order_event($message_created)
{
    //echo "detecting out of order events $message_created\n";
    if (empty(self::$events) || !is_array(self::$events) || !array_key_exists('minutes', self::$events) || !is_array(self::$events['minutes'])) {
        return;
    }

    $message_minute = self::get_minute_from_second($message_created);

    // Get the first minute in our processing array
    reset(self::$events['minutes']);
    $processing_minute = each(self::$events['minutes']);
    $processing_minute = $processing_minute[0];

    // if this message is not in the same minute as the other messages we have processed, send the previous minute for processing
    if ($processing_minute != $message_minute) {
        if ($processing_minute > $message_minute) {
            // This event is *older* than the previous one...
            throw new Exception('out of order event: ' . $processing_minute . ' is later than ' . $message_minute);
        }
    }
}

	public static function handle_sleep()
{
    // Return false if there is nothing to process
    if (!is_array(self::$events)) {
        return false;
    }

    $msg = array();
    $host_info = self::get_host_info();
    /*

if (!array_key_exists('seconds', self::$events))
{
  return false;
}

     foreach (self::$events['seconds'] as $second=>$runmodes)
    {
    foreach ($runmodes as $runmode=>$classes)
    {
    foreach ($classes as $class=>$types)
    {
    foreach ($types as $type=>$stat)
    {
    // Only doing Requests per Second
    if ($type = 'requests')
    {
    foreach (self::$downsamplers as $downsample)
    {
    $value = $stat->{$downsample}();
    $format = "webapp2.%s.%s.%s %s %s type=%s\n";
    $msg[] = sprintf($format, $runmode, $class, $downsample, $second, $value, $type);
    }
    }
    }
    }
    }
    }
    */

    if (!array_key_exists('minutes', self::$events)) {
        return false;
    }

    foreach (self::$events['minutes'] as $minute => $runmodes) {
        foreach ($runmodes as $runmode => $classes) {
            foreach ($classes as $class => $types) {
                foreach ($types as $type => $stat) {

                    foreach (self::$downsamplers as $downsample) {
                        $value = $stat->{$downsample}();
                        $format = "webapp2.%s.%s.1m.%s %s %s type=%s\n";
                        $msg[] = sprintf($format, $runmode, $class, $downsample, $minute, $value, $type);
                    }
                }
            }
        }
    }

    $sender = new Sender();
    $sender->send_metrics($msg);

    unset($msg);
    self::$events = array();
}

	public static function handle_eof()
{
    return;
}

	public static function handle_event($date, $level, $pid, $message)
{
    //echo "handling " . json_encode($message) . "\n";

    $msgline = '';
    $msg = array();

    if (empty(self::$events) || !is_array(self::$events)) {
        self::$events = array();
    }

    if (!array_key_exists('created', $message)) {
        throw new Exception('Message does not contain created');
    }

    $second = (string)$message['created'];

    $minute = (string)floor($second / 60) * 60;

    self::detect_out_of_order_event($second);

    // Create calculated metrics
    $message = self::metric_factory($message);

    //echo "processing value\n";
    foreach (self::$metrics as $index => $metric) {
        if (array_key_exists($index, $message)) {
            //echo "message does have $index\n";
            $class = $metric[0];
            $type = $metric[1];

            foreach (array('total', $message['current_rm']) as $runmode) {
                if ($runmode === '') {
                    throw new Exception('invalid runmode: ' . $runmode);
                }

                self::init_events($second, $minute, $runmode, $class, $type);
                self::$events['minutes']["$minute"][$runmode][$class][$type]->add_value($message[$index], "$minute");
                self::$events['seconds']["$second"][$runmode][$class][$type]->add_value($message[$index], "$second");
            }
        }
        //else
        //{
        //	echo "message does not have $index\n";
        //}
    }
    foreach (array('total', $message['current_rm']) as $runmode) {
        if ($runmode === '') {
            throw new Exception('invalid runmode: ' . $runmode);
        }
        // Add a counter to log this request.
        self::init_events($second, $minute, $runmode, 'counters', 'requests');
        self::$events['minutes']["$minute"][$runmode]['counters']['requests']->add_value($message[$index], "$minute");
        self::$events['seconds']["$second"][$runmode]['counters']['requests']->add_value($message[$index], "$second");
    }
}
}