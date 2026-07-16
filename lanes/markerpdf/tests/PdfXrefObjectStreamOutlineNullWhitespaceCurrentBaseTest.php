<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamOutlineNullWhitespaceCurrentBasePdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (NUL outline intro body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 700 Td (NUL outline appendix body) Tj ET';

    $members = [
        5 => '<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>',
        6 => '<< /Title (NUL Outline Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R >>',
        7 => '<< /Title (NUL Outline Appendix) /Parent 5 0 R /Prev 6 0 R /A 8 0 R >>',
        8 => '<< /S /GoTo /D [4 0 R /XYZ 144 null 0] /Next 9 0 R >>',
        9 => '<< /S /URI /URI (https://example.com/nul-outline-review) >>',
    ];

    $objectData = '';
    $headerParts = [];
    $memberIndexes = [];
    $memberIndex = 0;
    foreach ($members as $objectNumber => $body) {
        if ($objectData !== '') {
            $objectData .= "\0";
        }
        $headerParts[] = $objectNumber . "\0" . strlen($objectData);
        $memberIndexes[$objectNumber] = $memberIndex++;
        $objectData .= $body;
    }

    $header = implode("\0", $headerParts) . "\0";
    $objectStreamPayload = $header . $objectData;
    $compressedObjectStream = gzcompress($objectStreamPayload);
    if (!is_string($compressedObjectStream)) {
        throw new RuntimeException('Unable to compress NUL-whitespace outline object stream fixture.');
    }

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>');
    $addObject(4, '<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>');
    $addObject(20, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . strlen($header) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");
    $addObject(30, "<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream");
    $addObject(31, "<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream");

    $xrefOffset = strlen($pdf);
    $xrefRows = '';
    for ($objectNumber = 0; $objectNumber <= 40; $objectNumber++) {
        if ($objectNumber === 0) {
            $xrefRows .= $xrefRow(0, 0);
            continue;
        }
        if (isset($memberIndexes[$objectNumber])) {
            $xrefRows .= $xrefRow(2, 20, $memberIndexes[$objectNumber]);
            continue;
        }
        if ($objectNumber === 40) {
            $xrefRows .= $xrefRow(1, $xrefOffset);
            continue;
        }
        if (isset($offsets[$objectNumber])) {
            $xrefRows .= $xrefRow(1, $offsets[$objectNumber]);
            continue;
        }

        $xrefRows .= $xrefRow(0, 0);
    }

    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress NUL-whitespace outline xref stream fixture.');
    }

    $pdf .= "40 0 obj\n"
        . '<< /Type /XRef /Size 41 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'parses NUL PDF whitespace in xref-selected outline object streams before WordPress review' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamOutlineNullWhitespaceCurrentBasePdf): void {
        $pdf = $xrefObjectStreamOutlineNullWhitespaceCurrentBasePdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $metadata['document_outline']['source'] ?? null);
        $t->same(5, $metadata['document_outline']['outline_root_object'] ?? null);
        $t->same(['NUL Outline Chapter', 'NUL Outline Appendix'], $metadata['document_outline']['titles'] ?? []);
        $t->same([6, 7], array_column($metadata['document_outline']['items'] ?? [], 'outline_object'));
        $t->same(['NUL Outline Chapter', 'NUL Outline Appendix'], array_column($toc, 'title'));
        $t->same([0, 1], array_column($toc, 'page'));
        $t->same(['FitH', 'XYZ'], array_column($toc, 'view_mode'));
        $t->same(['NUL Outline Chapter', 'NUL Outline Appendix'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same(['GoTo', 'URI'], array_column($navigation['outline_action_review_actions'] ?? [], 'action_type'));
        $t->same(['local-destination', 'review-uri'], array_column($navigation['outline_action_review_actions'] ?? [], 'safety'));
        $t->same("NUL outline intro body\nNUL outline appendix body", $plainText);
        $t->true(is_string($encodedNavigation) && str_contains($encodedNavigation, 'https://example.com/nul-outline-review'));
        $t->true(!str_contains($plainText, 'NUL Outline Chapter'));
        $t->true(!str_contains($plainText, 'NUL Outline Appendix'));
        $t->true(!str_contains($plainText, 'nul-outline-review'));
    },
];
