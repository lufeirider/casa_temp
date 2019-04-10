<?php
class Animal{
    public $name;
    public $food;

    public function show_name(){
        system($this->name);
    }

    public function eat_xxx($food){
        system($food);
    }

    public function eat_fruit($fruit){
        $this->eat_xxx($fruit);
    }
}
?>