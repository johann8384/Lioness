<?phpclass Sender
{
    private
    $sent = array();

    public
    function __construct()
    {
        $this->sent = array();
    }

    protected
    function has_already_been_sent($metric)
    {
        // break the key stuff to a new function "see if already see"
        $data = explode(' ', $metric);
        $key = '';

        for ($i = 0; $i < count($data); $i++) {
            if ($i != 3) {
                $key .= ' ' . $data[$i];
            }
        }

        $hash = md5($key);

        if (in_array($hash, $this->sent)) {
            return true;
        }

        $this->sent[] = $hash;
        return false;
    }

    public
    function send_metrics($metrics)
    {
        $errno = 0;
        $errstr = '';

        if (!is_array($metrics)) {
            $metrics = array($metrics);
        }

        try {
            foreach ($metrics as $metric) {
                if (!$this->has_already_been_sent($metric)) {
                    file_put_contents('php://stdout', $metric);
                }
            }
        } catch (Exception $e) {
            throw $e;
        }
    }
}