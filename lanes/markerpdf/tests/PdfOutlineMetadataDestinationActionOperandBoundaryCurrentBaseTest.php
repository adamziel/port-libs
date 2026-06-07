<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineDestinationActionOperandBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Outline destination action operand boundary intro body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline destination action operand boundary appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 9 0 R /Count 4 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Operand Boundary Current Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Tailed Dest Operand Review) /Parent 5 0 R /Prev 6 0 R /Dest /ChapterStart 12 0 R /Next 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Title (Tailed Action Operand Review) /Parent 5 0 R /Prev 7 0 R /A 13 0 R 14 0 R /Next 9 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Title (Operand Boundary Appendix) /Parent 5 0 R /Prev 8 0 R /Dest /AppendixTarget >>\nendobj\n"
        . "12 0 obj\n<< /S /GoToR /F (tailed-dest-decoy.pdf) /D (tailed-dest) /NewWindow true >>\nendobj\n"
        . "13 0 obj\n<< /S /GoTo /D [4 0 R /Fit] >>\nendobj\n"
        . "14 0 obj\n<< /S /GoToR /F (tailed-action-decoy.pdf) /D (tailed-action) /NewWindow true >>\nendobj\n"
        . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'keeps tailed outline Dest and A operands unresolved in document metadata' => static function (
        TestRunner $t
    ) use ($outlineDestinationActionOperandBoundaryPdf): void {
        $pdf = $outlineDestinationActionOperandBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(9, $outline['last_item_object'] ?? null);
        $t->same(4, $outline['item_count'] ?? null);
        $t->same(2, $outline['resolved_destination_count'] ?? null);
        $t->same(2, $outline['unresolved_destination_count'] ?? null);
        $t->same([
            'Operand Boundary Current Chapter',
            'Tailed Dest Operand Review',
            'Tailed Action Operand Review',
            'Operand Boundary Appendix',
        ], $outline['titles'] ?? []);
        $t->same([true, false, false, true], array_column($items, 'destination_resolved'));

        $destReview = $items[1]['destination_operand_boundary_review'] ?? [];
        $t->same('outline_item_destination_operand_boundary', $destReview['source'] ?? null);
        $t->same('rejected_malformed_outline_item_dest_operand', $destReview['status'] ?? null);
        $t->same('Dest', $destReview['key'] ?? null);
        $t->same(2, $destReview['operand_count'] ?? null);
        $t->same('name', $destReview['operand_shape'] ?? null);
        $t->same(['indirect_reference'], $destReview['trailing_operand_shapes'] ?? null);
        $t->same([12], $destReview['trailing_reference_object_numbers'] ?? null);

        $actionReview = $items[2]['action_operand_boundary_review'] ?? [];
        $t->same('outline_item_action_operand_boundary', $actionReview['source'] ?? null);
        $t->same('rejected_malformed_outline_item_action_operand', $actionReview['status'] ?? null);
        $t->same('A', $actionReview['key'] ?? null);
        $t->same(2, $actionReview['operand_count'] ?? null);
        $t->same('indirect_reference', $actionReview['operand_shape'] ?? null);
        $t->same(13, $actionReview['object_number'] ?? null);
        $t->same([14], $actionReview['trailing_reference_object_numbers'] ?? null);
        $t->same(['indirect_reference'], $actionReview['trailing_operand_shapes'] ?? null);
        $t->same(null, $items[2]['action_type'] ?? null);
        $t->same(null, $items[2]['action_object'] ?? null);

        $t->true(is_string($encoded) && !str_contains($encoded, 'tailed-dest-decoy.pdf'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'tailed-action-decoy.pdf'));
    },
    'excludes tailed outline Dest and A operands from TOC navigation and remote actions' => static function (
        TestRunner $t
    ) use ($outlineDestinationActionOperandBoundaryPdf): void {
        $pdf = $outlineDestinationActionOperandBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $plainToc = $outlineExtractor->getPdfToc($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $lightweight = (new PdfTextExtractor())->extractOutlineMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $lightweightEncoded = json_encode($lightweight, JSON_UNESCAPED_SLASHES);

        $expectedTitles = ['Operand Boundary Current Chapter', 'Operand Boundary Appendix'];
        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same($expectedTitles, array_column($plainToc, 'title'));
        $t->same($expectedTitles, array_column($navigation['outline'] ?? [], 'title'));
        $t->same($expectedTitles, array_column($lightweight['pdf_toc'] ?? [], 'title'));
        $t->same([6, 9], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same([0, 1], array_column($toc, 'page'));
        $t->same(['FitH', 'Fit'], array_column($toc, 'view_mode'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same([], $remoteActions);
        $t->same("Outline destination action operand boundary intro body\nOutline destination action operand boundary appendix body", $plainText);

        foreach (['Tailed Dest Operand Review', 'Tailed Action Operand Review', 'tailed-dest-decoy.pdf', 'tailed-action-decoy.pdf'] as $forbidden) {
            $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, $forbidden));
            $t->true(is_string($lightweightEncoded) && !str_contains($lightweightEncoded, $forbidden));
            $t->true(!str_contains($plainText, $forbidden));
        }
        $t->true(!str_contains($plainText, 'Operand Boundary Current Chapter'));
        $t->true(!str_contains($plainText, 'Operand Boundary Appendix'));
    },
];
