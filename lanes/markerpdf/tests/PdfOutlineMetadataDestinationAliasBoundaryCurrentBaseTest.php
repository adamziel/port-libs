<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineDestinationAliasBoundaryPdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Outline alias target visible body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 8 0 R /Count 3 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Alias Boundary Chapter) /Parent 5 0 R /Dest /AliasStart /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Alias Cycle Boundary Chapter) /Parent 5 0 R /Prev 6 0 R /Dest /CycleA /Next 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Title (Direct Destination Boundary Chapter) /Parent 5 0 R /Prev 7 0 R /Dest /FinalTarget >>\nendobj\n"
        . "20 0 obj\n<< /Names [(AliasStart) /FinalTarget (CycleA) /CycleB (CycleB) /CycleA (FinalTarget) [3 0 R /FitH 700]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'preserves outline destination alias chains in document metadata' => static function (
        TestRunner $t
    ) use ($outlineDestinationAliasBoundaryPdf): void {
        $pdf = $outlineDestinationAliasBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(8, $outline['last_item_object'] ?? null);
        $t->same(3, $outline['declared_visible_count'] ?? null);
        $t->same(3, $outline['item_count'] ?? null);
        $t->same(2, $outline['resolved_destination_count'] ?? null);
        $t->same(1, $outline['unresolved_destination_count'] ?? null);
        $t->same([
            'Alias Boundary Chapter',
            'Alias Cycle Boundary Chapter',
            'Direct Destination Boundary Chapter',
        ], $outline['titles'] ?? []);

        $t->same('Alias Boundary Chapter', $items[0]['title'] ?? null);
        $t->same('FinalTarget', $items[0]['destination'] ?? null);
        $t->same('AliasStart', $items[0]['declared_destination'] ?? null);
        $t->same(['AliasStart', 'FinalTarget'], $items[0]['destination_alias_chain'] ?? null);
        $t->same('FinalTarget', $items[0]['destination_target'] ?? null);
        $t->same(true, $items[0]['destination_alias_resolved'] ?? null);
        $t->same(false, $items[0]['destination_alias_cycle'] ?? null);
        $t->same(true, $items[0]['destination_resolved'] ?? null);
        $t->same(0, $items[0]['page'] ?? null);
        $t->same(3, $items[0]['page_object'] ?? null);
        $t->same('FitH', $items[0]['view_mode'] ?? null);
        $t->same(['top' => 700.0], $items[0]['view_parameters'] ?? null);

        $t->same('Alias Cycle Boundary Chapter', $items[1]['title'] ?? null);
        $t->same('CycleA', $items[1]['destination'] ?? null);
        $t->same('CycleA', $items[1]['declared_destination'] ?? null);
        $t->same(['CycleA', 'CycleB', 'CycleA'], $items[1]['destination_alias_chain'] ?? null);
        $t->same(true, $items[1]['destination_alias_cycle'] ?? null);
        $t->same(false, $items[1]['destination_alias_resolved'] ?? null);
        $t->same('destination_alias_cycle', $items[1]['destination_unresolved_reason'] ?? null);
        $t->same(false, $items[1]['destination_resolved'] ?? null);
        $t->true(!array_key_exists('page', $items[1]));
        $t->true(!array_key_exists('view_mode', $items[1]));

        $t->same('Direct Destination Boundary Chapter', $items[2]['title'] ?? null);
        $t->same('FinalTarget', $items[2]['destination'] ?? null);
        $t->same(true, $items[2]['destination_resolved'] ?? null);
        $t->true(!array_key_exists('declared_destination', $items[2]));
        $t->true(!array_key_exists('destination_alias_chain', $items[2]));

        $t->same('Outline alias target visible body', $plainText);
        foreach (['Alias Boundary Chapter', 'Alias Cycle Boundary Chapter', 'Direct Destination Boundary Chapter', 'AliasStart', 'FinalTarget', 'CycleA', 'CycleB'] as $reviewOnly) {
            $t->true(!str_contains($plainText, $reviewOnly));
        }
    },
    'keeps outline alias cycles out of TOC navigation while preserving alias review context' => static function (
        TestRunner $t
    ) use ($outlineDestinationAliasBoundaryPdf): void {
        $pdf = $outlineDestinationAliasBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(['Alias Boundary Chapter', 'Direct Destination Boundary Chapter'], array_column($toc, 'title'));
        $t->same(['AliasStart', 'FinalTarget'], array_column($toc, 'destination'));
        $t->same([0, 0], array_column($toc, 'page'));
        $t->same(['outline'], $navigation['source']);
        $t->same(['Alias Boundary Chapter', 'Direct Destination Boundary Chapter'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same('AliasStart', $navigation['outline'][0]['destination'] ?? null);
        $t->same('AliasStart', $navigation['outline'][0]['declared_destination'] ?? null);
        $t->same(['AliasStart', 'FinalTarget'], $navigation['outline'][0]['destination_alias_chain'] ?? null);
        $t->same('FinalTarget', $navigation['outline'][0]['destination_target'] ?? null);
        $t->same(true, $navigation['outline'][0]['destination_alias_resolved'] ?? null);
        $t->same(false, $navigation['outline'][0]['destination_alias_cycle'] ?? null);
        $t->true(!array_key_exists('declared_destination', $navigation['outline'][1] ?? []));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same('Outline alias target visible body', $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Alias Cycle Boundary Chapter'));
        $t->true(!str_contains($plainText, 'Alias Boundary Chapter'));
        $t->true(!str_contains($plainText, 'Alias Cycle Boundary Chapter'));
        $t->true(!str_contains($plainText, 'Direct Destination Boundary Chapter'));
    },
];
