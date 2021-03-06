<?php
/**
 * Created by PhpStorm.
 * User: liuhongpeng
 * Date: 2019/4/8
 * Time: 13:22
 */
use PhpParser\Node;


/**
 * 从节点中遍历处$name这种
 * @param $object
 * @return array
 */
function recursive_var($node){
    $result = array();
    $sub_result = array();

    foreach($node as $sub_node)
    {
        if($node instanceof Node\Expr\PropertyFetch || $node instanceof Node\Expr\Variable)
        {
            $result[] = $node;
            return $result;
        }


        if(is_object($sub_node))
        {
            $sub_result = recursive_object_var($sub_node);
        }

        if(!empty($sub_node))
        {
            $result = array_merge($result,$sub_result);
        }

    }

    return $result;

}

/**
 * 遍历变量和对象属性，比如$name,$people->name
 * @param $object
 * @return array
 */
function recursive_property_var($object){
    //Expr_PropertyFetch
    $result = array();
    $sub_result = array();


    foreach($object as $sub_object)
    {
        if($object instanceof Node\Expr\PropertyFetch || $object instanceof Node\Expr\Variable)
        {
            $result[] = $object;
            return $result;
        }

        if($sub_object instanceof Node\Expr\PropertyFetch || $object instanceof Node\Expr\Variable)
        {
            $result[] = $sub_object;
            return $result;
        }

        if(is_object($sub_object))
        {
            $sub_result = recursive_property_var($sub_object);
        }

        if(!empty($sub_object))
        {
            $result = array_merge($result,$sub_result);
        }

    }

    return $result;

}

/**
 * 检测数组1是否在数组2的键值里面,如果遇到@@这种$people->name,people@@name,会先检查people@@name，再检查people
 * @param $arg_arr
 * @param $tained_arr
 * @return bool
 */
function check_is_tained($arg_arr,$tained_arr)
{
    foreach($arg_arr as $v)
    {
        $arg_object_arr = explode("@@",$v);

        for($i=count($arg_object_arr);$i>-1;$i--)
        {
            $arg_object_str = join(array_slice($arg_object_arr,0,$i),"@@");
            if(array_key_exists($arg_object_str,$tained_arr))
                return true;
        }
    }
    return false;
}


/**
 * 确定从某个节点取出的各种变量，是否被污染，返回被污染的列表
 * @param $unkown_var_arr
 * @param $tained_arr
 * @return array
 */
function check_var_tained($unkown_var_arr,$tained_arr)
{
    $sure_taine_arr = array();
    foreach($unkown_var_arr as $v)
    {
        $arg_object_arr = explode("@@",$v);

        for($i=count($arg_object_arr);$i>-1;$i--)
        {
            $arg_object_str = join(array_slice($arg_object_arr,0,$i),"@@");
            if(array_key_exists($arg_object_str,$tained_arr))
                $sure_taine_arr[] = $arg_object_str;
        }
    }
    return $sure_taine_arr;

}

/**
 * 解析对象，将$people->lufei->name,解析成people@@lufei@@name
 * @param $object
 * @return string
 */
function parse_object($object)
{
    $object_var_str = "";
    if($object->var instanceof Node\Expr\Variable)
    {
        return $object->var->name ."@@".$object->name->name;
    }

    if($object->var instanceof Node\Expr\PropertyFetch)
    {
        $property_name = "@@".$object->name->name;
        $sub_object_var_str = parse_object($object->var);
    }

    $object_var_str = $object_var_str.$sub_object_var_str.$property_name;

    return $object_var_str;
}