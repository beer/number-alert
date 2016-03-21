<?php

class PatternChecker
{
    public function pattern_distance($tmp_hour_value, $p_hour_value)
    {
        $long_hours = array();
        while (true) {
            $hours = array_keys($tmp_hour_value);
            $hour_diff = array_combine($hours, array_map(function($hour) use ($tmp_hour_value, $p_hour_value) {
                return abs($tmp_hour_value[$hour] - $p_hour_value[$hour]);
            }, $hours));
            arsort($hour_diff);

            $max_hour = array_keys($hour_diff)[0];
            $max_diff = array_values($hour_diff)[0];
            if ($max_diff <= 4) {
                break;
            }

            $long_hours[$max_hour] = $max_diff;
            unset($tmp_hour_value[$max_hour]);
            $tmp_hour_value = array_combine(array_keys($tmp_hour_value), range(0, count($tmp_hour_value) - 1));
            unset($p_hour_value[$max_hour]);
            $p_hour_value = array_combine(array_keys($p_hour_value), range(0, count($p_hour_value) - 1));

        }
        return count($long_hours);
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
        $split_hour = 6;
        foreach (NumberStdRecord::search(array('set_id' => $set->set_id)) as $record) {
            $ymd = date('Ymd', $record->time - $split_hour * 3600);
            $hour = date('G', $record->time);
            if ($hour < $split_hour) {
                $hour += 24;
            }
            $day_hour_value[$ymd][$hour] = intval($record->value);
        }

        $day_hour_rank = array();
        // 把人氣變成只取排名
        foreach ($day_hour_value as $day => $hour_value) {
            if (count($hour_value) != 24) {
                // 資料不滿 24hr 的先不列入計算
                //error_log("skip $day");
                unset($day_hour_value[$day]);
            } else {
                asort($day_hour_value[$day]);
                $day_hour_rank[$day] = array_flip(array_keys($day_hour_value[$day]));
            }
        }


        $clustered = MathLib::kmean($day_hour_rank, $k, array('PatternChecker', 'pattern_distance'), array('PatternChecker', 'patterns_center'), array('20160106', '20160109', '20160218'));
        uasort($clustered, function($a, $b) { return count($a) - count($b); });

        $ret = new StdClass;
        $ret->clusters = array();
        foreach ($clustered as $cluster_id => $dates) {
            $ret->clusters[] = array(
                'records' => array_map(function($d) use ($day_hour_value) {
                    $hour_value = $day_hour_value[$d];
                    ksort($hour_value);
                    return array(
                        'date' => $d,
                        'week_day' => date('D', strtotime($d)),
                        'values' => array_values(array_map(function($hour) use ($hour_value) { return array($hour, $hour_value[$hour]); }, array_keys($hour_value))),
                    ); 
                }, $dates),
            );
        }
        return $ret;
    }
}
