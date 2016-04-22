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
                $distance = 0;
                foreach ($centeroids as $center_v) {
                    $distance += $distance_func($v, $center_v);
                }
                if (is_null($max_distance) or $distance > $max_distance) {
                    $max_distance = $distance;
                    $max_match = $id;
                }
            }
            $centeroids[] = $dataset[$max_match];
            unset($dataset[$max_match]);
        }
        return $centeroids;
        return array_map(function($k) use ($dataset) { return $dataset[$k]; }, array_rand($dataset, $k));
    }

    public function kmean($dataset, $k, $distance_func, $centeroid_func, $init_centeriods = array())
    {
        $centeroids = self::getRandomCenteriods($dataset, $k, $distance_func, $init_centeriods);
        $cluster_set = null;
        $showed_centeroids = array();
        for ($i = 0; $i < 100; $i ++) {
            $centeroid_hash = md5(json_encode($centeroids));
            if (array_key_exists($centeroid_hash, $showed_centeroids)) {
                break;
            }
            $showed_centeroids[$centeroid_hash] = true;
            $cluster_set = array_fill(0, count($centeroids), array());

            // 先把所有的資料都跟最靠近的 $centeroids 靠攏
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
                $cluster_set[$min_center][$dataset_id] = array($min_distance, $dataset_id);
            }

            // 算出新的 $centeroids
            $centeroids = array();
            foreach ($cluster_set as $id => $data_ids) {
                $data_ids = array_values($data_ids);
                $cluster_set[$id] = $data_ids;
                if ($data_ids) {
                    usort($cluster_set[$id], function($a, $b) { return MathLib::number_compare($a[0], $b[0]); });
                    $centeroids[$id] = $centeroid_func(array_map(function($k) use ($dataset) { return $dataset[$k[1]]; }, $data_ids));
                } else {
                    unset($cluster_set[$id]);
                }
            }
            ksort($cluster_set);
            ksort($centeroids);
            $cluster_set = array_values($cluster_set);
            $centeroids = array_values($centeroids);

            // 合併太接近的 $centeroids
            list($cluster_set, $centeroids) = self::mergeCenteroids($dataset, $cluster_set, $centeroids, $distance_func, $centeroid_func);

            // 如果 $centeroids 數量小於 $k ，就從各 cluster 中找出最遠的拿出來獨立成為新的 centeroid
            if (count($centeroids) < $k) {
                $distance_map = array();
                foreach ($cluster_set as $cluster_id => $data_ids) {
                    if (!$data_ids) {
                        continue;
                    }
                    foreach ($data_ids as $distance_setid) {
                        list($distance, $dataset_id) = $distance_setid;
                        $distance = $distance_func($dataset[$dataset_id], $centeroids[$cluster_id]);
                        if (!array_key_exists($cluster_id, $distance_map)) {
                            if ($distance > 0) {
                                $distance_map[$cluster_id] = array('dataset_id' => $dataset_id, 'distance' => $distance);
                            }
                        } else {
                            if ($distance > $distance_map[$cluster_id]['distance']) {
                                $distance_map[$cluster_id] = array('dataset_id' => $dataset_id, 'distance' => $distance);
                            }
                        }
                    }
                }
                // 把 distance 最大的挖出來補足
                usort($distance_map, function ($a, $b) { return MathLib::number_compare($b['distance'], $a['distance']); });
                $distance_map = array_slice($distance_map, 0, $k - count($centeroids));
                foreach ($distance_map as $cluster_id => $distance_data) {
                    $dataset_id = $distance_data['dataset_id'];
                    $cluster_set[] = array(
                        array(0, $dataset_id),
                    );
                    $centeroids[] = $dataset[$dataset_id];
                    unset($cluster_set[$cluster_id][$dataset_id]);
                }

            }
            $cluster_set = array_values($cluster_set);

        }

        return $cluster_set;
    }

    /**
     * mergeCenteroids 把 centeroids 太接近的合在一起(只要 cluster_a 距離 cluster_b 小於 cluster_b 自己本身最遠的一個點，就把 cluster_a 併進 cluster_b)
     * 
     * @param array $cluster_set 
     * @param array $centeroids 
     * @param callable $distance_func 
     * @param callable $centeroid_func
     * @access public
     * @return array
     */
    public function mergeCenteroids($dataset, $cluster_set, $centeroids, $distance_func, $centeroid_func)
    {
        for ($i = 0; $i < count($centeroids); $i ++) {
            for ($j = $i + 1; $j < count($centeroids); $j ++) {
                if (is_null($centeroids[$j])) {
                    continue;
                }

                $distance = $distance_func($centeroids[$i], $centeroids[$j]);
                if ($distance >= max(array_map(function($s) { return $s[0]; }, $cluster_set[$j]))) {
                    continue;
                }

                $cluster_set[$i] = array_merge($cluster_set[$i], $cluster_set[$j]);
                $centeroids[$i] = $centeroid_func(array_map(function($k) use ($dataset) { return $dataset[$k[1]]; }, $cluster_set[$i]));
                $cluster_set[$i] = array_map(function($record) use ($dataset, $distance_func, $centeroids, $i) {
                    return array($distance_func($centeroids[$i], $dataset[$record[1]]), $record[1]);
                }, $cluster_set[$i]);
                usort($cluster_set[$i], function($a, $b) { return MathLib::number_compare($a[0], $b[0]); });
                $cluster_set[$i] = array_values($cluster_set[$i]);

                unset($centeroids[$j]);
                unset($cluster_set[$j]); 
                $centeroids = array_values($centeroids);
                $cluster_set = array_values($cluster_set);

                return self::mergeCenteroids($dataset, $cluster_set, $centeroids, $distance_func, $centeroid_func);
            }
        }
        return array($cluster_set, $centeroids);
    }

    public function number_compare($a, $b)
    {
        if ($a > $b) {
            return 1;
        }
        if ($b > $a) {
            return -1;
        }
        return 0;
    }
}

