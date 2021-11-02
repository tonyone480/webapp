<?php
if (class_exists('Leafo\ScssPhp\Version', FALSE) === FALSE)
{
    include __DIR__ . '/Base/Range.php';
    include __DIR__ . '/Block.php';
    include __DIR__ . '/Colors.php';
    include __DIR__ . '/Compiler.php';
    include __DIR__ . '/Compiler/Environment.php';
    include __DIR__ . '/Exception/CompilerException.php';
    include __DIR__ . '/Exception/ParserException.php';
    include __DIR__ . '/Exception/ServerException.php';
    include __DIR__ . '/Formatter.php';
    include __DIR__ . '/Formatter/Compact.php';
    include __DIR__ . '/Formatter/Compressed.php';
    include __DIR__ . '/Formatter/Crunched.php';
    include __DIR__ . '/Formatter/Debug.php';
    include __DIR__ . '/Formatter/Expanded.php';
    include __DIR__ . '/Formatter/Nested.php';
    include __DIR__ . '/Formatter/OutputBlock.php';
    include __DIR__ . '/Node.php';
    include __DIR__ . '/Node/Number.php';
    include __DIR__ . '/Parser.php';
    include __DIR__ . '/Type.php';
    include __DIR__ . '/Util.php';
    include __DIR__ . '/Version.php';
}
return new Leafo\ScssPhp\Compiler;