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

class MyNodeVisitor extends NodeVisitorAbstract
{

    //被污染的参数
    public $tained_var;
    //能返回tained function
    public $tained_function;
    //potential vul function,潜在的函数
    public $pvf;


    //做一个映射，token位置都对应函数名
    //[0] => echo_network，[1] => echo_network
    public $token_func_map;
    //做一个映射，token位置对应赋值表达式
    //[0] => $name,[1] => $age
    public $token_func_assign_map;
    //流程控制
    //[0] => condition(1==1)，[1] => [0] => condition(1==2)
    public $control_flow_map;


    //用户输入源
    public $user_source;
    //sink函数
    public $sensitive_sink;


    //函数位置（暂时不知道有没有用）
    public $func_position;
    //函数参数
    public $func_args;
    //函数作用域里面的变量
    public $func_var;
    //函数分配污染
    public $func_assign;
    //函数作用域里面被污染的变量
    //$name => $_GET['name'] , $age = $_GET['age']
    public $func_tained_var;

    //输出源代码
    public $prettyPrinter;

    public function __construct()
    {
        $this->control_flow_map = array();

        //做一个映射，token位置都对应函数名
        $this->token_func_map = array();
        //[0] => $name,[1] => $age
        $this->token_func_assign_map = array();

        $this->pvf = array();
        $this->tained_var = array();
        $this->tained_source = array();

        //函数的位置
        $this->func_position = array();
        $this->func_args = array();

        $this->func_assign = array();


        $this->prettyPrinter = new PrettyPrinter\Standard;

        $this->sensitive_sink = array('system'=>array(0),'evil'=>array(0),'shell_exec'=>array(0));
        $this->user_source = array('_GET','_POST','_COOKIE');
    }

