<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineSelectedDuplicateNavigationOperandBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Selected duplicate navigation operand intro body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Selected duplicate navigation operand appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 8 0 R /Count 3 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Selected Duplicate Boundary Intro) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Selected Duplicate Boundary Appendix) /Parent 5 0 R /Prev 6 0 R /Dest /LegacyTarget 12 0 R /Dest /AppendixTarget /Next 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Title (Selected Duplicate Boundary Closing) /Parent 5 0 R /Prev 7 0 R /Dest /ChapterStart >>\nendobj\n"
        . "12 0 obj\n<< /S /GoToR /F (stale-duplicate-dest.pdf) /D (stale-duplicate-dest) /NewWindow true >>\nendobj\n"
        . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /FitH 640] (ChapterStart) [3 0 R /Fit]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'uses selected duplicate outline Dest after stale malformed operand in document metadata' => static function (
        TestRunner $t
    ) use ($outlineSelectedDuplicateNavigationOperandBoundaryPdf): void {
        $pdf = $outlineSelectedDuplicateNavigationOperandBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $duplicateKeyReview = $items[1]['duplicate_key_review'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(8, $outline['last_item_object'] ?? null);
        $t->same(3, $outline['item_count'] ?? null);
        $t->same(3, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same([
            'Selected Duplicate Boundary Intro',
            'Selected Duplicate Boundary Appendix',
            'Selected Duplicate Boundary Closing',
        ], $outline['titles'] ?? []);
        $t->same([true, true, true], array_column($items, 'destination_resolved'));
        $t->same(['ChapterStart', 'AppendixTarget', 'ChapterStart'], array_column($items, 'destination'));
        $t->same([0, 1, 0], array_column($items, 'page'));
        $t->same([3, 4, 3], array_column($items, 'page_object'));
        $t->same(['Fit', 'FitH', 'Fit'], array_column($items, 'view_mode'));
        $t->same(['Dest'], $duplicateKeyReview['keys'] ?? null);
        $t->same(['Dest' => 2], $duplicateKeyReview['declared_entry_counts'] ?? null);
        $t->same(['Dest' => 1], $duplicateKeyReview['selected_entry_indexes'] ?? null);
        $t->true(!array_key_exists('destination_operand_boundary_review', $items[1]));
        $t->true(is_string($encoded) && !str_contains($encoded, 'rejected_malformed_outline_item_dest_operand'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-duplicate-dest.pdf'));
    },
    'keeps selected duplicate outline Dest in TOC navigation without promoting stale remote action' => static function (
        TestRunner $t
    ) use ($outlineSelectedDuplicateNavigationOperandBoundaryPdf): void {
        $pdf = $outlineSelectedDuplicateNavigationOperandBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $lightweight = (new PdfTextExtractor())->extractOutlineMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $lightweightEncoded = json_encode($lightweight, JSON_UNESCAPED_SLASHES);

        $expectedTitles = [
            'Selected Duplicate Boundary Intro',
            'Selected Duplicate Boundary Appendix',
            'Selected Duplicate Boundary Closing',
        ];
        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same($expectedTitles, array_column($navigation['outline'] ?? [], 'title'));
        $t->same($expectedTitles, array_column($lightweight['pdf_toc'] ?? [], 'title'));
        $t->same([6, 7, 8], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same([0, 1, 0], array_column($toc, 'page'));
        $t->same(['Fit', 'FitH', 'Fit'], array_column($toc, 'view_mode'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same([], $remoteActions);
        $t->same(
            "Selected duplicate navigation operand intro body\nSelected duplicate navigation operand appendix body",
            $plainText
        );

        foreach (['stale-duplicate-dest.pdf', 'stale-duplicate-dest', 'rejected_malformed_outline_item_dest_operand'] as $forbidden) {
            $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, $forbidden));
            $t->true(is_string($lightweightEncoded) && !str_contains($lightweightEncoded, $forbidden));
            $t->true(!str_contains($plainText, $forbidden));
        }
        foreach ($expectedTitles as $title) {
            $t->true(!str_contains($plainText, $title));
        }
    },
];
