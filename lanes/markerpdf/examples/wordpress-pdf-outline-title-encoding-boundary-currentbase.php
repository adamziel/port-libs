<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline encoding page one body) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline encoding page two body) Tj ET';
$pageThreeContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline encoding page three body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R 11 0 R] /Count 3 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 9 0 R /Count 3 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Import \\223nance \\200 Summary) /Parent 5 0 R /Dest [3 0 R /XYZ 72 720 0] /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title <52657669657720852044617368> /Parent 5 0 R /Prev 6 0 R /Dest [4 0 R /FitH 700] /Next 9 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Title 32 0 R /Parent 5 0 R /Prev 7 0 R /A << /S /GoTo /D [11 0 R /Fit] >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 33 0 R >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "32 0 obj\n(Checklist \\212 Sign)\nendobj\n"
    . "33 0 obj\n<< /Length " . strlen($pageThreeContent) . " >>\nstream\n{$pageThreeContent}\nendstream\nendobj\n"
    . "%%EOF";

$expectedTitles = [
    'Import ' . mb_chr(0xfb01, 'UTF-8') . 'nance ' . mb_chr(0x2022, 'UTF-8') . ' Summary',
    'Review ' . mb_chr(0x2013, 'UTF-8') . ' Dash',
    'Checklist ' . mb_chr(0x2212, 'UTF-8') . ' Sign',
];

$textExtractor = new PdfTextExtractor();
$outlineExtractor = new PdfOutlineExtractor();
$lightweight = $textExtractor->extractOutlineMetadata($pdf);
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

if (array_column($lightweight['pdf_toc'], 'title') !== $expectedTitles) {
    throw new RuntimeException('Expected lightweight outline metadata to decode PDFDocEncoding titles.');
}
if (array_column($toc, 'title') !== $expectedTitles || array_column($navigation['outline'] ?? [], 'title') !== $expectedTitles) {
    throw new RuntimeException('Expected outline TOC/navigation review to decode PDFDocEncoding titles.');
}
if (($metadata['document_outline']['titles'] ?? []) !== $expectedTitles) {
    throw new RuntimeException('Expected document outline metadata to preserve decoded PDFDocEncoding titles.');
}
foreach ($expectedTitles as $title) {
    if (str_contains($plainText, $title)) {
        throw new RuntimeException('Expected decoded outline titles to stay out of visible WordPress text.');
    }
}

echo '<!-- markerpdf-outline-title-encoding-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-title-encoding-boundary-currentbase',
    'support_component' => 'native-pdf-outline-text-string-decoder',
    'native_boundary' => 'outline /Title PDF text strings decode PDFDocEncoding for TOC/navigation metadata, not page body text',
    'outline_titles' => $metadata['document_outline']['titles'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'pdfdocencoding_literal_octals_decoded' => ($expectedTitles[0] ?? null) === ($metadata['document_outline']['titles'][0] ?? null),
    'pdfdocencoding_hex_string_decoded' => ($expectedTitles[1] ?? null) === ($metadata['document_outline']['titles'][1] ?? null),
    'pdfdocencoding_indirect_title_decoded' => ($expectedTitles[2] ?? null) === ($metadata['document_outline']['titles'][2] ?? null),
    'visible_text_excludes_outline_titles' => !str_contains($plainText, $expectedTitles[0])
        && !str_contains($plainText, $expectedTitles[1])
        && !str_contains($plainText, $expectedTitles[2]),
    'navigation_review_contains_decoded_titles' => is_string($encodedNavigation)
        && str_contains($encodedNavigation, $expectedTitles[0])
        && str_contains($encodedNavigation, $expectedTitles[1])
        && str_contains($encodedNavigation, $expectedTitles[2]),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline encoding review\"><ul>\n";
foreach ($metadata['document_outline']['items'] ?? [] as $item) {
    echo '<li data-marker-outline-level="' . (int) ($item['level'] ?? 0)
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
