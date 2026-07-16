<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$payload = "app.alert('current stream review');\n"
    . "endstream\nendobj\n"
    . "99 0 obj\n<< /S /JavaScript /JS (fake name-tree stream owner leak) >>\nendobj\n";
$compressedPayload = gzcompress($payload, 0);
if (!is_string($compressedPayload) || !str_contains($compressedPayload, "endstream\nendobj\n99 0 obj")) {
    throw new RuntimeException('Unable to build name-tree stream-filter owner smoke fixture.');
}

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current name tree stream filter owner page) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale name tree stream filter owner page) Tj ET';

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (?int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
    "%010d %05d %s \n",
    $offset ?? 0,
    $generation,
    $state
);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Names << /JavaScript 20 0 R >> >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 5 0 R >>');
$addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(20, 0, '<< /Names [(review-js) 40 0 R] >>');
$addObject(40, 0, '<< /S /JavaScript /JS 41 0 R >>');
$addObject(41, 0, '<< /Filter /FlateDecode /Length ' . strlen($compressedPayload) . " >>\nstream\n{$compressedPayload}\nendstream");

$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 42\n" . $xrefRow(0, 65535, 'f');
for ($objectNumber = 1; $objectNumber <= 41; $objectNumber++) {
    $pdf .= isset($offsets[$objectNumber . ':0'])
        ? $xrefRow($offsets[$objectNumber . ':0'])
        : $xrefRow(0, 65535, 'f');
}
$pdf .= "trailer\n<< /Size 42 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /JavaScript 70 0 R >> >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /S /JavaScript /JS (stale detached action) >>\nendobj\n"
    . "41 0 obj\n<< /Length 25 >>\nstream\nstale detached payload\nendstream\nendobj\n"
    . "70 0 obj\n<< /Names [(stale-detached-js) 40 0 R] >>\nendobj\n";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$source = $metadata['document_name_trees']['trees']['JavaScript']['entries'][0]['javascript_source'] ?? [];

if ($plainText !== 'Current name tree stream filter owner page') {
    throw new RuntimeException('Expected current xref-selected page text only.');
}

if (($source['source_type'] ?? null) !== 'stream' || ($source['filters'] ?? []) !== ['FlateDecode']) {
    throw new RuntimeException('Expected current filtered JavaScript name-tree payload stream review.');
}

if (
    !is_string($encoded)
    || str_contains($encoded, "app.alert('current stream review')")
    || str_contains($encoded, 'fake name-tree stream owner leak')
    || str_contains($encoded, '99 0 obj')
    || str_contains($encoded, 'stale detached action')
    || str_contains($encoded, 'stale-detached-js')
    || str_contains($plainText, 'Stale name tree stream filter owner page')
) {
    throw new RuntimeException('Expected name-tree payload and stale owner tokens to stay out of WordPress output.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-parser-nametree-stream-filter-owner-currentbase-smoke ' . $htmlJson([
    'support_component' => 'native-pdf-catalog-name-tree-review',
    'native_boundary' => 'catalog name-tree JavaScript payload streams keep current xref-selected stream-filter owners before WordPress import',
    'source_type' => $source['source_type'] ?? null,
    'object' => $source['object'] ?? null,
    'filters' => $source['filters'] ?? [],
    'payload_included' => $source['payload_included'] ?? null,
    'fake_stream_owner_excluded' => !str_contains($encoded, '99 0 obj'),
    'stale_detached_name_tree_excluded' => !str_contains($encoded, 'stale-detached-js'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
