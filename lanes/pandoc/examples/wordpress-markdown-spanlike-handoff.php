<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = '[test]{.foo .underline #bar .smallcaps .kbd}';
$document = (new MarkdownReader())->read($markdown);

echo (new WordPressBlockWriter())->write($document) . "\n";
