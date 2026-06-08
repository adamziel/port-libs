<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineRootTraversalReviewBoundaryPdf = static function (): string {
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Outline root traversal review visible body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R 9 0 R /Last 6 0 R /Count 1 11 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Title (Suppressed Root Traversal Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /A 12 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Type /Outlines /First 10 0 R /Last 10 0 R /Count 1 >>\nendobj\n"
        . "10 0 obj\n<< /Title (Hidden Root Traversal Decoy) /Parent 9 0 R /Dest [3 0 R /Fit] >>\nendobj\n"
        . "11 0 obj\n<< /Type /Outlines /First 13 0 R /Last 13 0 R /Count 1 >>\nendobj\n"
        . "12 0 obj\n<< /S /URI /URI (https://example.com/root-traversal-review-action) >>\nendobj\n"
        . "13 0 obj\n<< /Title (Hidden Count Operand Decoy) /Parent 11 0 R /Dest [3 0 R /Fit] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'records malformed outline root traversal operands as review metadata' => static function (
        TestRunner $t
    ) use ($outlineRootTraversalReviewBoundaryPdf): void {
        $pdf = $outlineRootTraversalReviewBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $review = $outline['outline_root_traversal_operand_boundary_review'] ?? [];
        $entries = $review['entries'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(6, $outline['last_item_object'] ?? null);
        $t->same(null, $outline['outline_count'] ?? null);
        $t->same(0, $outline['item_count'] ?? null);
        $t->same([], $outline['titles'] ?? null);

        $t->same(2, $outline['outline_root_traversal_operand_boundary_count'] ?? null);
        $t->same(['First', 'Count'], $outline['outline_root_traversal_operand_boundary_keys'] ?? null);
        $t->same([
            'rejected_malformed_outline_root_first_operand',
            'rejected_malformed_outline_root_count_operand',
        ], $outline['outline_root_traversal_operand_boundary_statuses'] ?? null);
        $t->same([9, 11], $outline['outline_root_traversal_operand_boundary_trailing_reference_objects'] ?? null);
        $t->same(true, $outline['outline_root_traversal_operand_boundary_review_only'] ?? null);
        $t->same(false, $outline['outline_root_traversal_operand_boundary_payload_included'] ?? null);
        $t->same(false, $outline['outline_root_traversal_operand_boundary_navigation_promoted'] ?? null);

        $t->same('outline_root_traversal_operand_boundary', $review['source'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(false, $review['visible_text_source'] ?? null);
        $t->same(false, $review['item_traversal_promoted'] ?? null);
        $t->same(false, $review['navigation_promoted'] ?? null);
        $t->same(['First', 'Count'], $review['keys'] ?? null);
        $t->same([9, 11], $review['trailing_reference_object_numbers'] ?? null);
        $t->same(2, count(is_array($entries) ? $entries : []));

        $t->same('First', $entries[0]['key'] ?? null);
        $t->same('indirect_reference', $entries[0]['operand_shape'] ?? null);
        $t->same(6, $entries[0]['object_number'] ?? null);
        $t->same([9], $entries[0]['trailing_reference_object_numbers'] ?? null);
        $t->same(['indirect_reference'], $entries[0]['trailing_operand_shapes'] ?? null);
        $t->same(false, $entries[0]['item_traversal_promoted'] ?? null);

        $t->same('Count', $entries[1]['key'] ?? null);
        $t->same('number', $entries[1]['operand_shape'] ?? null);
        $t->same([11], $entries[1]['trailing_reference_object_numbers'] ?? null);
        $t->same(['indirect_reference'], $entries[1]['trailing_operand_shapes'] ?? null);

        foreach ([
            'Suppressed Root Traversal Chapter',
            'Hidden Root Traversal Decoy',
            'Hidden Count Operand Decoy',
            'root-traversal-review-action',
        ] as $hidden) {
            $t->true(is_string($encoded) && !str_contains($encoded, $hidden));
        }
    },
    'carries malformed root traversal review into navigation without visible text leakage' => static function (
        TestRunner $t
    ) use ($outlineRootTraversalReviewBoundaryPdf): void {
        $pdf = $outlineRootTraversalReviewBoundaryPdf();
        $textExtractor = new PdfTextExtractor();
        $navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
        $lightweight = $textExtractor->extractOutlineMetadata($pdf);
        $plainText = $textExtractor->extractPlainText($pdf);
        $rootReview = $navigation['outline_root_review'] ?? [];
        $boundaryReview = $rootReview['outline_root_traversal_operand_boundary_review'] ?? [];
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $encodedLightweight = json_encode($lightweight, JSON_UNESCAPED_SLASHES);

        $t->true(in_array('outline_root_review', $navigation['source'] ?? [], true));
        $t->same([], $navigation['outline'] ?? []);
        $t->same([], $navigation['outline_action_review_actions'] ?? []);
        $t->same([], $lightweight['pdf_toc'] ?? []);
        $t->same('outline_root_review', $rootReview['source'] ?? null);
        $t->same(5, $rootReview['outline_root_object'] ?? null);
        $t->same(2, $rootReview['outline_root_traversal_operand_boundary_count'] ?? null);
        $t->same(['First', 'Count'], $rootReview['outline_root_traversal_operand_boundary_keys'] ?? null);
        $t->same('outline_root_traversal_operand_boundary', $boundaryReview['source'] ?? null);
        $t->same(false, $boundaryReview['navigation_promoted'] ?? null);
        $t->same([9, 11], $boundaryReview['trailing_reference_object_numbers'] ?? null);

        $t->same('Outline root traversal review visible body', $plainText);
        foreach ([
            'Suppressed Root Traversal Chapter',
            'Hidden Root Traversal Decoy',
            'Hidden Count Operand Decoy',
            'root-traversal-review-action',
        ] as $hidden) {
            $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, $hidden));
            $t->true(is_string($encodedLightweight) && !str_contains($encodedLightweight, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
