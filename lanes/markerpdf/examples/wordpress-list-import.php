<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\TextCleaner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Migration Checklist) Tj T* (• Preserve headings) Tj T* (● Normalize bullets) Tj T* (— Convert to list blocks) Tj ET';
$pdf = "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);
$heading = array_shift($lines);
$items = array_values(array_filter(
    explode("\n", (new TextCleaner())->cleanForMarkdown(implode("\n", $lines))),
    static fn (string $line): bool => str_starts_with($line, '- ')
));

if ($heading !== null) {
    echo "<!-- wp:heading -->\n";
    echo '<h2>' . htmlspecialchars($heading, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>\n";
    echo "<!-- /wp:heading -->\n\n";
}

if ($items !== []) {
    echo "<!-- wp:list -->\n<ul>\n";
    foreach ($items as $item) {
        echo '<li>' . htmlspecialchars(substr($item, 2), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
    }
    echo "</ul>\n<!-- /wp:list -->\n";
}
