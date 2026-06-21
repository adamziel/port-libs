<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Abbreviation Review

Mr. Bob and Dr. Rivera reviewed the import note before publication.

Escaped source keeps ordinary spacing: Hi Mr\. Bob.

Legacy glossary sample: e.g. examples from imported captions stay grouped for review.
MARKDOWN;

$document = (new MarkdownReader())->read($markdown);

echo (new WordPressBlockWriter())->write($document) . "\n";
