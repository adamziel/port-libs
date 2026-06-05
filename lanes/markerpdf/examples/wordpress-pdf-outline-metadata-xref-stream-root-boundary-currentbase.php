<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xrefStreamRootBoundaryPdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale outline xref stream root body) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current outline xref stream root body) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
    $addObject(4, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(5, '<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>');
    $addObject(6, '<< /Title (Stale XRef Stream Root Outline) /Parent 5 0 R /Dest [3 0 R /Fit] /A 12 0 R >>');
    $addObject(12, "<< /S /JavaScript /JS (app.alert\\('stale xref stream root outline action'\\)) >>");
    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Outlines 25 0 R /Names << /Dests 28 0 R >> /PageMode /UseOutlines >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Contents 30 0 R >>');
    $addObject(25, '<< /Type /Outlines /First 26 0 R /Last 26 0 R /Count 1 >>');
    $addObject(26, '<< /Title (Current XRef Stream Root Outline) /Parent 25 0 R /Dest /CurrentStart /C [0 .4 .8] /F 2 >>');
    $addObject(28, '<< /Names [(CurrentStart) [22 0 R /FitH 640]] >>');
    $addObject(30, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber <= 90; $objectNumber++) {
        if ($objectNumber === 0 || !isset($offsets[$objectNumber])) {
            $rows .= pack('CNN', 0, 0, $objectNumber === 0 ? 65535 : 0);
            continue;
        }

        $rows .= pack('CNN', 1, $offsets[$objectNumber], 0);
    }

    $compressedXref = gzcompress($rows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress outline xref-stream root fixture.');
    }

    return $pdf
        . "90 0 obj\n"
        . '<< /Type /XRef /Size 91 /Root 20 0 R /W [1 4 4] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";
};

$pdf = $xrefStreamRootBoundaryPdf();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$encodedReview = json_encode([$metadata, $toc, $navigation, $plainText], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

if (($outline['outline_root_object'] ?? null) !== 25
    || ($outline['first_item_object'] ?? null) !== 26
    || ($toc[0]['title'] ?? null) !== 'Current XRef Stream Root Outline'
    || ($toc[0]['view_mode'] ?? null) !== 'FitH'
    || (($toc[0]['view_parameters']['top'] ?? null) !== 640.0)
    || (($navigation['outline'][0]['text_color_hex'] ?? null) !== '#0066cc')
    || $plainText !== 'Current outline xref stream root body'
    || str_contains($encodedReview, 'Stale XRef Stream Root Outline')
    || str_contains($encodedReview, 'stale xref stream root outline action')
    || str_contains($plainText, 'Stale outline xref stream root body')
) {
    throw new RuntimeException('Expected xref-stream trailer Root to select the current outline, metadata, navigation, and page text.');
}

echo '<!-- markerpdf-outline-metadata-xref-stream-root-boundary-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-outline-metadata-xref-stream-root-boundary-currentbase',
    'source_truth' => 'PDF 1.5+ cross-reference streams store trailer entries such as /Root in the /Type /XRef stream dictionary; Marker-style TOC metadata should follow the current document catalog boundary.',
    'support_component' => 'native-pdf-outline-metadata-parser',
    'xref_stream_root_object' => 20,
    'outline_root_object' => $outline['outline_root_object'] ?? null,
    'first_item_object' => $outline['first_item_object'] ?? null,
    'current_outline_title' => $toc[0]['title'] ?? null,
    'current_view_mode' => $toc[0]['view_mode'] ?? null,
    'current_view_top' => $toc[0]['view_parameters']['top'] ?? null,
    'current_navigation_color' => $navigation['outline'][0]['text_color_hex'] ?? null,
    'stale_outline_excluded' => !str_contains($encodedReview, 'Stale XRef Stream Root Outline'),
    'stale_action_excluded' => !str_contains($encodedReview, 'stale xref stream root outline action'),
    'visible_wordpress_text' => $plainText,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

echo "<!-- wp:paragraph {\"metadata\":{\"markerpdfSource\":\"xref-stream-root-boundary\",\"markerpdfPage\":1}} -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:list {\"metadata\":{\"markerpdfDocumentOutlineSource\":\"xref-stream-root-boundary\"}} -->\n";
echo '<ul><li><a href="#page-1">' . htmlspecialchars((string) ($toc[0]['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</a></li></ul>\n";
echo "<!-- /wp:list -->\n";
