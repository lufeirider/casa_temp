<?php
//最简单文件检测
$cmd = $_GET['cmd'];
function echo_network($ip)
{
    system($ip);
}
echo_network($cmd);
?>


<?php
// 最简单的对象调用危险函数
class People{
    public $name;

    public function show_name($name){
        system($name);
    }
}
$cmd = $_GET['cmd'];
//$cmd = "whoami";
$people = new People();
$people->show_name($cmd)
?>


<?php
$a = $_GET['a'];
$b = $_GET['b'];
$c = $_GET['c'];
$d = $_GET['d'];
$e = $_GET['e'];
$f = $_GET['f'];

$arr = array(
    'abc'=>$_GET['gg'],
    'cdf'=>$_GET['aaa'],
);

$tmp = $_GET['gg'];
if($tmp == 1){
    // ArrayAccessExpression
    $hello = $arr['cdf'];
}
else if($tmp == 2){
    // ConcatenationExpression
    $hello = $a.$b;
}
else if($tmp == 3){
    // UnaryExpression
    $hello = -$a;

}
else if($tmp == 4){
    // TernaryExpression
    $hello = $c==1?$a:$b;

}
else if($tmp == 5){
    // ParenthesizedExpression
    $hello = ($c);
}

system($hello);
?>
