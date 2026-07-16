<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataTrailerRootBoundaryPdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale trailer root page leak) Tj ET';
    $currentIntro = 'BT /F1 12 Tf 72 720 Td (Current trailer root intro body) Tj ET';
    $currentAppendix = 'BT /F1 12 Tf 72 720 Td (Current trailer root appendix body) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Info 10 0 R /PageMode /UseOutlines >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
    $addObject(4, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(5, '<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>');
    $addObject(6, '<< /Title (Stale Root Outline) /Parent 5 0 R /Dest [3 0 R /Fit] /A 12 0 R >>');
    $addObject(10, '<< /Title (Stale Info Title) /Author (Stale Metadata Team) /Keywords (stale outline metadata) >>');
    $addObject(12, "<< /S /JavaScript /JS (app.alert\\('stale trailer root outline action'\\)) >>");
    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Outlines 25 0 R /Names << /Dests 28 0 R >> /PageMode /UseOutlines /PageLayout /OneColumn >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R 23 0 R] /Count 2 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Contents 30 0 R >>');
    $addObject(23, '<< /Type /Page /Parent 21 0 R /Contents 31 0 R >>');
    $addObject(25, '<< /Type /Outlines /First 26 0 R /Last 27 0 R /Count 2 >>');
    $addObject(26, '<< /Title (Current Trailer Chapter) /Parent 25 0 R /Dest [22 0 R /XYZ 72 720 0] /Next 27 0 R /C [0 .25 .5] /F 2 >>');
    $addObject(27, '<< /Title (Current Trailer Appendix) /Parent 25 0 R /Prev 26 0 R /A << /S /GoTo /D [23 0 R /FitH 640] /Next 29 0 R >> >>');
    $addObject(28, '<< /Names [(CurrentAppendix) [23 0 R /FitH 640] (CurrentStart) [22 0 R /XYZ 72 720 0]] >>');
    $addObject(29, '<< /S /URI /URI (https://example.com/current-trailer-outline-review) >>');
    $addObject(30, "<< /Length " . strlen($currentIntro) . " >>\nstream\n{$currentIntro}\nendstream");
    $addObject(31, "<< /Length " . strlen($currentAppendix) . " >>\nstream\n{$currentAppendix}\nendstream");
    $addObject(40, '<< /Title (Current Trailer Info) /Author (Current Metadata Team) /Keywords (current outline metadata) /CreationDate (D:20260605010226Z) >>');

    $xrefOffset = strlen($pdf);
    $maxObject = 40;
    $pdf .= "xref\n0 " . ($maxObject + 1) . "\n"
        . "0000000000 65535 f \n";
    for ($objectNumber = 1; $objectNumber <= $maxObject; $objectNumber++) {
        $pdf .= isset($offsets[$objectNumber])
            ? sprintf("%010d 00000 n \n", $offsets[$objectNumber])
            : "0000000000 00000 f \n";
    }

    $pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 20 0 R /Info 40 0 R >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'uses current trailer Root and Info for lightweight outline metadata' => static function (
        TestRunner $t
    ) use ($outlineMetadataTrailerRootBoundaryPdf): void {
        $pdf = $outlineMetadataTrailerRootBoundaryPdf();
        $metadata = (new PdfTextExtractor())->extractOutlineMetadata($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(2, $metadata['pages']);
        $t->same([
            ['title' => 'Current Trailer Chapter', 'level' => 1, 'page' => 0],
            ['title' => 'Current Trailer Appendix', 'level' => 1, 'page' => 1],
        ], $metadata['pdf_toc']);
        $t->same('Current Trailer Info', $metadata['document_info']['title']);
        $t->same('Current Metadata Team', $metadata['document_info']['author']);
        $t->same('current outline metadata', $metadata['document_info']['keywords']);
        $t->same('D:20260605010226Z', $metadata['document_info']['creation_date']);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Root Outline'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Info Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale trailer root outline action'));
    },
    'uses current trailer Root for outline navigation review and document metadata' => static function (
        TestRunner $t
    ) use ($outlineMetadataTrailerRootBoundaryPdf): void {
        $pdf = $outlineMetadataTrailerRootBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $metadataEncoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['Current Trailer Chapter', 'Current Trailer Appendix'], array_column($toc, 'title'));
        $t->same([0, 1], array_column($toc, 'page'));
        $t->same(['XYZ', 'FitH'], array_column($toc, 'view_mode'));
        $t->same(['Current Trailer Chapter', 'Current Trailer Appendix'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same(
            ['Current Trailer Appendix', 'Current Trailer Appendix'],
            array_column($navigation['outline_action_review_actions'] ?? [], 'outline_title')
        );
        $t->same(['GoTo', 'URI'], array_column($navigation['outline_action_review_actions'] ?? [], 'action_type'));
        $t->same('Current Trailer Info', $metadata['title']);
        $t->same(['Current Metadata Team'], $metadata['authors']);
        $t->same(['current outline metadata'], $metadata['keywords']);
        $t->same('catalog_outlines', $metadata['document_outline']['source'] ?? null);
        $t->same(25, $metadata['document_outline']['outline_root_object'] ?? null);
        $t->same(['Current Trailer Chapter', 'Current Trailer Appendix'], $metadata['document_outline']['titles'] ?? []);
        $t->same("Current trailer root intro body\nCurrent trailer root appendix body", $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Stale Root Outline'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'stale trailer root outline action'));
        $t->true(is_string($metadataEncoded) && !str_contains($metadataEncoded, 'Stale Root Outline'));
        $t->true(is_string($metadataEncoded) && !str_contains($metadataEncoded, 'Stale Info Title'));
        $t->true(!str_contains($plainText, 'Stale trailer root page leak'));
        $t->true(!str_contains($plainText, 'Stale Root Outline'));
    },
];
