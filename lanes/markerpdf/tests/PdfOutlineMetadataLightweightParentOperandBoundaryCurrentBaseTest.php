<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineLightweightParentOperandBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Lightweight parent operand body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Malformed Parent Operand Chapter) /Parent 5 0 R 9 0 R /Dest [3 0 R /FitH 720] /A 12 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Type /Outlines /First 10 0 R /Last 10 0 R /Count 1 >>\nendobj\n"
        . "10 0 obj\n<< /Title (Trailing Parent Operand Decoy) /Parent 9 0 R /Dest [3 0 R /Fit] >>\nendobj\n"
        . "12 0 obj\n<< /S /URI /URI (https://example.com/malformed-parent-outline) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Title (Lightweight Parent Operand Info) /Author (Current Outline Metadata Team) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 40 0 R >>\n%%EOF";
};

return [
    'rejects tailed outline Parent references before lightweight pdf_toc fallback promotion' => static function (
        TestRunner $t
    ) use ($outlineLightweightParentOperandBoundaryPdf): void {
        $pdf = $outlineLightweightParentOperandBoundaryPdf();
        $textExtractor = new PdfTextExtractor();
        $lightweight = $textExtractor->extractOutlineMetadata($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $plainText = $textExtractor->extractPlainText($pdf);
        $encodedLightweight = json_encode($lightweight, JSON_UNESCAPED_SLASHES);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(1, $lightweight['pages']);
        $t->same('Lightweight Parent Operand Info', $lightweight['document_info']['title'] ?? null);
        $t->same([], $lightweight['pdf_toc']);
        $t->same([], $toc);
        $t->same([], $metadata['document_outline']['titles'] ?? []);
        $t->same(0, $metadata['document_outline']['item_count'] ?? null);
        $t->same('Lightweight parent operand body', $plainText);
        $t->true(is_string($encodedLightweight) && !str_contains($encodedLightweight, 'Malformed Parent Operand Chapter'));
        $t->true(is_string($encodedLightweight) && !str_contains($encodedLightweight, 'Trailing Parent Operand Decoy'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'malformed-parent-outline'));
        $t->true(!str_contains($plainText, 'Malformed Parent Operand Chapter'));
        $t->true(!str_contains($plainText, 'Trailing Parent Operand Decoy'));
    },
    'keeps malformed lightweight Parent operand rows out of navigation review metadata' => static function (
        TestRunner $t
    ) use ($outlineLightweightParentOperandBoundaryPdf): void {
        $pdf = $outlineLightweightParentOperandBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same([], $navigation['outline'] ?? []);
        $t->same([], $navigation['outline_action_review_actions'] ?? []);
        $t->same([], $remoteActions);
        $t->same('Lightweight parent operand body', $plainText);
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, 'Malformed Parent Operand Chapter'));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, 'Trailing Parent Operand Decoy'));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, 'malformed-parent-outline'));
    },
];
