<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserStreamDecodeParmsOwnerCurrentBasePdf = static function (): string {
    $pngSubPredictorEncode = static function (string $bytes, int $columns): string {
        $encoded = '';
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $columns) {
            $row = substr($bytes, $offset, $columns);
            if (strlen($row) !== $columns) {
                throw new RuntimeException('Focused DecodeParms owner rows must be fixed-width.');
            }

            $encoded .= "\x01";
            for ($index = 0; $index < $columns; $index++) {
                $left = $index > 0 ? ord($row[$index - 1]) : 0;
                $encoded .= chr((ord($row[$index]) - $left) & 0xff);
            }
        }

        return $encoded;
    };

    $rowOne = 'BT /F1 12 Tf 72 720 Td (Current DecodeParms Owner) Tj T* ';
    $rowTwo = str_pad('(Indirect Length Skips Fake) Tj ET', strlen($rowOne));
    $compressed = gzcompress($pngSubPredictorEncode($rowOne . $rowTwo, strlen($rowOne)));
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress focused DecodeParms owner fixture.');
    }

    $visibleAfter = 'BT /F1 12 Tf 72 680 Td (Visible After Owner Boundary) Tj ET';
    $carrierPayload = "BT /F1 12 Tf 72 640 Td (Carrier stream text leak) Tj ET\n"
        . "endstream\nendobj\n"
        . "20 0 obj\n<< /Predictor /Twelve /Columns 1 >>\nendobj\n"
        . 'BT /F1 12 Tf 72 620 Td (Post fake decodeparms leak) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
        . "20 0 obj\n<< /Predictor 12 /Columns " . strlen($rowOne) . " >>\nendobj\n"
        . "30 0 obj\n" . strlen($carrierPayload) . "\nendobj\n"
        . "4 0 obj\n<< /Filter /FlateDecode /DecodeParms 20 0 R /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length 30 0 R >>\nstream\n{$carrierPayload}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'rejects stream-owned fake DecodeParms objects before current-base WordPress text extraction' => static function (TestRunner $t) use ($parserStreamDecodeParmsOwnerCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamDecodeParmsOwnerCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = ['Current DecodeParms Owner', 'Indirect Length Skips Fake', 'Visible After Owner Boundary'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Current DecodeParms Owner\nIndirect Length Skips Fake\nVisible After Owner Boundary", $text);
        $t->same("Current DecodeParms Owner\nIndirect Length Skips Fake\nVisible After Owner Boundary\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Carrier stream text leak'));
        $t->true(!str_contains($text, 'Post fake decodeparms leak'));
        $t->true(!str_contains($text, 'Twelve'));
        $t->true(!str_contains($text, "\0"));
    },
];
