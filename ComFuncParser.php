<?php
/**
 * Created by PhpStorm.
 * User: liuhongpeng
 * Date: 2019/4/4
 * Time: 18:44
 */
require_once 'Parser.php';

class ComFuncParser extends Parser
{
    //被污染的参数,$name => $_GET['name'] , $age = $_GET['age']
    public $func_tained_var;

    function __construct()
    {
        parent::__construct();
        $this->func_tained_var = array();
    }
}