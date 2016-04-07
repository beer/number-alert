<?php

class PatternChecker
{
    public function get_pattern_diff($tmp_hour_value, $p_hour_value)
    {
        $long_hours = array();
        if (count($tmp_hour_value) != count($p_hour_value)) {
            return false;
        }
        while (true) {
            $hours = array_keys($tmp_hour_value);
            $hour_diff = array_combine($hours, array_map(function($hour) use ($tmp_hour_value, $p_hour_value) {
                return abs($tmp_hour_value[$hour] - $p_hour_value[$hour]);
            }, $hours));
            arsort($hour_diff);

            $max_hour = array_keys($hour_diff)[0];
            $max_diff = array_values($hour_diff)[0];
            if ($max_diff <= 3) {
                break;
            }

            $long_hours[$max_hour] = $max_diff;
            unset($tmp_hour_value[$max_hour]);
            if (!$tmp_hour_value) {
                break;
            }
            $tmp_hour_value = array_combine(array_keys($tmp_hour_value), range(0, count($tmp_hour_value) - 1));
            unset($p_hour_value[$max_hour]);
            $p_hour_value = array_combine(array_keys($p_hour_value), range(0, count($p_hour_value) - 1));

        }
        return array_map(function($k) use ($long_hours) { return array($k, $long_hours[$k]); }, array_keys($long_hours));;
    }

    public function pattern_distance($tmp_hour_value, $p_hour_value)
    {
        $ret = self::get_pattern_diff($tmp_hour_value, $p_hour_value);
        if (false === $ret) {
            return 10000;
        }
        return count($ret);

    }

    public function patterns_center($patterns)
    {
        $hours = array_keys($patterns[0]);
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

        $day_hour_rank = array();
        // 把人氣變成只取排名
        foreach ($day_hour_value as $day => $hour_value) {
            if (strpos($set->name, 'ptt') === 0 and count($hour_value) != 24) {
                // 資料不滿 24hr 的先不列入計算
                //error_log("skip $day");
                unset($day_hour_value[$day]);
            } else {
                asort($day_hour_value[$day]);
                $day_hour_rank[$day] = array_flip(array_keys($day_hour_value[$day]));
            }
        }


        $clustered = MathLib::kmean($day_hour_rank, $k, array('PatternChecker', 'pattern_distance'), array('PatternChecker', 'patterns_center'));
        uasort($clustered, function($a, $b) { return count($a) - count($b); });

        $ret = new StdClass;
        $ret->clusters = array();
        foreach ($clustered as $cluster_id => $dates) {
            if ($dates) {
                $center = PatternChecker::patterns_center(array_map(function($d) use ($day_hour_rank) {
                    return $day_hour_rank[$d[1]];
                }, $dates));
                $center = array_map(function($k) use ($center) { return array($k, $center[$k]); }, array_keys($center));
            } else {
                $center = array();
            }
            $center_rank = array_combine(array_map(function($a) { return $a[0]; }, $center), array_map(function($a) { return $a[1]; }, $center));
            asort($center_rank);
            $ret->clusters[] = array(
                'records' => array_map(function($d) use ($day_hour_value, $day_hour_rank, $center, $center_rank, $date_tags) {
                    list($distance, $date) = $d;
                    $hour_value = $day_hour_value[$date];
                    $hour_rank = $day_hour_rank[$date];
                    ksort($hour_value);

                    return array(
                        'date' => $date,
                        'distance' => $distance,
                        'week_day' => date('D', strtotime($date)),
                        'values' => array_values(array_map(function($hour) use ($hour_value) { return array($hour, $hour_value[$hour]); }, array_keys($hour_value))),
                        'diff' => PatternChecker::get_pattern_diff($hour_rank, $center_rank),
                        'tags' => $date_tags->{$date} ?: array(),
                    ); 
                }, $dates),
                'center' => $center,
            );
        }
        return $ret;
    }
}
