<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataCommentBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Outline metadata comment boundary intro body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline metadata comment boundary appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 32 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First % 99 0 R stale first item\n 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title % /Title (Stale Comment Title)\n 30 0 R /Parent 5 0 R /Dest [ % 99 0 R ] stale destination\n 3 0 R /XYZ null null null ] /Next % 99 0 R stale sibling\n 7 0 R /C [ % 1 0 0 ] stale red color\n 0 .25 .5 ] /F % 0 stale style\n 2 >>\nendobj\n"
        . "7 0 obj\n<< /Title (Comment Boundary Appendix) /Parent 5 0 R /Prev 6 0 R /Dest 21 0 R /Next % /Next 99 0 R\n 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Title (Stale After Comment Next) /Parent 5 0 R /Prev 7 0 R /Dest [4 0 R /Fit] >>\nendobj\n"
        . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /FitH 640] (StaleComment) [99 0 R /Fit]] >>\nendobj\n"
        . "21 0 obj\n% [99 0 R /Fit] stale indirect destination array\n[4 0 R /FitH 640]\nendobj\n"
        . "30 0 obj\n% /Title (Fake indirect title)\n(Comment Boundary Chapter)\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "32 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'skips PDF comments while reading document outline metadata operands' => static function (
        TestRunner $t
    ) use ($outlineMetadataCommentBoundaryPdf): void {
        $pdf = $outlineMetadataCommentBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(7, $outline['last_item_object'] ?? null);
        $t->same(2, $outline['declared_visible_count'] ?? null);
        $t->same(2, $outline['item_count'] ?? null);
        $t->same(2, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(1, $outline['max_depth'] ?? null);
        $t->same(['Comment Boundary Chapter', 'Comment Boundary Appendix'], $outline['titles'] ?? []);

        $t->same(2, count($items));
        $t->same('Comment Boundary Chapter', $items[0]['title'] ?? null);
        $t->same(6, $items[0]['outline_object'] ?? null);
        $t->same(7, $items[0]['next_object'] ?? null);
        $t->same(true, $items[0]['destination_resolved'] ?? null);
        $t->same(0, $items[0]['page'] ?? null);
        $t->same(3, $items[0]['page_object'] ?? null);
        $t->same('XYZ', $items[0]['view_mode'] ?? null);
        $t->same(['left' => null, 'top' => null, 'zoom' => null], $items[0]['view_parameters'] ?? null);
        $t->same(2, $items[0]['style_flags'] ?? null);
        $t->same(true, $items[0]['is_bold'] ?? null);
        $t->same([0.0, 0.25, 0.5], $items[0]['text_color_rgb'] ?? null);
        $t->same('#004080', $items[0]['text_color_hex'] ?? null);

        $t->same('Comment Boundary Appendix', $items[1]['title'] ?? null);
        $t->same(7, $items[1]['outline_object'] ?? null);
        $t->same(8, $items[1]['next_object'] ?? null);
        $t->same(true, $items[1]['destination_resolved'] ?? null);
        $t->same(null, $items[1]['destination'] ?? null);
        $t->same(1, $items[1]['page'] ?? null);
        $t->same(4, $items[1]['page_object'] ?? null);
        $t->same('FitH', $items[1]['view_mode'] ?? null);
        $t->same(['top' => 640.0], $items[1]['view_parameters'] ?? null);

        $t->same("Outline metadata comment boundary intro body\nOutline metadata comment boundary appendix body", $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Comment Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Fake indirect title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale After Comment Next'));
        $t->true(is_string($encoded) && !str_contains($encoded, '99 0 R'));
        $t->true(!str_contains($plainText, 'Comment Boundary Chapter'));
        $t->true(!str_contains($plainText, 'Comment Boundary Appendix'));
        $t->true(!str_contains($plainText, 'Stale Comment Title'));
        $t->true(!str_contains($plainText, 'Stale After Comment Next'));
    },
    'keeps comment-only outline operands out of TOC and navigation review text' => static function (
        TestRunner $t
    ) use ($outlineMetadataCommentBoundaryPdf): void {
        $pdf = $outlineMetadataCommentBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $expectedTitles = ['Comment Boundary Chapter', 'Comment Boundary Appendix'];

        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same([1, 1], array_column($toc, 'level'));
        $t->same([0, 1], array_column($toc, 'page'));
        $t->same(['XYZ', 'FitH'], array_column($toc, 'view_mode'));
        $t->same($expectedTitles, array_column($navigation['outline'] ?? [], 'title'));
        $t->same([6, 7], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Stale Comment Title'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Stale After Comment Next'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, '99 0 R'));
    },
];
