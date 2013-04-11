<?php
class Stat
{
    private $data = array();
    private $class = '';
    private $metric = '';
    private $runmode = '';
    private $interval = 1;
    private $points = array();

    public function __get($name)
    {
        return $this->$name;
    }

    public function __construct($runmode, $class, $metric, $interval = 1, $values = NULL)
    {
        if (!empty($values)) {
            $this->add_values($values);
        }

        $this->data = array();
        $this->points = array();
        $this->runmode = $runmode;
        $this->metric = $metric;
        $this->interval = $interval;
        $this->class = $class;
    }

    public function get_values($aggregator = 'sum')
    {
        ksort($this->points);

        switch (strtolower($aggregator)) {
            case 'sum':
                foreach ($this->points as $timestamp => $values) {
                    $a["$timestamp"] = $this->sum($values);
                }
                break;
            case 'avg':
                foreach ($this->points as $timestamp => $values) {
                    $a["$timestamp"] = $this->avg($values);
                }
                break;
            case 'max':
                foreach ($this->points as $timestamp => $values) {
                    $a["$timestamp"] = $this->max($values);
                }
                break;
            case 'min':
                foreach ($this->points as $timestamp => $values) {
                    $a["$timestamp"] = $this->min($values);
                }
                break;
            default:
                foreach ($this->points as $timestamp => $values) {
                    $a["$timestamp"] = $this->sum($values);
                }
                break;
        }
        return $a;
    }

    public function get_data()
    {
        return $this->data;
    }

    public function add_values($values)
    {
        //echo "adding value: " . json_encode($values) . "\n";
        foreach ($values as $timestamp => $values) {
            foreach ($values as $value) {
                $this->add_value($value, $timestamp);
            }
        }
    }

    public function add_value($value, $timestamp)
    {
        if ((empty($value) && $value !== 0) || !is_numeric($value)) {
            throw new Exception('invalid value ' . $value);
        }

        if (!array_key_exists("$timestamp", $this->points)) {
            $this->points["$timestamp"] = array();
        }

        $this->points["$timestamp"][] = $value;

        $this->data[] = $value;
    }

    public function reset_data()
    {
        $this->points = array();
        $this->data = array();
    }

    public function range()
    {
        return $this->max() - $this->min();
    }

    public function rate($interval = null)
    {
        if (empty($interval)) {
            $interval = $this->interval;
        }
        return $this->count() / $interval;
    }

    public function count($data = NULL)
    {
        if (empty($data))
        {
            $data = $this->data;
        }

        return count($this->data);
    }

    public function median()
    {
        $median = $this->percentile(50);
        return $median;
    }

    public function min_timestamp()
    {
        return array_keys($this->points, min($this->points));
    }

    public function sum($data = NULL)
    {
        $sum = 0;

        if (empty($data))
        {
            $data = $this->data;
        }

        foreach ($data as $value) {
            $sum += $value;
        }
        return $sum;
    }

    public function sum2()
    {
        $sum = 0;

        foreach ($this->data as $value) {
            $sum += (float)pow($value, 2);
        }

        return $sum;
    }

    public function avg($data = NULL)
    {
        if (empty($data))
        {
            $data = $this->data;
        }

        if ($this->count($data) == 0 || $this->sum($data) == 0) {
            return 0;
        }
        return $this->sum($data) / $this->count($data);
    }

    public function min($data = NULL)
    {
        if (empty($data))
        {
            $data = $this->data;
        }

        if (count($this->data) < 1) {
            return 0;
        }
        return (float)min($this->data);
    }

    public function max($data = NULL)
    {
        if (empty($data))
        {
            $data = $this->data;
        }

        if (count($this->data) < 1) {
            return 0;
        }
        return (float)max($this->data);
    }

    public function standard_deviation()
    {
        return sqrt(($this->sum2() - $this->count() * pow($this->avg(), 2)) / (float)($this->count() - 1));
    }

    public function holt_winters($aggregator = 'sum', $season_length = 7, $alpha = 0.2, $beta = 0.01, $gamma = 0.01, $dev_gamma = 0.1)
    {
        $ret = PhpIR::holt_winters($this->get_values($aggregator), 10, 0.1, 0.01, 0.01, 0.1);
        return $ret;
    }

    public function __toString()
    {
        return $this->to_string();
    }

    public function to_string()
    {
        $s = '';
        $s .= sprintf("N = %8d\tRange = %-8.0f\tMin = %-8.4f\tMax = %-8.4f\tAvg = %-8.4f\n", $this->count(), $this->range(), $this->min(), $this->max(), $this->avg());
        $s .= sprintf("StDev = %-8.4f\tSum = %-8.4f\tSum^2 = %-8.4f\n", $this->standard_deviation(), $this->sum(), $this->sum2());
        return $s;
    }

    public function to_array($include_points = false)
    {
        $ret = array();
        $ret['metric'] = array('runmode' => $this->runmode, 'class' => $this->class, 'type' => $this->metric, 'interval' => $this->interval);

        $stats = array();
        $stats['min'] = $this->min();
        $stats['max'] = $this->max();
        $stats['count'] = $this->count();
        $stats['sum'] = $this->sum();
        $stats['sum2'] = $this->sum2();
        $stats['avg'] = $this->avg();
        $stats['stdv'] = $this->standard_deviation();
        $ret['stats'] = $stats;
        if ($include_points === true) {
            $ret['points'] = $this->points;
            $ret['holt_winters'] = $this->holt_winters();
        }
        return $ret;
    }

    public function to_json($include_points = false)
    {
        $ret = $this->to_array($include_points);
        $ret = json_encode($ret);
        return $ret;
    }

    public function percentile($percentile)
    {
        if (empty($percentile) || !is_numeric($percentile)) {
            throw new Exception('invalid percentile ' . $percentile);
        }

        if (0 < $percentile && $percentile < 1) {
            $p = $percentile;
        } else if (1 < $percentile && $percentile <= 100) {
            $p = $percentile * .01;
        } else {
            throw new Exception('invalid percentile ' . $percentile);
        }

        if (empty($this->data) || !is_array($this->data)) {
            throw new Exception('invalid data');
        }

        $count = count($this->data);

        $allindex = ($count - 1) * $p;

        $intvalindex = intval($allindex);

        $floatval = $allindex - $intvalindex;

        sort($this->data);

        if (!is_float($floatval)) {
            $result = $this->data[$intvalindex];
        } else {
            if ($count > $intvalindex + 1) {
                $result = $floatval * ($this->data[$intvalindex + 1] - $this->data[$intvalindex]) + $this->data[$intvalindex];
            } else {
                $result = $this->data[$intvalindex];
            }
        }
        return $result;
    }

    public function quartiles()
    {
        $q1 = $this->percentile(25);
        $q2 = $this->percentile(50);
        $q3 = $this->percentile(75);
        $quartile = array('25' => $q1, '50' => $q2, '75' => $q3);
        return $quartile;
    }

}
