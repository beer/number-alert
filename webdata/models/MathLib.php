<?php

class MathLib
{
    public static function getVar($numbers)
    {
        $avg = array_sum($numbers) / count($numbers);
        $std = pow(array_sum(array_map(function($n) use ($avg) { return pow($avg - $n, 2); }, $numbers)) / count($numbers), 0.5);
        return $std / $avg;
    }
}

