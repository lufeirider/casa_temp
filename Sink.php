<?php
/**
 * Created by PhpStorm.
 * User: liuhongpeng
 * Date: 2019/3/26
 * Time: 11:19
 */

$SENSITIVE_SINK = array('system','evil','shell_exec');

class Sink
{
    public $sensitive_sink;
    public function __construct()
    {
        $this->sensitive_sink = array('system','evil','shell_exec');
    }
}