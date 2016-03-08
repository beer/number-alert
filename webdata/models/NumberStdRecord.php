<?php

class NumberStdRecord extends Pix_Table
{
    public function init()
    {
        $this->_name = 'number_std_record';
        $this->_primary = array('set_id', 'time');

        $this->_columns['set_id'] = array('type' => 'int');
        $this->_columns['time'] = array('type' => 'int');
        $this->_columns['value'] = array('type' => 'int');
    }
}
