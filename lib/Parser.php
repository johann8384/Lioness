<?php
class Parser
{
    private static $delimiter = ' ';
    private static $strip_brackets = true;
    private static $bracket_chars = '[]';

    private static function clean($entry)
    {
        if (self::$strip_brackets === true) {
            $entry = trim($entry, self::$bracket_chars);
        }
        return $entry;
    }

    public static function webapp_line_parser($line)
    {
        $message = '';

        $parts = explode(self::$delimiter, $line);
        $date = self::clean($parts[0] . ' ' . $parts[1] . ' ' . $parts[2] . $parts[3]);
        $level = self::clean($parts[4]);
        $pid = self::clean($parts[5]);
        parse_str($parts[6], $message);

        $message['created'] = strtotime($date);

        if (empty($level) && empty($pid) && $date == ' ') {
            throw new Exception('Could not parse line, missing level, pid or date: ' . $line);
        }

        $event = array('date' => strtotime($date), 'level' => $level, 'pid' => $pid, 'message' => $message);
        return $event;
    }
}
