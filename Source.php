<?php
/**
 * Created by PhpStorm.
 * User: liuhongpeng
 * Date: 2019/3/26
 * Time: 10:57
 */

$USER_SOUCRE = array('_GET','_POST','_COOKIE');

class Source
{
    public $user_source;

    public function checkSoure($name){
        $this->user_source = array('_GET','_POST','_COOKIE');
    }
}