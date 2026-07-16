<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$nativePacket = <<<'NATIVE'
[ Para [ Str "Source\160\&42" , Space , Str "keeps" , Space , Str "its" , Space , Str "audit" , Space , Str "identifier." ]
, Para [ Str "M.A.\160\&2007" , Space , Str "bibliography" , Space , Str "tokens" , Space , Str "round-trip." ]
]
NATIVE;

$document = (new NativeReader())->read($nativePacket);

echo (new WordPressBlockWriter())->write($document) . "\n";
