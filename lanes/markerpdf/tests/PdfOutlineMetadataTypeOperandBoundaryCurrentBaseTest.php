<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineTypeOperandBoundaryPdf = static function (): string {
    $chapterContent = 'BT /F1 12 Tf 72 720 Td (Outline type operand chapter body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline type operand appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 8 0 R /Count 3 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Type Operand Boundary Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Type /Outline 99 0 R /Title (Malformed Type Operand Appendix) /Parent 5 0 R /Prev 6 0 R /Dest /AppendixTarget /A 12 0 R /Next 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Title (Untrusted Tail After Type Operand) /Parent 5 0 R /Prev 7 0 R /Dest /TailTarget /A 13 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /GoToR /F (malformed-type-operand-outline.pdf) /D (appendix-target) /NewWindow true >>\nendobj\n"
        . "13 0 obj\n<< /S /GoToR /F (type-operand-tail.pdf) /D (tail-target) /NewWindow true >>\nendobj\n"
        . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720] (TailTarget) [4 0 R /XYZ 12 34 0]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($chapterContent) . " >>\nstream\n{$chapterContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'rejects outline item Type values with trailing operands before document metadata traversal' => static function (
        TestRunner $t
    ) use ($outlineTypeOperandBoundaryPdf): void {
        $pdf = $outlineTypeOperandBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(8, $outline['last_item_object'] ?? null);
        $t->same(3, $outline['declared_visible_count'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(['Type Operand Boundary Chapter'], $outline['titles'] ?? []);
        $t->same([6], array_column($items, 'outline_object'));
        $t->same([5], array_column($items, 'parent_object'));
        $t->same([7], array_column($items, 'next_object'));
        $t->same([0], array_column($items, 'page'));
        $t->same(['FitH'], array_column($items, 'view_mode'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Malformed Type Operand Appendix'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Untrusted Tail After Type Operand'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'malformed-type-operand-outline.pdf'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'type-operand-tail.pdf'));
    },
    'applies malformed Type operand boundary to TOC navigation and remote action review' => static function (
        TestRunner $t
    ) use ($outlineTypeOperandBoundaryPdf): void {
        $pdf = $outlineTypeOperandBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $lightweightToc = $outlineExtractor->getPdfToc($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $lightweightMetadata = (new PdfTextExtractor())->extractOutlineMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $lightweightEncoded = json_encode($lightweightMetadata, JSON_UNESCAPED_SLASHES);

        $t->same(['Type Operand Boundary Chapter'], array_column($toc, 'title'));
        $t->same(['Type Operand Boundary Chapter'], array_column($lightweightToc, 'title'));
        $t->same([1], array_column($toc, 'level'));
        $t->same([0], array_column($toc, 'page'));
        $t->same(['FitH'], array_column($toc, 'view_mode'));
        $t->same(['Type Operand Boundary Chapter'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same([6], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same([], $remoteActions);
        $t->same([
            ['title' => 'Type Operand Boundary Chapter', 'level' => 1, 'page' => 0],
        ], $lightweightMetadata['pdf_toc']);
        $t->same("Outline type operand chapter body\nOutline type operand appendix body", $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Malformed Type Operand Appendix'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'malformed-type-operand-outline.pdf'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'type-operand-tail.pdf'));
        $t->true(is_string($lightweightEncoded) && !str_contains($lightweightEncoded, 'Malformed Type Operand Appendix'));
        $t->true(is_string($lightweightEncoded) && !str_contains($lightweightEncoded, 'malformed-type-operand-outline.pdf'));
        $t->true(!str_contains($plainText, 'Type Operand Boundary Chapter'));
        $t->true(!str_contains($plainText, 'Malformed Type Operand Appendix'));
        $t->true(!str_contains($plainText, 'Untrusted Tail After Type Operand'));
        $t->true(!str_contains($plainText, 'malformed-type-operand-outline.pdf'));
    },
];
