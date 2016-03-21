<?php

class NumberSetRow extends Pix_Table_Row
{
    public function addAlert($time_start, $time_end, $note)
    {
        $neightbor_alerts = array();

        // 找左邊接壤的
        if ($alert = SetAlert::search(array('set_id' => $this->set_id))->search("start_at <= $time_start and $time_start <= end_at")->first()) {
            $neightbor_alerts[$alert->alert_id] = $alert;
        }
        // 找右邊接壤的
        if ($alert = SetAlert::search(array('set_id' => $this->set_id))->search("start_at <= $time_end and $time_end <= end_at")->first()) {
            $neightbor_alerts[$alert->alert_id] = $alert;
        }

        if (count($neightbor_alerts) == 2) { // 左右各接一個，合倂起來
            $neightbor_alerts = array_values($neightbor_alerts);
            $start_at = min($neightbor_alerts[0]->start_at, $neightbor_alerts[1]->start_at, $time_start);
            $end_at = max($neightbor_alerts[0]->end_at, $neightbor_alerts[1]->end_at, $time_end);
            $notes = new StdClass;
            $notes->{'t' . $time_start} = $note;
            foreach (json_decode($neightbor_alerts[0]->note) as $k => $n) {
                $notes->{$k} = $n;
            }
            foreach (json_decode($neightbor_alerts[1]->note) as $k => $n) {
                $notes->{$k} = $n;
            }
            $neightbor_alerts[1]->delete();
            $neightbor_alerts[0]->update(array(
                'start_at' => $start_at,
                'end_at' => $end_at,
                'note' => json_encode($notes),
            ));
            return $neightbor_alerts[0];
        }

        if (count($neightbor_alerts) == 1) { // 左右各接一個，合倂起來
            $alert = array_values($neightbor_alerts)[0];
            $start_at = min($alert->start_at, $time_start);
            $end_at = max($alert->end_at, $time_end);
            $notes = json_decode($alert->note);
            $notes->{'t' . $time_start} = $note;
            $alert->update(array(
                'start_at' => $start_at,
                'end_at' => $end_at,
                'note' => json_encode($notes),
            ));
            return $alert;
        }

        $alert = SetAlert::insert(array(
            'set_id' => $this->set_id,
            'start_at' => $time_start,
            'end_at' => $time_end,
            'note' => json_encode(array('t' . $time_start => $note)),
        ));
        return $alert;
    }

    public function stdNumber()
    {
        $current_std_time = null;
        $values = array();
        $insert = array();
        $total = 0;
        $var_count = 0;
        $prev_time = null;
        $prev_avg = null;
        foreach (NumberRawRecord::search(array('set_id' => $this->set_id))->order('time ASC') as $number) {
            $Ymdh = mktime(date('H', $number->time), 0, 0, date('m', $number->time), date('d', $number->time), date('Y', $number->time));
            if (!is_null($current_std_time) and $current_std_time != $Ymdh) {
                $ways = array_map(function($i) use ($values) { return $values[$i + 1] > $values[$i] ? 1 : 0; }, range(0, count($values) - 2));
                $count_values = array_count_values($ways);
                $note = new StdClass;
                if (count($count_values) == 1) {
                    if (array_keys($count_values)[0] == 0) {
                        $note->way = 'desc';
                    } else {
                        $note->way = 'asc';
                    }
                } else {
                    if (array_search($ways[0], array_slice($ways, array_search(1 - $ways[0], $ways))) === false) {
                        if ($ways[0] == 1) {
                            $note->way = 'top';
                        } else {
                            $note->way = 'bottom';
                        }
                    } else {
                        $note->way = '-';
                    }
                }
                $note->max = intval(max($values));
                $note->min = intval(min($values));
                $note->var = MathLib::getVar($values);
                $avg = floor(array_sum($values) / count($values));
                if (!is_null($prev_time) and $current_std_time - $prev_time == 3600) {
                    $note->diff = $avg - $prev_avg;
                }

                $prev_time = $current_std_time;
                $prev_avg = $avg;
                $total ++;
                if ($note->var > 0.1) {
                    $var_count ++;
                }

                $insert[] = array(
                    $this->set_id,
                    $current_std_time,
                    $avg,
                    json_encode($note),
                );
                $values = array();
            }
            $current_std_time = $Ymdh;
            $values[] = $number->value;
        }
        if ($insert) {
            NumberStdRecord::bulkInsert(array('set_id', 'time', 'value', 'note'), $insert, array('replace' => true));
        }
        return array(
            'total' => $total,
            'var_count' => $var_count,
        );
    }
}

class NumberSet extends Pix_Table
{
    public function init()
    {
        $this->_name = 'number_set';
        $this->_primary = array('set_id');
        $this->_rowClass = 'NumberSetRow';

        $this->_columns['set_id'] = array('type' => 'int', 'auto_increment' => true);
        $this->_columns['name'] = array('type' => 'varchar', 'size' => 32);

        $this->addIndex('name', array('name'), 'unique');
    }

    public static function getSet($name)
    {
        if (!$set = NumberSet::find_by_name($name)) {
            $set = NumberSet::insert(array(
                'name' => $name,
            ));
        }
        return $set;
    }
}
