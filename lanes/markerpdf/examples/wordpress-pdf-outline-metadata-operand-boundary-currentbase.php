<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$visibleContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline metadata operand boundary body) Tj ET';
$arrayPayload = '<outline-metadata>WordPress array operand payload should stay review only</outline-metadata>';
$unusedPayload = '<outline-metadata>WordPress unused array metadata stream should stay hidden</outline-metadata>';
$arrayStream = gzcompress($arrayPayload);
$unusedStream = gzcompress($unusedPayload);
if (!is_string($arrayStream) || !is_string($unusedStream)) {
    throw new RuntimeException('Unable to compress WordPress outline metadata operand boundary payloads.');
}

$directPayload = 'WordPress direct outline Metadata dictionary payload should stay review only';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Import Array Metadata Operand) /Parent 5 0 R /Dest [3 0 R /Fit] /Metadata [8 0 R 9 0 R] /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Import Dictionary Metadata Operand) /Parent 5 0 R /Prev 6 0 R /Dest [3 0 R /FitH 640] /Metadata << /Type /Metadata /Subtype /XML /Note ({$directPayload}) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($arrayStream) . " >>\nstream\n{$arrayStream}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($unusedStream) . " >>\nstream\n{$unusedStream}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$outline = $metadata['document_outline'] ?? [];
$items = $outline['items'] ?? [];
$arrayReview = $items[0]['metadata_stream_review'] ?? [];
$dictionaryReview = $items[1]['metadata_stream_review'] ?? [];
$metadataEncoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($arrayReview['status'] ?? null) !== 'rejected_non_indirect_metadata_reference'
    || ($arrayReview['operand_shape'] ?? null) !== 'array'
    || ($dictionaryReview['operand_shape'] ?? null) !== 'dictionary'
) {
    throw new RuntimeException('Expected non-indirect outline /Metadata operands to fail closed with operand-shape review.');
}
if (($arrayReview['indirect_reference_required'] ?? null) !== true || ($dictionaryReview['indirect_reference_required'] ?? null) !== true) {
    throw new RuntimeException('Expected outline /Metadata operands to require one indirect stream reference.');
}
if (($outline['titles'] ?? []) !== ['Import Array Metadata Operand', 'Import Dictionary Metadata Operand']) {
    throw new RuntimeException('Expected valid outline rows to remain available for WordPress navigation review.');
}
if (array_column($toc, 'title') !== ['Import Array Metadata Operand', 'Import Dictionary Metadata Operand']) {
    throw new RuntimeException('Expected TOC extraction to remain aligned with rejected metadata operands.');
}
if (!is_string($metadataEncoded)
    || !is_string($navigationEncoded)
    || str_contains($metadataEncoded, $arrayPayload)
    || str_contains($metadataEncoded, $unusedPayload)
    || str_contains($metadataEncoded, $directPayload)
    || str_contains($navigationEncoded, $arrayPayload)
    || str_contains($navigationEncoded, $unusedPayload)
    || str_contains($navigationEncoded, $directPayload)
) {
    throw new RuntimeException('Expected rejected outline metadata payloads to stay out of WordPress review output.');
}
if (str_contains($plainText, 'Import Array Metadata Operand')
    || str_contains($plainText, 'Import Dictionary Metadata Operand')
    || str_contains($plainText, 'WordPress array operand payload')
    || str_contains($plainText, $directPayload)
) {
    throw new RuntimeException('Expected outline metadata operands to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-metadata-operand-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-operand-boundary-currentbase',
    'support_component' => 'native-pdf-outline-metadata-operand-boundary-review',
    'native_boundary' => 'outline item /Metadata must be one indirect metadata stream reference; arrays and direct dictionaries are review-only rejection rows',
    'outline_titles' => $outline['titles'] ?? [],
    'operand_shapes' => [$arrayReview['operand_shape'] ?? null, $dictionaryReview['operand_shape'] ?? null],
    'review_statuses' => [$arrayReview['status'] ?? null, $dictionaryReview['status'] ?? null],
    'indirect_reference_required' => ($arrayReview['indirect_reference_required'] ?? null) === true
        && ($dictionaryReview['indirect_reference_required'] ?? null) === true,
    'toc_titles' => array_column($toc, 'title'),
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'payloads_excluded_from_metadata' => is_string($metadataEncoded)
        && !str_contains($metadataEncoded, $arrayPayload)
        && !str_contains($metadataEncoded, $directPayload),
    'payloads_excluded_from_navigation' => is_string($navigationEncoded)
        && !str_contains($navigationEncoded, $arrayPayload)
        && !str_contains($navigationEncoded, $directPayload),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Import Array Metadata Operand')
        && !str_contains($plainText, 'WordPress array operand payload'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline metadata operand review\"><ul>\n";
foreach ($items as $item) {
    $review = $item['metadata_stream_review'] ?? [];
    echo '<li data-marker-outline-level="' . (int) ($item['level'] ?? 0)
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-metadata-operand-shape="' . htmlspecialchars((string) ($review['operand_shape'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
