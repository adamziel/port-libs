<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$content = 'BT /F1 12 Tf 72 720 Td (Current WordPress unindexed outline body) Tj ET';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /Outlines 40 0 R /PageMode /UseOutlines >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
$addObject(4, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(10, '<< /Title (WordPress Current Outline Info) /Author (Data Liberation Team) >>');
$addObject(40, '<< /Type /Outlines /First 41 0 R /Last 41 0 R /Count 1 >>');
$addObject(41, '<< /Title (Current WordPress Outline Chapter) /Parent 40 0 R /Dest [3 0 R /FitH 720] /C [0 .4 .8] /F 2 >>');

$xrefOffset = strlen($pdf);
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$pdf .= "xref\n"
    . "0 11\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[1])
    . $xrefRow($offsets[2])
    . $xrefRow($offsets[3])
    . $xrefRow($offsets[4])
    . $xrefRow(0, 0, 'f')
    . $xrefRow(0, 0, 'f')
    . $xrefRow(0, 0, 'f')
    . $xrefRow(0, 0, 'f')
    . $xrefRow(0, 0, 'f')
    . $xrefRow($offsets[10])
    . "trailer\n<< /Size 11 /Root 1 0 R /Info 10 0 R >>\n"
    . "startxref\n{$xrefOffset}\n%%EOF\n"
    . "40 0 obj\n<< /Type /Outlines /First 42 0 R /Last 42 0 R /Count 1 >>\nendobj\n"
    . "42 0 obj\n<< /Title (Stale WordPress Post EOF Outline) /Parent 40 0 R /Dest [3 0 R /Fit] >>\nendobj\n";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$titles = $outline['titles'] ?? [];

if ($titles !== ['Current WordPress Outline Chapter']) {
    throw new RuntimeException('Expected the current pre-EOF outline title.');
}
if (($outline['first_item_object'] ?? null) !== 41 || ($outline['last_item_object'] ?? null) !== 41) {
    throw new RuntimeException('Expected current pre-EOF outline item object boundaries.');
}
if (($outline['resolved_destination_count'] ?? null) !== 1 || ($outline['items'][0]['page'] ?? null) !== 0) {
    throw new RuntimeException('Expected current outline destination to resolve to the imported page.');
}
if (!is_string($encoded) || str_contains($encoded, 'Stale WordPress Post EOF Outline')) {
    throw new RuntimeException('Expected stale post-EOF outline metadata to be excluded.');
}
if (str_contains($plainText, 'Current WordPress Outline Chapter') || str_contains($plainText, 'Stale WordPress Post EOF Outline')) {
    throw new RuntimeException('Expected outline metadata to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-metadata-unindexed-eof-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-unindexed-eof-boundary-currentbase',
    'support_component' => 'native-pdf-catalog-outline-metadata-review',
    'native_boundary' => 'unindexed outline repair is bounded to the selected EOF before WordPress navigation metadata is emitted',
    'title' => $metadata['title'] ?? null,
    'authors' => $metadata['authors'] ?? [],
    'outline_root_object' => $outline['outline_root_object'] ?? null,
    'outline_titles' => $titles,
    'resolved_destination_count' => $outline['resolved_destination_count'] ?? null,
    'stale_post_eof_outline_excluded' => is_string($encoded) && !str_contains($encoded, 'Stale WordPress Post EOF Outline'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Current WordPress Outline Chapter')
        && !str_contains($plainText, 'Stale WordPress Post EOF Outline'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline review\"><ul>\n";
foreach ($outline['items'] ?? [] as $item) {
    echo '<li data-marker-outline-object="' . (int) ($item['outline_object'] ?? 0)
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-color="' . htmlspecialchars((string) ($item['text_color_hex'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
