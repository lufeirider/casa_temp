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

    //class_name => 魔术操作，people => array(toString,get)
    public $start_class;

    //class_name => 魔术操作，people['before'] => array(toString,get),people['after'] => array(toString,get)
    public $between_class;

    //class_name => 魔术方法，people => array(get)
    public $target_class;

    public function __construct()
    {
        parent::__construct();
        $this->func_tained_var = array();
        $this->start_class = array();
        $this->between_class = array();
        $this->target_class = array();
    }
}