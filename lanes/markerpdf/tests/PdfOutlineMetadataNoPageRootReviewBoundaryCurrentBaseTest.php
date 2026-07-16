<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineNoPageRootReviewBoundaryPdf = static function (): array {
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (No page outline root review visible body) Tj ET';
    $rootMetadataPayload = '<x:xmpmeta>No-page outline root metadata payload must stay hidden</x:xmpmeta>';
    $rootMetadataStream = gzcompress($rootMetadataPayload);
    if (!is_string($rootMetadataStream)) {
        throw new RuntimeException('Unable to compress no-page outline root metadata payload.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /Metadata 8 0 R /Private /A 20 0 R /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (No Page Root Review Chapter) /Parent 5 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($rootMetadataStream) . " >>\nstream\n{$rootMetadataStream}\nendstream\nendobj\n"
        . "20 0 obj\n<< /S /URI /URI (https://example.com/hidden-no-page-outline-root-action) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $rootMetadataPayload];
};

return [
    'preserves no-page outline root Metadata boundary review in navigation metadata' => static function (
        TestRunner $t
    ) use ($outlineNoPageRootReviewBoundaryPdf): void {
        [$pdf, $rootMetadataPayload] = $outlineNoPageRootReviewBoundaryPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $metadataReview = $outline['metadata_stream_review'] ?? [];
        $navigationRoot = $navigation['outline_root_review'] ?? [];
        $navigationReview = $navigationRoot['metadata_stream_review'] ?? [];
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(6, $outline['last_item_object'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(0, $outline['resolved_destination_count'] ?? null);
        $t->same(1, $outline['unresolved_destination_count'] ?? null);
        $t->same(['No Page Root Review Chapter'], $outline['titles'] ?? null);

        $t->same('outline_root_metadata_stream', $metadataReview['source'] ?? null);
        $t->same('rejected_malformed_outline_root_metadata_operand', $metadataReview['status'] ?? null);
        $t->same(true, $metadataReview['review_only'] ?? null);
        $t->same(false, $metadataReview['payload_included'] ?? null);
        $t->same(false, $metadataReview['visible_text_source'] ?? null);
        $t->same(false, $metadataReview['accepted_as_document_xmp'] ?? null);
        $t->same(1, $metadataReview['metadata_entry_count'] ?? null);
        $t->same(4, $metadataReview['metadata_operand_count'] ?? null);
        $t->same(8, $metadataReview['object_number'] ?? null);
        $t->same(0, $metadataReview['object_generation'] ?? null);
        $t->same([20], $metadataReview['trailing_reference_object_numbers'] ?? null);
        $t->same(['name', 'indirect_reference'], $metadataReview['trailing_operand_shapes'] ?? null);
        $t->same(['Private', 'A'], $metadataReview['trailing_operand_names'] ?? null);
        $t->true(!array_key_exists('bytes', $metadataReview));
        $t->true(!array_key_exists('sha256', $metadataReview));

        $t->same(['outline_root_review'], $navigation['source']);
        $t->same([], $navigation['outline']);
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same([], $navigation['open_action_review_actions']);
        $t->same([], $navigation['page_presentations']);
        $t->same([], $navigation['page_review']);
        $t->same('outline_root_review', $navigationRoot['source'] ?? null);
        $t->same(5, $navigationRoot['outline_root_object'] ?? null);
        $t->same(6, $navigationRoot['first_item_object'] ?? null);
        $t->same(6, $navigationRoot['last_item_object'] ?? null);
        $t->same($metadataReview, $navigationReview);
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, $rootMetadataPayload));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, 'hidden-no-page-outline-root-action'));
    },
    'keeps no-page outline root Metadata payloads out of lightweight WordPress text' => static function (
        TestRunner $t
    ) use ($outlineNoPageRootReviewBoundaryPdf): void {
        [$pdf, $rootMetadataPayload] = $outlineNoPageRootReviewBoundaryPdf();

        $textExtractor = new PdfTextExtractor();
        $plainText = $textExtractor->extractPlainText($pdf);
        $lightweight = $textExtractor->extractOutlineMetadata($pdf);
        $navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
        $encoded = json_encode([$lightweight, $navigation], JSON_UNESCAPED_SLASHES);

        $t->same('No page outline root review visible body', $plainText);
        $t->same(0, $lightweight['pages'] ?? null);
        $t->same([], $lightweight['pdf_toc'] ?? null);
        $t->same(['outline_root_review'], $navigation['source']);
        $t->same([], $navigation['outline']);
        $t->true(is_string($encoded) && !str_contains($encoded, $rootMetadataPayload));
        $t->true(is_string($encoded) && !str_contains($encoded, 'hidden-no-page-outline-root-action'));
        $t->true(!str_contains($plainText, 'No Page Root Review Chapter'));
        $t->true(!str_contains($plainText, 'No-page outline root metadata payload must stay hidden'));
        $t->true(!str_contains($plainText, 'hidden-no-page-outline-root-action'));
    },
];
