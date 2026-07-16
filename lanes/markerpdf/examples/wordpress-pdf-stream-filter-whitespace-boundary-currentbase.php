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

        $digits = '';
        for ($index = 0; $index < 5; $index++) {
            $digits = chr(($value % 85) + 33) . $digits;
            $value = intdiv($value, 85);
        }
        $encoded .= substr($digits, 0, $chunkLength + 1);
    }

    return $encoded . '~>';
};

$asciiHex = static function (string $bytes): string {
    return strtoupper(bin2hex($bytes)) . '>';
};

$visibleContent = 'BT /F1 12 Tf 72 720 Td (Visible After Filter Whitespace Boundary) Tj ET';
$leakedContent = 'BT /F1 12 Tf 72 700 Td (Leading ASCII85 Whitespace Leak) Tj ET';
$goodPayload = "Title,Status\nClean Filter Stack,Ready\n";
$badPayload = "Title,Status\nBad Non-PDF Whitespace,Leaked\n";
$goodCompressed = gzcompress($goodPayload);
$badCompressed = gzcompress($badPayload);
if (!is_string($goodCompressed) || !is_string($badCompressed)) {
    throw new RuntimeException('Unable to compress stream-filter whitespace boundary smoke fixture.');
}

$badHex = $asciiHex($badCompressed);
$badHexWithNonPdfWhitespace = substr($badHex, 0, 12) . chr(11) . substr($badHex, 12);
$badAscii85WithNonPdfWhitespace = chr(11) . $ascii85($leakedContent);
$goodHex = $asciiHex($goodCompressed);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 8 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 20 0 R >> >> /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($badAscii85WithNonPdfWhitespace) . " /Filter /ASCII85Decode >>\n"
    . "stream\n{$badAscii85WithNonPdfWhitespace}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($visibleContent) . " >>\n"
    . "stream\n{$visibleContent}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Names [(bad-whitespace.csv) 10 0 R (clean-stack.csv) 12 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (bad-whitespace.csv) /Desc (Bad whitespace WordPress review) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCIIHexDecode /FlateDecode ] /Params << /Size "
    . strlen($badPayload) . ' /CheckSum <' . md5($badPayload) . '> >> /Length ' . strlen($badHexWithNonPdfWhitespace) . " >>\n"
    . "stream\n{$badHexWithNonPdfWhitespace}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /Filespec /F (clean-stack.csv) /Desc (Clean stack WordPress review) /AFRelationship /Source /EF << /F 13 0 R >> >>\nendobj\n"
    . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCIIHexDecode /FlateDecode ] /Params << /Size "
    . strlen($goodPayload) . ' /CheckSum <' . md5($goodPayload) . '> >> /Length ' . strlen($goodHex) . " >>\n"
    . "stream\n{$goodHex}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$embedded = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$embeddedJson = json_encode($embedded, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$attachmentNames = $summary['filenames'] ?? [];

$smoke = [
    'native_boundary' => 'PDF stream filters accept only PDF-defined whitespace around ASCII85 and ASCIIHex data',
    'page_text' => trim($plainText),
    'attachment_count' => $summary['attachment_count'] ?? null,
    'leading_ascii85_non_pdf_whitespace_rejected' => str_contains($plainText, 'Visible After Filter Whitespace Boundary')
        && !str_contains($plainText, 'Leading ASCII85 Whitespace Leak'),
    'attachment_asciihex_stack_non_pdf_whitespace_rejected' => ($summary['attachment_count'] ?? null) === 1
        && $attachmentNames === ['clean-stack.csv']
        && !str_contains($summaryJson, 'bad-whitespace.csv')
        && !str_contains($embeddedJson, $badPayload),
    'clean_attachment_preserved' => count($embedded) === 1
        && ($embedded[0]['filename'] ?? null) === 'clean-stack.csv'
        && ($embedded[0]['content'] ?? null) === $goodPayload,
    'payload_bytes_omitted_from_summary' => !str_contains($summaryJson, $goodPayload)
        && !str_contains($summaryJson, $badPayload),
    'executes_python_or_models' => $summary['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'] ?? null,
];

foreach ([
    'leading_ascii85_non_pdf_whitespace_rejected',
    'attachment_asciihex_stack_non_pdf_whitespace_rejected',
    'clean_attachment_preserved',
    'payload_bytes_omitted_from_summary',
] as $flag) {
    if (($smoke[$flag] ?? false) !== true) {
        throw new RuntimeException('Stream-filter whitespace boundary smoke failed: ' . $flag);
    }
}

if (($smoke['executes_python_or_models'] ?? true) !== false || ($smoke['executes_external_pdf_tools'] ?? true) !== false) {
    throw new RuntimeException('Stream-filter whitespace boundary smoke used a disallowed execution path.');
}

echo '<!-- markerpdf-stream-filter-whitespace-boundary-currentbase-smoke ' . htmlspecialchars(
    json_encode($smoke, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";

foreach (explode("\n", trim($plainText)) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo "<!-- wp:list -->\n<ul>\n";
foreach ($summary['attachments'] ?? [] as $attachment) {
    echo '<li data-marker-attachment-checksum="'
        . htmlspecialchars((string) ($attachment['checksum_hex'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars(
            (string) ($attachment['filename'] ?? 'attachment')
            . ' - ' . (string) ($attachment['relationship'] ?? 'unassociated')
            . ', ' . (string) ($attachment['content_type'] ?? 'application/octet-stream')
            . ', ' . (string) ($attachment['byte_length'] ?? 0) . ' bytes',
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        )
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
