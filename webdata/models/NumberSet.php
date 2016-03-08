<?php

class NumberSet extends Pix_Table
{
    public function init()
    {
        $this->_name = 'number_set';
        $this->_primary = array('set_id');

        $this->_columns['set_id'] = array('type' => 'int', 'auto_increment' => true);
        $this->_columns['name'] = array('type' => 'varchar', 'size' => 32);

        $this->addIndex('name', array('name'), 'unique');
    }
}
