<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-import-content.pdf');
if ($fixture === false) {
    throw new RuntimeException('Unable to read markerpdf WordPress import fixture.');
}

$lines = (new PdfTextExtractor())->extractTextLines($fixture);
$heading = array_shift($lines);

if ($heading !== null) {
    echo "<!-- wp:heading -->\n";
    echo '<h2>' . htmlspecialchars($heading, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>\n";
    echo "<!-- /wp:heading -->\n\n";
}

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
