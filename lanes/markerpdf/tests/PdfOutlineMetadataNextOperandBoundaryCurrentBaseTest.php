<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineNextOperandBoundaryPdf = static function (): string {
    $chapterContent = 'BT /F1 12 Tf 72 720 Td (Outline next operand chapter body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline next operand appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Next Operand Boundary Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 8 0 R 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Valid But Ambiguous Next Operand Target) /Parent 5 0 R /Prev 6 0 R /Dest /AppendixTarget >>\nendobj\n"
        . "8 0 obj\n<< /Title (Stale Next Operand Remote Review) /Parent 5 0 R /Prev 6 0 R /A 12 0 R /Next 7 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /GoToR /F (stale-next-operand-review.pdf) /D (stale-next) /NewWindow true >>\nendobj\n"
        . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($chapterContent) . " >>\nstream\n{$chapterContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'rejects outline Next references with trailing operands before document metadata traversal' => static function (
        TestRunner $t
    ) use ($outlineNextOperandBoundaryPdf): void {
        $pdf = $outlineNextOperandBoundaryPdf();
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
        $t->same(['Next Operand Boundary Chapter'], $outline['titles'] ?? []);
        $t->same([6], array_column($items, 'outline_object'));
        $t->same([5], array_column($items, 'parent_object'));
        $t->same(8, $items[0]['next_object'] ?? null, 'The first malformed /Next reference remains reviewable but is not traversed.');
        $t->same([0], array_column($items, 'page'));
        $t->same(['FitH'], array_column($items, 'view_mode'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Next Operand Remote Review'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Valid But Ambiguous Next Operand Target'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-next-operand-review.pdf'));
    },
    'applies malformed Next operand boundary to TOC navigation and remote action review' => static function (
        TestRunner $t
    ) use ($outlineNextOperandBoundaryPdf): void {
        $pdf = $outlineNextOperandBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $lightweightToc = $outlineExtractor->getPdfToc($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $lightweightMetadata = (new PdfTextExtractor())->extractOutlineMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $lightweightEncoded = json_encode($lightweightMetadata, JSON_UNESCAPED_SLASHES);

        $t->same(['Next Operand Boundary Chapter'], array_column($toc, 'title'));
        $t->same([1], array_column($toc, 'level'));
        $t->same([0], array_column($toc, 'page'));
        $t->same(['FitH'], array_column($toc, 'view_mode'));
        $t->same([
            [
                'title' => 'Next Operand Boundary Chapter',
                'level' => 1,
                'page' => 0,
                'destination' => 'ChapterStart',
            ],
        ], $lightweightToc);
        $t->same(['Next Operand Boundary Chapter'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same([6], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same([], $remoteActions);
        $t->same(['Next Operand Boundary Chapter'], array_column($lightweightMetadata['pdf_toc'] ?? [], 'title'));
        $t->same("Outline next operand chapter body\nOutline next operand appendix body", $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Stale Next Operand Remote Review'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'stale-next-operand-review.pdf'));
        $t->true(is_string($lightweightEncoded) && !str_contains($lightweightEncoded, 'Valid But Ambiguous Next Operand Target'));
        $t->true(!str_contains($plainText, 'Next Operand Boundary Chapter'));
        $t->true(!str_contains($plainText, 'Stale Next Operand Remote Review'));
        $t->true(!str_contains($plainText, 'Valid But Ambiguous Next Operand Target'));
        $t->true(!str_contains($plainText, 'stale-next-operand-review.pdf'));
    },
];
