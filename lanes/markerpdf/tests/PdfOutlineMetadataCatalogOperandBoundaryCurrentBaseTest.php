<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineCatalogOperandBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Catalog outline operand boundary body) Tj ET';
    $hiddenPayload = 'BT /F1 12 Tf 72 720 Td (Ambiguous catalog outline payload must stay hidden) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R 8 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Ambiguous Catalog Outline Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 9 0 R /A 12 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Outlines /First 11 0 R /Last 11 0 R /Count 1 >>\nendobj\n"
        . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Length " . strlen($hiddenPayload) . " >>\nstream\n{$hiddenPayload}\nendstream\nendobj\n"
        . "11 0 obj\n<< /Title (Trailing Catalog Outline Operand) /Parent 8 0 R /Dest [3 0 R /Fit] >>\nendobj\n"
        . "12 0 obj\n<< /S /URI /URI (https://example.com/ambiguous-catalog-outline) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'rejects malformed catalog Outlines operands before document outline promotion' => static function (
        TestRunner $t
    ) use ($outlineCatalogOperandBoundaryPdf): void {
        $pdf = $outlineCatalogOperandBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $review = $metadata['document_outline_boundary_review'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->true(!array_key_exists('document_outline', $metadata));
        $t->same('catalog_outlines_operand_boundary', $review['source'] ?? null);
        $t->same(true, $review['review_only'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(false, $review['visible_text_source'] ?? null);
        $t->same('rejected_malformed_catalog_outlines_operand', $review['status'] ?? null);
        $t->same(1, $review['outlines_entry_count'] ?? null);
        $t->same(2, $review['outlines_operand_count'] ?? null);
        $t->same(5, $review['selected_outline_root_object'] ?? null);
        $t->same([8], $review['trailing_reference_object_numbers'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Ambiguous Catalog Outline Chapter'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Catalog Outline Operand'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Ambiguous catalog outline payload must stay hidden'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'ambiguous-catalog-outline'));
    },
    'keeps ambiguous catalog Outlines operands out of TOC navigation and visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineCatalogOperandBoundaryPdf): void {
        $pdf = $outlineCatalogOperandBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $textExtractor = new PdfTextExtractor();
        $lightweight = $textExtractor->extractOutlineMetadata($pdf);
        $plainText = $textExtractor->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $lightweightEncoded = json_encode($lightweight, JSON_UNESCAPED_SLASHES);

        $t->same([], $toc);
        $t->same([], $lightweight['pdf_toc'] ?? []);
        $t->same([], $navigation['outline'] ?? []);
        $t->same([], $navigation['outline_action_review_actions'] ?? []);
        $t->same('Catalog outline operand boundary body', $plainText);
        $t->true(is_string($lightweightEncoded) && !str_contains($lightweightEncoded, 'Ambiguous Catalog Outline Chapter'));
        $t->true(is_string($lightweightEncoded) && !str_contains($lightweightEncoded, 'Trailing Catalog Outline Operand'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Ambiguous Catalog Outline Chapter'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Trailing Catalog Outline Operand'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'ambiguous-catalog-outline'));
        $t->true(!str_contains($plainText, 'Ambiguous Catalog Outline Chapter'));
        $t->true(!str_contains($plainText, 'Trailing Catalog Outline Operand'));
        $t->true(!str_contains($plainText, 'Ambiguous catalog outline payload must stay hidden'));
    },
];
