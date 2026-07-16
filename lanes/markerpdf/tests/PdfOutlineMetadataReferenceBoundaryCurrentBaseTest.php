<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataReferenceBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Outline metadata reference boundary body) Tj ET';
    $inlinePayload = 'Inline outline metadata payload must stay hidden';
    $nonStreamPayload = 'Resolved non-stream outline metadata payload must stay hidden';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 8 0 R /Count 3 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Direct Metadata Operand) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R /Metadata << /Type /Metadata /Subtype /XML /Private ({$inlinePayload}) >> >>\nendobj\n"
        . "7 0 obj\n<< /Title (Unresolved Metadata Operand) /Parent 5 0 R /Prev 6 0 R /Dest [3 0 R /FitH 680] /Next 8 0 R /Metadata 99 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Title (Non Stream Metadata Operand) /Parent 5 0 R /Prev 7 0 R /Dest [3 0 R /FitH 640] /Metadata 12 0 R >>\nendobj\n"
        . "12 0 obj\n<< /Type /Metadata /Subtype /XML /Private ({$nonStreamPayload}) /Length 47 >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'records malformed outline Metadata operand shapes as fail-closed review rows' => static function (
        TestRunner $t
    ) use ($outlineMetadataReferenceBoundaryPdf): void {
        $pdf = $outlineMetadataReferenceBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $items = $metadata['document_outline']['items'] ?? [];

        $t->same(['Direct Metadata Operand', 'Unresolved Metadata Operand', 'Non Stream Metadata Operand'], array_column($items, 'title'));
        $t->same(3, $metadata['document_outline']['item_count'] ?? null);
        $t->same(3, $metadata['document_outline']['resolved_destination_count'] ?? null);

        $direct = $items[0]['metadata_stream_review'] ?? [];
        $t->same('outline_item_metadata_stream', $direct['source'] ?? null);
        $t->same('rejected_non_indirect_metadata_reference', $direct['status'] ?? null);
        $t->same('dictionary', $direct['operand_shape'] ?? null);
        $t->same(true, $direct['indirect_reference_required'] ?? null);
        $t->same(true, $direct['review_only'] ?? null);
        $t->same(false, $direct['payload_included'] ?? null);
        $t->same(false, $direct['accepted_as_document_xmp'] ?? null);
    },
    'distinguishes unresolved and resolved non-stream outline Metadata references' => static function (
        TestRunner $t
    ) use ($outlineMetadataReferenceBoundaryPdf): void {
        $pdf = $outlineMetadataReferenceBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $items = $metadata['document_outline']['items'] ?? [];

        $unresolved = $items[1]['metadata_stream_review'] ?? [];
        $t->same('unresolved_metadata_reference', $unresolved['status'] ?? null);
        $t->same(99, $unresolved['object_number'] ?? null);
        $t->same('indirect_reference', $unresolved['operand_shape'] ?? null);
        $t->same(false, $unresolved['metadata_reference_resolved'] ?? null);
        $t->same(true, $unresolved['indirect_reference_required'] ?? null);

        $nonStream = $items[2]['metadata_stream_review'] ?? [];
        $t->same('rejected_non_stream_outline_item_metadata', $nonStream['status'] ?? null);
        $t->same(12, $nonStream['object_number'] ?? null);
        $t->same(true, $nonStream['metadata_reference_resolved'] ?? null);
        $t->same(false, $nonStream['has_stream'] ?? null);
        $t->same('Metadata', $nonStream['type'] ?? null);
        $t->same('XML', $nonStream['subtype'] ?? null);
        $t->same(47, $nonStream['declared_length'] ?? null);
        $t->same(true, $nonStream['review_only'] ?? null);
        $t->same(false, $nonStream['payload_included'] ?? null);
        $t->same(false, $nonStream['visible_text_source'] ?? null);
        $t->same(false, $nonStream['accepted_as_document_xmp'] ?? null);
    },
    'keeps malformed outline Metadata references out of navigation and visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineMetadataReferenceBoundaryPdf): void {
        $pdf = $outlineMetadataReferenceBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(['Direct Metadata Operand', 'Unresolved Metadata Operand', 'Non Stream Metadata Operand'], array_column($toc, 'title'));
        $t->same(['Direct Metadata Operand', 'Unresolved Metadata Operand', 'Non Stream Metadata Operand'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same('Outline metadata reference boundary body', $plainText);
        foreach ([
            'Inline outline metadata payload must stay hidden',
            'Resolved non-stream outline metadata payload must stay hidden',
        ] as $payload) {
            $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, $payload));
            $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, $payload));
            $t->true(!str_contains($plainText, $payload));
        }
        $t->true(!str_contains($plainText, 'Direct Metadata Operand'));
        $t->true(!str_contains($plainText, 'Unresolved Metadata Operand'));
        $t->true(!str_contains($plainText, 'Non Stream Metadata Operand'));
    },
];
