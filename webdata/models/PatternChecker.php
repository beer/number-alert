<?php

class PatternChecker
{
    public function getRankFromValues($hour_value)
    {
        asort($hour_value);
        return array_flip(array_keys($hour_value));
    }

    public function get_pattern_diff($tmp_hour_value, $p_hour_value)
    {
        $long_hours = array();
        if (count($tmp_hour_value) != count($p_hour_value)) {
            return false;
        }
        $tmp_hour_rank = self::getRankFromValues($tmp_hour_value);
        $p_hour_rank = self::getRankFromValues($p_hour_value);
        while (true) {
            $hours = array_keys($tmp_hour_rank);
            $hour_diff = array_combine($hours, array_map(function($hour) use ($tmp_hour_rank, $p_hour_rank) {
                return abs($tmp_hour_rank[$hour] - $p_hour_rank[$hour]);
            }, $hours));
            arsort($hour_diff);

            $max_hour = array_keys($hour_diff)[0];
            $max_diff = array_values($hour_diff)[0];
            if ($max_diff <= 3) {
                break;
            }

            $long_hours[$max_hour] = $max_diff;
            unset($tmp_hour_rank[$max_hour]);
            if (!$tmp_hour_rank) {
                break;
            }
            $tmp_hour_rank= array_combine(array_keys($tmp_hour_rank), range(0, count($tmp_hour_rank) - 1));
            unset($p_hour_rank[$max_hour]);
            $p_hour_rank = array_combine(array_keys($p_hour_rank), range(0, count($p_hour_rank) - 1));

        }
        return array_map(function($k) use ($long_hours) { return array($k, $long_hours[$k]); }, array_keys($long_hours));;
    }

    public function get_pattern_diff2($tmp_hour_value, $p_hour_value)
    {
        $long_hours = array();

        foreach (array_keys($tmp_hour_value) as $k) {
            if (!array_key_exists($k, $p_hour_value)) {
                $p_hour_value[$k] = 0;
            }
        }
        foreach (array_keys($p_hour_value) as $k) {
            if (!array_key_exists($k, $tmp_hour_value)) {
                $tmp_hour_value[$k] = 0;
            }
        }
        if (count($tmp_hour_value) != count($p_hour_value)) {
            return false;
        }

        while (true) {
            // 先把最高人氣數字取出來當作基準點
            $tmp_max = max(array_values($tmp_hour_value));
            $p_max = max(array_values($p_hour_value));

            // 改成 0 ~ 100% 相對數字而非絕對數字
            $tmp_hour_rate = array_map(function($v) use ($tmp_max) { return $tmp_max ? $v / $tmp_max : 0; }, $tmp_hour_value);
            $p_hour_rate = array_map(function($v) use ($p_max) { return $p_max ? $v / $p_max : 0; }, $p_hour_value);

            $hours = array_keys($tmp_hour_rate);
            $hour_diff = array_combine($hours, array_map(function($hour) use ($tmp_hour_rate, $p_hour_rate) {
                return pow(1 + abs($tmp_hour_rate[$hour] - $p_hour_rate[$hour]), 3) - 1;
            }, $hours));
            arsort($hour_diff);

            $max_hour = array_keys($hour_diff)[0];
            $max_diff = array_values($hour_diff)[0];

            $long_hours[$max_hour] = round($max_diff, 2);
            unset($tmp_hour_value[$max_hour]);
            if (!$tmp_hour_value) {
                break;
            }
            unset($p_hour_value[$max_hour]);

        }
        return array_map(function($k) use ($long_hours) { return array($k, $long_hours[$k]); }, array_keys($long_hours));;
    }

    public function pattern_distance($tmp_hour_value, $p_hour_value)
    {
        $ret = self::get_pattern_diff2($tmp_hour_value, $p_hour_value);
        if (false === $ret) {
            return 10000;
        }
        return array_sum(array_map(function($r) { return $r[1]; }, $ret));

    }

    public function patterns_center($patterns)
    {
        if (!$patterns) {
            return array();
        }
        $hours = array_keys($patterns[0]);
        foreach ($patterns as $id => $pattern) {
            $max_value = max($pattern);
            $patterns[$id] = array_map(function($v) use ($max_value) { return ($max_value) ? ($v / $max_value) : 0;}, $pattern);
        }
        $centers = array();
        foreach ($hours as $hour) {
            $centers[$hour] = MathLib::getMedian(array_map(function($pattern) use ($hour) { return $pattern[$hour]; }, $patterns));
        }
        return $centers;
    }

    public static function getClusteredPatterns($set, $k = 5)
    {
        $ret = $set->stdNumber();
        $day_hour_value = array();

        // 先把數字都塞進日期中
        $split_hour = 5;
        foreach (NumberStdRecord::search(array('set_id' => $set->set_id)) as $record) {
            $ymd = date('Ymd', $record->time - $split_hour * 3600);
            $hour = date('G', $record->time);
            if ($hour < $split_hour) {
                $hour += 24;
            }
            $day_hour_value[$ymd][$hour] = intval($record->value);
        }
        $date_tags = TimeTag::findTagsByTimes(1, array_keys($day_hour_value));

        // 把人氣變成只取排名
        foreach ($day_hour_value as $day => $hour_value) {
            if (strpos($set->name, 'ptt') === 0 and count($hour_value) != 24) {
                // 資料不滿 24hr 的先不列入計算
                //error_log("skip $day");
                unset($day_hour_value[$day]);
            }
        }


        $clustered = MathLib::kmean($day_hour_value, $k, array('PatternChecker', 'pattern_distance'), array('PatternChecker', 'patterns_center'));
        uasort($clustered, function($a, $b) { return count($a) - count($b); });

        $ret = new StdClass;
        $ret->clusters = array();
        foreach ($clustered as $cluster_id => $distance_dates) {
            if ($distance_dates) {
                $center_value = PatternChecker::patterns_center(array_map(function($distance_date) use ($day_hour_value) {
                    list($distance, $date) = $distance_date;
                    return $day_hour_value[$date];
                }, $distance_dates));
            } else {
                $center_value = array();
            }

            $cluster = array(
                'records' => array_map(function($distance_date) use ($day_hour_value, $center_value, $date_tags) {
                    list($distance, $date) = $distance_date;
                    $hour_value = $day_hour_value[$date];
                    ksort($hour_value);
                    $distance = PatternChecker::pattern_distance($hour_value, $center_value);

                    return array(
                        'date' => $date,
                        'distance' => $distance,
                        'week_day' => date('D', strtotime($date)),
                        'values' => array_values(array_map(function($hour) use ($hour_value) { return array($hour, $hour_value[$hour]); }, array_keys($hour_value))),
                        'diff' => PatternChecker::get_pattern_diff2($hour_value, $center_value),
                        'tags' => $date_tags->{$date} ?: array(),
                    ); 
                }, $distance_dates),
                'center_value' => $center_value,
                'center_rank' => array_map(function($hour) use ($center_value) { return array($hour, $center_value[$hour]); }, array_keys($center_value)),
            );
            usort($cluster['records'], function($a, $b) { return MathLib::number_compare($a['distance'], $b['distance']); });
            $ret->clusters[] = $cluster;
        }
        return $ret;
    }
}
