<?php
/**
 * License for Holt-Winters Class:
 *
 * @url https://github.com/ianbarber/PHPIR
 * Copyright (c) 2011, Ian Barber
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 *
 *    * Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 *    * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 **/
class PhpIR
{
    /**
     * Smooth supplied timeline data 3 ways - overall, by trend and by season.
     *
     * @param array $data - 1d array of data
     * @param int $season_length  - the number of entries that represent a 'season'
     * @param float $alpha - data smoothing factor
     * @param float $beta - trend smoothing factor
     * @param float $gamma - seasonality smoothing factor
     * @param float $dev_gamma - smoothing factor for deviations
     * @return array - the smoothed data
     */
    public static function holt_winters($data, $season_length = 7, $alpha = 0.2, $beta = 0.01, $gamma = 0.01, $dev_gamma = 0.1)
    {
        // Calculate an initial trend level
        $trend1 = 0;
        for ($i = 0; $i < $season_length; $i++) {
            $trend1 += $data[$i];
        }
        $trend1 /= $season_length;

        $trend2 = 0;
        for ($i = $season_length; $i < 2 * $season_length; $i++) {
            $trend2 += $data[$i];
        }
        $trend2 /= $season_length;

        $initial_trend = ($trend2 - $trend1) / $season_length;

        // Take the first value as the initial level
        $initial_level = $data[0];

        // Build index
        $index = array();
        foreach ($data as $key => $val) {
            $index[$key] = $val / ($initial_level + ($key + 1) * $initial_trend);
        }

        // Build season buffer
        $season = array_fill(0, count($data), 0);
        for ($i = 0; $i < $season_length; $i++) {
            $season[$i] = ($index[$i] + $index[$i + $season_length]) / 2;
        }

        // Normalise season
        $season_factor = $season_length / array_sum($season);
        foreach ($season as $key => $val) {
            $season[$key] *= $season_factor;
        }


        $holt_winters = array();
        $deviations = array();
        $alpha_level = $initial_level;
        $beta_trend = $initial_trend;

        foreach ($data as $key => $value) {
            $temp_level = $alpha_level;
            $temp_trend = $beta_trend;

            $alpha_level = $alpha * $value / $season[$key] + (1.0 - $alpha) * ($temp_level + $temp_trend);
            $beta_trend = $beta * ($alpha_level - $temp_level) + (1.0 - $beta) * $temp_trend;

            $season[$key + $season_length] = $gamma * $value / $alpha_level + (1.0 - $gamma) * $season[$key];

            $holt_winters[$key] = ($alpha_level + $beta_trend * ($key + 1)) * $season[$key];
            $deviations[$key] = $dev_gamma * abs($value - $holt_winters[$key]) + (1 - $dev_gamma)
                * (isset($deviations[$key - $season_length]) ? $deviations[$key - $season_length] : 0);
        }

        $forecast_length = $season_length * 2;
        for ($i = 1; $i <= $forecast_length; $i++) {
            $holt_winters[$key + $i] = $alpha_level + $beta_trend * $season[$key + $i];
        }

        return array('holt_winters' => $holt_winters, 'deviations' => $deviations);
    }
}