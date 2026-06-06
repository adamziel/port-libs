<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceObjectStreamInheritanceCurrentBasePdf = static function (): string {
    $pageContent = "BT /FObj 12 Tf 72 720 Td (Compressed object stream page resource text) Tj ET\n"
        . "q 1 0 0 1 72 690 cm /CompressedForm Do Q";
    $formContent = 'BT /FObj 10 Tf 0 0 Td (Compressed object stream form text) Tj ET';

    $resourceBody = '<< /Font << /FObj 5 0 R >> /XObject << /CompressedForm 6 0 R >> /ProcSet [/PDF /Text] >>';
    $decoyResourceBody = '<< /Font << /DecoyFont 5 0 R >> /XObject << /DecoyForm 6 0 R >> >>';
    $members = [
        10 => $resourceBody,
        11 => $decoyResourceBody,
    ];

    $objectData = '';
    $headerPairs = [];
    $memberIndexes = [];
    $memberIndex = 0;
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $memberIndexes[$objectNumber] = $memberIndex;
        $objectData .= $body . "\n";
        $memberIndex++;
    }

    $header = implode(' ', $headerPairs);
    $objectStreamPlain = $header . "\n" . $objectData;
    $compressedObjectStream = gzcompress($objectStreamPlain);
    if (!is_string($compressedObjectStream)) {
        throw new RuntimeException('Unable to compress page-resource object stream fixture.');
    }

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(6, 0, "<< /Type /XObject /Subtype /Form /BBox [0 0 200 50] /Resources << /Font << /FObj 5 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream");
    $addObject(20, 0, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");

    $row = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);
    $xrefRows = ''
        . $row(1, $offsets['1:0'])
        . $row(1, $offsets['2:0'])
        . $row(1, $offsets['3:0'])
        . $row(1, $offsets['4:0'])
        . $row(1, $offsets['5:0'])
        . $row(1, $offsets['6:0'])
        . $row(2, 20, $memberIndexes[10])
        . $row(1, $offsets['20:0']);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress page-resource xref stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "30 0 obj\n"
        . '<< /Type /XRef /Size 31 /Root 1 0 R /Index [1 6 10 1 20 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'inherits xref-selected object-stream resource dictionaries for page metadata and form text extraction' => static function (TestRunner $t) use ($pageResourceObjectStreamInheritanceCurrentBasePdf): void {
        $pdf = $pageResourceObjectStreamInheritanceCurrentBasePdf();
        $textExtractor = new PdfTextExtractor();
        $metadata = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $plainText = $textExtractor->extractPlainText($pdf);

        $t->same([
            'Compressed object stream page resource text',
            'Compressed object stream form text',
        ], $textExtractor->extractTextLines($pdf));
        $t->same("Compressed object stream page resource text\nCompressed object stream form text", $plainText);
        $t->same(1, count($metadata));
        $t->same(3, $metadata[0]['page_object'] ?? null);
        $t->same(true, $metadata[0]['resources']['inherited'] ?? null);
        $t->same(2, $metadata[0]['resources']['resource_owner_object'] ?? null);
        $t->same(10, $metadata[0]['resources']['resource_object'] ?? null);
        $t->same(0, $metadata[0]['resources']['resource_generation'] ?? null);
        $t->same(['Font', 'XObject', 'ProcSet'], $metadata[0]['resources']['categories'] ?? null);
        $t->same(['FObj'], $metadata[0]['resources']['font_names'] ?? null);
        $t->same(['CompressedForm'], $metadata[0]['resources']['xobject_names'] ?? null);
        $t->same(['PDF', 'Text'], $metadata[0]['resources']['procset_names'] ?? null);
        $t->true(!str_contains(json_encode($metadata, JSON_THROW_ON_ERROR), 'Decoy'));
        $t->true(!str_contains($plainText, 'Decoy'));
    },
];
