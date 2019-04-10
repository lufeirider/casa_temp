<?php
$cmd = $_GET['cmd'];
$cmd = 'whoami';
function show_name($var)
{
    global $cmd;
    print_r($var);
    system($cmd);
}

show_name($cmd);