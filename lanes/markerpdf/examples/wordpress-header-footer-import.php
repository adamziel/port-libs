<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\HeaderFooterCleaner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pages = [
    ['WordPress Migration Report', 'Imported post one paragraph.', 'Internal draft'],
    ['WordPress Migration Report', 'Imported post two paragraph.', 'Internal draft'],
    ['WordPress Migration Report', 'Imported post three paragraph.', 'Internal draft'],
    ['WordPress Migration Report', 'Imported post four paragraph.', 'Internal draft'],
];

$cleanedPages = (new HeaderFooterCleaner())->removeCommonEdgeLines($pages);

foreach ($cleanedPages as $page) {
    foreach ($page as $line) {
        echo "<!-- wp:paragraph -->\n";
        echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
        echo "<!-- /wp:paragraph -->\n\n";
    }
}
