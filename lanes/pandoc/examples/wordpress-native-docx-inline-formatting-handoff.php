<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$nativePacket = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-inline-formatting.native');
$document = (new NativeReader())->read($nativePacket);

echo (new WordPressBlockWriter())->write($document) . "\n";
