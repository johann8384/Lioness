<?phpclass Tailer
{
    private
    $start;
    private
    $end;
    private
    $offset;
    private
    $memcache_host;
    private
    $event_callback = '';
    private
    $sleep_callback = '';
    private
    $parse_callback = '';
    private
    $interval = 5;
    private
    $eof_callback = '';

    public
    function __get($name)
    {
        return $this->$name;
    }

    public
    function __construct($filename, $start = 0, $end = NULL)
    {
        $this->filename = $filename;
        $this->start = $start;
        $this->end = $end;
        $this->set_offset($start);
    }

    public
    function get_events($event_callback, $sleep_callback, $parse_callback, $eof_callback)
    {
        if (empty($this->filename)) {
            throw new Exception('No filename specified');
        }

        if (!file_exists($this->filename)) {
            throw new Exception('File does not exist: ' . $this->filename);
        }

        if (!is_readable($this->filename)) {
            throw new Exception('File is not readable: ' . $this->filename);
        }

        $this->event_callback = $event_callback;
        $this->sleep_callback = $sleep_callback;
        $this->parse_callback = $parse_callback;
        $this->eof_callback = $eof_callback;

        clearstatcache();

        $this->init_inode();

        $finished = false;
        while (!$finished) {
            $finished = $this->read_lines_from_file();
            if (!$finished) {
                sleep($this->interval);
            }
        }
    }

    private
    function set_offset($bytes)
    {
        $this->offset = $bytes;
    }

    private
    function open_file()
    {
        if (empty($this->filename)) {
            throw new Exception('No filename specified');
        }

        if (!file_exists($this->filename)) {
            throw new Exception('File does not exist: ' . $this->filename);
        }

        if (!is_readable($this->filename)) {
            throw new Exception('File is not readable: ' . $this->filename);
        }

        $this->file_handle = fopen($this->filename, 'r');

        if (!is_resource($this->file_handle)) {
            throw new Exception('Could not open file handle for ' . $this->filename);
        }
    }

    private
    function is_seekable()
    {
        $meta = stream_get_meta_data($this->file_handle);
        return $meta['seekable'];
    }

    private
    function seek_to_offset()
    {
        if (!is_int($this->offset) || $this->offset < 0) {
            throw new Exception('Invalid offset: ' . $this->offset);
        }

        if (!$this->is_seekable()) {
            throw new Exception('Not Seekable');
        }

        fseek($this->file_handle, $this->offset);
    }

    private
    function get_inode()
    {
        return fileinode($this->filename);
    }

    private
    function init_inode()
    {
        $inode = $this->get_inode();
        $this->set_inode($inode);
    }

    private
    function set_inode($inode)
    {
        $this->inode = $inode;
    }

    private
    function has_inode_changed()
    {
        $new_inode = $this->get_inode();

        if ($this->inode != $new_inode) {
            $this->set_inode($new_inode);
            return true;
        }

        return false;
    }

    private
    function read_line_from_file()
    {
        $line = fgets($this->file_handle, 5242880);
        $line = str_replace("\n", '', $line);
        $this->set_offset(ftell($this->file_handle));
        return $line;
    }

    /**
     *
     * This function performs the work of this class.
     * @throws Exception
     */
    private
    function read_lines_from_file()
    {
        try {
            // Open the File
            $this->open_file();

            // Seek to the offset, either 0 or where we left off
            $this->seek_to_offset();

            // Record what time we started
            $time = date('U');

            while (!feof($this->file_handle)) {
                // Read a line from the file and handle it.
                $line = $this->read_line_from_file();

                // Call the parser callback to parse the line into an event
                try {
                    $event = call_user_func_array($this->parse_callback, array('line' => $line));
                } catch (Exception $e) {
                    file_put_contents('/var/log/tcollectors/webapp2.log', $this->parse_callback . ' threw ' . $e->getMessage(), FILE_APPEND);
                }

                if ($this->end !== 0 && is_array($event) && array_key_exists('date', $event) && $this->end < $event['date']) {
                    //	echo "calling sleep, hit max time\n";
                    call_user_func_array($this->sleep_callback, array(true));
                    return true;
                }
                //else
                //{
                //	echo "not at max time yet: " . $this->end . " " . $event['date'] . "\n";
                //}

                // Call the event handler callback to handle the event
                try {
                    if ($event !== false) {
                        //echo "event is not false\n";
                        call_user_func_array($this->event_callback, $event);
                    }
                    //else
                    //{
                    //	echo "event is false\n";
                    //}
                } catch (Exception $e) {
                    //echo $e->getMessage() . "\n";
                    file_put_contents('/var/log/tcollectors/webapp2.log', $this->event_callback . ' threw ' . $e->getMessage(), FILE_APPEND);
                }

                // Check to see if we need to sleep yet
                if (date('U') - $this->sleep_time > $time) {
                    // If it is time to sleep, but we haven't reached EOF yet, then go ahead and call
                    // sleep handler but do not actually sleep. This allows the handler class to perform
                    // periodic actions but then we continue try to reach EOF.
                    // No longer doing this, the handle_event callback and eof callbacks handle this
                    // call_user_func_array($this->sleep_callback, array(true));

                    // Save the new time
                    $time = date('U');
                }
            }

            // Congratulations, you have reached EOF. However, your job may not be done. There may be more file to parse!
            // First, tell the event handler you have reached EOF
            call_user_func_array($this->eof_callback, array());

            // Check to see if log_rotate has changed the file out from under you and if so, close your file handle and
            // start again.
            if ($this->has_inode_changed()) {
                // Close our previous handle and prepare to open another
                fclose($this->file_handle);

                // Set our offset back to 0
                $this->set_offset(0);

                // Recursively call this function to begin reading the new file
                // This will return when EOF is reached, and no rotate has happened
                // The while(1) in get_events will call it again.
                $this->read_lines_from_file();
            }
            return false;
        } catch (Exception $e) {
            return false;
            throw $e;
        }
    }
}