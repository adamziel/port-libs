<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\HeadingCleaner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$document = new class {
    /**
     * @return list<object>
     */
    public function get_toc(int $max_depth = 15): array
    {
        return [
            (object) ['title' => 'Migration Runbook', 'level' => 1, 'page_index' => 0],
            (object) ['title' => 'Content Checks', 'level' => 2, 'page_index' => 3],
            (object) ['title' => 'Media Cleanup', 'level' => 2, 'page_index' => 5],
        ];
    }
};

$toc = (new HeadingCleaner())->getPdfToc($document, 8);

echo "<!-- wp:list -->\n<ul>\n";
foreach ($toc as $item) {
    $title = htmlspecialchars($item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<li data-marker-outline-level="' . $item['level'] . '" data-marker-outline-page="' . $item['page'] . '">'
        . $title
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
