<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$visibleContent = 'BT /F1 12 Tf 72 720 Td (WordPress no-page outline root review visible body) Tj ET';
$rootMetadataPayload = '<x:xmpmeta>WordPress no-page outline root metadata payload must stay hidden</x:xmpmeta>';
$rootMetadataStream = gzcompress($rootMetadataPayload);
if (!is_string($rootMetadataStream)) {
    throw new RuntimeException('Unable to compress WordPress no-page outline root metadata payload.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /Metadata 8 0 R /Private /A 20 0 R /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress No Page Root Review Chapter) /Parent 5 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($rootMetadataStream) . " >>\nstream\n{$rootMetadataStream}\nendstream\nendobj\n"
    . "20 0 obj\n<< /S /URI /URI (https://example.com/hidden-wordpress-no-page-outline-root-action) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$lightweight = (new PdfTextExtractor())->extractOutlineMetadata($pdf);
$outline = $metadata['document_outline'] ?? [];
$metadataReview = $outline['metadata_stream_review'] ?? [];
$navigationRoot = $navigation['outline_root_review'] ?? [];
$navigationReview = $navigationRoot['metadata_stream_review'] ?? [];
$encoded = json_encode([$metadata, $navigation, $lightweight], JSON_UNESCAPED_SLASHES);

if (($metadataReview['status'] ?? null) !== 'rejected_malformed_outline_root_metadata_operand') {
    throw new RuntimeException('Expected malformed no-page outline root metadata operand review.');
}
if (($navigation['source'] ?? []) !== ['outline_root_review']) {
    throw new RuntimeException('Expected no-page navigation metadata to retain outline root review only.');
}
if (($navigation['outline'] ?? null) !== []) {
    throw new RuntimeException('Expected no-page navigation outline rows to stay empty.');
}
if ($metadataReview !== $navigationReview) {
    throw new RuntimeException('Expected navigation metadata to reuse the sanitized outline root metadata review.');
}
if ($plainText !== 'WordPress no-page outline root review visible body') {
    throw new RuntimeException('Expected visible WordPress text to exclude outline metadata.');
}
if (!is_string($encoded) || str_contains($encoded, $rootMetadataPayload)) {
    throw new RuntimeException('Expected hidden no-page outline root metadata payload to stay out of review JSON.');
}
if (str_contains($plainText, 'WordPress No Page Root Review Chapter') || str_contains($plainText, 'hidden-wordpress-no-page-outline-root-action')) {
    throw new RuntimeException('Expected outline title/action metadata to stay out of visible text.');
}

echo '<!-- markerpdf-outline-no-page-root-review-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-no-page-root-review-currentbase',
    'support_component' => 'native-pdf-outline-root-metadata-review',
    'native_boundary' => 'no-page PDFs preserve sanitized outline root /Metadata review while keeping navigation rows empty',
    'navigation_sources' => $navigation['source'] ?? [],
    'outline_rows' => count($navigation['outline'] ?? []),
    'root_metadata_status' => $metadataReview['status'] ?? null,
    'root_metadata_operand_count' => $metadataReview['metadata_operand_count'] ?? null,
    'trailing_reference_object_numbers' => $metadataReview['trailing_reference_object_numbers'] ?? [],
    'payload_included' => $metadataReview['payload_included'] ?? null,
    'visible_text_source' => $metadataReview['visible_text_source'] ?? null,
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'WordPress No Page Root Review Chapter')
        && !str_contains($plainText, 'WordPress no-page outline root metadata payload must stay hidden'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
