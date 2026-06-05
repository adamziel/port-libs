<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$runLengthEncode = static function (string $bytes): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += 128) {
        $chunk = substr($bytes, $offset, 128);
        $encoded .= chr(strlen($chunk) - 1) . $chunk;
    }

    return $encoded . chr(128);
};

$wrappedJpxPayload = $runLengthEncode(
    "\xff\x4f wrapped JPX bytes EI BT /F1 12 Tf 72 636 Td (Wrapped JPX Inline Payload Noise) Tj ET \xff\xd9"
);

$content = "BT /F1 12 Tf 72 720 Td (Before Tokenizer Boundary) Tj ET\n"
    . "BI BT /F1 12 Tf 72 704 Td (Stray BI Text Survives) Tj ET\n"
    . "BT /F1 12 Tf 72 688 Td (After Tokenizer Boundary) Tj ET\n"
    . "BI /W 1 /H 1 /CS /G /BPC 8 ID\n"
    . "BT /F1 12 Tf 72 660 Td (Inline Image Payload Noise) Tj ET\n"
    . "EI\n"
    . "BI /W 16 /H 1 /CS /G /BPC 8 ID\n"
    . "abc EI BT /F1 12 Tf 72 646 Td (Early EI Inline Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 656 Td (After Early EI Boundary) Tj ET\n"
    . "BI/W 16/H 1/CS/G/BPC 8 ID\n"
    . "abc EI BT /F1 12 Tf 72 650 Td (Compact Delimiter Inline Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 652 Td (After Compact Delimiter Boundary) Tj ET\n"
    . "BI /W 1 /H 1 /CS /RGB /BPC 8 /F [/RL /JPXDecode] ID\n"
    . $wrappedJpxPayload . "\nEI\n"
    . "BT /F1 12 Tf 72 640 Td (After Wrapped Preview Filter) Tj ET\n"
    . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
    . "\x97JB2\r\n\x1a\n EI BT /F1 12 Tf 72 644 Td (JBIG2 Inline Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 648 Td (After JBIG2 Boundary) Tj ET\n"
    . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
    . "\x00\x01\x02 EI BT /F1 12 Tf 72 642 Td (Raw JBIG2 Inline Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 644 Td (After Raw JBIG2 Boundary) Tj ET\n"
    . "BT /F1 12 Tf 72 672 Td (After Real Inline Image) Tj ET";

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF";

$ccittContent = "BT /F1 12 Tf 72 720 Td (Before CCITT Boundary) Tj ET\n"
    . "BI /W 128 /H 1 /IM true /F /CCITTFaxDecode ID\n"
    . "\x00\x10\x04 EI BT /F1 12 Tf 72 632 Td (CCITT Inline Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 704 Td (After CCITT Boundary) Tj ET";
$ccittPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($ccittContent) . " >>\nstream\n{$ccittContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF";

$multipleCcittContent = "BT /F1 12 Tf 72 720 Td (Before First CCITT) Tj ET\n"
    . "BI /W 128 /H 1 /IM true /F /CCITTFaxDecode ID\n"
    . "\x00\x10\x04 EI BT /F1 12 Tf 72 632 Td (First CCITT Inline Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 704 Td (Between CCITT Images) Tj ET\n"
    . "BI /W 128 /H 1 /IM true /F /CCF ID\n"
    . "\x00\x10\x04 EI BT /F1 12 Tf 72 620 Td (Second CCF Inline Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 688 Td (After Second CCITT) Tj ET";
$multipleCcittPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($multipleCcittContent) . " >>\nstream\n{$multipleCcittContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$ccittLines = $extractor->extractTextLines($ccittPdf);
$ccittPlainText = $extractor->extractPlainText($ccittPdf);
$multipleCcittLines = $extractor->extractTextLines($multipleCcittPdf);
$multipleCcittPlainText = $extractor->extractPlainText($multipleCcittPdf);

echo '<!-- markerpdf-inline-image-tokenizer-boundary-currentbase ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'content tokenizer recovers malformed BI preambles and slash-delimited BI dictionaries while still excluding real BI ID EI inline image payloads before Gutenberg paragraphs',
    'stray_bi_text_preserved' => str_contains($plainText, 'Stray BI Text Survives')
        && str_contains($plainText, 'After Tokenizer Boundary'),
    'real_inline_image_payload_excluded' => !str_contains($plainText, 'Inline Image Payload Noise'),
    'early_ei_payload_text_excluded_until_sample_boundary' => !str_contains($plainText, 'Early EI Inline Payload Noise')
        && !str_contains($plainText, 'rawtail')
        && str_contains($plainText, 'After Early EI Boundary'),
    'compact_slash_delimited_inline_image_excluded' => !str_contains($plainText, 'Compact Delimiter Inline Payload Noise')
        && !str_contains($plainText, 'BI/W')
        && str_contains($plainText, 'After Compact Delimiter Boundary'),
    'preview_only_jbig2_payload_excluded_until_safe_boundary' => !str_contains($plainText, 'JBIG2 Inline Payload Noise')
        && !str_contains($plainText, 'rawtail')
        && str_contains($plainText, 'After JBIG2 Boundary'),
    'raw_jbig2_segment_payload_excluded_until_safe_boundary' => !str_contains($plainText, 'Raw JBIG2 Inline Payload Noise')
        && !str_contains($plainText, 'rawtail')
        && str_contains($plainText, 'After Raw JBIG2 Boundary'),
    'preview_only_ccitt_payload_excluded_until_safe_boundary' => !str_contains($ccittPlainText, 'CCITT Inline Payload Noise')
        && !str_contains($ccittPlainText, 'rawtail')
        && str_contains($ccittPlainText, 'Before CCITT Boundary')
        && str_contains($ccittPlainText, 'After CCITT Boundary'),
    'multiple_preview_only_ccitt_text_between_images_preserved' => str_contains($multipleCcittPlainText, 'Before First CCITT')
        && str_contains($multipleCcittPlainText, 'Between CCITT Images')
        && str_contains($multipleCcittPlainText, 'After Second CCITT')
        && !str_contains($multipleCcittPlainText, 'First CCITT Inline Payload Noise')
        && !str_contains($multipleCcittPlainText, 'Second CCF Inline Payload Noise')
        && !str_contains($multipleCcittPlainText, 'rawtail'),
    'wrapped_preview_filter_chain_text_preserved' => str_contains($plainText, 'After Wrapped Preview Filter')
        && str_contains($plainText, 'After JBIG2 Boundary'),
    'wrapped_preview_filter_payload_excluded' => !str_contains($plainText, 'Wrapped JPX Inline Payload Noise')
        && !str_contains($plainText, "\xff\x4f"),
    'visible_text_imported' => str_contains($plainText, 'Before Tokenizer Boundary')
        && str_contains($plainText, 'After Real Inline Image'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach (array_merge($lines, $ccittLines, $multipleCcittLines) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
