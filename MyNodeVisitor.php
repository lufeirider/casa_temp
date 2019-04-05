<?php
/**
 * Created by PhpStorm.
 * User: liuhongpeng
 * Date: 2019/3/26
 * Time: 10:19
 */
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter;
require_once 'ComFuncParser.php';
require_once 'ClassParser.php';
require_once 'Info.php';

class MyNodeVisitor extends NodeVisitorAbstract
{
    //////////////////////////////
    /// 配置文件
    //用户输入源
    public $user_source;
    //sink函数
    public $sink;
    //////////////////////////////


    //////////////////////////////
    /// 映射
    public $class_map;
    public $class_func_map;
    public $func_map;
    public $assing_map;
    public $condition_map;
    //////////////////////////////

    //输出源代码
    public $prettyPrinter;

    //全局函数解析
    public $com_func_parser;
    //类解析
    public $class_parser;


    public function __construct()
    {
        $this->class_map = array();
        $this->class_func_map = array();
        $this->func_map = array();
        $this->assing_map = array();
        $this->condition_map = array();

        $this->com_func_parser = new ComFuncParser();
        $this->class_parser = new ClassParser();

        $this->info = new Info();

        $this->prettyPrinter = new PrettyPrinter\Standard;

        $this->sink = array('system'=>array(0),'evil'=>array(0),'shell_exec'=>array(0));
        $this->user_source = array('_GET','_POST','_COOKIE');
    }

