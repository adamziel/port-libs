<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineStructureElementOperandBoundaryPdf = static function (): array {
    $pageText = 'BT /F1 12 Tf /ChapterTitle << /MCID 0 >> BDC 72 720 Td (Outline SE operand boundary visible body) Tj EMC ET';
    $selectedPayload = '<wp-export><post id="selected-outline-se"/></wp-export>';
    $hiddenPayload = '<wp-export><post id="hidden-outline-se"/></wp-export>';
    $selectedChecksum = strtoupper(hash('md5', $selectedPayload));
    $hiddenChecksum = strtoupper(hash('md5', $hiddenPayload));

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /MarkInfo << /Marked true >> /StructTreeRoot 50 0 R /Outlines 40 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 7 0 R >> >> /Contents 30 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Outlines /First 41 0 R /Last 41 0 R /Count 1 >>\nendobj\n"
        . "41 0 obj\n<< /Title (Malformed SE Operand Boundary) /Parent 40 0 R /Dest [3 0 R /FitH 720] /SE 60 0 R 61 0 R /A 42 0 R /F 3 >>\nendobj\n"
        . "42 0 obj\n<< /S /GoTo /D [3 0 R /FitH 720] /Next 43 0 R >>\nendobj\n"
        . "43 0 obj\n<< /S /URI /URI (https://example.com/outline-se-operand-boundary) >>\nendobj\n"
        . "50 0 obj\n<< /Type /StructTreeRoot /RoleMap << /ChapterTitle /H1 /HiddenTitle /H2 >> /ParentTree 55 0 R /K [60 0 R 61 0 R] >>\nendobj\n"
        . "55 0 obj\n<< /Nums [0 [60 0 R 61 0 R]] >>\nendobj\n"
        . "60 0 obj\n<< /Type /StructElem /S /ChapterTitle /P 50 0 R /Pg 3 0 R /T (Selected SE Title) /Alt (Selected SE Alt Summary) /AF [70 0 R] /K << /Type /MCR /Pg 3 0 R /MCID 0 >> >>\nendobj\n"
        . "61 0 obj\n<< /Type /StructElem /S /HiddenTitle /P 50 0 R /Pg 3 0 R /T (Hidden Trailing SE Title) /Alt (Hidden Trailing SE Alt Summary) /AF [72 0 R] /K << /Type /MCR /Pg 3 0 R /MCID 0 >> >>\nendobj\n"
        . "70 0 obj\n<< /Type /Filespec /F (selected-outline-source.xml) /Desc (Selected outline source payload) /AFRelationship /Source /EF << /F 71 0 R >> >>\nendobj\n"
        . "71 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($selectedPayload) . " /CheckSum <{$selectedChecksum}> >> /Length " . strlen($selectedPayload) . " >>\nstream\n{$selectedPayload}\nendstream\nendobj\n"
        . "72 0 obj\n<< /Type /Filespec /F (hidden-outline-source.xml) /Desc (Hidden outline source payload) /AFRelationship /Alternative /EF << /F 73 0 R >> >>\nendobj\n"
        . "73 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($hiddenPayload) . " /CheckSum <{$hiddenChecksum}> >> /Length " . strlen($hiddenPayload) . " >>\nstream\n{$hiddenPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $selectedPayload, $hiddenPayload];
};

