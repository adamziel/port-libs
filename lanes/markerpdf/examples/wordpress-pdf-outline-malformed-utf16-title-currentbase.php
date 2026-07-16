<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$introContent = 'BT /F1 12 Tf 72 720 Td (Outline malformed UTF16 title intro body) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline malformed UTF16 title appendix body) Tj ET';
$validUtf16Title = '<FEFF00430075007200720065006E00740020005200650076006900650077>';
$malformedUtf16Title = '<FEFF004D0061006C0066006F0072006D0065FF>';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title {$validUtf16Title} /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title {$malformedUtf16Title} /Parent 5 0 R /Prev 6 0 R /A 12 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /GoToR /F (malformed-title-remote.pdf) /D (malformed-title-target) /NewWindow true >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['document_outline']['titles'] ?? []) !== ['Current Review'] || array_column($toc, 'title') !== ['Current Review']) {
    throw new RuntimeException('Expected only the valid UTF-16 outline title to reach review metadata.');
}
if (($navigation['outline_action_review_actions'] ?? []) !== [] || $remoteActions !== []) {
    throw new RuntimeException('Expected malformed-title outline action operands to fail closed.');
}
if (!is_string($encodedNavigation) || str_contains($encodedNavigation, 'malformed-title-remote.pdf')) {
    throw new RuntimeException('Expected malformed remote action target to stay out of navigation review metadata.');
}
if (!is_string($encodedMetadata) || str_contains($encodedMetadata, 'Malformed')) {
    throw new RuntimeException('Expected malformed UTF-16 outline title to stay out of document metadata.');
}
if (str_contains($plainText, 'Current Review') || str_contains($plainText, 'malformed-title-remote.pdf')) {
    throw new RuntimeException('Expected outline metadata and remote action operands to stay out of visible text.');
}

echo '<!-- markerpdf-outline-malformed-utf16-title-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-malformed-utf16-title-currentbase',
    'support_component' => 'native-pdf-outline-title-decoder',
    'native_boundary' => 'malformed UTF-16 outline titles are treated as absent before TOC, navigation, remote-action, and document-outline review metadata',
    'outline_titles' => $metadata['document_outline']['titles'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'malformed_title_rejected' => is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Malformed'),
    'malformed_remote_action_rejected' => $remoteActions === []
        && is_string($encodedNavigation)
        && !str_contains($encodedNavigation, 'malformed-title-remote.pdf'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Current Review')
        && !str_contains($plainText, 'malformed-title-remote.pdf'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline review\"><ul>\n";
foreach ($toc as $item) {
    echo '<li data-marker-outline-page="' . htmlspecialchars((string) (($item['page'] ?? 0) + 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
