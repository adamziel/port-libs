<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$ascii85Encode = static function (string $bytes): string {
    $out = '<~';
    $length = strlen($bytes);
    for ($offset = 0; $offset < $length; $offset += 4) {
        $chunk = substr($bytes, $offset, 4);
        $pad = 4 - strlen($chunk);
        if ($pad > 0) {
            $chunk .= str_repeat("\0", $pad);
        }

        $value = 0;
        foreach (unpack('C*', $chunk) ?: [] as $byte) {
            $value = ($value << 8) + $byte;
        }

        if ($value === 0 && $pad === 0) {
            $out .= 'z';
            continue;
        }

        $encoded = '';
        for ($index = 0; $index < 5; $index++) {
            $encoded = chr(($value % 85) + 33) . $encoded;
            $value = intdiv($value, 85);
        }

        $out .= $pad === 0 ? $encoded : substr($encoded, 0, 5 - $pad);
    }

    return $out . '~>';
};

$runLengthEncode = static function (string $bytes): string {
    $out = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length;) {
        $chunk = substr($bytes, $offset, 128);
        $out .= chr(strlen($chunk) - 1) . $chunk;
        $offset += strlen($chunk);
    }

    return $out . chr(128);
};

$safePayload = '<wp-export><post id="safe-embedded-filter-stack"/></wp-export>';
$safeCompressed = gzcompress($safePayload);
if (!is_string($safeCompressed)) {
    throw new RuntimeException('Unable to compress safe embedded-file fixture.');
}

$ascii85Payload = '<wp-export><post id="ascii85-embedded-filter-stack"/></wp-export>';
$ascii85Compressed = gzcompress($ascii85Payload);
if (!is_string($ascii85Compressed)) {
    throw new RuntimeException('Unable to compress ASCII85 embedded-file fixture.');
}

$runLengthPayload = "Title,Status\nRunLength EmbeddedFile,Ready\n";
$runLengthCompressed = gzcompress($runLengthPayload);
if (!is_string($runLengthCompressed)) {
    throw new RuntimeException('Unable to compress RunLength embedded-file fixture.');
}

$unsafePayload = 'RAW_UNSUPPORTED_EMBEDDED_BYTES_SHOULD_NOT_COUNT';
$safeHex = strtoupper(bin2hex($safeCompressed)) . '>';
$ascii85Encoded = $ascii85Encode($ascii85Compressed);
$runLengthEncoded = strtoupper(bin2hex($runLengthEncode($runLengthCompressed))) . '>';
$unsafeHex = strtoupper(bin2hex($unsafePayload)) . '>';
$safeChecksum = strtoupper(hash('md5', $safePayload));
$ascii85Checksum = strtoupper(hash('md5', $ascii85Payload));
$runLengthChecksum = strtoupper(hash('md5', $runLengthPayload));
$unsafeChecksum = strtoupper(hash('md5', $unsafePayload));

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
    . "6 0 obj\n<< /Names [(ascii85.xml) 30 0 R (runlength.csv) 40 0 R (safe.xml) 10 0 R (unsafe.bin) 20 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (safe.xml) /Desc (Safe supported stack) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Filter [ /ASCIIHexDecode /FlateDecode ] /Params << /Size " . strlen($safePayload) . " /CheckSum <{$safeChecksum}> >> /Length " . strlen($safeHex) . " >>\nstream\n{$safeHex}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Filespec /F (ascii85.xml) /Desc (ASCII85 EmbeddedFile stack) /AFRelationship /Source /EF << /F 31 0 R >> >>\nendobj\n"
    . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Filter [ null /A85 /FlateDecode ] /Params << /Size " . strlen($ascii85Payload) . " /CheckSum <{$ascii85Checksum}> >> /Length " . strlen($ascii85Encoded) . " >>\nstream\n{$ascii85Encoded}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Filespec /F (runlength.csv) /Desc (RunLength EmbeddedFile stack) /AFRelationship /Data /EF << /F 41 0 R >> >>\nendobj\n"
    . "41 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCIIHexDecode /RL /Fl ] /Params << /Size " . strlen($runLengthPayload) . " /CheckSum <{$runLengthChecksum}> >> /Length " . strlen($runLengthEncoded) . " >>\nstream\n{$runLengthEncoded}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (unsafe.bin) /Desc (Unsupported preview-only terminal filter) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Foctet-stream /Filter [ /ASCIIHexDecode /DCTDecode ] /Params << /Size " . strlen($unsafePayload) . " /CheckSum <{$unsafeChecksum}> >> /Length " . strlen($unsafeHex) . " >>\nstream\n{$unsafeHex}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$encoded = json_encode($files, JSON_UNESCAPED_SLASHES);
if (!is_string($encoded)) {
    throw new RuntimeException('Unable to encode embedded-file smoke metadata.');
}

$unsafeRejected = !str_contains($encoded, 'unsafe.bin')
    && !str_contains($encoded, 'UNSUPPORTED_EMBEDDED_BYTES')
    && !str_contains($encoded, strtolower($unsafeChecksum));
$filesByName = [];
foreach ($files as $file) {
    if (is_array($file) && isset($file['filename']) && is_string($file['filename'])) {
        $filesByName[$file['filename']] = $file;
    }
}

$safeFile = $filesByName['safe.xml'] ?? null;
$ascii85File = $filesByName['ascii85.xml'] ?? null;
$runLengthFile = $filesByName['runlength.csv'] ?? null;
$safePayloadPreserved = is_array($safeFile)
    && ($safeFile['filename'] ?? null) === 'safe.xml'
    && ($safeFile['content'] ?? null) === $safePayload
    && ($safeFile['checksum_matches'] ?? false) === true;
$ascii85PayloadPreserved = is_array($ascii85File)
    && ($ascii85File['content'] ?? null) === $ascii85Payload
    && ($ascii85File['checksum_matches'] ?? false) === true;
$runLengthPayloadPreserved = is_array($runLengthFile)
    && ($runLengthFile['content'] ?? null) === $runLengthPayload
    && ($runLengthFile['checksum_matches'] ?? false) === true;

if (count($files) !== 3 || !$safePayloadPreserved || !$ascii85PayloadPreserved || !$runLengthPayloadPreserved || !$unsafeRejected) {
    throw new RuntimeException('Supported EmbeddedFile stream stacks must decode and unsupported terminal filters must be rejected before payload review.');
}

$metadata = [
    'native_boundary' => 'WordPress EmbeddedFiles stream-filter stack safe ASCII85/RunLength plus unsupported terminal fail-closed',
    'safe_file_count' => count($files),
    'safe_filters' => $safeFile['filters'] ?? [],
    'ascii85_filters' => $ascii85File['filters'] ?? [],
    'runlength_filters' => $runLengthFile['filters'] ?? [],
    'safe_payload_preserved' => $safePayloadPreserved,
    'ascii85_payload_preserved' => $ascii85PayloadPreserved,
    'runlength_payload_preserved' => $runLengthPayloadPreserved,
    'unsupported_stack_rejected' => $unsafeRejected,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:embedded-file-filter-stack-boundary ' . htmlspecialchars(
    json_encode($metadata, JSON_UNESCAPED_SLASHES),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";
echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'safe.xml, ascii85.xml, and runlength.csv EmbeddedFiles decoded while unsupported terminal stream filters were excluded from payload review',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
