<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current Trailer Info NameTree Body) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale Trailer Info NameTree Body) Tj ET';
$script = "app.alert('metadata review only')";
$staleScript = "app.alert('stale metadata action')";

$pdf = "%PDF-2.0\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Names << /JavaScript 20 0 R /URLS 30 0 R /Dests 70 0 R >> >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>');
$addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(20, 0, '<< /Kids [21 0 R 22 0 R] >>');
$addObject(21, 0, '<< /Limits [(a) (m)] /Names [(import-init) 40 0 R (z-stale-js) 41 0 R] >>');
$addObject(22, 0, '<< /Limits [(n) (z)] /Names [(review-close) 42 0 R] >>');
$addObject(30, 0, '<< /Kids [31 0 R] >>');
$addObject(31, 0, '<< /Limits [(source-url) (source-url)] /Names [(source-url) 50 0 R (zz-stale-url) 51 0 R] >>');
$addObject(40, 0, "<< /S /JavaScript /JS ({$script}) >>");
$addObject(41, 0, "<< /S /JavaScript /JS ({$staleScript}) >>");
$addObject(42, 0, '<< /S /JavaScript /JS <' . strtoupper(bin2hex("\xfe\xff\0a\0p\0p\0.\0a\0l\0e\0r\0t\0(\0'\0r\0e\0v\0i\0e\0w\0-\0c\0l\0o\0s\0e\0'\0)")) . '> >>');
$addObject(50, 0, '<< /S /URI /URI (https://example.test/current-import-source) >>');
$addObject(51, 0, '<< /S /URI /URI (https://example.test/stale-import-source) >>');
$addObject(60, 0, '<< /Title (Current Trailer Info NameTree Title) /Author (Current Trailer Author) /Producer (Current Trailer Producer) /Trapped /False /ImportRevision 46 /ImportFlags [/WordPress /MetadataReview] /CustomReview << /Stage (current-base) /Safe true >> >>');
$addObject(70, 0, '<< /Names [(Review Start) [3 0 R /FitH 700]] >>');

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
    throw new RuntimeException('Unable to compress trailer Info name-tree xref stream.');
}

$pdf .= "90 0 obj\n"
    . '<< /Type /XRef /Size 91 /Root 1 0 R /Info 60 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /JavaScript 80 0 R /URLS 81 0 R >> >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /S /JavaScript /JS ({$staleScript}) >>\nendobj\n"
    . "50 0 obj\n<< /S /URI /URI (https://example.test/stale-detached-source) >>\nendobj\n"
    . "60 0 obj\n<< /Title (Stale Trailer Info NameTree Title) /Author (Stale Trailer Author) /Trapped /True /ImportRevision 99 >>\nendobj\n"
    . "80 0 obj\n<< /Names [(stale-appended-js) 40 0 R] >>\nendobj\n"
    . "81 0 obj\n<< /Names [(stale-appended-url) 50 0 R] >>\nendobj\n";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$nameTrees = $metadata['document_name_trees'] ?? [];

if (($metadata['title'] ?? null) !== 'Current Trailer Info NameTree Title') {
    throw new RuntimeException('Expected current trailer Info title.');
}
if (($metadata['trailer_info_review']['Trapped'] ?? null) !== 'False') {
    throw new RuntimeException('Expected trailer Info /Trapped review metadata.');
}
if (($nameTrees['trees']['JavaScript']['names'] ?? []) !== ['import-init', 'review-close']) {
    throw new RuntimeException('Expected current JavaScript name-tree review rows.');
}
if (($nameTrees['trees']['URLS']['names'] ?? []) !== ['source-url']) {
    throw new RuntimeException('Expected current URLS name-tree review rows.');
}
if (
    !is_string($encoded)
    || str_contains($encoded, $script)
    || str_contains($encoded, $staleScript)
    || str_contains($encoded, 'https://example.test/current-import-source')
    || str_contains($encoded, 'stale-appended-js')
    || str_contains($plainText, 'Stale Trailer Info NameTree Body')
    || str_contains($plainText, 'metadata review only')
) {
    throw new RuntimeException('Expected name-tree action payloads and stale trailer rows to stay out of import output.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-metadata-trailer-info-name-tree-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-trailer-info-catalog-name-tree-review',
    'native_boundary' => 'current xref-stream trailer /Info plus catalog /Names /JavaScript and /URLS review metadata before WordPress import',
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title' => $metadata['title'] ?? null,
    'trapped' => $metadata['trailer_info_review']['Trapped'] ?? null,
    'info_import_revision' => $metadata['trailer_info_review']['ImportRevision'] ?? null,
    'name_tree_names' => $nameTrees['tree_names'] ?? [],
    'javascript_name_rows' => $nameTrees['trees']['JavaScript']['names'] ?? [],
    'urls_name_rows' => $nameTrees['trees']['URLS']['names'] ?? [],
    'payload_included' => $nameTrees['payload_included'] ?? null,
    'visible_text' => $plainText,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:metadata-review ' . $htmlJson([
    'trailer_info_review' => $metadata['trailer_info_review'] ?? [],
    'document_name_trees' => [
        'tree_names' => $nameTrees['tree_names'] ?? [],
        'payload_included' => $nameTrees['payload_included'] ?? null,
        'javascript_actions_execute' => $nameTrees['trees']['JavaScript']['entries'][0]['executes_action'] ?? null,
        'javascript_payload_included' => $nameTrees['trees']['JavaScript']['entries'][0]['javascript_payload_included'] ?? null,
        'uri_payload_included' => $nameTrees['trees']['URLS']['entries'][0]['uri_payload_included'] ?? null,
    ],
]) . " -->\n";
