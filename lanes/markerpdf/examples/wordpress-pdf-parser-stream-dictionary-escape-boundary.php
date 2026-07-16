<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Escaped dictionary boundary) Tj T* (WordPress parser safe) Tj ET';
$compressed = gzcompress($content);
if (!is_string($compressed)) {
    throw new RuntimeException('Unable to compress stream dictionary escape-boundary smoke fixture.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n"
    . "<< /Producer (Ignore /Filter /DCTDecode /Length 1 /DecodeParms << /Predictor 12 >>) "
    . "% /Filter /DCTDecode /Length 1\n"
    . "/Fil#74er /FlateDecode /Len#67th " . strlen($compressed) . " >>\n"
    . "stream\n{$compressed}\nendstream\n"
    . "endobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
if ($lines !== ['Escaped dictionary boundary', 'WordPress parser safe']) {
    throw new RuntimeException('Expected escaped top-level stream dictionary keys to decode current text.');
}

echo '<!-- markerpdf-parser-stream-dictionary-escape-boundary-smoke ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-stream-dictionary-parser',
    'native_boundary' => 'stream dictionaries skip literal and comment names while decoding escaped top-level keys',
    'escaped_filter_key_resolved' => true,
    'escaped_length_key_resolved' => true,
    'literal_filter_noise_excluded' => !str_contains($plainText, 'DCTDecode'),
    'comment_filter_noise_excluded' => true,
    'page_labels' => $extractor->extractPageLabels($pdf),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
