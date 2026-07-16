<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineObjectValueBoundaryPdf = static function (): string {
    $chapterContent = 'BT /F1 12 Tf 72 720 Td (Outline object value boundary chapter body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline object value boundary appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Object Value Boundary Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Stale Tail Outline Appendix) /Parent 5 0 R /Prev 6 0 R /Dest [4 0 R /Fit] /A 12 0 R >> /Next 99 0 R\nendobj\n"
        . "12 0 obj\n<< /S /URI /URI (https://example.com/stale-tail-outline-action) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($chapterContent) . " >>\nstream\n{$chapterContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'rejects outline item objects with extra top-level operands from document metadata' => static function (
        TestRunner $t
    ) use ($outlineObjectValueBoundaryPdf): void {
        $pdf = $outlineObjectValueBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(7, $outline['last_item_object'] ?? null);
        $t->same(2, $outline['declared_visible_count'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(['Object Value Boundary Chapter'], $outline['titles'] ?? []);
        $t->same([6], array_column($items, 'outline_object'));
        $t->same([5], array_column($items, 'parent_object'));
        $t->same([0], array_column($items, 'page'));
        $t->same(['FitH'], array_column($items, 'view_mode'));
        $t->same(2, $outline['declared_count_expected_visible_item_count'] ?? null);
        $t->same(1, $outline['declared_count_actual_item_count'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Tail Outline Appendix'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-tail-outline-action'));
    },
    'applies outline item object-value boundary to TOC navigation and lightweight metadata' => static function (
        TestRunner $t
    ) use ($outlineObjectValueBoundaryPdf): void {
        $pdf = $outlineObjectValueBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $plainToc = $outlineExtractor->getPdfToc($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $lightweightMetadata = (new PdfTextExtractor())->extractOutlineMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $lightweightEncoded = json_encode($lightweightMetadata, JSON_UNESCAPED_SLASHES);

        $expectedToc = [
            ['title' => 'Object Value Boundary Chapter', 'level' => 1, 'page' => 0],
        ];

        $t->same(['Object Value Boundary Chapter'], array_column($toc, 'title'));
        $t->same(['Object Value Boundary Chapter'], array_column($plainToc, 'title'));
        $t->same([0], array_column($toc, 'page'));
        $t->same(['FitH'], array_column($toc, 'view_mode'));
        $t->same(['Object Value Boundary Chapter'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same([6], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same($expectedToc, $lightweightMetadata['pdf_toc']);
        $t->same("Outline object value boundary chapter body\nOutline object value boundary appendix body", $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Stale Tail Outline Appendix'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'stale-tail-outline-action'));
        $t->true(is_string($lightweightEncoded) && !str_contains($lightweightEncoded, 'Stale Tail Outline Appendix'));
        $t->true(!str_contains($plainText, 'Object Value Boundary Chapter'));
        $t->true(!str_contains($plainText, 'Stale Tail Outline Appendix'));
        $t->true(!str_contains($plainText, 'stale-tail-outline-action'));
    },
];
