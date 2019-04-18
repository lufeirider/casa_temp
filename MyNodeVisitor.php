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
require_once 'CommonFunction.php';

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
    //node_position => func ,只不过这个函数在类中
    public $class_func_map;
    //position => classname_magicmethod
    public $class_magic_method_map;
    public $func_map;
    public $assing_map;
    public $condition_map;
    //////////////////////////////

    //////////////////////////////
    /// 分析信息
    //可能存在漏洞的函数potential vul function
    public $tained_var;
    public $pvf;
    //people_command,类名_函数名
    public $class_pvf;
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

        $this->tained_var = array();
        $this->pvf = array();
        $this->class_pvf = array();
        $this->class_magic_method_map = array();

        $this->com_func_parser = new ComFuncParser();
        $this->class_parser = new ClassParser();

        $this->info = new Info();

        $this->prettyPrinter = new PrettyPrinter\Standard;

        $this->sink = array('system'=>array(0),'evil'=>array(0),'shell_exec'=>array(0));
        $this->user_source = array('_GET','_POST','_COOKIE');

        $this->magic_function = array("__construct","__destruct","__call","__callStatic","__get","__set","__isset","__unset","__invoke","__toString");

        //设置污染源
        foreach($this->user_source as $source)
        {
            $this->tained_var[$source] = $source;
        }

    }

    public function enterNode(Node $node) {

//        print_r($node);
//        return;


        /**
         * if流程控制
         */
        if ($node instanceof Node\Stmt\If_){
            //ast,if条件节点
            $cond = $node->cond;
            //获取php原语句
            $php_cond = $this->prettyPrinter->prettyPrintExpr($cond);

            //做一个映射,$node->stmts为if里面的内容
            for($i=$node->getStartTokenPos();$i<$node->getEndTokenPos()+1;$i++)
            {
                $this->condition_map[$i] = $php_cond;
            }
        }

        /**
         * 字符串连接
         */
        if ($node instanceof Node\Expr\BinaryOp\Concat)
        {
            $node_position = $node->getStartTokenPos();
            $property_var_arr = recursive_property_var($node);

            foreach($property_var_arr as $property_var)
            {
                $cur_var = array();
                //$var->name,就是变量名，$people
                if($property_var instanceof Node\Expr\PropertyFetch)
                {
                    //$people->admin->name，parse_object是解析这种多层调用
                    $cur_var[] = parse_object($property_var);
                }
                else if($property_var instanceof Node\Expr\Variable)
                {
                    //基本变量
                    $cur_var[] = $property_var->name;
                }

                if(!empty($cur_var))
                {
                    if(array_key_exists($node_position,$this->class_magic_method_map))
                    {
                        $in_class_name = $this->get_in_class($node);
                        $in_func_name = $this->get_in_class_func($node);
                        $in_class_func_name = $in_class_name."@@".$in_func_name;
                        //确定从某个节点取出的各种变量，是否被污染，返回被污染的列表
                        $tained_var_arr = check_var_tained($cur_var,$this->class_parser->func_tained_var[$in_class_func_name]);
                        foreach ($tained_var_arr as $tained_var)
                        {
                            //判断污染源是否来源于this
                            if($this->class_parser->func_tained_var[$in_class_func_name][$tained_var] == 'this')
                            {
                                $this->class_parser->between_class[$in_class_name]['before'] = $in_func_name;
                                $this->class_parser->between_class[$in_class_name]['after'] = '__toString';
                            }

                            if($in_func_name == "__destruct")
                            {
                                $this->class_parser->start_class[$in_class_name] = '__toString';
                            }
                        }

                    }
                }


            }
        }


        if ($node instanceof Node\Expr\PropertyFetch)
        {
            $node_position = $node->getStartTokenPos();

            if(array_key_exists($node_position,$this->class_magic_method_map))
            {
                $in_class_name = $this->get_in_class($node);
                $in_func_name = $this->get_in_class_func($node);

                $property = parse_object($node);
                if(substr_count($property,"@@")>1)
                {
                    $this->class_parser->between_class[$in_class_name]['before'] = $in_func_name;
                    $this->class_parser->between_class[$in_class_name]['after'] = '__get';

                    if($in_func_name == "__destruct")
                    {
                        $this->class_parser->start_class[] = $in_class_name;
                    }
                }
            }
        }

        /**
         * 赋值解析
         * 解析左边的变量以及做赋值语句的映射
         */
        if ($node instanceof Node\Expr\Assign){
            //获取左边变量名,$a = $b,$a的情况

            $left_var_arr = recursive_property_var($node);
            /**
             * $a = $b,解析变量$a的情况
             */
            foreach($left_var_arr as $unkown_var)
            {
                if($unkown_var instanceof Node\Expr\PropertyFetch)
                {
                    //$people->admin->name，parse_object是解析这种多层调用
                    $arg = parse_object($unkown_var);
                    $left_var = $arg;
                }
                else if($unkown_var instanceof Node\Expr\Variable)
                {
                    //基本变量、或者$a[0]
                    $left_var = $unkown_var->name;
                }
            }

            //赋值语句映射, token position => assign expr
            for($i=$node->expr->getStartTokenPos();$i<$node->expr->getEndTokenPos()+1;$i++){
                $this->assing_map[$i] = $left_var;
            }
        }


        /**
         * 公共函数解析
         * 公共函数映射，函数参数作为污染源
         */
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


        /**
         * 变量解析
         * 主要分析左边的变量是否被污染
         */
        if ($node instanceof Node\Expr\Variable) {
            //获取右边变量名,$a = $b,$b的情况
            $right_var = $node->name;

            $node_position = $node->getStartTokenPos();
            //赋值语句，右边有被污染的变量，左边的变量就算污染，现在寻找到右边的表达式中有被污染变量，则把左边的变量纳入污染变量列表中。
            /**
             * 公共函数中
             */
            if(array_key_exists($node_position,$this->assing_map) && array_key_exists($node_position,$this->func_map)) {
                $in_func_name = $this->func_map[$node_position];
                //判断右边的表达式是否包含被tained的变量
                if(array_key_exists($right_var,$this->com_func_parser->func_tained_var[$in_func_name]))
                {
                    $origin_tained_var = $this->com_func_parser->func_tained_var[$in_func_name][$right_var];
                    //$this->assing_map,赋值语句的token映射，$this->assing_map[$node_position],获取$a赋值语句左边名字
                    $this->com_func_parser->func_tained_var[$in_func_name][$this->assing_map[$node_position]] = $origin_tained_var;
                }
            }
            /**
             * 类函数中
             */
            else if(array_key_exists($node_position,$this->assing_map) && array_key_exists($node_position,$this->class_func_map)){
                $in_class_name = $this->get_in_class($node_position);
                $in_func_name = $this->class_func_map[$node_position];
                $in_class_func_name = $in_class_name."@@".$in_func_name;
                //判断右边的表达式是否包含被tained的变量
                if(array_key_exists($right_var,$this->class_parser->func_tained_var[$in_class_func_name]))
                {
                    $origin_tained_var = $this->class_parser->func_tained_var[$in_class_func_name][$right_var];
                    //$this->assing_map,赋值语句的token映射，$this->assing_map[$node_position],获取$a赋值语句左边名字
                    $this->class_parser->func_tained_var[$in_class_func_name][$this->assing_map[$node_position]] = $origin_tained_var;
                }
            }
            /**
             * 流程中
             */
            else{
                //分析污染源来自设置的污染源，$a = $_GET['aa']情况
                if(array_key_exists($node_position,$this->assing_map) && in_array($right_var,$this->user_source)){
                    $this->tained_var[$right_var] = $right_var;
                    $this->tained_var[$this->assing_map[$node_position]] = $right_var;
                //分析出来的污染源来自分析结果，$a = $b情况
                }else if(array_key_exists($node_position,$this->assing_map) && array_key_exists($right_var,$this->tained_var)){
                    $origin_tained_var = $this->tained_var[$right_var];
                    $this->tained_var[$this->assing_map[$node_position]] = $origin_tained_var;
                }
            }
        }


        /**
         * 类解析
         */
        if ($node instanceof Node\Stmt\Class_){
            //获取类的名字
            $class_name = $node->name->name;

            //设置函数token范围
            for($i = $node->getStartTokenPos();$i<$node->getEndTokenPos() + 1;$i++)
            {
                $this->class_map[$i] = $class_name;
            }

        }


        /**
         * 类方法解析
         * 类方法映射，把this已经类方法的参数作为污染源
         */
        if ($node instanceof Node\Stmt\ClassMethod){


            $in_func_name = $node->name->name;
            $in_class_name = $this->get_in_class($node);
            $in_class_func_name = $in_class_name."@@".$in_func_name;

            //设置函数token范围
            for($i = $node->getStartTokenPos();$i<$node->getEndTokenPos() + 1;$i++)
            {
                $this->class_func_map[$i] = $in_func_name;
            }

            if(in_array($in_func_name,$this->magic_function))
            {
                for($i = $node->getStartTokenPos();$i<$node->getEndTokenPos() + 1;$i++)
                {
                    $this->class_magic_method_map[$i] = $in_class_func_name;
                }

            }

            //获取函数参数，作为污染源
            foreach($node->params as $param){
                $this->class_parser->func_tained_var[$in_class_func_name][$param->var->name] = $param->var->name;
            }
            $this->class_parser->func_tained_var[$in_class_func_name]['this'] = 'this';

        }


        /**
         * 调用对象方法，$lufei->eat_meat()
         */
        if ($node instanceof Node\Expr\MethodCall){
            //先暂时不管对象是什么，只要使用相同的方法就算,现在也没有办法，现在php是动态语言。

            $is_tained = false;
            //判断代码属于哪个作用域
            $node_position = $node->getStartTokenPos();
            //调用的函数名
            $called_func_name = $node->name->name;
            $call_func_args = array();

            /**
             * 获取被调用函数参数信息
             */
            foreach ($node->args as $index => $arg){
                //func($this->people.$this->animal.$xxxxx."xxxxxxx")，这里是一个表达式，recursive_object递归获取里面所有的对象和变量
                $object_var_arr = recursive_property_var($arg);

                //函数参数可能是表达式，所以有多个值，对象或者变量进行分析，然后搞成参数数组
                foreach ($object_var_arr as $object_var)
                {
                    if($object_var instanceof Node\Expr\PropertyFetch)
                    {
                        //$people->admin->name，parse_object是解析这种多层调用
                        $arg = parse_object($object_var);
                        $call_func_args[$index][] = $arg;
                    }
                    else if($object_var instanceof Node\Expr\Variable)
                    {
                        //基本变量
                        $call_func_args[$index][] = $object_var->name;
                    }
                }
            }

            //解析在类函数,判断调用的函数是否是sink
            if(array_key_exists($node_position,$this->class_func_map))
            {

                $in_class_name = $this->get_in_class($node);
                $in_func_name = $this->class_func_map[$node_position];
                $in_class_func_name = $in_class_name."@@".$in_func_name;

                if(array_key_exists($called_func_name,$this->class_pvf))
                {
                    //获取sink的参数地址
                    $sinked_arg_position = $this->class_pvf[$called_func_name];
                    //开始遍历被调用函数的参数
                    foreach ($call_func_args as $index=> $sinked_arg_name)
                    {
                        $is_tained = true;
                        //判断是否在sink函数的漏洞位置，并且判断参数是否被污染
                        if(in_array($index,$sinked_arg_position) && check_is_tained($sinked_arg_name,$this->class_parser->func_tained_var[$in_class_func_name]))
                        {
                            //是否有条件，可能有多层条件，这里就简化，就设置为一个条件，以后要扩展成数据
                            $this->output_result($node,$in_func_name);

                            $this->class_pvf[$in_func_name][] = $index;
                        }
                    }

                    if($is_tained && in_array($in_func_name,$this->magic_function))
                    {
                        $this->class_parser->target_class[$in_class_name][] = $in_func_name;
                    }
                }

            }
            /**
             * 在流程中
             */
            else
            {
                if(array_key_exists($called_func_name,$this->class_pvf))
                {
                    //获取sink的参数地址
                    $sinked_arg_position = $this->class_pvf[$called_func_name];
                    //开始遍历被调用函数的参数
                    foreach ($call_func_args as $index=> $sinked_arg_name)
                    {
                        //判断是否在sink函数的漏洞位置，并且判断参数是否被污染
                        if(in_array($index,$sinked_arg_position) && check_is_tained($sinked_arg_name,$this->tained_var))
                        {
                            //是否有条件，可能有多层条件，这里就简化，就设置为一个条件，以后要扩展成数据
                            $this->output_result($node,'');
                        }
                    }
                }
            }
        }


        /**
         * 调用公共函数或者系统函数
         */
        if ($node instanceof Node\Expr\FuncCall){
            //判断代码属于哪个作用域
            $node_position = $node->getStartTokenPos();
            //这里有个数组，但是这里的[0]是固定的，没有其他的情况，可以放心获取到调用函数名，如果出问题，php-parser背锅
            $called_func_name = $node->name->parts[0];
            $call_func_args = array();

            /**
             * 获取被调用函数参数信息
             */
            foreach ($node->args as $index => $arg){

                //func($this->people.$this->animal.$xxxxx."xxxxxxx")，这里是一个表达式，recursive_object递归获取里面所有的对象和变量
                $object_var_arr = recursive_property_var($arg);

                //函数参数可能是表达式，所以有多个值，对象或者变量进行分析，然后搞成参数数组
                foreach ($object_var_arr as $object_var)
                {
                    if($object_var instanceof Node\Expr\PropertyFetch)
                    {
                        //$people->admin->name，parse_object是解析这种多层调用
                        $arg = parse_object($object_var);
                        $call_func_args[$index][] = $arg;
                    }
                    else if($object_var instanceof Node\Expr\Variable)
                    {
                        //基本变量
                        $call_func_args[$index][] = $object_var->name;
                    }
                }

            }

            /**
             * 在类函数中,判断调用的函数是否是危险
             */
            if(array_key_exists($node_position,$this->class_func_map))
            {
                $is_tained = false;
                $in_class_name = $this->get_in_class($node);
                $in_func_name = $this->get_in_class_func($node);
                $in_class_func_name = $in_class_name."@@".$in_func_name;

                /**
                 * 检测被调用函数是不是危险的php函数
                 */
                if(array_key_exists($called_func_name,$this->sink))
                {
                    //获取sink的参数地址
                    $sinked_arg_position = $this->sink[$called_func_name];
                    //开始遍历被调用函数的参数
                    foreach ($call_func_args as $index=> $sinked_arg_name)
                    {
                        //判断是否在sink函数的漏洞位置，并且判断参数是否被污染，这里还只要是类的属性，或者通过类赋值过来的都算。
                        //check_is_tained($sinked_arg_name,$this->class_parser->func_tained_var[$in_func_name]) 判断函数参数的数组是否存在被污染的数组里面
                        if(in_array($index,$sinked_arg_position) && check_is_tained($sinked_arg_name,$this->class_parser->func_tained_var[$in_class_func_name]) )
                        {
                            //是否有条件，可能有多层条件，这里就简化，就设置为一个条件，以后要扩展成数据
                            $this->output_result($node,$in_func_name);

                            $this->class_pvf[$in_func_name][] = $index;
                        }
                    }
                }
                /**
                 * 检测被调用函数是不是危险的类方法
                 */
                else if(array_key_exists($called_func_name,$this->class_pvf))
                {
                    //获取sink的参数地址
                    $sinked_arg_position = $this->class_pvf[$called_func_name];
                    //开始遍历被调用函数的参数
                    foreach ($call_func_args as $index=> $sinked_arg_name)
                    {
                        //判断是否在sink函数的漏洞位置，并且判断参数是否被污染
                        if(in_array($index,$sinked_arg_position) && check_is_tained($sinked_arg_name,$this->class_parser->func_tained_var[$in_class_func_name]))
                        {
                            $is_tained = true;
                            //是否有条件，可能有多层条件，这里就简化，就设置为一个条件，以后要扩展成数据
                            $this->output_result($node,$in_func_name);

                            $this->class_pvf[$in_func_name][] = $index;

                        }
                    }

                    if($is_tained && in_array($in_func_name,$this->magic_function))
                    {
                        $this->class_parser->target_class[$in_class_name][] = $in_func_name;
                    }
                }

            }
            /**
             * 在公共函数中,判断调用的函数是否是危险
             */
            elseif(array_key_exists($node_position,$this->func_map) )
            {
                $in_func_name = $this->get_in_func($node);

                /**
                 * 检测被调用函数是否是php的危险函数
                 */
                if(array_key_exists($called_func_name,$this->sink))
                {
                    //获取sink的参数地址
                    $sinked_arg_position = $this->sink[$called_func_name];
                    //开始遍历被调用函数的参数
                    foreach ($call_func_args as $index=> $sinked_arg_name)
                    {
                        //判断是否在sink函数的漏洞位置，并且判断参数是否被污染
                        if(in_array($index,$sinked_arg_position) && check_is_tained($sinked_arg_name,$this->com_func_parser->func_tained_var[$in_func_name]))
                        {

                            //是否有条件，可能有多层条件，这里就简化，就设置为一个条件，以后要扩展成数据
                            $this->output_result($node,$in_func_name);

                            //添加到pvf中
                            $this->pvf[$in_func_name][] = $index;
                        }
                    }
                }
                /**
                 * 检测被调用函数是否是危险的公共函数
                 */
                else if(array_key_exists($called_func_name,$this->pvf))
                {
                    //获取sink的参数地址
                    $sinked_arg_position = $this->pvf[$called_func_name];
                    foreach ($call_func_args as $index=> $sinked_arg_name)
                    {
                        //判断是否在sink函数的漏洞位置，并且判断参数是否被污染
                        if(in_array($index,$sinked_arg_position) && check_is_tained($sinked_arg_name,$this->com_func_parser->func_tained_var[$in_func_name]))
                        {
                            //是否有条件，可能有多层条件，这里就简化，就设置为一个条件，以后要扩展成数据
                            $this->output_result($node,$in_func_name);

                            //添加到pvf中
                            $this->pvf[$in_func_name][] = $index;
                        }
                    }
                }
            }
            /**
             * 在流程中
             */
            else
            {
                /**
                 * 检测被调用的函数是否是php的危险函数
                 */
                if(array_key_exists($called_func_name,$this->sink)) {
                    //获取sink的参数地址
                    $sinked_arg_position = $this->sink[$called_func_name];
                    //开始遍历被调用函数的参数
                    foreach ($call_func_args as $index => $sinked_arg_name) {
                        //判断是否在sink函数的漏洞位置，并且判断参数是否被污染
                        if (in_array($index, $sinked_arg_position) && check_is_tained($sinked_arg_name, $this->tained_var)) {
                            $this->output_result($node,'');
                        }
                    }
                }
                /**
                 * 检测被调用的函数是否是危险的公共函数
                 */
                else if(array_key_exists($called_func_name,$this->pvf))
                {
                    //获取sink的参数地址
                    $sinked_arg_position = $this->pvf[$called_func_name];

                    foreach ($call_func_args as $index => $sinked_arg_name) {
                        //判断是否在sink函数的漏洞位置，并且判断参数是否被污染
                        if (in_array($index, $sinked_arg_position) && check_is_tained($sinked_arg_name, $this->tained_var)) {
                            //打印匹配结果
                            $this->output_result($node,'');
                        }
                    }
                }
            }
        }
    }

    /**
     * @param $node_position
     * @return mixed|string
     * 获取node所在的公共函数，如果没有结果返回空字符
     */
    public function get_in_func($node)
    {
        $func_name = "";
        $node_position = $node->getStartTokenPos();
        if(array_key_exists($node_position,$this->func_map))
        {
            $func_name = $this->func_map[$node_position];
        }
        return $func_name;
    }

    /**
     * @param $node_position
     * @return mixed|string
     * 获取node所在的类，如果没有结果返回空字符
     */
    public function get_in_class($node)
    {
        $class_name = "";
        $node_position = $node->getStartTokenPos();
        if(array_key_exists($node_position,$this->class_map))
        {
            $class_name = $this->class_map[$node_position];
        }
        return $class_name;
    }

    /**
     * @param $node_position
     * @return string
     * 获取node所在的类函数，如果没有结果返回空字符
     */
    public function get_in_class_func($node)
    {
        $func_name = "";
        $node_position = $node->getStartTokenPos();
        if(array_key_exists($node_position,$this->class_func_map))
        {
            $func_name = $this->class_func_map[$node_position];
        }
        return $func_name;
    }


    public function output_result($node,$in_func_name)
    {
        $node_position = $node->getStartTokenPos();
        //是否有条件，可能有多层条件，这里就简化，就设置为一个条件，以后要扩展成数据
        print("\n#####################################\n");
        print("条件：\n");
        if($node_position!='' && array_key_exists($node_position,$this->condition_map))
        {
            print_r($this->condition_map[$node_position]);
        }
        print("节点：\n");
        print_r($in_func_name."############".$this->prettyPrinter->prettyPrintExpr($node));
        print("\n#####################################\n");
    }

    public function recur_pop_chain($start_method,$between_class,$target_method)
    {
        $pop_chain = array();
        $sub_result = array();
        foreach ($between_class as $class_name => $class_method)
        {
            if($class_method['after'] == $target_method && $class_method['before'] == $start_method)
            {
                $pop_chain[] = $class_method['before']."@@".$class_name."@@".$class_method['after'];
                return $pop_chain;
            }

            if($class_method['after'] == $target_method )
            {
                $sub_after_method = $class_method['before'];
                $sub_result = $this->recur_pop_chain($start_method,$between_class,$sub_after_method);
            }

            $pop_chain = array_merge($pop_chain,$sub_result);
        }

        return $pop_chain;


    }

    public function afterTraverse(array $nodes)
    {
        foreach ($this->class_parser->start_class as $start_class => $start_class_method)
        {
            foreach ($this->class_parser->target_class as $target_class => $target_class_method_arr)
            {
                //这里target可能有多个危险魔术方法
                foreach ($target_class_method_arr as $target_class_method)
                {
                    $pop_chain_arr = $this->recur_pop_chain($start_class_method,$this->class_parser->between_class,$target_class_method);

                    $pop_chain = "";
                    foreach ($pop_chain_arr as $pop)
                    {
                        $pop_chain.= $pop;
                    }
                    print("\n#####################################\n");
                    print("pop chain:\n");
                    print($start_class."@@".$start_class_method."||".$pop_chain."||".$target_class_method[0]."@@".$target_class);
                    print("\n#####################################\n");
                }

            }
        }
    }
}