    public function enterNode(Node $node) {
        //print_r($node->getSubNodeNames());

//        if ($node instanceof Node\Expr\Variable) {
//            $this->all_var[] = $node->name;
//            //print_r($node);
//        }
//        if ($node instanceof Node\Scalar\String_) {
//            $node->value = 'foo';
//        }


        //print_r($node);



        //////////////////////////////////////////////////////////////流程控制
        if ($node instanceof Node\Stmt\If_){
            //if条件
            $cond = $node->cond;
            $php_cond = $this->prettyPrinter->prettyPrintExpr($cond);

            //做一个映射,$node->stmts为if里面的内容
            for($i=$node->getStartTokenPos();$i<$node->getEndTokenPos()+1;$i++)
            {
                $this->control_flow_map[$i] = $php_cond;
            }
        }


        //////////////////////////////////////////////////////////////赋值语句
        if ($node instanceof Node\Expr\Assign){

            //获取当前行，判断属于哪个作用域里面
            $node_line = $node->getStartTokenPos();
            if(array_key_exists($node_line,$this->token_func_map))
            {
                $in_func_name = $this->token_func_map[$node_line];
                //$node->var->name等号左边的变量
                $assigned_var = $node->var->name;

                //$node->expr->name，等号右边的变量
                //$node->expr->getStartTokenPos token开始的位置
                $this->func_assign[$in_func_name][$assigned_var]['startTokenPos'] = $node->expr->getStartTokenPos();
                $this->func_assign[$in_func_name][$assigned_var]['endTokenPos'] = $node->expr->getEndTokenPos();

                for($i=$node->expr->getStartTokenPos();$i<$node->expr->getEndTokenPos()+1;$i++){
                    $this->token_func_assign_map[$i] = $assigned_var;
                }

            }

        }

        //////////////////////////////////////////////////////////////解析变量
        if ($node instanceof Node\Expr\Variable) {
            //获取变量名,$a = $b,$b的情况
            $cur_name = $node->name;

            $node_token_pos = $node->getStartTokenPos();
            //赋值语句，右边有被污染的变量，左边的变量就算污染，现在寻找到右边的表达式中有被污染变量，则把左边的变量纳入污染变量列表中。
            //判断是否在赋值表达式中内
            if(array_key_exists($node_token_pos,$this->token_func_assign_map)) {
                $in_func_name = $this->token_func_map[$node_token_pos];
                //判断右边的表达式是否包含被tained的变量
                if(array_key_exists($cur_name,$this->func_tained_var[$in_func_name]))
                {
                    $origin_tained_var = $this->func_tained_var[$in_func_name][$cur_name];
                    $this->func_tained_var[$in_func_name][$this->token_func_assign_map[$node_token_pos]] = $origin_tained_var;
                }
            }else{
                //现在仅仅考虑函数外面的变量情况
                if(array_key_exists($node_token_pos,$this->token_func_assign_map))
                {
                    $this->tained_var[] = $this->token_func_assign_map[$node_token_pos];
                }
            }
        }

//        //////////////////////////////////////////////////////////////解析return
//        if ($node instanceof Node\Stmt\Return_){
//            $node_token_pos = $node->getStartTokenPos();
//
//            //
//            if(array_key_exists($node_token_pos,$this->token_func_map))
//            {
//                $in_func_name = $this->token_func_map[$node_token_pos];
//                $return_var_name = $node->expr->name;
//                //$node->expr->name，等号右边的变量
//                if(array_key_exists($return_var_name,$this->func_tained_var[$in_func_name]))
//                {
//                    //$node->var->name等号左边的变量，放到函数污染函数里面
//                    $this->tained_function[] = $in_func_name;
//                }
//            }
//        }
//        //////////////////////////////////////////////////////////////解析函数
        if ($node instanceof Node\Stmt\Function_){
            //获取函数名
            $func_name = $node->name->name;
            //获取函数的范围，暂时没有用到
            $this->func_position[$func_name][0] = $node->getStartTokenPos();
            $this->func_position[$func_name][1] = $node->getEndTokenPos();
            //设置函数token范围
            for($i= $node->getStartTokenPos();$i<$node->getEndTokenPos();$i++)
            {
                $this->token_func_map[$i] = $func_name;
            }
            //获取函数参数，作为污染源
            foreach($node->params as $param){
                $this->func_tained_var[$func_name][$param->var->name] = $param->var->name;
            }
        }


        //////////////////////////////////////////////////////////////函数调用
        if ($node instanceof Node\Expr\FuncCall){
            //判断代码属于哪个作用域
            $node_token_pos = $node->getStartTokenPos();
            if(array_key_exists($node_token_pos,$this->token_func_map))
            {
                $in_func_name = $this->token_func_map[$node_token_pos];
                //这里有个数组，但是这里的[0]是固定的，没有其他的情况，可以放心获取到调用函数名，如果出问题，php-parser背锅
                $call_func_name = $node->name->parts[0];
                //获取被调用函数参数信息
                foreach ($node->args as $index => $arg){
                    //获取到参数的名字
                    $arg_name = $arg->value->name;
                    $call_func_args[$index] = $arg_name;
                }

                //判断调用的函数是否是sink
                if(array_key_exists($call_func_name,$this->sensitive_sink))
                {
                    //获取sink的参数地址
                    $sinked_arg_position = $this->sensitive_sink[$call_func_name];
                    //开始遍历被调用函数的参数
                    foreach ($call_func_args as $index=> $sinked_arg_name)
                    {
                        //判断是否在sink函数的漏洞位置，并且判断参数是否被污染
                        if(in_array($index,$sinked_arg_position) && array_key_exists($sinked_arg_name,$this->func_tained_var[$in_func_name]))
                        {

                            //是否有条件，可能有多层条件，这里就简化，就设置为一个条件，以后要扩展成数据
                            print("#####################################result:\n");
                            print("\n#####################################参数：\n");
                            print($sinked_arg_name);
                            print("\n#####################################污染参数的来源：\n");
                            print($this->func_tained_var[$in_func_name][$sinked_arg_name]);
                            print("\n#####################################条件：\n");
                            if(array_key_exists($node_token_pos,$this->control_flow_map))
                            {
                                print_r($this->control_flow_map[$node_token_pos]);
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

//           print_r($node);
//            print_r($node->args);
        }
    }

}