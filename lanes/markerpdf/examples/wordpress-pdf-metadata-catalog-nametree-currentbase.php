<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentSourcePayload = '<wp-export><post id="1838"/></wp-export>';
$currentPreviewPayload = 'Current bounded preview bytes';
$staleSourcePayload = '<wp-export><post id="stale-nametree"/></wp-export>';
$currentSourceChecksum = strtoupper(hash('md5', $currentSourcePayload));
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current Catalog NameTree Limits Body) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale Catalog NameTree Body) Tj ET';

$pdf = "%PDF-2.0\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 20 0 R /Dests 30 0 R >> >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>');
$addObject(4, 0, '<< /Type /Page /Parent 2 0 R >>');
$addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(20, 0, '<< /Kids [21 0 R 22 0 R] >>');
$addObject(21, 0, '<< /Limits [(a) (m)] /Names [(current-source.xml) 40 0 R (z-stale-source.xml) 50 0 R] >>');
$addObject(22, 0, '<< /Limits [(n) (z)] /Names [(review-bundle.pdf) 42 0 R] >>');
$addObject(30, 0, '<< /Kids [31 0 R 32 0 R] >>');
$addObject(31, 0, '<< /Limits [(A) (M)] /Names [(Current Start) [3 0 R /FitH 700] (Z Stale Destination) [4 0 R /Fit]] >>');
$addObject(32, 0, '<< /Limits [(N) (Z)] /Names [(Review Summary) [4 0 R /XYZ 144 null 0]] >>');
$addObject(40, 0, '<< /Type /Filespec /F (current-source.xml) /UF (current-source.xml) /Desc (Current bounded source export) /AFRelationship /Source /EF << /F 41 0 R >> >>');
$addObject(41, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentSourcePayload) . ' /CheckSum <' . $currentSourceChecksum . "> >> /Length " . strlen($currentSourcePayload) . " >>\nstream\n{$currentSourcePayload}\nendstream");
$addObject(42, 0, '<< /Type /Filespec /F (review-bundle.pdf) /Desc (Current bounded preview) /AFRelationship /Alternative /EF << /F 43 0 R >> >>');
$addObject(43, 0, '<< /Type /EmbeddedFile /Subtype /application#2Fpdf /Length ' . strlen($currentPreviewPayload) . " >>\nstream\n{$currentPreviewPayload}\nendstream");
$addObject(50, 0, '<< /Type /Filespec /F (z-stale-source.xml) /Desc (Stale out-of-limits source) /AFRelationship /Source /EF << /F 51 0 R >> >>');
$addObject(51, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($staleSourcePayload) . " >>\nstream\n{$staleSourcePayload}\nendstream");
$addObject(60, 0, '<< /Title (Current NameTree Info) /Author (Current NameTree Author) /Producer (Current NameTree Producer) >>');

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
    throw new RuntimeException('Unable to compress current name-tree xref stream.');
}

$pdf .= "90 0 obj\n"
    . '<< /Type /XRef /Size 91 /Root 1 0 R /Info 60 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 70 0 R /Dests 72 0 R >> >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Filespec /F (stale-selected-source.xml) /Desc (Stale appended source) /AFRelationship /Source /EF << /F 41 0 R >> >>\nendobj\n"
    . "41 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length " . strlen($staleSourcePayload) . " >>\nstream\n{$staleSourcePayload}\nendstream\nendobj\n"
    . "60 0 obj\n<< /Title (Stale NameTree Info) /Author (Stale NameTree Author) /Producer (Stale NameTree Producer) >>\nendobj\n"
    . "70 0 obj\n<< /Names [(stale-detached.xml) 40 0 R] >>\nendobj\n"
    . "72 0 obj\n<< /Names [(Stale Detached Destination) [4 0 R /Fit]] >>\nendobj\n";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$embeddedFiles = $metadata['embedded_files'] ?? [];
$destinations = $metadata['document_destinations'] ?? [];
$embeddedNames = array_values(array_filter(array_column($embeddedFiles, 'name_tree_name'), 'is_string'));

if (($metadata['title'] ?? null) !== 'Current NameTree Info') {
    throw new RuntimeException('Expected current xref-selected Info metadata.');
}
if ($embeddedNames !== ['current-source.xml', 'review-bundle.pdf']) {
    throw new RuntimeException('Expected only in-limits current embedded-file name-tree rows.');
}
if (($destinations['names'] ?? []) !== ['Current Start', 'Review Summary']) {
    throw new RuntimeException('Expected only in-limits current destination name-tree rows.');
}
if (
    !is_string($encoded)
    || str_contains($encoded, 'z-stale-source.xml')
    || str_contains($encoded, 'Z Stale Destination')
    || str_contains($encoded, 'Stale NameTree Info')
    || str_contains($encoded, 'stale-detached.xml')
    || str_contains($encoded, $staleSourcePayload)
    || str_contains($plainText, 'Stale Catalog NameTree Body')
    || str_contains($plainText, '<wp-export>')
) {
    throw new RuntimeException('Expected stale and out-of-limits name-tree payloads to stay out of WordPress import output.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-metadata-catalog-nametree-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-catalog-name-tree-review',
    'native_boundary' => 'current xref-selected catalog /Names /EmbeddedFiles and /Dests with node /Limits before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title' => $metadata['title'] ?? null,
    'embedded_name_tree_files' => $embeddedNames,
    'destination_names' => $destinations['names'] ?? [],
    'stale_out_of_limits_rows_excluded' => true,
    'visible_text' => $plainText,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:metadata-review ' . $htmlJson([
    'document_title' => $metadata['title'] ?? null,
    'embedded_files' => array_map(static fn (array $file): array => [
        'name_tree_name' => $file['name_tree_name'] ?? null,
        'filename' => $file['filename'] ?? null,
        'relationship' => $file['relationship'] ?? null,
        'payload_included' => array_key_exists('content', $file),
    ], $embeddedFiles),
    'destinations' => $destinations['names'] ?? [],
]) . " -->\n";