    public function enterNode(Node $node) {

        //print_r($node);



        //////////////////////////////////////////////////////////////流程控制
        if ($node instanceof Node\Stmt\If_){
            //if条件
            $cond = $node->cond;
            $php_cond = $this->prettyPrinter->prettyPrintExpr($cond);

            //做一个映射,$node->stmts为if里面的内容
            for($i=$node->getStartTokenPos();$i<$node->getEndTokenPos()+1;$i++)
            {
                $this->condition_map[$i] = $php_cond;
            }
        }


        //////////////////////////////////////////////////////////////赋值语句
        if ($node instanceof Node\Expr\Assign){
            //$node->var->name等号左边的变量
            $assigned_var = $node->var->name;

            //赋值语句映射, token position => assign expr
            for($i=$node->expr->getStartTokenPos();$i<$node->expr->getEndTokenPos()+1;$i++){
                $this->assing_map[$i] = $assigned_var;
            }
        }


        //////////////////////////////////////////////////////////////解析函数
        if ($node instanceof Node\Stmt\Function_){
            //获取函数名
            $func_name = $node->name->name;

            //设置函数token范围
            for($i= $node->getStartTokenPos();$i<$node->getEndTokenPos()+1;$i++)
            {
                $this->func_map[$i] = $func_name;
            }
            //获取函数参数，作为污染源
            foreach($node->params as $param){
                $this->com_func_parser->func_tained_var[$func_name][$param->var->name] = $param->var->name;
            }
        }


        //////////////////////////////////////////////////////////////解析变量
        if ($node instanceof Node\Expr\Variable) {
            //获取变量名,$a = $b,$b的情况
            $cur_name = $node->name;

            $node_token_pos = $node->getStartTokenPos();
            //赋值语句，右边有被污染的变量，左边的变量就算污染，现在寻找到右边的表达式中有被污染变量，则把左边的变量纳入污染变量列表中。
            //判断是否在赋值表达式中内
            if(array_key_exists($node_token_pos,$this->assing_map) && array_key_exists($node_token_pos,$this->func_map)) {
                $in_func_name = $this->func_map[$node_token_pos];
                //判断右边的表达式是否包含被tained的变量
                if(array_key_exists($cur_name,$this->com_func_parser->func_tained_var[$in_func_name]))
                {
                    $origin_tained_var = $this->com_func_parser->func_tained_var[$in_func_name][$cur_name];
                    //$this->com_func_parser->func_assign_map,这个赋值语句的token映射，$this->com_func_parser->func_assign_map[$node_token_pos],获取$a的名字
                    $this->com_func_parser->func_tained_var[$in_func_name][$this->assing_map[$node_token_pos]] = $origin_tained_var;
                }
            }else{
                //
            }
        }


        //////////////////////////////////////////////////////////////类解析
        if ($node instanceof Node\Stmt\Class_){
            //获取类的名字
            $class_name = $node->name->name;

            //设置函数token范围
            for($i = $node->getStartTokenPos();$i<$node->getEndTokenPos() + 1;$i++)
            {
                $this->class_map[$i] = $class_name;
            }

        }

        //////////////////////////////////////////////////////////////类方法
        if ($node instanceof Node\Stmt\ClassMethod){

            $func_name = $node->name->name;
            //设置函数token范围
            for($i = $node->getStartTokenPos();$i<$node->getEndTokenPos() + 1;$i++)
            {
                $this->class_func_map[$i] = $func_name;
            }

            //获取函数参数，作为污染源
            foreach($node->params as $param){
                $this->class_parser->func_tained_var[$func_name][$param->var->name] = $param->var->name;
            }

            //待做
            //分析属性是否被类方法改变，或者被外部赋值了
        }

        //////////////////////////////////////////////////////////////对象方法调用
        if ($node instanceof Node\Expr\MethodCall){

            //先暂时不管对象是什么，只要使用相同的方法就算
        }

        //////////////////////////////////////////////////////////////函数调用
        if ($node instanceof Node\Expr\FuncCall){
            //判断代码属于哪个作用域
            $node_token_pos = $node->getStartTokenPos();
            //这里有个数组，但是这里的[0]是固定的，没有其他的情况，可以放心获取到调用函数名，如果出问题，php-parser背锅
            $call_func_name = $node->name->parts[0];

            //解析在类函数
            if(array_key_exists($node_token_pos,$this->class_func_map))
            {
                $in_func_name = $this->class_func_map[$node_token_pos];
                //获取被调用函数参数信息
                foreach ($node->args as $index => $arg){
                    //获取到参数的名字
                    $arg_name = $arg->value->name;
                    $call_func_args[$index] = $arg_name;
                }

                //判断调用的函数是否是sink
                if(array_key_exists($call_func_name,$this->sink))
                {
                    //获取sink的参数地址
                    $sinked_arg_position = $this->sink[$call_func_name];
                    //开始遍历被调用函数的参数
                    foreach ($call_func_args as $index=> $sinked_arg_name)
                    {
                        //判断是否在sink函数的漏洞位置，并且判断参数是否被污染
                        if(in_array($index,$sinked_arg_position) && array_key_exists($sinked_arg_name,$this->class_parser->func_tained_var[$in_func_name]))
                        {
                            //是否有条件，可能有多层条件，这里就简化，就设置为一个条件，以后要扩展成数据
                            print("#####################################result:\n");
                            print("#####################################危险函数：\n");
                            print($call_func_name);
                            print("\n#####################################污染参数的来源：\n");
                            print($this->class_parser->func_tained_var[$in_func_name][$sinked_arg_name]);
                            print("\n#####################################条件：\n");
                            if(array_key_exists($node_token_pos,$this->condition_map))
                            {
                                print_r($this->condition_map[$node_token_pos]);
                            }
                            print("\n#####################################函数：\n");
                            print($in_func_name);
                            print("\n#####################################\n");
                        }
                    }
                }
            }

            //解析公共函数
            if(array_key_exists($node_token_pos,$this->func_map))
            {
                $in_func_name = $this->func_map[$node_token_pos];
                //这里有个数组，但是这里的[0]是固定的，没有其他的情况，可以放心获取到调用函数名，如果出问题，php-parser背锅
                $call_func_name = $node->name->parts[0];
                //获取被调用函数参数信息
                foreach ($node->args as $index => $arg){
                    //获取到参数的名字
                    $arg_name = $arg->value->name;
                    $call_func_args[$index] = $arg_name;
                }

                //判断调用的函数是否是sink
                if(array_key_exists($call_func_name,$this->sink))
                {
                    //获取sink的参数地址
                    $sinked_arg_position = $this->sink[$call_func_name];
                    //开始遍历被调用函数的参数
                    foreach ($call_func_args as $index=> $sinked_arg_name)
                    {
                        //判断是否在sink函数的漏洞位置，并且判断参数是否被污染
                        if(in_array($index,$sinked_arg_position) && array_key_exists($sinked_arg_name,$this->com_func_parser->func_tained_var[$in_func_name]))
                        {

                            //是否有条件，可能有多层条件，这里就简化，就设置为一个条件，以后要扩展成数据
                            print("#####################################result:\n");
                            print("#####################################危险函数：\n");
                            print($call_func_name);
                            print("\n#####################################污染参数的来源：\n");
                            print($this->com_func_parser->func_tained_var[$in_func_name][$sinked_arg_name]);
                            print("\n#####################################条件：\n");
                            if(array_key_exists($node_token_pos,$this->condition_map))
                            {
                                print_r($this->condition_map[$node_token_pos]);
                            }
                            print("\n#####################################函数：\n");
                            print($in_func_name);
                            print("\n#####################################");

                            //添加到pvf中
                            $this->pvf[] = $in_func_name;
                        }
                    }
                }else{

                }

            }

        }
    }

}