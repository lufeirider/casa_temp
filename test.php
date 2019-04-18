<?php
require './vendor/autoload.php';
require './MyNodeVisitor.php';

use PhpParser\Error;
use PhpParser\NodeDumper;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;

$traverser     = new NodeTraverser;
$prettyPrinter = new PrettyPrinter\Standard;

$lexer = new PhpParser\Lexer(array(
    'usedAttributes' => array(
        'comments', 'startLine', 'endLine', 'startTokenPos', 'endTokenPos'
    )
));

// add visitor
$traverser->addVisitor(new MyNodeVisitor);

$code = <<<'CODE'
<?php
//pvf函数调用
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

$animal = new Animal();
$animal->eat_fruit($_GET['cmd']);
?>

CODE;

$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7,$lexer);
try {
    $ast = $parser->parse($code);

    // traverse
    $ast = $traverser->traverse($ast);

    // pretty print
    $code = $prettyPrinter->prettyPrintFile($ast);

    print($code);
} catch (Error $error) {
    echo "Parse error: {$error->getMessage()}\n";
    return;
}

$dumper = new NodeDumper;
//echo $dumper->dump($ast) . "\n";