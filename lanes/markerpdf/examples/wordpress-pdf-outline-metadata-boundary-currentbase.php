<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">WordPress Outline Metadata Boundary</rdf:li></rdf:Alt></dc:title>'
    . '<xmp:CreateDate>2026-06-02T23:00:09Z</xmp:CreateDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$staleXmp = str_replace('WordPress Outline Metadata Boundary', 'Stale WordPress Outline Metadata Boundary', $xmp);
$xmpStream = gzcompress($xmp);
$staleXmpStream = gzcompress($staleXmp);
if (!is_string($xmpStream) || !is_string($staleXmpStream)) {
    throw new RuntimeException('Unable to compress WordPress outline metadata smoke streams.');
}

$introContent = 'BT /F1 12 Tf 72 720 Td (WordPress current outline metadata intro) Tj ET';
$targetContent = 'BT /F1 12 Tf 72 720 Td (WordPress current outline metadata target) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale WordPress outline metadata body) Tj ET';

$pdf = "%PDF-2.0\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 14 0 R /Outlines 40 0 R /Names << /Dests 50 0 R >> /PageMode /UseOutlines >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>');
$addObject(4, '<< /Type /Page /Parent 2 0 R /Contents 32 0 R >>');
$addObject(14, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($xmpStream) . " >>\nstream\n{$xmpStream}\nendstream");
$addObject(31, "<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream");
$addObject(32, "<< /Length " . strlen($targetContent) . " >>\nstream\n{$targetContent}\nendstream");
$addObject(40, '<< /Type /Outlines /First 41 0 R /Last 42 0 R /Count 2 >>');
$addObject(41, '<< /Title (Import Runbook) /Parent 40 0 R /Dest /ImportStart /Next 42 0 R /First 43 0 R /Last 43 0 R /Count -1 /C [0 .35 .7] /F 2 >>');
$addObject(42, '<< /Title (Media Appendix) /Parent 40 0 R /Prev 41 0 R /A 44 0 R >>');
$addObject(43, '<< /Title (Collapsed Review Child) /Parent 41 0 R /Dest /MediaTarget /C [80 0 R 81 0 R 82 0 R] >>');
$addObject(44, '<< /S /GoTo /D [4 0 R /FitR 10 20 300 700] >>');
$addObject(50, '<< /Names [(ImportStart) [3 0 R /FitH 720] (MediaTarget) [4 0 R /XYZ 144 null 0]] >>');
$addObject(60, '<< /Title (Outline Metadata Info Fallback) /Author (Data Liberation Team) >>');
$addObject(80, '-.25');
$addObject(81, '.5');
$addObject(82, '1.2');

$xrefOffset = strlen($pdf);
$rows = '';
for ($objectNumber = 0; $objectNumber < 91; $objectNumber++) {
    if ($objectNumber === 0 || (!isset($offsets[$objectNumber]) && $objectNumber !== 90)) {
        $rows .= pack('CNn', 0, 0, $objectNumber === 0 ? 65535 : 0);
        continue;
    }

    $rows .= pack('CNn', 1, $objectNumber === 90 ? $xrefOffset : $offsets[$objectNumber], 0);
}

$compressedXref = gzcompress($rows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress WordPress outline metadata xref stream.');
}

$pdf .= "90 0 obj\n"
    . '<< /Type /XRef /Size 91 /Root 1 0 R /Info 60 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 15 0 R /Outlines 70 0 R >>\nendobj\n"
    . "14 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($staleXmpStream) . " >>\nstream\n{$staleXmpStream}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
    . "70 0 obj\n<< /Type /Outlines /First 71 0 R /Last 71 0 R /Count 1 >>\nendobj\n"
    . "71 0 obj\n<< /Title (Stale Outline Review Title) /Parent 70 0 R /Dest [3 0 R /Fit] >>\nendobj\n";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($outline['titles'] ?? []) !== ['Import Runbook', 'Collapsed Review Child', 'Media Appendix']) {
    throw new RuntimeException('Expected current xref-selected outline metadata titles.');
}
if (($outline['resolved_destination_count'] ?? null) !== 3) {
    throw new RuntimeException('Expected all current outline metadata destinations to resolve.');
}
if (($outline['items'][0]['text_color_hex'] ?? null) !== '#0059b3' || ($outline['items'][1]['text_color_hex'] ?? null) !== '#0080ff') {
    throw new RuntimeException('Expected current outline color metadata to be preserved for WordPress navigation review.');
}
if (!is_string($encoded) || str_contains($encoded, 'Stale Outline Review Title') || str_contains($encoded, 'Stale WordPress Outline Metadata Boundary')) {
    throw new RuntimeException('Expected stale appended outline and XMP objects to stay out of metadata.');
}
if (str_contains($plainText, 'Import Runbook')
    || str_contains($plainText, 'Collapsed Review Child')
    || str_contains($plainText, 'Media Appendix')
    || str_contains($plainText, 'Stale WordPress outline metadata body')
    || str_contains($plainText, 'WordPress Outline Metadata Boundary')
) {
    throw new RuntimeException('Expected outline and XMP metadata to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-metadata-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-boundary-currentbase',
    'support_component' => 'native-pdf-catalog-outline-metadata-review',
    'native_boundary' => 'catalog /Outlines is current xref-selected document metadata and review-only TOC context, not page body text',
    'title' => $metadata['title'] ?? null,
    'outline_root_object' => $outline['outline_root_object'] ?? null,
    'outline_titles' => $outline['titles'] ?? [],
    'outline_text_colors' => array_values(array_filter(array_map(
        static fn (array $item): ?string => $item['text_color_hex'] ?? null,
        $outline['items'] ?? []
    ))),
    'resolved_destination_count' => $outline['resolved_destination_count'] ?? null,
    'stale_outline_excluded' => is_string($encoded) && !str_contains($encoded, 'Stale Outline Review Title'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Import Runbook')
        && !str_contains($plainText, 'Collapsed Review Child')
        && !str_contains($plainText, 'Media Appendix'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline review\"><ul>\n";
foreach ($outline['items'] ?? [] as $item) {
    echo '<li data-marker-outline-level="' . (int) ($item['level'] ?? 0)
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-state="' . htmlspecialchars((string) ($item['structure_state'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-color="' . htmlspecialchars((string) ($item['text_color_hex'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
