<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$chapterContent = 'BT /F1 12 Tf 72 720 Td (Outline next operand chapter body) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline next operand appendix body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Next Operand Boundary Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 8 0 R 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Valid But Ambiguous Next Operand Target) /Parent 5 0 R /Prev 6 0 R /Dest /AppendixTarget >>\nendobj\n"
    . "8 0 obj\n<< /Title (Stale Next Operand Remote Review) /Parent 5 0 R /Prev 6 0 R /A 12 0 R /Next 7 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /GoToR /F (stale-next-operand-review.pdf) /D (stale-next) /NewWindow true >>\nendobj\n"
    . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($chapterContent) . " >>\nstream\n{$chapterContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES) ?: '';

if (($metadata['document_outline']['titles'] ?? []) !== ['Next Operand Boundary Chapter']) {
    throw new RuntimeException('Expected malformed /Next tail to stop document-outline traversal.');
}
if (array_column($toc, 'title') !== ['Next Operand Boundary Chapter']) {
    throw new RuntimeException('Expected malformed /Next tail to stop TOC traversal.');
}
if (($navigation['outline_action_review_actions'] ?? []) !== [] || $remoteActions !== []) {
    throw new RuntimeException('Expected stale remote outline action to stay out of review metadata.');
}
if (
    str_contains($encodedMetadata, 'Stale Next Operand Remote Review')
    || str_contains($encodedNavigation, 'stale-next-operand-review.pdf')
    || str_contains($plainText, 'Stale Next Operand Remote Review')
) {
    throw new RuntimeException('Expected stale malformed /Next operand target to stay hidden.');
}

echo '<!-- markerpdf-outline-next-operand-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-next-operand-boundary-currentbase',
    'support_component' => 'native-pdf-outline-sibling-reference-boundary',
    'native_boundary' => 'outline /Next references with trailing top-level operands stop traversal before stale sibling/action metadata',
    'outline_titles' => $metadata['document_outline']['titles'] ?? [],
    'toc_titles' => array_column($toc, 'title'),
    'raw_first_next_object_reviewed' => $metadata['document_outline']['items'][0]['next_object'] ?? null,
    'stale_next_operand_excluded' => !str_contains($encodedMetadata, 'Stale Next Operand Remote Review')
        && !str_contains($encodedNavigation, 'stale-next-operand-review.pdf'),
    'remote_actions_excluded' => $remoteActions === [],
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Next Operand Boundary Chapter')
        && !str_contains($plainText, 'Stale Next Operand Remote Review'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline review\"><ul>\n";
foreach ($metadata['document_outline']['items'] ?? [] as $item) {
    echo '<li data-marker-outline-object="' . htmlspecialchars((string) ($item['outline_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-next-object="' . htmlspecialchars((string) ($item['next_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
