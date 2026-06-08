<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$ascii85 = static function (string $bytes): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += 4) {
        $chunk = substr($bytes, $offset, 4);
        $chunkLength = strlen($chunk);
        if ($chunkLength < 4) {
            $chunk = str_pad($chunk, 4, "\0");
        }

        $value = unpack('N', $chunk)[1];
        if ($chunkLength === 4 && $value === 0) {
            $encoded .= 'z';
            continue;
        }

        $chars = '';
        for ($index = 0; $index < 5; $index++) {
            $chars = chr(($value % 85) + 33) . $chars;
            $value = intdiv($value, 85);
        }

        $encoded .= substr($chars, 0, $chunkLength + 1);
    }

    return $encoded;
};

$zlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    $s1 = 1;
    $s2 = 0;
    for ($index = 0; $index < $length; $index++) {
        $s1 = ($s1 + ord($bytes[$index])) % 65521;
        $s2 = ($s2 + $s1) % 65521;
    }

    return "\x78\x01"
        . "\x01"
        . pack('v', $length)
        . pack('v', (~$length) & 0xffff)
        . $bytes
        . pack('N', ($s2 << 16) | $s1);
};

$visible = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Fake Endstream Boundary) Tj ET';
$payloadPrefix = "Title,Status\nAttachment Stack Before,";
while ((7 + strlen($payloadPrefix)) % 4 !== 0) {
    $payloadPrefix .= ' ';
}
$fakeEncodedEndstreamBytes = hex2bin('d66c4ac5fe8a5a71');
if (!is_string($fakeEncodedEndstreamBytes)) {
    throw new RuntimeException('Unable to build fake endstream bytes.');
}

$payload = $payloadPrefix . $fakeEncodedEndstreamBytes . "Attachment Stack After,Ready\n";
$encoded = $ascii85($zlibStored($payload));
if (!str_contains($encoded, 'endstream!')) {
    throw new RuntimeException('Fake endstream marker was not generated.');
}

$encoded = str_replace('endstream!', "\nendstream\n!", $encoded) . '~>';
$checksum = md5($payload);
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(fake-endstream-stack.csv) 10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (fake-endstream-stack.csv) /Desc (Fake endstream attachment stack) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null null ] /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> >> /Length " . strlen($encoded) . " >>\n"
    . "stream\n{$encoded}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$attachment = $summary['attachments'][0] ?? [];
$file = $files[0] ?? [];

if (($summary['attachment_count'] ?? null) !== 1
    || ($attachment['filename'] ?? null) !== 'fake-endstream-stack.csv'
    || ($attachment['byte_length'] ?? null) !== strlen($payload)
    || ($attachment['checksum_matches'] ?? null) !== true
    || ($attachment['filters'] ?? null) !== ['ASCII85Decode', 'FlateDecode']
    || count($files) !== 1
    || ($file['content'] ?? null) !== $payload
    || ($file['checksum_matches'] ?? null) !== true
    || !str_contains($encoded, "\nendstream\n!")
    || str_contains($summaryJson, 'Attachment Stack Before')
    || str_contains($summaryJson, 'Attachment Stack After')
    || !str_contains($plainText, 'Visible Attachment Fake Endstream Boundary')
    || str_contains($plainText, 'Attachment Stack Before')
) {
    throw new RuntimeException('Expected fake encoded endstream attachment stack to be recovered by declared stream length.');
}

echo '<!-- markerpdf-attachment-stream-filter-fake-endstream-currentbase ' . htmlspecialchars(json_encode([
    'attachment_count' => $summary['attachment_count'],
    'embedded_file_count' => count($files),
    'filename' => $attachment['filename'] ?? null,
    'filters' => $attachment['filters'] ?? [],
    'declared_length_boundary_used' => true,
    'fake_encoded_endstream_present' => str_contains($encoded, "\nendstream\n!"),
    'checksum_matches' => $attachment['checksum_matches'] ?? false,
    'payload_omitted_from_summary' => !str_contains($summaryJson, 'Attachment Stack Before'),
    'visible_text_preserved' => str_contains($plainText, 'Visible Attachment Fake Endstream Boundary'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:file {\"href\":\"media/fake-endstream-stack.csv\"} -->\n";
echo '<div class="wp-block-file"><a href="media/fake-endstream-stack.csv">fake-endstream-stack.csv</a></div>' . "\n";
echo "<!-- /wp:file -->\n";
