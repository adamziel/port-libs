<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pngSubPredictorEncode = static function (string $bytes, int $columns): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $columns) {
        $row = substr($bytes, $offset, $columns);
        if (strlen($row) !== $columns) {
            throw new RuntimeException('Stream-dictionary owner smoke rows must be fixed-width.');
        }

        $encoded .= "\x01";
        for ($index = 0; $index < $columns; $index++) {
            $left = $index > 0 ? ord($row[$index - 1]) : 0;
            $encoded .= chr((ord($row[$index]) - $left) & 0xff);
        }
    }

    return $encoded;
};

$rowOne = 'BT /F1 12 Tf 72 720 Td (Current stream dictionary owner) Tj T* ';
$rowTwo = str_pad('(Current DecodeParms helper applied) Tj ET', strlen($rowOne));
$currentPayload = $rowOne . $rowTwo;
$currentCompressed = gzcompress($pngSubPredictorEncode($currentPayload, strlen($rowOne)), 0);
$stalePayload = 'BT /F1 12 Tf 72 720 Td (Stale stream dictionary leak) Tj ET';
$staleCompressed = gzcompress($stalePayload, 0);
if (!is_string($currentCompressed) || !is_string($staleCompressed)) {
    throw new RuntimeException('Unable to compress stream-dictionary owner smoke fixture.');
}

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (?int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
    "%010d %05d %s \n",
    $offset ?? 0,
    $generation,
    $state
);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 1 R >>');
$addObject(4, 0, "<< /Filter /FlateDecode /Length " . strlen($staleCompressed) . " >>\nstream\n{$staleCompressed}\nendstream");
$addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(7, 0, '1');
$addObject(8, 0, '/ASCIIHexDecode');
$addObject(9, 0, '<< /Predictor /Twelve /Columns 1 >>');
$addObject(4, 1, "<< /Filter 8 1 R /DecodeParms 9 1 R /Length 7 1 R /Note (99 0 obj fake dictionary owner) >>\nstream\n{$currentCompressed}\nendstream");
$addObject(7, 1, (string) strlen($currentCompressed));
$addObject(8, 1, '/FlateDecode');
$addObject(9, 1, '<< /Predictor 12 /Columns ' . strlen($rowOne) . ' >>');

$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 10\n" . $xrefRow(0, 65535, 'f');
for ($objectNumber = 1; $objectNumber <= 9; $objectNumber++) {
    if (isset($offsets[$objectNumber . ':1'])) {
        $pdf .= $xrefRow($offsets[$objectNumber . ':1'], 1);
        continue;
    }

    $pdf .= isset($offsets[$objectNumber . ':0'])
        ? $xrefRow($offsets[$objectNumber . ':0'])
        : $xrefRow(0, 65535, 'f');
}
$pdf .= "trailer\n<< /Size 10 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf-parser-stream-dictionary-owner-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF page content streams use the current xref-selected stream dictionary generation and matching Length/Filter/DecodeParms helpers before WordPress text extraction',
    'uses_current_stream_dictionary_owner' => str_contains($plainText, 'Current stream dictionary owner'),
    'current_decodeparms_helper_applied' => str_contains($plainText, 'Current DecodeParms helper applied'),
    'stale_stream_dictionary_excluded' => !str_contains($plainText, 'Stale stream dictionary leak'),
    'stale_helper_names_excluded' => !str_contains($plainText, 'ASCIIHexDecode') && !str_contains($plainText, 'Twelve'),
    'fake_dictionary_owner_excluded' => !str_contains($plainText, '99 0 obj'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
