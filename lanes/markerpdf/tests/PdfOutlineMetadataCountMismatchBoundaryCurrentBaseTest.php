<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataCountMismatchBoundaryPdf = static function (): string {
    $chapterContent = 'BT /F1 12 Tf 72 720 Td (Outline count mismatch chapter body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline count mismatch appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Count Mismatch Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Count Mismatch Appendix) /Parent 5 0 R /Prev 6 0 R /Dest /AppendixTarget /A 12 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /GoTo /D /AppendixTarget /Next 13 0 R >>\nendobj\n"
        . "13 0 obj\n<< /S /URI /URI (https://example.com/count-mismatch-review) >>\nendobj\n"
        . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($chapterContent) . " >>\nstream\n{$chapterContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'records outline root Count mismatch without dropping valid linked rows' => static function (
        TestRunner $t
    ) use ($outlineMetadataCountMismatchBoundaryPdf): void {
        $pdf = $outlineMetadataCountMismatchBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(null, $outline['last_item_object'] ?? null);
        $t->same(1, $outline['outline_count'] ?? null);
        $t->same(1, $outline['declared_visible_count'] ?? null);
        $t->same(1, $outline['descendant_count'] ?? null);
        $t->same(2, $outline['item_count'] ?? null);
        $t->same(2, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(1, $outline['max_depth'] ?? null);
        $t->same([
            'Count Mismatch Chapter',
            'Count Mismatch Appendix',
        ], $outline['titles'] ?? []);
        $t->same([6, 7], array_column($items, 'outline_object'));
        $t->same([5, 5], array_column($items, 'parent_object'));
        $t->same([0, 1], array_column($items, 'page'));
        $t->same(['FitH', 'Fit'], array_column($items, 'view_mode'));

        $t->same(true, $outline['declared_count_mismatch_review_only'] ?? null);
        $t->same(false, $outline['declared_count_mismatch_payload_included'] ?? null);
        $t->same(1, $outline['declared_count_expected_visible_item_count'] ?? null);
        $t->same(2, $outline['declared_count_actual_visible_item_count'] ?? null);
        $t->same(2, $outline['declared_count_actual_item_count'] ?? null);
        $t->same(1, $outline['declared_count_visible_item_count_delta'] ?? null);

        $t->true(is_string($encoded) && !str_contains($encoded, 'count-mismatch-review'));
    },
    'keeps Count mismatch review separate from TOC navigation and visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineMetadataCountMismatchBoundaryPdf): void {
        $pdf = $outlineMetadataCountMismatchBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $expectedTitles = [
            'Count Mismatch Chapter',
            'Count Mismatch Appendix',
        ];

        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same([1, 1], array_column($toc, 'level'));
        $t->same([0, 1], array_column($toc, 'page'));
        $t->same($expectedTitles, array_column($navigation['outline'] ?? [], 'title'));
        $t->same([6, 7], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same(['GoTo', 'URI'], array_column($navigation['outline_action_review_actions'] ?? [], 'action_type'));
        $t->same("Outline count mismatch chapter body\nOutline count mismatch appendix body", $plainText);
        $t->true(is_string($navigationEncoded) && str_contains($navigationEncoded, 'count-mismatch-review'));
        $t->true(!str_contains($plainText, 'Count Mismatch Chapter'));
        $t->true(!str_contains($plainText, 'Count Mismatch Appendix'));
        $t->true(!str_contains($plainText, 'count-mismatch-review'));
    },
];
