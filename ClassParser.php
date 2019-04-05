<?php
/**
 * Created by PhpStorm.
 * User: liuhongpeng
 * Date: 2019/4/3
 * Time: 20:58
 */
require_once 'Parser.php';

class ClassParser extends Parser
{
    //$name => $_GET['name'] , $age = $_GET['age']
    public $func_tained_var;

    public function __construct()
    {
        parent::__construct();
        $this->func_map = array();
        $this->func_tained_var = array();
        $this->func_dangerous = array();
    }
}