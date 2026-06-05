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
    . "BI /W 16 /H 1 /CS /G /BPC 8 ID"
    . "abc EI BT /F1 12 Tf 72 650 Td (Tight ID Inline Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 654 Td (After Tight ID Boundary) Tj ET\n"
    . "BI /W 16 /H 1 /CS /G /BPC 8 ID% comment after ID token\n"
    . "abc EI BT /F1 12 Tf 72 650 Td (Comment ID Inline Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 654 Td (After Comment ID Boundary) Tj ET\n"
    . "BI /W 4 /H 1 /CS /G /BPC 8 ID% comment after ID whitespace token\n"
    . " abcEI\n"
    . "BT /F1 12 Tf 72 653 Td (After Comment Whitespace Boundary) Tj ET\n"
    . "BI /W 1 /H 1 /CS /G /BPC 8 IDxEI\n"
    . "BT /F1 12 Tf 72 653 Td (After Tight EI Boundary) Tj ET\n"
    . "BI\0/W 1\0/H 1\0/CS /G\0/BPC 8\0ID\0"
    . "x\0BT /F1 12 Tf 72 652 Td (NUL Inline Payload Noise) Tj ET rawtail\0EI\0"
    . "BT /F1 12 Tf 72 653 Td (After NUL Whitespace Boundary) Tj ET\n"
    . "BI/W 16/H 1/CS/G/BPC 8 ID\n"
    . "abc EI BT /F1 12 Tf 72 650 Td (Compact Delimiter Inline Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 652 Td (After Compact Delimiter Boundary) Tj ET\n"
    . "BI /DP << /Width 1 /Filter /FlateDecode /BitsPerComponent 8 >> ID\n"
    . "BT /F1 12 Tf 72 650 Td (Nested Dictionary Decoy Text Survives) Tj ET\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 648 Td (After Nested Dictionary Decoy) Tj ET\n"
    . "BT /F1 12 Tf 72 647 Td (Before Text Object BI) Tj\n"
    . "0 -16 Td BI /W 1 /H 1 /CS /G /BPC 8 ID\n"
    . "(Text Object BI Survives) Tj\n"
    . "0 -16 Td EI\n"
    . "(After Text Object BI) Tj ET\n"
    . "BI /W 1 /H 1 /CS /RGB /BPC 8 /F [/RL /JPXDecode] ID\n"
    . $wrappedJpxPayload . "\nEI\n"
    . "BT /F1 12 Tf 72 640 Td (After Wrapped Preview Filter) Tj ET\n"
    . "BI /W 1 /H 1 /CS /RGB /BPC 8 /F /DCTDecode ID\n"
    . "\xff\xd8\xff\xd9" . "EI\n"
    . "BT /F1 12 Tf 72 639 Td (Between Tight Preview Filters) Tj ET\n"
    . "BI /W 1 /H 1 /CS /RGB /BPC 8 /F /JPXDecode ID\n"
    . "\xff\x4f\xff\xd9" . "EI\n"
    . "BT /F1 12 Tf 72 638 Td (After Tight Preview Filters) Tj ET\n"
    . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
    . "\x97JB2\r\n\x1a\n EI BT /F1 12 Tf 72 644 Td (JBIG2 Inline Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 648 Td (After JBIG2 Boundary) Tj ET\n"
    . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
    . "\x00\x01\x02 EI BT /F1 12 Tf 72 642 Td (Raw JBIG2 Inline Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 644 Td (After Raw JBIG2 Boundary) Tj ET\n"
    . "BI /W 8 /H 1 /CS /G /BPC 8 /F /Crypt ID\n"
    . "abc EI BT /F1 12 Tf 72 638 Td (Unsupported Inline Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 640 Td (After Unsupported Filter Boundary) Tj ET\n"
    . "BI /W 16 /H 1 /CS /CSWordPress /BPC 8 ID\n"
    . "abc EI BT /F1 12 Tf 72 636 Td (Named ColorSpace Inline Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 638 Td (After Named ColorSpace Boundary) Tj ET\n"
    . "BI /W 1 /H 1 /CS /G /BPC 8 ID\n"
    . "x\n"
    . "EI/Decorative Do\n"
    . "BT /F1 12 Tf 72 637 Td (After Slash EI Boundary) Tj ET\n"
    . "BI /W 1 /H 1 /CS /G /BPC 8 ID\n"
    . "x\n"
    . "EI/Span << /ActualText (Slash EI ActualText) >> BDC BT /F1 12 Tf 72 636 Td (Hidden Slash EI Text) Tj ET EMC\n"
    . "BT /F1 12 Tf 72 637 Td (After Slash Marked EI) Tj ET\n"
    . "BI /W 1 /H 1 /CS /G /BPC 8 ID\n"
    . "x\n"
    . "EI/Span /PActual BDC BT /F1 12 Tf 72 636 Td (Hidden Named Property Text) Tj ET EMC\n"
    . "BT /F1 12 Tf 72 637 Td (After Named Property EI) Tj ET\n"
    . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
    . "\x00\x01\x02 EI BT /F1 12 Tf 72 636 Td (Preview Payload EI Noise) Tj ET rawtail\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 638 Td (Visible EI Marker Text) Tj ET\n"
    . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
    . "\x00\x01\x02 EI BT /F1 12 Tf 72 636 Td (TJ Array Payload EI Noise) Tj ET rawtail\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 636 Td [(Visible EI Array Text)] TJ ET\n"
    . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
    . "\x00\x01\x02 EI BT /F1 12 Tf 72 635 Td (Marked ActualText Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "/Span << /ActualText (Visible EI ActualText) >> BDC BT /F1 12 Tf 72 635 Td (Hidden ActualText Source) Tj ET EMC\n"
    . "BI /W 8 /H 1 /IM true /F /JBIG2Decode ID\n"
    . "\x80 EI\n/Span << /ActualText (Visible Sample Floor ActualText) >> BDC BT /F1 12 Tf 72 635 Td (Hidden Sample Floor Text) Tj ET EMC\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 635 Td (After Sample Floor ActualText) Tj ET\n"
    . "BT /F1 12 Tf 72 634 Td (Before Comment EI Boundary) Tj ET\n"
    . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
    . "\x00\x01\x02 EI BT /F1 12 Tf 72 633 Td (Comment EI Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "% comment EI BT /F1 12 Tf 72 632 Td (Comment After Inline Noise) Tj ET\n"
    . "BT /F1 12 Tf 72 631 Td (After Comment EI Boundary) Tj ET\n"
    . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
    . "\x00\x01\x02 EI BT /F1 12 Tf 72 630 Td (Stray Operator Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 629 Td (Visible Before Stray Operator) Tj ET\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 628 Td (Visible After Stray Operator) Tj ET\n"
    . "BT /F1 12 Tf 72 627 Td (Before Same Line Stray) Tj ET\n"
    . "BI /W 8 /H 1 /IM true /F /JBIG2Decode ID\n"
    . "\x80 EI BT /F1 12 Tf 72 626 Td (Visible Same Line Before Stray) Tj ET EI\n"
    . "BT /F1 12 Tf 72 625 Td (Visible After Same Line Stray) Tj ET\n"
    . "BT /F1 12 Tf 72 627 Td (Before Q Wrapped Stray) Tj ET\n"
    . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
    . "\x00\x01\x02 EI BT /F1 12 Tf 72 626 Td (Q Wrapped Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "q\n"
    . "BT /F1 12 Tf 72 625 Td (Visible Q Wrapped Before Stray) Tj ET\n"
    . "Q\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 624 Td (Visible After Q Wrapped Stray) Tj ET\n"
    . "BT /F1 12 Tf 72 623 Td (Before CM Wrapped Stray) Tj ET\n"
    . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
    . "\x00\x01\x02 EI BT /F1 12 Tf 72 622 Td (CM Wrapped Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "q\n"
    . "1 0 0 1 24 0 cm\n"
    . "BT /F1 12 Tf 48 621 Td (Visible CM Wrapped Before Stray) Tj ET\n"
    . "Q\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 620 Td (Visible After CM Wrapped Stray) Tj ET\n"
    . "BT /F1 12 Tf 72 619 Td (Before Clip Path Stray) Tj ET\n"
    . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
    . "\x00\x01\x02 EI BT /F1 12 Tf 72 618 Td (Clip Path Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "q\n"
    . "60 600 260 60 re W n\n"
    . "BT /F1 12 Tf 72 617 Td (Visible Clip Path Before Stray) Tj ET\n"
    . "Q\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 616 Td (Visible After Clip Path Stray) Tj ET\n"
    . "BT /F1 12 Tf 72 615 Td (Before XObject Do Stray) Tj ET\n"
    . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
    . "\x00\x01\x02 EI BT /F1 12 Tf 72 614 Td (XObject Do Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "/Decorative Do\n"
    . "BT /F1 12 Tf 72 613 Td (Visible XObject Do Before Stray) Tj ET\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 612 Td (Visible After XObject Do Stray) Tj ET\n"
    . "BT /F1 12 Tf 72 615 Td (Before Color State Stray) Tj ET\n"
    . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
    . "\x00\x01\x02 EI BT /F1 12 Tf 72 614 Td (Color State Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "0 0 1 rg\n"
    . "BT /F1 12 Tf 72 613 Td (Visible RGB Color Before Stray) Tj ET\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 612 Td (Visible After RGB Color Stray) Tj ET\n"
    . "BT /F1 12 Tf 72 611 Td (Before Named Color State Stray) Tj ET\n"
    . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
    . "\x00\x01\x02 EI BT /F1 12 Tf 72 610 Td (Named Color State Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "/DeviceRGB cs\n"
    . "0.2 0.3 0.4 scn\n"
    . "BT /F1 12 Tf 72 609 Td (Visible SCN Color Before Stray) Tj ET\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 608 Td (Visible After SCN Color Stray) Tj ET\n"
    . "BT /F1 12 Tf 72 607 Td (Before Pattern Color Stray) Tj ET\n"
    . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
    . "\x00\x01\x02 EI BT /F1 12 Tf 72 606 Td (Pattern Color Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "/Pattern cs\n"
    . "0.5 /P1 scn\n"
    . "BT /F1 12 Tf 72 605 Td (Visible Pattern Color Before Stray) Tj ET\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 604 Td (Visible After Pattern Color Stray) Tj ET\n"
    . "BT /F1 12 Tf 72 603 Td (Before Shading Stray) Tj ET\n"
    . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
    . "\x00\x01\x02 EI BT /F1 12 Tf 72 602 Td (Shading Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "/Shade1 sh\n"
    . "BT /F1 12 Tf 72 601 Td (Visible Shading Before Stray) Tj ET\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 600 Td (Visible After Shading Stray) Tj ET\n"
    . "BT /F1 12 Tf 72 672 Td (After Real Inline Image) Tj ET";

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> /ColorSpace << /CSWordPress /DeviceRGB >> /Pattern << /P1 7 0 R >> /Shading << /Shade1 8 0 R >> /XObject << /Decorative 6 0 R >> /Properties << /PActual << /ActualText (Named Property EI ActualText) >> >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Length 1 >>\nstream\ny\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 2 /TilingType 1 /BBox [0 0 8 8] /XStep 8 /YStep 8 /Resources << >> /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "8 0 obj\n<< /ShadingType 2 /ColorSpace /DeviceRGB /Coords [0 0 100 0] /Function << /FunctionType 2 /Domain [0 1] /C0 [0 0 0] /C1 [1 1 1] /N 1 >> /Extend [true true] >>\nendobj\n"
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
    'native_boundary' => 'content tokenizer recovers malformed BI preambles, tight ID data separators, immediate PDF comments after ID, PDF NUL whitespace around BI/ID/EI, tight EI sample terminators, tight DCT/JPX preview-filter terminators, nested modifier-dictionary decoys, text-object BI decoys, and slash-delimited, named-color-space, unsupported-filter, visible-literal, TJ-array, marked-content ActualText, named marked-content property ActualText, sample-floor marked-content ActualText, post-terminator comment EI, later stray EI operator, same-line text before stray EI operator, graphics-state wrapped stray EI, clipping-path wrapped stray EI, XObject Do wrapped stray EI, numeric color graphics-state wrapped stray EI, pattern color graphics-state wrapped stray EI, and shading-paint wrapped stray EI inline image boundaries before Gutenberg paragraphs',
    'stray_bi_text_preserved' => str_contains($plainText, 'Stray BI Text Survives')
        && str_contains($plainText, 'After Tokenizer Boundary'),
    'real_inline_image_payload_excluded' => !str_contains($plainText, 'Inline Image Payload Noise'),
    'early_ei_payload_text_excluded_until_sample_boundary' => !str_contains($plainText, 'Early EI Inline Payload Noise')
        && !str_contains($plainText, 'rawtail')
        && str_contains($plainText, 'After Early EI Boundary'),
    'tight_id_inline_payload_excluded_until_sample_boundary' => !str_contains($plainText, 'Tight ID Inline Payload Noise')
        && !str_contains($plainText, 'IDabc')
        && str_contains($plainText, 'After Tight ID Boundary'),
    'comment_after_id_inline_payload_excluded_until_sample_boundary' => !str_contains($plainText, 'Comment ID Inline Payload Noise')
        && !str_contains($plainText, 'comment after ID token')
        && str_contains($plainText, 'After Comment ID Boundary'),
    'comment_after_id_leading_whitespace_sample_preserved_for_tight_ei' => str_contains($plainText, 'After Comment Whitespace Boundary')
        && !str_contains($plainText, 'comment after ID whitespace token')
        && !str_contains($plainText, 'abcEI'),
    'tight_ei_inline_terminator_recovers_after_exact_sample_floor' => str_contains($plainText, 'After Tight EI Boundary')
        && !str_contains($plainText, 'IDxEI')
        && !str_contains($plainText, 'xEI'),
    'pdf_null_whitespace_inline_payload_excluded' => str_contains($plainText, 'After NUL Whitespace Boundary')
        && !str_contains($plainText, 'NUL Inline Payload Noise')
        && !str_contains($plainText, "\0")
        && !str_contains($plainText, 'rawtail'),
    'compact_slash_delimited_inline_image_excluded' => !str_contains($plainText, 'Compact Delimiter Inline Payload Noise')
        && !str_contains($plainText, 'BI/W')
        && str_contains($plainText, 'After Compact Delimiter Boundary'),
    'malformed_bi_nested_dictionary_decoy_preserved_as_text_boundary' => str_contains($plainText, 'Nested Dictionary Decoy Text Survives')
        && str_contains($plainText, 'After Nested Dictionary Decoy')
        && !str_contains($plainText, 'FlateDecode')
        && !str_contains($plainText, 'BitsPerComponent'),
    'text_object_bi_decoy_preserved_as_text_boundary' => str_contains($plainText, 'Before Text Object BI')
        && str_contains($plainText, 'Text Object BI Survives')
        && str_contains($plainText, 'After Text Object BI')
        && !str_contains($plainText, '/W 1')
        && !str_contains($plainText, 'BitsPerComponent'),
    'preview_only_jbig2_payload_excluded_until_safe_boundary' => !str_contains($plainText, 'JBIG2 Inline Payload Noise')
        && !str_contains($plainText, 'rawtail')
        && str_contains($plainText, 'After JBIG2 Boundary'),
    'raw_jbig2_segment_payload_excluded_until_safe_boundary' => !str_contains($plainText, 'Raw JBIG2 Inline Payload Noise')
        && !str_contains($plainText, 'rawtail')
        && str_contains($plainText, 'After Raw JBIG2 Boundary'),
    'unsupported_inline_filter_payload_excluded_until_safe_boundary' => !str_contains($plainText, 'Unsupported Inline Payload Noise')
        && !str_contains($plainText, 'Crypt')
        && str_contains($plainText, 'After Unsupported Filter Boundary'),
    'named_colorspace_inline_payload_excluded_until_safe_boundary' => !str_contains($plainText, 'Named ColorSpace Inline Payload Noise')
        && !str_contains($plainText, 'CSWordPress')
        && str_contains($plainText, 'After Named ColorSpace Boundary'),
    'slash_after_inline_ei_closes_before_name_operand' => str_contains($plainText, 'After Slash EI Boundary')
        && !str_contains($plainText, 'Decorative'),
    'slash_after_inline_ei_marked_actualtext_preserved' => str_contains($plainText, 'Slash EI ActualText')
        && str_contains($plainText, 'After Slash Marked EI')
        && !str_contains($plainText, 'Hidden Slash EI Text')
        && !str_contains($plainText, 'EI/Span'),
    'slash_after_inline_ei_named_property_actualtext_preserved' => str_contains($plainText, 'Named Property EI ActualText')
        && str_contains($plainText, 'After Named Property EI')
        && !str_contains($plainText, 'Hidden Named Property Text')
        && !str_contains($plainText, '/PActual'),
    'preview_only_visible_ei_text_preserved_after_safe_boundary' => str_contains($plainText, 'Visible EI Marker Text')
        && !str_contains($plainText, 'Preview Payload EI Noise')
        && !str_contains($plainText, 'rawtail'),
    'preview_only_visible_ei_tj_array_text_preserved_after_safe_boundary' => str_contains($plainText, 'Visible EI Array Text')
        && !str_contains($plainText, 'TJ Array Payload EI Noise')
        && !str_contains($plainText, 'rawtail'),
    'preview_only_marked_actualtext_ei_preserved_after_safe_boundary' => str_contains($plainText, 'Visible EI ActualText')
        && !str_contains($plainText, 'Hidden ActualText Source')
        && !str_contains($plainText, 'Marked ActualText Payload Noise')
        && !str_contains($plainText, 'rawtail'),
    'sample_floor_marked_actualtext_preserved_after_inline_ei' => str_contains($plainText, 'Visible Sample Floor ActualText')
        && str_contains($plainText, 'After Sample Floor ActualText')
        && !str_contains($plainText, 'Hidden Sample Floor Text')
        && !str_contains($plainText, "\x80 EI"),
    'post_inline_image_comment_ei_excluded' => str_contains($plainText, 'Before Comment EI Boundary')
        && str_contains($plainText, 'After Comment EI Boundary')
        && !str_contains($plainText, 'Comment EI Payload Noise')
        && !str_contains($plainText, 'Comment After Inline Noise')
        && !str_contains($plainText, 'rawtail'),
    'preview_only_stray_ei_operator_text_preserved_after_safe_boundary' => str_contains($plainText, 'Visible Before Stray Operator')
        && str_contains($plainText, 'Visible After Stray Operator')
        && !str_contains($plainText, 'Stray Operator Payload Noise')
        && !str_contains($plainText, 'rawtail'),
    'preview_only_same_line_stray_ei_text_preserved_after_safe_boundary' => str_contains($plainText, 'Before Same Line Stray')
        && str_contains($plainText, 'Visible Same Line Before Stray')
        && str_contains($plainText, 'Visible After Same Line Stray')
        && !str_contains($plainText, "\x80 EI"),
    'preview_only_q_wrapped_stray_ei_text_preserved_after_safe_boundary' => str_contains($plainText, 'Before Q Wrapped Stray')
        && str_contains($plainText, 'Visible Q Wrapped Before Stray')
        && str_contains($plainText, 'Visible After Q Wrapped Stray')
        && !str_contains($plainText, 'Q Wrapped Payload Noise')
        && !str_contains($plainText, 'rawtail'),
    'preview_only_cm_wrapped_stray_ei_text_preserved_after_safe_boundary' => str_contains($plainText, 'Before CM Wrapped Stray')
        && str_contains($plainText, 'Visible CM Wrapped Before Stray')
        && str_contains($plainText, 'Visible After CM Wrapped Stray')
        && !str_contains($plainText, 'CM Wrapped Payload Noise')
        && !str_contains($plainText, 'rawtail'),
    'preview_only_clip_path_stray_ei_text_preserved_after_safe_boundary' => str_contains($plainText, 'Before Clip Path Stray')
        && str_contains($plainText, 'Visible Clip Path Before Stray')
        && str_contains($plainText, 'Visible After Clip Path Stray')
        && !str_contains($plainText, 'Clip Path Payload Noise')
        && !str_contains($plainText, 'rawtail'),
    'preview_only_xobject_do_stray_ei_text_preserved_after_safe_boundary' => str_contains($plainText, 'Before XObject Do Stray')
        && str_contains($plainText, 'Visible XObject Do Before Stray')
        && str_contains($plainText, 'Visible After XObject Do Stray')
        && !str_contains($plainText, 'XObject Do Payload Noise')
        && !str_contains($plainText, 'Decorative')
        && !str_contains($plainText, 'rawtail'),
    'preview_only_color_state_stray_ei_text_preserved_after_safe_boundary' => str_contains($plainText, 'Before Color State Stray')
        && str_contains($plainText, 'Visible RGB Color Before Stray')
        && str_contains($plainText, 'Visible After RGB Color Stray')
        && str_contains($plainText, 'Before Named Color State Stray')
        && str_contains($plainText, 'Visible SCN Color Before Stray')
        && str_contains($plainText, 'Visible After SCN Color Stray')
        && !str_contains($plainText, 'Color State Payload Noise')
        && !str_contains($plainText, 'Named Color State Payload Noise')
        && !str_contains($plainText, 'rawtail'),
    'preview_only_pattern_color_state_stray_ei_text_preserved_after_safe_boundary' => str_contains($plainText, 'Before Pattern Color Stray')
        && str_contains($plainText, 'Visible Pattern Color Before Stray')
        && str_contains($plainText, 'Visible After Pattern Color Stray')
        && !str_contains($plainText, 'Pattern Color Payload Noise')
        && !str_contains($plainText, '/P1 scn')
        && !str_contains($plainText, 'rawtail'),
    'preview_only_shading_stray_ei_text_preserved_after_safe_boundary' => str_contains($plainText, 'Before Shading Stray')
        && str_contains($plainText, 'Visible Shading Before Stray')
        && str_contains($plainText, 'Visible After Shading Stray')
        && !str_contains($plainText, 'Shading Payload Noise')
        && !str_contains($plainText, 'Shade1')
        && !str_contains($plainText, 'rawtail'),
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
    'tight_preview_filter_terminators_preserve_following_text' => str_contains($plainText, 'Between Tight Preview Filters')
        && str_contains($plainText, 'After Tight Preview Filters')
        && !str_contains($plainText, 'DCTDecode')
        && !str_contains($plainText, 'JPXDecode'),
    'visible_text_imported' => str_contains($plainText, 'Before Tokenizer Boundary')
        && str_contains($plainText, 'After Real Inline Image'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach (array_merge($lines, $ccittLines, $multipleCcittLines) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
