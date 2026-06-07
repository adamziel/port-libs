<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$chapterContent = 'BT /F1 12 Tf 72 720 Td (Malformed item count chapter body) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (Malformed item count appendix body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 3 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Malformed Item Count Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R /First 8 0 R /Last 8 0 R /Count 0.5 >>\nendobj\n"
    . "7 0 obj\n<< /Title (Malformed Item Count Appendix) /Parent 5 0 R /Prev 6 0 R /Dest [4 0 R /Fit] >>\nendobj\n"
    . "8 0 obj\n<< /Title (Malformed Item Count Child) /Parent 6 0 R /Dest [3 0 R /XYZ 72 680 0] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($chapterContent) . " >>\nstream\n{$chapterContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "%%EOF";

$textExtractor = new PdfTextExtractor();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$lightweight = $textExtractor->extractOutlineMetadata($pdf);
$toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$encodedLightweight = json_encode($lightweight, JSON_UNESCAPED_SLASHES);

$expectedTitles = [
    'Malformed Item Count Chapter',
    'Malformed Item Count Child',
    'Malformed Item Count Appendix',
];

if (array_column($lightweight['pdf_toc'] ?? [], 'title') !== $expectedTitles) {
    throw new RuntimeException('Expected malformed decimal /Count to behave as absent in lightweight outline metadata.');
}
if (($metadata['document_outline']['titles'] ?? null) !== $expectedTitles) {
    throw new RuntimeException('Expected document outline metadata to stay aligned with lightweight outline metadata.');
}
if (array_column($toc, 'title') !== $expectedTitles) {
    throw new RuntimeException('Expected navigation TOC rows to stay aligned with lightweight outline metadata.');
}
if (!is_string($encodedLightweight) || str_contains($encodedLightweight, '0.5')) {
    throw new RuntimeException('Expected malformed count operand bytes to remain out of lightweight metadata payload.');
}
if (str_contains($plainText, 'Malformed Item Count Child')) {
    throw new RuntimeException('Expected outline metadata to stay out of visible WordPress paragraph text.');
}

echo '<!-- markerpdf-outline-lightweight-count-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-lightweight-count-boundary-currentbase',
    'support_component' => 'native-pdf-lightweight-outline-count-boundary',
    'native_boundary' => 'lightweight pdf_toc outline /Count accepts only a single signed integer token; malformed decimal counts behave as absent',
    'lightweight_titles' => array_column($lightweight['pdf_toc'] ?? [], 'title'),
    'document_outline_titles' => $metadata['document_outline']['titles'] ?? [],
    'navigation_titles' => array_column($toc, 'title'),
    'malformed_decimal_count_treated_as_absent' => true,
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Malformed Item Count Child'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline review\"><ul>\n";
foreach ($lightweight['pdf_toc'] ?? [] as $item) {
    echo '<li data-marker-outline-page="' . htmlspecialchars((string) ($item['page'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
