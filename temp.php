<?php
function filter_evil($str)
{
    return $str;
}

function echo_network($cmd)
{
    $temp = filter_evil($cmd);
    system($temp);
}