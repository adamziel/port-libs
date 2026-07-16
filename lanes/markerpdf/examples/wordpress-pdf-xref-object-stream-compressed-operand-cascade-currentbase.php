<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = 'BT /F1 12 Tf 72 720 Td (Compressed operand cascade page) Tj ET';
$payload = '<wp-export><post id="object-stream-operand-cascade"/></wp-export>';
$checksum = md5($payload);

$objectStream = static function (array $members): array {
    $headerPairs = [];
    $indexes = [];
    $data = '';
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($data);
        $indexes[$objectNumber] = count($indexes);
        $data .= $body . "\n";
    }

    $header = implode(' ', $headerPairs);
    $compressed = gzcompress($header . "\n" . $data);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress object-stream operand cascade smoke fixture.');
    }

    return [
        'count' => count($members),
        'first' => strlen($header) + 1,
        'indexes' => $indexes,
        'content' => $compressed,
    ];
};

$carrier = $objectStream([
    1 => '<< /Type /Catalog /Pages 2 0 R /Lang (en-CA) /PageMode /UseOutlines /ViewerPreferences << /DisplayDocTitle true >> /Names << /EmbeddedFiles 8 0 R >> /AF [10 0 R] >>',
    6 => '<< /Title (Compressed Operand Cascade Title) /Author (Cascade Author) /Producer (Cascade Producer) >>',
    8 => '<< /Names [(operand-cascade.xml) 10 0 R] >>',
    10 => '<< /Type /Filespec /F (operand-cascade.xml) /Desc (Compressed operand cascade attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>',
]);
$helper = $objectStream([
    90 => (string) $carrier['count'],
    91 => (string) $carrier['first'],
]);

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};
$row = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . pack('n', $fieldThree);

$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream");
$addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(11, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($payload) . ' /CheckSum <' . $checksum . "> >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream");
$addObject(20, '<< /Type /ObjStm /N 90 0 R /First 91 0 R /Filter /FlateDecode /Length ' . strlen($carrier['content']) . " >>\nstream\n{$carrier['content']}\nendstream");
$addObject(21, '<< /Type /ObjStm /N ' . $helper['count'] . ' /First ' . $helper['first'] . ' /Filter /FlateDecode /Length ' . strlen($helper['content']) . " >>\nstream\n{$helper['content']}\nendstream");

$xrefRows = '';
for ($objectNumber = 0; $objectNumber <= 91; $objectNumber++) {
    if ($objectNumber === 0) {
        $xrefRows .= $row(0, 0, 65535);
        continue;
    }

    if (isset($carrier['indexes'][$objectNumber])) {
        $xrefRows .= $row(2, 20, $carrier['indexes'][$objectNumber]);
        continue;
    }

    if (isset($helper['indexes'][$objectNumber])) {
        $xrefRows .= $row(2, 21, $helper['indexes'][$objectNumber]);
        continue;
    }

    $xrefRows .= isset($offsets[$objectNumber])
        ? $row(1, $offsets[$objectNumber], 0)
        : $row(0, 0, 0);
}

$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress object-stream operand cascade xref smoke rows.');
}

$xrefOffset = strlen($pdf);
$pdf .= "30 0 obj\n"
    . '<< /Type /XRef /Size 92 /Root 1 0 R /Info 6 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$textExtractor = new PdfTextExtractor();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$embedded = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$review = $textExtractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');
$embeddedJson = json_encode($embedded, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$plainText = $textExtractor->extractPlainText($pdf);

$smoke = [
    'native_boundary' => 'object-stream /N and /First operands are recovered from xref-selected compressed helper members',
    'metadata_selected_from_compressed_catalog' => ($metadata['title'] ?? null) === 'Compressed Operand Cascade Title'
        && ($metadata['language'] ?? null) === 'en-CA',
    'embedded_file_selected_from_compressed_filespec' => ($embedded[0]['filename'] ?? null) === 'operand-cascade.xml'
        && ($embedded[0]['content_sha256'] ?? null) === hash('sha256', $payload),
    'helper_members_expanded_before_carrier' => ($entries[90]['object_stream'] ?? null) === 21
        && ($entries[1]['object_stream'] ?? null) === 20,
    'payload_excluded_from_visible_text' => !str_contains($plainText, $payload),
    'embedded_payload_available_for_attachment_review' => str_contains($embeddedJson, 'object-stream-operand-cascade'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'metadata_selected_from_compressed_catalog',
    'embedded_file_selected_from_compressed_filespec',
    'helper_members_expanded_before_carrier',
    'payload_excluded_from_visible_text',
    'embedded_payload_available_for_attachment_review',
] as $requiredFlag) {
    if (($smoke[$requiredFlag] ?? false) !== true) {
        throw new RuntimeException('Object-stream compressed operand cascade smoke failed: ' . $requiredFlag);
    }
}

echo '<!-- markerpdf-xref-object-stream-compressed-operand-cascade-currentbase-smoke ' . htmlspecialchars(json_encode(
    $smoke,
    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($textExtractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo "<!-- wp:list -->\n<ul>\n";
echo '<li data-marker-file-sha256="'
    . htmlspecialchars((string) ($embedded[0]['content_sha256'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '">'
    . htmlspecialchars(
        (string) ($embedded[0]['filename'] ?? 'attachment')
        . ' - ' . (string) ($embedded[0]['relationship'] ?? 'unassociated')
        . ', ' . (string) ($embedded[0]['mime_type'] ?? 'application/octet-stream')
        . ', ' . strlen($payload) . ' bytes',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    )
    . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
