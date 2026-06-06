<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineIndirectTitleBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Outline indirect title boundary intro body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline indirect title boundary appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 10 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title 7 0 R /Parent 5 0 R /Dest [3 0 R /Fit] /A 8 0 R /Next 10 0 R >>\nendobj\n"
        . "7 0 obj\n(Malformed Indirect Outline Title) /A 8 0 R\nendobj\n"
        . "8 0 obj\n<< /S /JavaScript /JS (app.alert\\('malformed indirect outline title action'\\)) >>\nendobj\n"
        . "10 0 obj\n<< /Title (Current Safe Outline Appendix) /Parent 5 0 R /Prev 6 0 R /Dest [4 0 R /FitH 640] >>\nendobj\n"
        . "11 0 obj\n<< /Title (Info Title Stays Current) /Author (MarkerPDF Lane) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "trailer\n<< /Info 11 0 R >>\n%%EOF";
};

return [
    'rejects indirect outline title objects with trailing action tokens in lightweight metadata' => static function (
        TestRunner $t
    ) use ($outlineIndirectTitleBoundaryPdf): void {
        $pdf = $outlineIndirectTitleBoundaryPdf();
        $textMetadata = (new PdfTextExtractor())->extractOutlineMetadata($pdf);
        $documentMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
        $encodedTextMetadata = json_encode($textMetadata, JSON_UNESCAPED_SLASHES);
        $encodedDocumentMetadata = json_encode($documentMetadata, JSON_UNESCAPED_SLASHES);

        $t->same(['Current Safe Outline Appendix'], array_column($textMetadata['pdf_toc'], 'title'));
        $t->same([1], array_column($textMetadata['pdf_toc'], 'page'));
        $t->same('Info Title Stays Current', $textMetadata['document_info']['title'] ?? null);
        $t->same('MarkerPDF Lane', $textMetadata['document_info']['author'] ?? null);
        $t->same(['Current Safe Outline Appendix'], $documentMetadata['document_outline']['titles'] ?? []);
        $t->same(['Current Safe Outline Appendix'], array_column($toc, 'title'));
        $t->same([10], array_column($documentMetadata['document_outline']['items'] ?? [], 'outline_object'));
        $t->same(6, $documentMetadata['document_outline']['items'][0]['previous_object'] ?? null);
        $t->true(is_string($encodedTextMetadata) && !str_contains($encodedTextMetadata, 'Malformed Indirect Outline Title'));
        $t->true(is_string($encodedDocumentMetadata) && !str_contains($encodedDocumentMetadata, 'Malformed Indirect Outline Title'));
        $t->true(is_string($encodedTextMetadata) && !str_contains($encodedTextMetadata, 'malformed indirect outline title action'));
        $t->true(is_string($encodedDocumentMetadata) && !str_contains($encodedDocumentMetadata, 'malformed indirect outline title action'));
    },
    'keeps malformed indirect outline title actions out of visible WordPress text and navigation review' => static function (
        TestRunner $t
    ) use ($outlineIndirectTitleBoundaryPdf): void {
        $pdf = $outlineIndirectTitleBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(['Current Safe Outline Appendix'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same([10], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same([], $remoteActions);
        $t->same("Outline indirect title boundary intro body\nOutline indirect title boundary appendix body", $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Malformed Indirect Outline Title'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'malformed indirect outline title action'));
        $t->true(!str_contains($plainText, 'Malformed Indirect Outline Title'));
        $t->true(!str_contains($plainText, 'Current Safe Outline Appendix'));
        $t->true(!str_contains($plainText, 'malformed indirect outline title action'));
    },
];
