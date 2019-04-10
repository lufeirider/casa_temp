<?php
// 把this作为了污染源
class People{
    public $name;

    public function show_name($name){
        system($name);
    }
}
?>

<?php
// 把this作为了污染源
class People{
    public $name;

    public function show_name(){
        system($this->name);
    }
}
?>

<?php
// 参数为表达式
class People{
    public $name;

    public function show_name($name){
        system($name."xxxxxxxxxx");
    }
}
?>

<?php
// 参数为表达式
class People{
    public $name;

    public function show_name(){
        system($this->name."xxxxxxx");
    }
}
?>

<?php
// 对等调用，污染变量是属性，函数调用也为属性
class People{
    public $age;

    public function show_name($aaaaaaaaaa){
        $animal->name = $aaaaaaaaaa;
        system($animal->name."xxxxxxxx");
    }
}
?>

<?php
// 对等调用，污染变量是属性，函数调用也为属性
class People{
    public $age;

    public function show_name($aaaaaaaaaa){
        $animal->lufei->name = $aaaaaaaaaa;
        system($animal->lufei->name."xxxxxxxx");
    }
}
?>


<?php
// 不对等调用，污染变量是对象，函数调用是属性
class People{
    public $age;

    public function show_name($aaaaaaaaaa){
        $animal = $aaaaaaaaaa;
        system($animal->name."xxxxxxxx");
    }
}
?>


<?php
// 不对等赋值，$animal = $aaaaaaaaaa->lufei;
class People{
    public $age;

    public function show_name($aaaaaaaaaa){
        $animal = $aaaaaaaaaa->lufei;
        system($animal->name."xxxxxxxx");
    }
}
?>