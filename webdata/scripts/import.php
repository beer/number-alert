<?php

// wget 'http://ronnywang-ptthot.s3-website-ap-northeast-1.amazonaws.com/ptthot-201601.csv.gz'
// wget 'http://ronnywang-ptthot.s3-website-ap-northeast-1.amazonaws.com/ptthot-201602.csv.gz'
// wget 'http://ronnywang-ptthot.s3-website-ap-northeast-1.amazonaws.com/ptthot-201603.csv.gz'
include(__DIR__ . '/../init.inc.php');
$argv = $_SERVER['argv'];
array_shift($argv);
$map = array();
$insert = array();
foreach ($argv as $f) {
    $fp = gzopen($f, 'r');
    // A-MEI,1451582581,423
    fgetcsv($fp);

    while ($rows = fgetcsv($fp)) {
        if (!array_key_exists($rows[0], $map)) {
            $map[$rows[0]] = NumberSet::getSet('ptt:' . $rows[0])->set_id;
        }

        $insert[] = array($map[$rows[0]], $rows[1], $rows[2]);
        if (count($insert) > 10000) {
            NumberRawRecord::bulkInsert(array('set_id', 'time', 'value'), $insert, array('replace' => true));
            $insert = array();
        }
    }
}
if (count($insert)) {
    NumberRawRecord::bulkInsert(array('set_id', 'time', 'value'), $insert, array('replace' => true));
    $insert = array();
}
