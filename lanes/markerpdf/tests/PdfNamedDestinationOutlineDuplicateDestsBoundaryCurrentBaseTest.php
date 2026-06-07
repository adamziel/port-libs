<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationOutlineDuplicateDestsBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Outline duplicate Dests source page) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Outline duplicate Dests target page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /#44ests 20 0 R /Dests 21 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] >> /Outlines 50 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "20 0 obj\n<< /Limits [(Current Tree) (Current Tree)] /Names [(Current Tree) [4 0 R /FitH 700]] >>\nendobj\n"
        . "21 0 obj\n<< /Limits [(Stale Tree) (Stale Tree)] /Names [(Stale Tree) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 52 0 R /Count 2 >>\nendobj\n"
        . "51 0 obj\n<< /Title (Legacy Outline) /Parent 50 0 R /Dest /LegacyOk /Next 52 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Title (Stale Tree Outline) /Parent 50 0 R /Dest (Stale Tree) /Prev 51 0 R >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'rejects duplicate catalog Names Dests keys before outline destination views' => static function (
        TestRunner $t
    ) use ($namedDestinationOutlineDuplicateDestsBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationOutlineDuplicateDestsBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['LegacyOk'], array_column($destinations, 'name'));
        $t->same(['LegacyOk'], $metadata['document_destinations']['names'] ?? null);
        $t->same(['Legacy Outline'], array_column($toc, 'title'));
        $t->same(['LegacyOk'], array_column($toc, 'destination'));
        $t->same([1], array_column($toc, 'page'));
        $t->same(['FitV'], array_column($toc, 'view_mode'));
        $t->same([['left' => 120.0]], array_column($toc, 'view_parameters'));

        $encoded = json_encode([$destinations, $metadata['document_destinations'] ?? [], $toc], JSON_UNESCAPED_SLASHES) ?: '';
        foreach (['Current Tree', 'Stale Tree', 'Stale Tree Outline', 'FitH', 'XYZ', '700', '640'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
        }
    },
    'keeps duplicate name-tree outline labels out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationOutlineDuplicateDestsBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationOutlineDuplicateDestsBoundaryCurrentBasePdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
        $encodedToc = json_encode($toc, JSON_UNESCAPED_SLASHES) ?: '';

        $t->contains('Outline duplicate Dests source page', $plainText);
        $t->contains('Outline duplicate Dests target page', $plainText);
        foreach (['Current Tree', 'Stale Tree', 'Stale Tree Outline', 'FitH', 'XYZ'] as $hidden) {
            $t->same(false, str_contains($plainText, $hidden));
            $t->same(false, str_contains($encodedToc, $hidden));
        }
        $t->same(false, str_contains($plainText, 'Legacy Outline'));
        $t->same(false, str_contains($plainText, 'LegacyOk'));
    },
];
