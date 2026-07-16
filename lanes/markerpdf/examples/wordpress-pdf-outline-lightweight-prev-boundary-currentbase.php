<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$coverContent = 'BT /F1 12 Tf 72 720 Td (WordPress lightweight outline cover) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (WordPress lightweight outline appendix) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 8 0 R /Count 3 >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Lightweight Current Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Stale WordPress Lightweight Remote Review) /Parent 5 0 R /Prev 99 0 R /Dest [4 0 R /Fit] /A 12 0 R /Next 8 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Title (Untrusted WordPress Lightweight Tail) /Parent 5 0 R /Prev 7 0 R /Dest [4 0 R /Fit] >>\nendobj\n"
    . "12 0 obj\n<< /S /GoToR /F (stale-wordpress-lightweight-outline.pdf) /D (stale-lightweight-target) /NewWindow true >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($coverContent) . " >>\nstream\n{$coverContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Title (WordPress Lightweight Info Title) /Author (Current Lightweight Metadata Team) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 40 0 R >>\n"
    . "%%EOF";

$textExtractor = new PdfTextExtractor();
$lightweight = $textExtractor->extractOutlineMetadata($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$encodedLightweight = json_encode($lightweight, JSON_UNESCAPED_SLASHES);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (array_column($lightweight['pdf_toc'], 'title') !== ['WordPress Lightweight Current Chapter']) {
    throw new RuntimeException('Expected lightweight pdf_toc to stop before the bad /Prev outline sibling.');
}
if (($metadata['document_outline']['titles'] ?? []) !== ['WordPress Lightweight Current Chapter']) {
    throw new RuntimeException('Expected document outline metadata to stay aligned with lightweight pdf_toc.');
}
if (!is_string($encodedLightweight) || str_contains($encodedLightweight, 'Stale WordPress Lightweight Remote Review')) {
    throw new RuntimeException('Expected stale outline title to stay out of lightweight metadata.');
}
if (!is_string($encodedMetadata) || str_contains($encodedMetadata, 'stale-wordpress-lightweight-outline.pdf')) {
    throw new RuntimeException('Expected stale remote action target to stay out of document metadata.');
}
if (str_contains($plainText, 'WordPress Lightweight Current Chapter')
    || str_contains($plainText, 'Stale WordPress Lightweight Remote Review')
    || str_contains($plainText, 'Untrusted WordPress Lightweight Tail')
) {
    throw new RuntimeException('Expected outline titles to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-lightweight-prev-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-lightweight-prev-boundary-currentbase',
    'support_component' => 'native-pdf-lightweight-outline-toc-boundary',
    'native_boundary' => 'PdfTextExtractor pdf_toc stops on explicit mismatched outline /Prev backlinks before WordPress TOC block rendering',
    'pages' => $lightweight['pages'],
    'document_info_title' => $lightweight['document_info']['title'] ?? null,
    'pdf_toc_titles' => array_column($lightweight['pdf_toc'], 'title'),
    'document_outline_titles' => $metadata['document_outline']['titles'] ?? [],
    'stale_outline_excluded' => is_string($encodedLightweight) && !str_contains($encodedLightweight, 'Stale WordPress Lightweight Remote Review'),
    'stale_remote_action_excluded' => is_string($encodedMetadata) && !str_contains($encodedMetadata, 'stale-wordpress-lightweight-outline.pdf'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'WordPress Lightweight Current Chapter')
        && !str_contains($plainText, 'Stale WordPress Lightweight Remote Review'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline review\"><ul>\n";
foreach ($lightweight['pdf_toc'] as $item) {
    echo '<li data-marker-outline-level="' . (int) ($item['level'] ?? 0)
        . '" data-marker-outline-page="' . (int) ($item['page'] ?? 0)
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
