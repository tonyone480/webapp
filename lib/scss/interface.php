<?php
if (!class_exists('Leafo\ScssPhp\Version', false))
{
    include_once __DIR__ . '/Base/Range.php';
    include_once __DIR__ . '/Block.php';
    include_once __DIR__ . '/Colors.php';
    include_once __DIR__ . '/Compiler.php';
    include_once __DIR__ . '/Compiler/Environment.php';
    include_once __DIR__ . '/Exception/CompilerException.php';
    include_once __DIR__ . '/Exception/ParserException.php';
    include_once __DIR__ . '/Exception/ServerException.php';
    include_once __DIR__ . '/Formatter.php';
    include_once __DIR__ . '/Formatter/Compact.php';
    include_once __DIR__ . '/Formatter/Compressed.php';
    include_once __DIR__ . '/Formatter/Crunched.php';
    include_once __DIR__ . '/Formatter/Debug.php';
    include_once __DIR__ . '/Formatter/Expanded.php';
    include_once __DIR__ . '/Formatter/Nested.php';
    include_once __DIR__ . '/Formatter/OutputBlock.php';
    include_once __DIR__ . '/Node.php';
    include_once __DIR__ . '/Node/Number.php';
    include_once __DIR__ . '/Parser.php';
    include_once __DIR__ . '/Type.php';
    include_once __DIR__ . '/Util.php';
    include_once __DIR__ . '/Version.php';
}
return new Leafo\ScssPhp\Compiler;