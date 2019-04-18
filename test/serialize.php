<?php
class Peoploe{
    public $bird;
    function __construct()
    {
        $this->bird = new Bird();
    }
}

class Bird{
    public $water;
    function __construct()
    {
        $this->water = new Dog();
    }
}

class Dog{
    public $food;
    function __construct()
    {
        $this->food = 'whoami';
    }
}

$people = new Peoploe();

print_r($people);
print(serialize($people));
?>



<?php
class Peoploe{
    public $bird;
    function __destruct()
    {
        echo "I have a bird：".$this->bird;
    }
}

class Bird{
    public $water;
    public function __toString()
    {
        return "a red bird".$this->water->name;
    }
}

class Dog{
    public $food;
    function __get($food)
    {
        system($this->food);
    }
}

$people = unserialize('O:7:"Peoploe":1:{s:4:"bird";O:4:"Bird":1:{s:5:"water";O:3:"Dog":1:{s:4:"food";s:6:"whoami";}}}');
print_r($people);
?>


<?php
class Peoploe{
    public $bird;
    function __destruct()
    {
        echo "I have a bird：".$this->bird;
    }
}

class Bird{
    public $water;

    public function __toString()
    {
        return "a red bird".$this->water->name;
    }
}

class Dog{
    public $food;

    function evil($thing)
    {
        system($thing);
    }

    function __get($food)
    {
        $this->evil($this->food);
    }

}

$people = unserialize('O:7:"Peoploe":1:{s:4:"bird";O:4:"Bird":1:{s:5:"water";O:3:"Dog":1:{s:4:"food";s:6:"whoami";}}}');
?>


<?php
//最简单的混合案例
class Peoploe{
    public $bird;
    function __destruct()
    {
        echo "I have a bird：".$this->bird;
    }
}

class test1{
    public $water;

    public function __set()
    {
        return "a red bird".$this->water->name;
    }
}

class test2{
    public $water;

    public function __call()
    {
        return "a red bird".$this->water->name;
    }
}


class Bird{
    public $water;

    public function __toString()
    {
        return "a red bird".$this->water->name;
    }
}



class Dog{
    public $food;

    function evil($thing)
    {
        system($thing);
    }

    function __get($food)
    {
        $this->evil($this->food);
    }

}
