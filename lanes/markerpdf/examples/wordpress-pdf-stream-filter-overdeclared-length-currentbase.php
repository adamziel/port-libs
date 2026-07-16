<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstContent = 'BT /F1 12 Tf 72 720 Td (Overdeclared Flate Stack Before) Tj ET';
$compressed = gzcompress($firstContent);
if (!is_string($compressed)) {
    throw new RuntimeException('Unable to compress overdeclared Flate stack smoke fixture.');
}

$afterContent = 'BT /F1 12 Tf 72 700 Td (Visible After Overdeclared Stack) Tj ET';
$afterObject = "5 0 obj\n<< /Length " . strlen($afterContent) . " >>\nstream\n{$afterContent}\nendstream\nendobj\n";
$tail = "\nendstream\nendobj\n{$afterObject}%%EOF";
$declaredLength = strlen($compressed) + strlen($tail) - 20;
if ($declaredLength <= strlen($compressed)) {
    throw new RuntimeException('Overdeclared Flate stack smoke fixture did not overrun the stream boundary.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents [4 0 R 5 0 R] >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "4 0 obj\n<< /Length {$declaredLength} /Filter /FlateDecode >>\nstream\n{$compressed}{$tail}";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$expected = ['Overdeclared Flate Stack Before', 'Visible After Overdeclared Stack'];

if ($lines !== $expected) {
    throw new RuntimeException('Expected overdeclared Flate stream and following content object to both import.');
}

$streamSyntaxExcluded = !str_contains($plainText, 'endstream')
    && !str_contains($plainText, 'endobj')
    && !str_contains($plainText, 'FlateDecode');
if (!$streamSyntaxExcluded) {
    throw new RuntimeException('Expected stream syntax to stay out of WordPress paragraph text.');
}

echo '<!-- markerpdf:pdf-stream-filter-overdeclared-length ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-stream-filter-stack',
    'declared_length' => $declaredLength,
    'compressed_length' => strlen($compressed),
    'overdeclared_length_recovered_at_filter_stack' => true,
    'later_content_object_preserved' => $lines[1] === 'Visible After Overdeclared Stack',
    'stream_syntax_excluded' => $streamSyntaxExcluded,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