return [
    'rejects malformed outline SE operands before promoting structure metadata' => static function (
        TestRunner $t
    ) use ($outlineStructureElementOperandBoundaryPdf): void {
        [$pdf, $selectedPayload, $hiddenPayload] = $outlineStructureElementOperandBoundaryPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $item = $outline['items'][0] ?? [];
        $review = $item['structure_element_boundary_review'] ?? [];
        $outlineEncoded = json_encode($outline, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(['Malformed SE Operand Boundary'], $outline['titles'] ?? []);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $item['page'] ?? null);
        $t->same('FitH', $item['view_mode'] ?? null);
        $t->same(['GoTo', 'URI'], $item['action_chain_types'] ?? []);

        $t->same('outline_item_structure_element_boundary', $review['source'] ?? null);
        $t->same('rejected_malformed_outline_item_structure_element_operand', $review['status'] ?? null);
        $t->same(true, $review['review_only'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(false, $review['visible_text_source'] ?? null);
        $t->same(false, $review['structure_element_promoted'] ?? null);
        $t->same(1, $review['structure_element_entry_count'] ?? null);
        $t->same(0, $review['selected_entry_index'] ?? null);
        $t->same(2, $review['structure_element_operand_count'] ?? null);
        $t->same('indirect_reference', $review['operand_shape'] ?? null);
        $t->same(60, $review['object_number'] ?? null);
        $t->same(0, $review['object_generation'] ?? null);
        $t->same([61], $review['trailing_reference_object_numbers'] ?? null);
        $t->same(['indirect_reference'], $review['trailing_operand_shapes'] ?? null);
        $t->same(1, $outline['structure_element_boundary_review_count'] ?? null);
        $t->same(['rejected_malformed_outline_item_structure_element_operand'], $outline['structure_element_boundary_statuses'] ?? null);
        $t->same([60], $outline['structure_element_boundary_objects'] ?? null);
        $t->same([61], $outline['structure_element_boundary_trailing_reference_objects'] ?? null);

        $t->same(false, array_key_exists('structure_element', $item));
        $t->same(false, array_key_exists('structure_element_object', $item));
        $t->same(false, array_key_exists('structure_element_role', $item));
        $t->same(false, array_key_exists('structure_element_count', $outline));
        $t->true(is_string($outlineEncoded) && !str_contains($outlineEncoded, 'Selected SE Title'));
        $t->true(is_string($outlineEncoded) && !str_contains($outlineEncoded, 'Hidden Trailing SE Title'));
        $t->true(is_string($outlineEncoded) && !str_contains($outlineEncoded, $selectedPayload));
        $t->true(is_string($outlineEncoded) && !str_contains($outlineEncoded, $hiddenPayload));
    },
    'carries malformed outline SE boundary review into navigation without visible text leakage' => static function (
        TestRunner $t
    ) use ($outlineStructureElementOperandBoundaryPdf): void {
        [$pdf, $selectedPayload, $hiddenPayload] = $outlineStructureElementOperandBoundaryPdf();

        $extractor = new PdfOutlineExtractor();
        $navigation = $extractor->getNavigationReviewMetadata($pdf);
        $directRows = $extractor->getOutlineStructureDestinationPageContext($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $outline = $navigation['outline'][0] ?? [];
        $directRow = $directRows[0] ?? [];
        $actions = $navigation['outline_action_review_actions'] ?? [];
        $outlineReview = $outline['structure_element_boundary_review'] ?? [];
        $directReview = $directRow['structure_element_boundary_review'] ?? [];

        $t->true(in_array('outline', $navigation['source'], true));
        $t->true(in_array('outline_actions', $navigation['source'], true));
        $t->same('Malformed SE Operand Boundary', $outline['title'] ?? null);
        $t->same(41, $outline['outline_object'] ?? null);
        $t->same(0, $outline['page'] ?? null);
        $t->same('FitH', $outline['view_mode'] ?? null);
        $t->same('rejected_malformed_outline_item_structure_element_operand', $outlineReview['status'] ?? null);
        $t->same([61], $outlineReview['trailing_reference_object_numbers'] ?? null);
        $t->same('rejected_malformed_outline_item_structure_element_operand', $directReview['status'] ?? null);
        $t->same(60, $directReview['object_number'] ?? null);
        $t->same(false, array_key_exists('structure_element', $outline));
        $t->same(false, array_key_exists('structure_element_role', $outline));
        $t->same(false, array_key_exists('structure_element', $directRow));

        $t->same(['GoTo', 'URI'], array_column($actions, 'action_type'));
        $t->same(['local-destination', 'review-uri'], array_column($actions, 'safety'));
        foreach ($actions as $action) {
            $actionReview = $action['outline_structure_element_boundary_review'] ?? [];
            $t->same(41, $action['outline_object'] ?? null);
            $t->same('rejected_malformed_outline_item_structure_element_operand', $actionReview['status'] ?? null);
            $t->same(false, $actionReview['structure_element_promoted'] ?? null);
            $t->same(false, array_key_exists('outline_structure_element', $action));
            $t->same(false, array_key_exists('outline_structure_element_role', $action));
        }
        $outlineOwnedReviewEncoded = json_encode(
            [
                $outlineReview,
                $directReview,
                array_map(
                    static fn (array $action): array => is_array($action['outline_structure_element_boundary_review'] ?? null)
                        ? $action['outline_structure_element_boundary_review']
                        : [],
                    $actions
                ),
            ],
            JSON_UNESCAPED_SLASHES
        );

        $t->same('https://example.com/outline-se-operand-boundary', $actions[1]['uri'] ?? null);
        $t->same('Outline SE operand boundary visible body', $plainText);
        $t->true(is_string($outlineOwnedReviewEncoded) && !str_contains($outlineOwnedReviewEncoded, 'Selected SE Title'));
        $t->true(is_string($outlineOwnedReviewEncoded) && !str_contains($outlineOwnedReviewEncoded, 'Hidden Trailing SE Title'));
        $t->true(is_string($outlineOwnedReviewEncoded) && !str_contains($outlineOwnedReviewEncoded, $selectedPayload));
        $t->true(is_string($outlineOwnedReviewEncoded) && !str_contains($outlineOwnedReviewEncoded, $hiddenPayload));
        $t->true(!str_contains($plainText, 'Malformed SE Operand Boundary'));
        $t->true(!str_contains($plainText, 'Selected SE Title'));
        $t->true(!str_contains($plainText, 'Hidden Trailing SE Title'));
        $t->true(!str_contains($plainText, 'outline-se-operand-boundary'));
        $t->true(!str_contains($plainText, '<wp-export>'));
    },
];
