<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineStructureElementActionBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Outline SE action boundary visible body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /StructTreeRoot 50 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 7 0 R >> >> /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (SE Action Boundary Chapter) /Parent 5 0 R /Dest /CurrentTarget /SE 12 0 R /A 13 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "12 0 obj\n<< /S /JavaScript /JS (app.alert('outline se action should not become structure metadata')) /P 50 0 R /K 0 >>\nendobj\n"
        . "13 0 obj\n<< /S /GoTo /D /CurrentTarget /Next 14 0 R >>\nendobj\n"
        . "14 0 obj\n<< /S /URI /URI (https://example.com/outline-se-action-review) >>\nendobj\n"
        . "20 0 obj\n<< /Names [(CurrentTarget) [3 0 R /FitH 720]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /StructTreeRoot /K [] >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'rejects action dictionaries misreferenced as outline SE structure metadata' => static function (
        TestRunner $t
    ) use ($outlineStructureElementActionBoundaryPdf): void {
        $pdf = $outlineStructureElementActionBoundaryPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $item = $outline['items'][0] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(['SE Action Boundary Chapter'], $outline['titles'] ?? []);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(0, $item['page'] ?? null);
        $t->same(3, $item['page_object'] ?? null);
        $t->same('CurrentTarget', $item['destination'] ?? null);
        $t->same('FitH', $item['view_mode'] ?? null);
        $t->same(true, $item['action_review_only'] ?? null);
        $t->same(['GoTo', 'URI'], $item['action_chain_types'] ?? []);
        $t->same(false, array_key_exists('structure_element', $item));
        $t->same(false, array_key_exists('structure_element_object', $item));
        $t->same(false, array_key_exists('structure_element_role', $item));
        $t->same(false, array_key_exists('structure_element_count', $outline));
        $t->same(false, array_key_exists('structure_element_roles', $outline));
        $t->true(is_string($encoded) && !str_contains($encoded, 'outline se action should not become structure metadata'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'outline-se-action-review'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'JavaScript","role"'));
    },
    'keeps invalid outline SE action dictionaries out of navigation structure rows and visible text' => static function (
        TestRunner $t
    ) use ($outlineStructureElementActionBoundaryPdf): void {
        $pdf = $outlineStructureElementActionBoundaryPdf();

        $metadata = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $outline = $metadata['outline'][0] ?? [];
        $actions = $metadata['outline_action_review_actions'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->true(in_array('outline', $metadata['source'], true));
        $t->true(in_array('outline_actions', $metadata['source'], true));
        $t->same('SE Action Boundary Chapter', $outline['title'] ?? null);
        $t->same(6, $outline['outline_object'] ?? null);
        $t->same(0, $outline['page'] ?? null);
        $t->same(3, $outline['page_object'] ?? null);
        $t->same('FitH', $outline['view_mode'] ?? null);
        $t->same(['GoTo', 'URI'], array_column($actions, 'action_type'));
        $t->same(['local-destination', 'review-uri'], array_column($actions, 'safety'));
        $t->same(false, array_key_exists('structure_element', $outline));
        $t->same(false, array_key_exists('structure_element_object', $outline));
        $t->same(false, array_key_exists('structure_element_role', $outline));
        foreach ($actions as $action) {
            $t->same(6, $action['outline_object'] ?? null);
            $t->same(false, array_key_exists('outline_structure_element', $action));
            $t->same(false, array_key_exists('outline_structure_element_object', $action));
            $t->same(false, array_key_exists('outline_structure_element_role', $action));
        }
        $t->same('https://example.com/outline-se-action-review', $actions[1]['uri'] ?? null);
        $t->same(false, $actions[1]['executes_on_import'] ?? null);
        $t->same('Outline SE action boundary visible body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'outline se action should not become structure metadata'));
        $t->true(!str_contains($plainText, 'SE Action Boundary Chapter'));
        $t->true(!str_contains($plainText, 'outline se action should not become structure metadata'));
        $t->true(!str_contains($plainText, 'outline-se-action-review'));
    },
];
