<?php
include BASE_PATH . '/lib/Stat.php';
/**
 *
 * Extend Stats class to include Histogram support
 * borrows heavily from Jesus M Castagnetto's code published here: http://px.sklar.com/code.html?id=119
 * @author jcreasy
 */
/*
 * // Original Header
* This is a histogram class that accepts and unidimensional array of data
* Returns 2 arrays by using the getStats() and getBins() methods.
* Note: Tested only w/ PHP 3.0.7
* (c) Jesus M. Castagnetto, 1999.
* Gnu GPL'd code, see www.fsf.org for the details.
*/
class Histogram extends Stat
{
    private $bins = array();
    private $title = '';

    public function title()
    {
        return $this->runmode . '_' . $this->class . '_' . $this->metric;
    }

    private function print_stats()
    {
        $s = "Statistics for histogram: " . $this->title() . "\n";
        $s .= parent::to_string();
        return $s;
    }

    private function print_bins()
    {
        $s = sprintf("Number of bins: %s\n", count($this->bins));
        $s .= sprintf("BIN\tVAL\t\tFREQ\n");

        $maxbin = max($this->bins);
        reset($this->bins);

        for ($i = 0; $i < count($this->bins); $i++) {
        list($key, $val) = each($this->bins);
            $s .= sprintf("%d\t%-8.2f\t%-8d |%s\n", $i + 1, $key, $val, $this->print_bar($val, $maxbin));
        }
        return $s;
    }

    private function number_of_bins()
    {
        $count = count(array_unique($this->data));

        //http://www.amazon.com/Jurans-Quality-Control-Handbook-Juran/dp/0070331766
        if ($count < 1) {
            return 0;
        }

        if ($count < 20) {
            return 5;
        }

        if ($count <= 50) {
            return 6;
        }

        if ($count <= 100) {
            return 7;
        }

        if ($count <= 200) {
            return 8;
        }

        if ($count <= 500) {
            return 9;
        }

        if ($count <= 1000) {
            return 10;
        }

        if ($count <= 5000) {
            $n = ($count / 100) + 1;
        }

        return 52;
    }

    private function validate()
    {
        if ($this->count() <= 1) {
            throw new Exception("Not enough data, " . $this->count() . " values");
        }

        if ($this->number_of_bins() < 1) {
            throw new Exception("Insufficient number of bins.");
        }

        return;
    }

    private function print_bar($val, $maxbin)
    {
        $fact = (float)($maxbin > 40) ? 40 / $maxbin : 1;
        $niter = (int)$val * $fact;
        $out = "";

        for ($i = 0; $i < $niter; $i++) {
            $out .= "*";
        }

        return $out;
    }

    public function __construct($runmode, $class, $metric, $interval = '1', $values = NULL)
    {
        parent::__construct($runmode, $class, $metric, $interval, $values);
        if (empty($this->data)) {
            return;
        }
        $this->validate();
        $this->histogram();
    }

    public function histogram($number_of_bins = NULL, $first_bin = NULL, $bin_width = NULL)
    {
        $bin = array();

        /* init bins array */
        if (empty($number_of_bins)) {
            $number_of_bins = $this->number_of_bins();
        }

        /* width of bins */
        if (empty($bin_width)) {
            $bin_width = $this->delta($number_of_bins);
        }

        if (empty($first_bin)) {
            $first_bin = $this->min();
        }

        for ($i = 0; $i < $number_of_bins; $i++) {
            $bin[$i] = (float)$first_bin + $bin_width * $i;
            $this->bins[(string)$bin[$i]] = 0;
        }

        /* calculate frequencies and populate bins array */
        $data = $this->data;
        $tmp = ($number_of_bins - 1);

        for ($i = 0; $i < $this->count(); $i++) {
            for ($j = $tmp; $j >= 0; $j--) {
                if ($data[$i] >= $bin[$j]) {
                    $this->bins[(string)$bin[$j]]++;
                    break;
                }
            }
        }
    }

    public function delta($number_of_bins = NULL)
    {
        if (empty($number_of_bins)) {
            $number_of_bins = $this->number_of_bins();
        }
        return (float)($this->max() - $this->min()) / $number_of_bins;
    }

    /* send back BINS array */
    public function get_bins()
    {
        return $this->bins;
    }

    public function __toString()
    {
        return $this->to_string();
    }

    public function to_string()
    {
        $s = sprintf("%s\n%s\n", $this->print_stats(), $this->print_bins());
        return $s;
    }

    public function to_array($include_points = false)
    {
        $ret = parent::to_array($include_points);
        $ret['histogram'] = $this->get_bins();
        return $ret;
    }
}