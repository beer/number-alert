<?php

class MathLib
{
    public static function getVar($numbers)
    {
        $avg = array_sum($numbers) / count($numbers);
        $std = pow(array_sum(array_map(function($n) use ($avg) { return pow($avg - $n, 2); }, $numbers)) / count($numbers), 0.5);
        return $std / $avg;
    }

    public static function getStd($numbers)
    {
        $avg = array_sum($numbers) / count($numbers);
        $std = pow(array_sum(array_map(function($n) use ($avg) { return pow($avg - $n, 2); }, $numbers)) / count($numbers), 0.5);
        return $std;
    }

    public static function getMedian($numbers)
    {
        $values = array_values($numbers);
        sort($values);
        if (count($numbers) % 2 == 0) {
            $med = ($values[count($numbers) / 2] + $values[count($numbers) / 2 - 1]) / 2;
        } else {
            $med = $values[(count($numbers) - 1) / 2];
        }
        return $med;
    }

    public static function getOutier($numbers)
    {
        $median = MathLib::getMedian($numbers);
        $mad = 0;
        //$mad = MathLib::getMedian(array_map(function($n) use ($median) { return abs($n - $median); }, $numbers));
        $outeiers = array();
        foreach ($numbers as $k => $v) {
            if ($mad) {
                if (abs($v - $median) / $mad >= 3.5) {
                    $outeiers[$k] = $v;
                    unset($numbers[$k]);
                }
            } else {
                if (abs($v - $median) > 3.5) {
                    $outeiers[$k] = $v;
                    unset($numbers[$k]);
                }
            }
        }
        return array($numbers, $outeiers);
    }

    public function getRandomCenteriods($dataset, $k, $distance_func, $init_centeriods = array())
    {
        $centeroids = array();
        foreach ($init_centeriods as $key) {
            $centeroids[] = $dataset[$key];
            unset($dataset[$key]);
        }
        shuffle($dataset);
        $centeroids[] = array_shift($dataset);

        while (count($centeroids) != $k) {
            $max_distance = null;
            $max_match = null;
            foreach ($dataset as $id => $v) {
                $min_distance = null;
                foreach ($centeroids as $center_v) {
                    $distance = $distance_func($v, $center_v);
                    if (is_null($min_distance) or $distance < $min_distance) {
                        $min_distance = $distance;
                        if ($distance == 0) {
                            break;
                        }
                    }
                }
                if (is_null($max_distance) or $min_distance > $max_distance) {
                    $max_distance = $min_distance;
                    $max_match = $id;
                }
            }
            $centeroids[] = $dataset[$id];
            unset($dataset[$id]);
        }
        return $centeroids;
        return array_map(function($k) use ($dataset) { return $dataset[$k]; }, array_rand($dataset, $k));
    }

    public function kmean($dataset, $k, $distance_func, $centeroid_func, $init_centeriods = array())
    {
        $centeroids = self::getRandomCenteriods($dataset, $k, $distance_func, $init_centeriods);
        $old_centeroids = null;
        $cluster_set = null;
        for ($i = 0; $i < 100; $i ++) {
            error_log($i);
            if (!is_null($old_centeroids) and $old_centeroids == $centeroids) {
                break;
            }
            $cluster_set = array_fill(0, count($cluster_set), array());
            $old_centeroids = $centeroids;

            foreach ($dataset as $dataset_id => $v) {
                $min_distance = null;
                $min_center = null;
                foreach ($centeroids as $center_id => $centeroid) {
                    if (is_null($centeroid)) {
                        continue;
                    }
                    $distance = $distance_func($centeroid, $v);
                    if (is_null($min_distance) or $min_distance > $distance) {
                        $min_distance = $distance;
                        $min_center = $center_id;
                        if ($distance == 0) {
                            break;
                        }
                    }
                }
                $cluster_set[$min_center][] = $dataset_id;
            }

            foreach ($cluster_set as $id => $data_ids) {
                if ($data_ids) {
                    $centeroids[$id] = $centeroid_func(array_map(function($k) use ($dataset) { return $dataset[$k]; }, $data_ids));
                } else {
                    $centeroids[$id] = null;
                }
            }
        }
        return $cluster_set;
    }
}

