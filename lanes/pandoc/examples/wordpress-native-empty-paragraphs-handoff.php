<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$native = '[Para [Str "hi"], Para [], Para [], Para [Str "lo"]]';
$document = (new NativeReader())->read($native);

echo (new WordPressBlockWriter(['preserveEmptyParagraphs' => true]))->write($document) . "\n";
