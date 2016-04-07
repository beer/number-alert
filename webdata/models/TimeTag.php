<?php

class TimeTag extends Pix_Table
{
    public function init()
    {
        $this->_name = 'time_tag';
        $this->_primary = array('tag_id');

        $this->_columns['tag_id'] = array('type' => 'int', 'auto_increment' => true);
        // 1 ymd
        $this->_columns['group'] = array('type' => 'int');
        $this->_columns['time'] = array('type' => 'int');
        $this->_columns['tag'] = array('type' => 'varchar', 'size' => 255);

        $this->addIndex('group_time', array('group', 'time'));
    }

    public function findTagsByTimes($group, $times)
    {
        $ret = new StdClass;
        if (!$times) {
            return $ret;
        }
        $records = TimeTag::search(array('group' => intval($group)))->searchIn('time', $times);
        foreach ($records as $record) {
            if (!property_exists($ret, $record->time)) {
                $ret->{$record->time} = array();
            }
            $ret->{$record->time}[] = $record->tag;
        }
        return $ret;
    }
}
