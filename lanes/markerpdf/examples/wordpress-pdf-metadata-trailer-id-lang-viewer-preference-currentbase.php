<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Current ID Lang Viewer Body) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale ID Lang Viewer Body) Tj ET';
$permanentId = 'Current Permanent';
$changingId = 'Current Changing';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};

$addObject(
    1,
    0,
    '<< /Type /Catalog /PieceInfo << /WPImport << /Lang (stale-nested-lang)'
    . ' /ViewerPreferences << /Direction /L2R /NumCopies 99 >> >> >>'
    . ' /Pages 2 0 R /Lang (en-US) /PageLayout /TwoPageRight /PageMode /UseOutlines'
    . ' /ViewerPreferences 7 0 R >>'
);
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(
    7,
    0,
    '<< /Review << /Direction /L2R /NumCopies 99 >> /DisplayDocTitle true'
    . ' /Direction /R2L /PrintScaling /None /PrintPageRange [1 2] /NumCopies 2'
    . ' /Enforce [ /PrintScaling /Direction /Bogus ] >>'
);

$pdf .= "trailer\n<< /Root 1 0 R /ID [(Stale Permanent) (Stale Changing)] >>\n";
$xrefOffset = strlen($pdf);
$rows = '';
for ($objectNumber = 0; $objectNumber < 11; $objectNumber++) {
    $rows .= pack(
        'CNn',
        isset($offsets[$objectNumber]) || $objectNumber === 10 ? 1 : 0,
        $objectNumber === 10 ? $xrefOffset : ($offsets[$objectNumber] ?? 0),
        $objectNumber === 0 ? 65535 : 0
    );
}

$compressedXref = gzcompress($rows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress metadata trailer/current catalog xref stream.');
}

$pdf .= "10 0 obj\n"
    . '<< /Type /XRef /Size 11 /Root 1 0 R /ID [(Current\040Permanent) <'
    . strtoupper(bin2hex($changingId))
    . '>] /W [1 4 2] /Filter /FlateDecode /Length '
    . strlen($compressedXref)
    . " >>\nstream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Lang (de-DE)"
    . " /ViewerPreferences << /DisplayDocTitle false /Direction /L2R >> >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$viewerPreferences = $metadata['viewer_preferences'] ?? [];

echo '<!-- markerpdf-metadata-trailer-id-lang-viewer-preference-currentbase ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'Current xref-stream trailer /ID plus top-level catalog /Lang and /ViewerPreferences review metadata',
    'source' => $metadata['source'],
    'current_id_selected' => ($metadata['trailer_ids']['permanent']['hex'] ?? null) === bin2hex($permanentId),
    'document_fingerprint_source' => $metadata['document_fingerprint_source'] ?? null,
    'language' => $metadata['language'] ?? null,
    'page_layout' => $metadata['page_layout'] ?? null,
    'page_mode' => $metadata['page_mode'] ?? null,
    'viewer_preferences' => $viewerPreferences,
    'nested_catalog_decoy_excluded' => !str_contains(json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '', 'stale-nested-lang'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p lang="' . htmlspecialchars((string) ($metadata['language'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:catalog-review ' . htmlspecialchars(json_encode([
    'display_doc_title' => $viewerPreferences['display_doc_title'] ?? null,
    'direction' => $viewerPreferences['direction'] ?? null,
    'print_scaling' => $viewerPreferences['print_scaling'] ?? null,
    'print_page_range' => $viewerPreferences['print_page_range'] ?? [],
    'num_copies' => $viewerPreferences['num_copies'] ?? null,
    'fingerprint' => $metadata['document_fingerprint'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
