<?php

class Peoploe{
    public $dog;
    function __construct($dog){
        echo "I have a bird".$dog;
    }

}

class Bird{
    public $food;

    function __construct($food)
    {
        $this->food = $food;
    }

    function __toString(){
        return "bird eat".$this->food->type;
    }
}

class Dog{
    public $food;

    function __construct($food)
    {
        $this->food = $food;
    }

    public function __get($water){
        system($this->food);
    }
}

$dog = new Dog("whoami");
$bird = new Bird($dog);
$people = new Peoploe($bird);