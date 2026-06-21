<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$document = (new NativeReader())->read(
    (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-overlapping-targets.native')
);

echo (new WordPressBlockWriter())->write($document) . "\n";
