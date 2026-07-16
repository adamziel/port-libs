<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataDestinationViewBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Outline destination view boundary intro body) Tj ET';
    $targetContent = 'BT /F1 12 Tf 72 720 Td (Outline destination view boundary target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 10 0 R /Count 5 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Valid Direct FitH Bookmark) /Parent 5 0 R /Dest [4 0 R /FitH 680 999] /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Invalid Action View Bookmark) /Parent 5 0 R /Prev 6 0 R /Dest [4 0 R /Launch 77] /Next 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Title (Invalid Indirect View Bookmark) /Parent 5 0 R /Prev 7 0 R /Dest [4 0 R 12 0 R 88] /Next 9 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Title (Valid Named FitB Bookmark) /Parent 5 0 R /Prev 8 0 R /Dest /BoxTarget /Next 10 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Title (Valid Action XYZ Bookmark) /Parent 5 0 R /Prev 9 0 R /A 14 0 R >>\nendobj\n"
        . "12 0 obj\n/RichMedia\nendobj\n"
        . "14 0 obj\n<< /S /GoTo /D /ZoomTarget >>\nendobj\n"
        . "20 0 obj\n<< /Names [(BoxTarget) [3 0 R /FitB 111 222] (ZoomTarget) [4 0 R /XYZ 72 640 0 444] (InvalidNamed) [4 0 R /Movie 99]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetContent) . " >>\nstream\n{$targetContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'rejects unknown outline destination view names in document metadata while normalizing valid view operands' => static function (
        TestRunner $t
    ) use ($outlineMetadataDestinationViewBoundaryPdf): void {
        $pdf = $outlineMetadataDestinationViewBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['declared_visible_count'] ?? null);
        $t->same(5, $outline['item_count'] ?? null);
        $t->same(3, $outline['resolved_destination_count'] ?? null);
        $t->same(2, $outline['unresolved_destination_count'] ?? null);
        $t->same([
            'Valid Direct FitH Bookmark',
            'Invalid Action View Bookmark',
            'Invalid Indirect View Bookmark',
            'Valid Named FitB Bookmark',
            'Valid Action XYZ Bookmark',
        ], $outline['titles'] ?? []);

        $t->same([true, false, false, true, true], array_column($items, 'destination_resolved'));
        $t->same(['FitH', null, null, 'FitB', 'XYZ'], [
            $items[0]['view_mode'] ?? null,
            $items[1]['view_mode'] ?? null,
            $items[2]['view_mode'] ?? null,
            $items[3]['view_mode'] ?? null,
            $items[4]['view_mode'] ?? null,
        ]);
        $t->same([680.0], $items[0]['view_position'] ?? null);
        $t->same(['top' => 680.0], $items[0]['view_parameters'] ?? null);
        $t->same([], $items[3]['view_position'] ?? null);
        $t->same([], $items[3]['view_parameters'] ?? null);
        $t->same([72.0, 640.0, null], $items[4]['view_position'] ?? null);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => null], $items[4]['view_parameters'] ?? null);

        foreach ([1, 2] as $index) {
            $t->true(!array_key_exists('page', $items[$index]));
            $t->true(!array_key_exists('page_object', $items[$index]));
            $t->true(!array_key_exists('view_mode', $items[$index]));
            $t->true(!array_key_exists('view_position', $items[$index]));
            $t->true(!array_key_exists('view_parameters', $items[$index]));
        }

        foreach (['Launch', 'RichMedia', 'Movie', '77', '88', '99', '444'] as $hidden) {
            $t->true(is_string($encoded) && !str_contains($encoded, $hidden));
        }
    },
    'keeps outline TOC and navigation review aligned with valid destination view boundaries' => static function (
        TestRunner $t
    ) use ($outlineMetadataDestinationViewBoundaryPdf): void {
        $pdf = $outlineMetadataDestinationViewBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same([
            'Valid Direct FitH Bookmark',
            'Valid Named FitB Bookmark',
            'Valid Action XYZ Bookmark',
        ], array_column($toc, 'title'));
        $t->same(['FitH', 'FitB', 'XYZ'], array_column($toc, 'view_mode'));
        $t->same([[680.0], [], [72.0, 640.0, null]], array_column($toc, 'view_position'));
        $t->same([['top' => 680.0], [], ['left' => 72.0, 'top' => 640.0, 'zoom' => null]], array_column($toc, 'view_parameters'));

        $t->same(['outline'], $navigation['source']);
        $t->same(array_column($toc, 'title'), array_column($navigation['outline'], 'title'));
        $t->same(['FitH', 'FitB', 'XYZ'], array_column($navigation['outline'], 'view_mode'));
        $t->same([], $navigation['outline_action_review_actions']);

        $t->same("Outline destination view boundary intro body\nOutline destination view boundary target body", $plainText);
        foreach ([
            'Invalid Action View Bookmark',
            'Invalid Indirect View Bookmark',
            'Launch',
            'RichMedia',
            'Movie',
            '77',
            '88',
            '99',
            '444',
        ] as $hidden) {
            $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
