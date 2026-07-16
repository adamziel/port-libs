<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineXrefOwnerBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Current xref owner outline intro body) Tj ET';
    $targetContent = 'BT /F1 12 Tf 72 720 Td (Current xref owner outline target body) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>');
    $addObject(4, '<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>');
    $addObject(5, '<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>');
    $addObject(6, '<< /Title (Current XRef Owner Chapter) /Parent 5 0 R /Dest /CurrentStart /Next 7 0 R /C [0 .2 .6] /F 2 >>');
    $addObject(7, '<< /Title (Current XRef Owner Appendix) /Parent 5 0 R /Prev 6 0 R /A 12 0 R >>');
    $addObject(12, '<< /S /GoTo /D /CurrentTarget /Next 13 0 R >>');
    $addObject(13, '<< /S /URI /URI (https://example.com/current-xref-owner-outline-review) >>');
    $addObject(20, '<< /Names [(CurrentStart) [3 0 R /FitH 720] (CurrentTarget) [4 0 R /XYZ 144 640 0]] >>');
    $addObject(30, "<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream");
    $addObject(31, "<< /Length " . strlen($targetContent) . " >>\nstream\n{$targetContent}\nendstream");

    $xrefOffset = strlen($pdf);
    $maxObject = 31;
    $pdf .= "xref\n0 " . ($maxObject + 1) . "\n"
        . "0000000000 65535 f \n";
    for ($objectNumber = 1; $objectNumber <= $maxObject; $objectNumber++) {
        $pdf .= isset($offsets[$objectNumber])
            ? sprintf("%010d 00000 n \n", $offsets[$objectNumber])
            : "0000000000 00000 f \n";
    }
    $pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 1 0 R >>\n"
        . "5 0 obj\n<< /Type /Outlines /First 70 0 R /Last 70 0 R /Count 1 >>\nendobj\n"
        . "70 0 obj\n<< /Title (Stale XRef Owner Outline) /Parent 5 0 R /A 71 0 R >>\nendobj\n"
        . "71 0 obj\n<< /S /JavaScript /JS (app.alert\\('stale xref outline action'\\)) >>\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'uses xref-selected outline objects before unindexed duplicate outline metadata' => static function (
        TestRunner $t
    ) use ($outlineXrefOwnerBoundaryPdf): void {
        $pdf = $outlineXrefOwnerBoundaryPdf();
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
        $t->same(2, $outline['item_count'] ?? null);
        $t->same(2, $outline['resolved_destination_count'] ?? null);
        $t->same(['Current XRef Owner Chapter', 'Current XRef Owner Appendix'], $outline['titles'] ?? []);
        $t->same([6, 7], array_column($items, 'outline_object'));
        $t->same([0, 1], array_column($items, 'page'));
        $t->same(['FitH', 'XYZ'], array_column($items, 'view_mode'));
        $t->same('#003399', $items[0]['text_color_hex'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale XRef Owner Outline'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale xref outline action'));
    },
    'applies xref-selected outline owners to TOC navigation and visible text boundaries' => static function (
        TestRunner $t
    ) use ($outlineXrefOwnerBoundaryPdf): void {
        $pdf = $outlineXrefOwnerBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(['Current XRef Owner Chapter', 'Current XRef Owner Appendix'], array_column($toc, 'title'));
        $t->same([1, 1], array_column($toc, 'level'));
        $t->same([0, 1], array_column($toc, 'page'));
        $t->same(['Current XRef Owner Chapter', 'Current XRef Owner Appendix'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same(
            ['Current XRef Owner Appendix', 'Current XRef Owner Appendix'],
            array_column($navigation['outline_action_review_actions'] ?? [], 'outline_title')
        );
        $t->same(['GoTo', 'URI'], array_column($navigation['outline_action_review_actions'] ?? [], 'action_type'));
        $t->same("Current xref owner outline intro body\nCurrent xref owner outline target body", $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Stale XRef Owner Outline'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'stale xref outline action'));
        $t->true(!str_contains($plainText, 'Current XRef Owner Chapter'));
        $t->true(!str_contains($plainText, 'Current XRef Owner Appendix'));
        $t->true(!str_contains($plainText, 'Stale XRef Owner Outline'));
        $t->true(!str_contains($plainText, 'stale xref outline action'));
    },
];
