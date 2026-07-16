<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataPageLabelBoundaryPdf = static function (): string {
    $coverText = 'BT /F1 12 Tf 72 720 Td (Outline label metadata cover body) Tj ET';
    $chapterText = 'BT /F1 12 Tf 72 720 Td (Outline label metadata chapter body) Tj ET';
    $appendixText = 'BT /F1 12 Tf 72 720 Td (Outline label metadata appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageLabels 30 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R 6 0 R] /Count 3 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Contents 10 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Contents 11 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Contents 12 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 7 0 R /Last 9 0 R /Count 3 >>\nendobj\n"
        . "7 0 obj\n<< /Title (Metadata Cover Bookmark) /Parent 5 0 R /Dest [3 0 R /Fit] /Next 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Title (Metadata Chapter Bookmark) /Parent 5 0 R /Prev 7 0 R /Dest /ChapterStart /Next 9 0 R /C [0 .2 .8] /F 2 >>\nendobj\n"
        . "9 0 obj\n<< /Title (Metadata Appendix Action) /Parent 5 0 R /Prev 8 0 R /A 21 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Length " . strlen($coverText) . " >>\nstream\n{$coverText}\nendstream\nendobj\n"
        . "11 0 obj\n<< /Length " . strlen($chapterText) . " >>\nstream\n{$chapterText}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Length " . strlen($appendixText) . " >>\nstream\n{$appendixText}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Names [(AppendixTarget) [6 0 R /XYZ 72 null 1.25] (ChapterStart) [4 0 R /FitH 640]] >>\nendobj\n"
        . "21 0 obj\n<< /S /GoTo /D /AppendixTarget /Next 22 0 R >>\nendobj\n"
        . "22 0 obj\n<< /S /URI /URI (https://example.com/outline-label-review) >>\nendobj\n"
        . "30 0 obj\n<< /Nums [0 << /P (Cover-) /S /r /St 3 >> 1 << /P (Chapter ) /S /D /St 7 >> 2 << /P (Appendix-) /S /A /St 1 >>] >>\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'carries page labels onto document outline metadata rows' => static function (
        TestRunner $t
    ) use ($outlineMetadataPageLabelBoundaryPdf): void {
        $pdf = $outlineMetadataPageLabelBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
        $pageLabels = (new PdfTextExtractor())->extractPageLabels($pdf);

        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];

        $t->same(['catalog'], $metadata['source']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(['Cover-iii', 'Chapter 7', 'Appendix-A'], $pageLabels);
        $t->same(['Metadata Cover Bookmark', 'Metadata Chapter Bookmark', 'Metadata Appendix Action'], array_column($items, 'title'));
        $t->same([0, 1, 2], array_column($items, 'page'));
        $t->same([3, 4, 6], array_column($items, 'page_object'));
        $t->same(['Cover-iii', 'Chapter 7', 'Appendix-A'], array_column($items, 'page_label'));
        $t->same(['Cover-iii', 'Chapter 7', 'Appendix-A'], $outline['page_labels'] ?? []);
        $t->same(['Cover-iii', 'Chapter 7', 'Appendix-A'], array_column($navigation['outline'] ?? [], 'page_label'));
        $t->same('Chapter 7', $items[1]['page_label'] ?? null);
        $t->same('Appendix-A', $items[2]['page_label'] ?? null);
        $t->same(['GoTo', 'URI'], $items[2]['action_chain_types'] ?? []);
        $t->same(true, $items[2]['action_chain_has_next'] ?? null);
        $t->same(false, $items[2]['action_payload_included'] ?? null);
    },
    'keeps outline page labels and action operands out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineMetadataPageLabelBoundaryPdf): void {
        $pdf = $outlineMetadataPageLabelBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(
            "Outline label metadata cover body\nOutline label metadata chapter body\nOutline label metadata appendix body",
            $plainText
        );
        $t->true(is_string($encoded) && str_contains($encoded, 'Appendix-A'));
        $t->true(!str_contains($plainText, 'Cover-iii'));
        $t->true(!str_contains($plainText, 'Chapter 7'));
        $t->true(!str_contains($plainText, 'Appendix-A'));
        $t->true(!str_contains($plainText, 'Metadata Cover Bookmark'));
        $t->true(!str_contains($plainText, 'Metadata Chapter Bookmark'));
        $t->true(!str_contains($plainText, 'Metadata Appendix Action'));
        $t->true(!str_contains($plainText, 'outline-label-review'));
    },
];
