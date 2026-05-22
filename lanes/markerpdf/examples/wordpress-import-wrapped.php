<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-wrapped-content.pdf');
if ($fixture === false) {
    throw new RuntimeException('Unable to read markerpdf wrapped WordPress fixture.');
}

$lines = (new PdfTextExtractor())->extractTextLines($fixture);
$heading = array_shift($lines);
$paragraphs = preg_split('/\n{2,}/', (new MarkdownPostProcessor())->mergeLines($lines)) ?: [];

if ($heading !== null) {
    echo "<!-- wp:heading -->\n";
    echo '<h2>' . htmlspecialchars($heading, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>\n";
    echo "<!-- /wp:heading -->\n\n";
}

foreach ($paragraphs as $paragraph) {
    $paragraph = trim($paragraph);
    if ($paragraph === '') {
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
