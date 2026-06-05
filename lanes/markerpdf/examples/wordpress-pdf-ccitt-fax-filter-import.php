<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$zlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('Focused CCITT Flate-prefix fixture must fit one deflate stored block.');
    }

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

$before = 'BT /F1 12 Tf 72 720 Td (CCITT Boundary) Tj ET';
$after = 'BT /F1 12 Tf 72 688 Td (Native Import) Tj ET';
$scanNoise = 'BT /F1 12 Tf 72 704 Td (Raster Fax Noise) Tj ET';
$aliasNoise = 'BT /F1 12 Tf 72 672 Td (Alias Fax Noise) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 5 0 R 6 0 R 7 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /CCITTFaxDecode /DecodeParms << /K -1 /Columns 1728 /Rows 1 /BlackIs1 true >> /Length " . strlen($scanNoise) . " >>\nstream\n{$scanNoise}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Filter /CCF /DecodeParms << /K 0 /Columns 8 /Rows 1 /EncodedByteAlign true >> /Length " . strlen($aliasNoise) . " >>\nstream\n{$aliasNoise}\nendstream\nendobj\n"
    . "%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);
$inlineReview = (new PdfImageRenderer())->inlineImageReviewPlan(
    '/W 16 /H 1 /IM true /F /CCF /DP << /K 0 /Columns 16 /Rows 1 /BlackIs1 false /EncodedByteAlign true /EndOfLine false /EndOfBlock true >> /D [1 0]',
    "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline CCITT fax payload noise) Tj ET final"
);
$defaultInlineReview = (new PdfImageRenderer())->inlineImageReviewPlan(
    '/W 1728 /H 1 /IM true /F /CCF',
    "\x00\x10\x01\x00\x10\x01\x00\x10\x01"
);
$invalidInlinePayload = "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline invalid CCITT fax payload noise) Tj ET final";
$invalidInlineReview = (new PdfImageRenderer())->inlineImageReviewPlan(
    '/W 8 /H 1 /IM true /F /CCF /DP << /K /TwoD /Columns 0 /Rows -1 /BlackIs1 /Maybe /EncodedByteAlign true /EndOfLine /No /EndOfBlock true /DamagedRowsBeforeError -2 >> /D [1 0]',
    $invalidInlinePayload
);
$unresolvedInlinePayload = "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline unresolved CCITT DecodeParms payload noise) Tj ET final";
$unresolvedInlineReview = (new PdfImageRenderer())->inlineImageReviewPlan(
    '/W 16 /H 1 /IM true /F /CCF /DP 99 0 R /D [1 0]',
    $unresolvedInlinePayload
);
$extraDecodeParmsInlinePayload = "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline extra CCITT DecodeParms payload noise) Tj ET final";
$extraDecodeParmsInlineReview = (new PdfImageRenderer())->inlineImageReviewPlan(
    '/W 16 /H 1 /IM true /F /CCF /DP [<< /K -1 /Columns 16 /Rows 1 /BlackIs1 true /EndOfBlock false >> << /K /Bad /Columns 1 >>] /D [1 0]',
    $extraDecodeParmsInlinePayload
);
$inlineGeometryReview = (new PdfImageRenderer())->inlineImageReviewPlan(
    '/W 8 /H 3 /IM true /F /CCF /DP << /Columns 16 /Rows 4 /BlackIs1 true >>',
    "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline CCITT geometry payload noise) Tj ET final"
);
$escapedInlinePayload = "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline escaped CCITT fax payload noise) Tj ET final";
$escapedInlineReview = (new PdfImageRenderer())->inlineImageReviewPlan(
    '/W 8 /H 3 /IM true /F /CCF /DP << /Decoy << /Columns 4 /Rows 1 /BlackIs1 false /EndOfBlock true >> /#4B -1 /Colu#6Dns 32 /Ro#77s 3 /Black#49s1 true /EncodedByte#41lign true /EndOf#4cine true /EndOf#42lock false /DamagedRowsBefore#45rror 5 >>',
    $escapedInlinePayload
);
$nullFilterInlinePayload = "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline null-filter CCITT payload noise) Tj ET final";
$nullFilterInlineReview = (new PdfImageRenderer())->inlineImageReviewPlan(
    '/W 16 /H 2 /IM true /F [null /CCF] /DP [null << /K -1 /Columns 16 /Rows 2 /BlackIs1 true /EndOfBlock false >>] /D [1 0]',
    $nullFilterInlinePayload
);
$fakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress Flate CCITT prefix leak) Tj ET';
$flateWrappedFaxPayload = "\x00\x11\x22\x33\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "\x44\x55\x66";
$compressedFaxPayload = $zlibStored($flateWrappedFaxPayload);
$staleLength = strpos($compressedFaxPayload, "\nendstream\n");
if ($staleLength === false) {
    throw new RuntimeException('Unable to build CCITT Flate-prefix stale-length fixture.');
}
$boundaryBefore = 'BT /F1 12 Tf 72 720 Td (Before Flate CCITT import) Tj ET';
$boundaryAfter = 'BT /F1 12 Tf 72 680 Td (After Flate CCITT import) Tj ET';
$boundaryPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Fax#20Flate 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($boundaryBefore) . " >>\nstream\n{$boundaryBefore}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter [/FlateDecode /CCITTFaxDecode] /DecodeParms [null << /K 0 /Columns 16 /Rows 1 /EndOfBlock false >>] /Length {$staleLength} >>\nstream\n{$compressedFaxPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($boundaryAfter) . " >>\nstream\n{$boundaryAfter}\nendstream\nendobj\n%%EOF";
$boundaryExtractor = new PdfTextExtractor();
$boundaryLines = $boundaryExtractor->extractTextLines($boundaryPdf);
$boundaryReview = $boundaryExtractor->extractImageXObjectBoundaryReview($boundaryPdf);
$cryptFakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress Crypt CCITT prefix leak) Tj ET';
$cryptCcittEofb = "\x00\x10\x01";
$cryptWrappedFaxPayload = "\x01\x02\x03\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($cryptFakeObject) . " >>\nstream\n{$cryptFakeObject}\nendstream\nendobj\n"
    . "\x04\x05{$cryptCcittEofb}";
$cryptStaleLength = strpos($cryptWrappedFaxPayload, "\nendstream\n");
if ($cryptStaleLength === false) {
    throw new RuntimeException('Unable to build Crypt-wrapped CCITT stale-length fixture.');
}
$cryptBoundaryBefore = 'BT /F1 12 Tf 72 720 Td (Before Crypt CCITT import) Tj ET';
$cryptBoundaryAfter = 'BT /F1 12 Tf 72 680 Td (After Crypt CCITT import) Tj ET';
$cryptBoundaryPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Fax#20Crypt 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($cryptBoundaryBefore) . " >>\nstream\n{$cryptBoundaryBefore}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 0 /ImageMask true /BitsPerComponent 1 /Filter [/Crypt /CCITTFaxDecode] /DecodeParms [<< /Name /Identity >> << /K -1 /Columns 16 /Rows 0 /EndOfBlock true >>] /Length {$cryptStaleLength} >>\nstream\n{$cryptWrappedFaxPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($cryptBoundaryAfter) . " >>\nstream\n{$cryptBoundaryAfter}\nendstream\nendobj\n%%EOF";
$cryptBoundaryLines = $boundaryExtractor->extractTextLines($cryptBoundaryPdf);
$cryptBoundaryReview = $boundaryExtractor->extractImageXObjectBoundaryReview($cryptBoundaryPdf);
$cryptBoundaryEntry = $cryptBoundaryReview['entries'][0] ?? [];
$directBefore = 'BT /F1 12 Tf 72 720 Td (Before direct CCITT EOB import) Tj ET';
$directBetween = 'BT /F1 12 Tf 72 690 Td (Between direct CCITT EOB import) Tj ET';
$directAfter = 'BT /F1 12 Tf 72 660 Td (After direct CCITT EOB import) Tj ET';
$directFakeG4 = 'BT /F1 12 Tf 72 700 Td (WordPress direct G4 CCITT leak) Tj ET';
$directFakeRtc = 'BT /F1 12 Tf 72 680 Td (WordPress direct RTC CCITT leak) Tj ET';
$ccittEofb = "\x00\x10\x01";
$ccittRtc = $ccittEofb . $ccittEofb . $ccittEofb;
$directG4Payload = "\x11\x22\n"
    . "endstream\nendobj\n"
    . "50 0 obj\n<< /Length " . strlen($directFakeG4) . " >>\nstream\n{$directFakeG4}\nendstream\nendobj\n"
    . "\x33\x44{$ccittEofb}";
$directRtcPayload = "\x55\x66\n"
    . "endstream\nendobj\n"
    . "51 0 obj\n<< /Length " . strlen($directFakeRtc) . " >>\nstream\n{$directFakeRtc}\nendstream\nendobj\n"
    . "\x77\x88{$ccittRtc}";
$directRtcStaleLength = strpos($directRtcPayload, "\nendstream\n");
if ($directRtcStaleLength === false) {
    throw new RuntimeException('Unable to build direct CCITT RTC stale-length fixture.');
}
$directBoundaryPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxG4 5 0 R /FaxRtc 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 7 0 R 8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($directBefore) . " >>\nstream\n{$directBefore}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 0 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /K -1 /Columns 16 /Rows 0 /EndOfBlock true >> >>\nstream\n{$directG4Payload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /ImageMask true /BitsPerComponent 1 /Filter /CCF /DecodeParms << /K 0 /Columns 16 /Rows 0 /EndOfBlock true >> /Length {$directRtcStaleLength} >>\nstream\n{$directRtcPayload}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($directBetween) . " >>\nstream\n{$directBetween}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($directAfter) . " >>\nstream\n{$directAfter}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
$directBoundaryLines = $boundaryExtractor->extractTextLines($directBoundaryPdf);
$directBoundaryReview = $boundaryExtractor->extractImageXObjectBoundaryReview($directBoundaryPdf);
$directG4Entry = $directBoundaryReview['entries'][0] ?? [];
$directRtcEntry = $directBoundaryReview['entries'][1] ?? [];
$geometryBefore = 'BT /F1 12 Tf 72 720 Td (Before CCITT geometry import) Tj ET';
$geometryAfter = 'BT /F1 12 Tf 72 680 Td (After CCITT geometry import) Tj ET';
$geometryPayload = 'BT /F1 12 Tf 72 700 Td (WordPress CCITT geometry payload noise) Tj ET';
$geometryPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxGeometry 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($geometryBefore) . " >>\nstream\n{$geometryBefore}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /Columns 16 /Rows 4 /BlackIs1 true >> /Length " . strlen($geometryPayload) . " >>\nstream\n{$geometryPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($geometryAfter) . " >>\nstream\n{$geometryAfter}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
$geometryReview = $boundaryExtractor->extractImageXObjectBoundaryReview($geometryPdf);
$compactXobjectBefore = 'BT /F1 12 Tf 72 720 Td (Before compact CCITT import) Tj ET';
$compactXobjectAfter = 'BT /F1 12 Tf 72 680 Td (After compact CCITT import) Tj ET';
$compactXobjectPayload = 'BT /F1 12 Tf 72 700 Td (WordPress compact CCITT DecodeParms leak) Tj ET';
$compactXobjectEncodedPayload = strtoupper(bin2hex($compactXobjectPayload)) . '>';
$compactXobjectPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /CompactFax 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($compactXobjectBefore) . " >>\nstream\n{$compactXobjectBefore}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 24 /Height 2 /ImageMask true /BitsPerComponent 1 /Filter [null /ASCIIHexDecode /CCF] /DecodeParms [null << /K -1 /Columns 24 /Rows 2 /BlackIs1 true /EncodedByteAlign true /EndOfLine true /EndOfBlock false /DamagedRowsBeforeError 1 >>] /Length " . strlen($compactXobjectEncodedPayload) . " >>\nstream\n{$compactXobjectEncodedPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($compactXobjectAfter) . " >>\nstream\n{$compactXobjectAfter}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
$compactXobjectReview = $boundaryExtractor->extractImageXObjectBoundaryReview($compactXobjectPdf);
$compactXobjectEntry = $compactXobjectReview['entries'][0] ?? [];
$compactXobjectParms = $compactXobjectEntry['filter_details'][1]['decode_parms'] ?? [];
$compactXobjectFilterBoundary = $compactXobjectEntry['ccitt_fax_filter_boundary'] ?? [];
$compactXobjectBoundary = $compactXobjectEntry['ccitt_fax_decode_boundary'] ?? [];
$compactXobjectPolarityBoundary = $compactXobjectEntry['ccitt_fax_imagemask_polarity_boundary'] ?? [];
$postCcittFilterReview = (new PdfImageRenderer())->imageColorSpaceSoftMaskPlan(
    '<< /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 '
    . '/Filter [/CCF /ASCIIHexDecode /FlateDecode /DCTDecode] '
    . '/DecodeParms [<< /K 0 /Columns 16 /Rows 1 /EndOfBlock true >> null null null] >>'
);
$postCcittFilterBoundary = $postCcittFilterReview['ccitt_fax_filter_boundary'] ?? [];
$softMaskPrefixFaxBytes = "\x00\x10\x01";
$softMaskPrefixPayload = strtoupper(bin2hex($softMaskPrefixFaxBytes)) . '>';
$softMaskPrefixReview = (new PdfImageRenderer())->imageColorSpaceSoftMaskPlan(
    '<< /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /SMask 41 0 R >>',
    [
        41 => "<< /Type /XObject /Subtype /Image /Width 16 /Height 0 /ColorSpace /DeviceGray /BitsPerComponent 1 /Filter [/ASCIIHexDecode /CCF] /DecodeParms [null << /K -1 /Columns 16 /Rows 0 /EndOfBlock true >>] /Length " . strlen($softMaskPrefixPayload) . " >>\nstream\n{$softMaskPrefixPayload}\nendstream",
    ]
);
$softMaskPrefixBoundary = $softMaskPrefixReview['soft_mask_filter_boundary'] ?? [];
$unresolvedXobjectBefore = 'BT /F1 12 Tf 72 720 Td (Before unresolved CCITT import) Tj ET';
$unresolvedXobjectAfter = 'BT /F1 12 Tf 72 680 Td (After unresolved CCITT import) Tj ET';
$unresolvedXobjectPayload = 'BT /F1 12 Tf 72 700 Td (WordPress unresolved CCITT DecodeParms leak) Tj ET';
$unresolvedXobjectPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /MissingParmsFax 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($unresolvedXobjectBefore) . " >>\nstream\n{$unresolvedXobjectBefore}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms 99 0 R /Length " . strlen($unresolvedXobjectPayload) . " >>\nstream\n{$unresolvedXobjectPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($unresolvedXobjectAfter) . " >>\nstream\n{$unresolvedXobjectAfter}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
$unresolvedXobjectReview = $boundaryExtractor->extractImageXObjectBoundaryReview($unresolvedXobjectPdf);
$unresolvedXobjectEntry = $unresolvedXobjectReview['entries'][0] ?? [];
$unresolvedXobjectParms = $unresolvedXobjectEntry['filter_details'][0]['decode_parms'] ?? [];
$unresolvedXobjectBoundary = $unresolvedXobjectEntry['ccitt_fax_decode_boundary'] ?? [];
$extraDecodeParmsBefore = 'BT /F1 12 Tf 72 720 Td (Before extra CCITT DecodeParms import) Tj ET';
$extraDecodeParmsAfter = 'BT /F1 12 Tf 72 680 Td (After extra CCITT DecodeParms import) Tj ET';
$extraDecodeParmsPayload = 'BT /F1 12 Tf 72 700 Td (WordPress extra CCITT DecodeParms leak) Tj ET';
$extraDecodeParmsPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /ExtraParmsFax 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($extraDecodeParmsBefore) . " >>\nstream\n{$extraDecodeParmsBefore}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms [<< /K -1 /Columns 16 /Rows 1 /BlackIs1 true /EndOfBlock false >> << /K /Bad /Columns 1 >>] /Length " . strlen($extraDecodeParmsPayload) . " >>\nstream\n{$extraDecodeParmsPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($extraDecodeParmsAfter) . " >>\nstream\n{$extraDecodeParmsAfter}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
$extraDecodeParmsReview = $boundaryExtractor->extractImageXObjectBoundaryReview($extraDecodeParmsPdf);
$extraDecodeParmsEntry = $extraDecodeParmsReview['entries'][0] ?? [];
$extraDecodeParmsParms = $extraDecodeParmsEntry['filter_details'][0]['decode_parms'] ?? [];
$extraDecodeParmsBoundary = $extraDecodeParmsEntry['ccitt_fax_decode_boundary'] ?? [];
$invalidOwnerBefore = 'BT /F1 12 Tf 72 720 Td (Before invalid-owner CCITT import) Tj ET';
$invalidOwnerAfter = 'BT /F1 12 Tf 72 680 Td (After invalid-owner CCITT import) Tj ET';
$invalidOwnerFakeText = 'BT /F1 12 Tf 72 700 Td (WordPress invalid-owner CCITT leak) Tj ET';
$invalidOwnerEofb = "\x00\x10\x01";
$invalidOwnerRtc = $invalidOwnerEofb . $invalidOwnerEofb . $invalidOwnerEofb;
$invalidOwnerPayload = "\x01\x02\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($invalidOwnerFakeText) . " >>\nstream\n{$invalidOwnerFakeText}\nendstream\nendobj\n"
    . "\x03{$invalidOwnerRtc}";
$invalidOwnerStaleLength = strpos($invalidOwnerPayload, "\nendstream\n");
if ($invalidOwnerStaleLength === false) {
    throw new RuntimeException('Unable to build malformed CCITT owner-boundary fixture.');
}
$invalidOwnerPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxInvalidOwner 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 9 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($invalidOwnerBefore) . " >>\nstream\n{$invalidOwnerBefore}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 0 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /K /Bad /Columns 16 /Rows 0 /EndOfBlock true >> /Length {$invalidOwnerStaleLength} >>\nstream\n{$invalidOwnerPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($invalidOwnerAfter) . " >>\nstream\n{$invalidOwnerAfter}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
$invalidOwnerLines = $boundaryExtractor->extractTextLines($invalidOwnerPdf);
$invalidOwnerReview = $boundaryExtractor->extractImageXObjectBoundaryReview($invalidOwnerPdf);
$invalidOwnerEntry = $invalidOwnerReview['entries'][0] ?? [];
$invalidOwnerParms = $invalidOwnerEntry['filter_details'][0]['decode_parms'] ?? [];
$rowEolBefore = 'BT /F1 12 Tf 72 720 Td (Before row-EOL CCITT import) Tj ET';
$rowEolAfter = 'BT /F1 12 Tf 72 680 Td (After row-EOL CCITT import) Tj ET';
$rowEolFakeText = 'BT /F1 12 Tf 72 700 Td (WordPress row-EOL CCITT leak) Tj ET';
$rowEolMarker = "\x00\x10\x01";
$rowEolPayload = "\x01\x02\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($rowEolFakeText) . " >>\nstream\n{$rowEolFakeText}\nendstream\nendobj\n"
    . "\x03{$rowEolMarker}";
$rowEolStaleLength = strpos($rowEolPayload, "\nendstream\n");
if ($rowEolStaleLength === false) {
    throw new RuntimeException('Unable to build row-EOL CCITT stale-length fixture.');
}
$rowEolPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxRowEol 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 9 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($rowEolBefore) . " >>\nstream\n{$rowEolBefore}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /K 0 /Columns 16 /Rows 1 /EndOfLine true /EndOfBlock false >> /Length {$rowEolStaleLength} >>\nstream\n{$rowEolPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($rowEolAfter) . " >>\nstream\n{$rowEolAfter}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
$rowEolLines = $boundaryExtractor->extractTextLines($rowEolPdf);
$rowEolReview = $boundaryExtractor->extractImageXObjectBoundaryReview($rowEolPdf);
$rowEolEntry = $rowEolReview['entries'][0] ?? [];
$indirectHeightBefore = 'BT /F1 12 Tf 72 720 Td (Before indirect-height CCITT import) Tj ET';
$indirectHeightAfter = 'BT /F1 12 Tf 72 680 Td (After indirect-height CCITT import) Tj ET';
$indirectHeightFakeText = 'BT /F1 12 Tf 72 700 Td (WordPress indirect-height CCITT leak) Tj ET';
$indirectHeightPayload = "\x01\x02{$rowEolMarker}\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($indirectHeightFakeText) . " >>\nstream\n{$indirectHeightFakeText}\nendstream\nendobj\n"
    . "\x03\x04{$rowEolMarker}";
$indirectHeightStaleLength = strpos($indirectHeightPayload, "\nendstream\n");
if ($indirectHeightStaleLength === false) {
    throw new RuntimeException('Unable to build indirect-height CCITT stale-length fixture.');
}
$indirectHeightPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxIndirectHeight 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 9 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($indirectHeightBefore) . " >>\nstream\n{$indirectHeightBefore}\nendstream\nendobj\n"
    . "11 0 obj\n2\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 11 0 R /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /K 0 /Columns 16 /EndOfLine true /EndOfBlock false >> /Length {$indirectHeightStaleLength} >>\nstream\n{$indirectHeightPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($indirectHeightAfter) . " >>\nstream\n{$indirectHeightAfter}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
$indirectHeightLines = $boundaryExtractor->extractTextLines($indirectHeightPdf);
$indirectHeightReview = $boundaryExtractor->extractImageXObjectBoundaryReview($indirectHeightPdf);
$indirectHeightEntry = $indirectHeightReview['entries'][0] ?? [];
$inlineCodingBoundary = $inlineReview['ccitt_fax_coding_boundary'] ?? [];
$inlinePolarityBoundary = $inlineReview['ccitt_fax_imagemask_polarity_boundary'] ?? [];
$defaultInlineCodingBoundary = $defaultInlineReview['ccitt_fax_coding_boundary'] ?? [];
$inlineNotes = $inlineReview['notes'] ?? [];
$invalidInlineParms = $invalidInlineReview['image_filter_details'][0]['decode_parms'] ?? [];
$unresolvedInlineParms = $unresolvedInlineReview['image_filter_details'][0]['decode_parms'] ?? [];
$unresolvedInlineBoundary = $unresolvedInlineReview['ccitt_fax_decode_boundary'] ?? [];
$extraDecodeParmsInlineParms = $extraDecodeParmsInlineReview['image_filter_details'][0]['decode_parms'] ?? [];
$extraDecodeParmsInlineBoundary = $extraDecodeParmsInlineReview['ccitt_fax_decode_boundary'] ?? [];
$inlineGeometryBoundary = $inlineGeometryReview['ccitt_fax_decode_boundary'] ?? [];
$escapedInlineParms = $escapedInlineReview['image_filter_details'][0]['decode_parms'] ?? [];
$escapedInlineBoundary = $escapedInlineReview['ccitt_fax_decode_boundary'] ?? [];
$nullFilterInlineParms = $nullFilterInlineReview['image_filter_details'][0]['decode_parms'] ?? [];
$nullFilterInlineBoundary = $nullFilterInlineReview['ccitt_fax_decode_boundary'] ?? [];
$geometryBoundary = $geometryReview['entries'][0]['ccitt_fax_decode_boundary'] ?? [];
if (!in_array('inline_ccitt_fax_image_filter_review_only', $inlineNotes, true)) {
    throw new RuntimeException('Inline CCITT Fax review boundary smoke failed.');
}
if (
    !in_array('ccitt_fax_imagemask_polarity_review_before_rgb_conversion', $inlineNotes, true)
    || !in_array('inline_ccitt_fax_imagemask_polarity_review_before_rgb_conversion', $inlineNotes, true)
    || ($inlinePolarityBoundary['black_sample_value'] ?? null) !== 0
    || ($inlinePolarityBoundary['white_sample_value'] ?? null) !== 1
    || ($inlinePolarityBoundary['black_sample_opacity'] ?? null) !== 1.0
    || ($inlinePolarityBoundary['white_sample_opacity'] ?? null) !== 0.0
    || ($inlinePolarityBoundary['image_mask_decode_source'] ?? null) !== 'explicit'
) {
    throw new RuntimeException('Inline CCITT ImageMask polarity smoke failed.');
}
if (
    ($invalidInlineParms['valid_decode_parms'] ?? null) !== false
    || !in_array('columns', $invalidInlineParms['invalid_decode_parms_fields'] ?? [], true)
    || !in_array('damaged_rows_before_error', $invalidInlineParms['invalid_decode_parms_fields'] ?? [], true)
) {
    throw new RuntimeException('Inline CCITT Fax invalid DecodeParms smoke failed.');
}
if (
    ($unresolvedInlineParms['valid_decode_parms'] ?? null) !== false
    || ($unresolvedInlineParms['decode_parms_review'] ?? null) !== 'unresolved_ccitt_decodeparms_fail_closed'
    || ($unresolvedInlineBoundary['invalid_decode_parms'] ?? null) !== true
    || !in_array('decode_parms_operand', $unresolvedInlineBoundary['invalid_decode_parms_fields'] ?? [], true)
    || str_contains(json_encode($unresolvedInlineReview, JSON_UNESCAPED_SLASHES) ?: '', $unresolvedInlinePayload)
) {
    throw new RuntimeException('Inline unresolved CCITT DecodeParms smoke failed.');
}
if (
    ($extraDecodeParmsInlineParms['valid_decode_parms'] ?? null) !== false
    || ($extraDecodeParmsInlineParms['decode_parms_review'] ?? null) !== 'unaligned_ccitt_decodeparms_fail_closed'
    || ($extraDecodeParmsInlineParms['decode_parms_alignment'] ?? null) !== 'unapplied_filter_slot'
    || ($extraDecodeParmsInlineParms['unapplied_decode_parms_slots'] ?? null) !== [1]
    || ($extraDecodeParmsInlineBoundary['invalid_decode_parms'] ?? null) !== true
    || !in_array('decode_parms_alignment', $extraDecodeParmsInlineBoundary['invalid_decode_parms_fields'] ?? [], true)
    || str_contains(json_encode($extraDecodeParmsInlineReview, JSON_UNESCAPED_SLASHES) ?: '', $extraDecodeParmsInlinePayload)
) {
    throw new RuntimeException('Inline extra CCITT DecodeParms boundary smoke failed.');
}
if (
    $boundaryLines !== ['Before Flate CCITT import', 'After Flate CCITT import']
    || str_contains($boundaryExtractor->extractPlainText($boundaryPdf), 'WordPress Flate CCITT prefix leak')
    || (($boundaryReview['entries'][0]['raw_length'] ?? null) !== strlen($compressedFaxPayload))
) {
    throw new RuntimeException('Flate-wrapped CCITT Fax stale-length boundary smoke failed.');
}
if (
    $cryptBoundaryLines !== ['Before Crypt CCITT import', 'After Crypt CCITT import']
    || str_contains($boundaryExtractor->extractPlainText($cryptBoundaryPdf), 'WordPress Crypt CCITT prefix leak')
    || (($cryptBoundaryEntry['raw_length'] ?? null) !== strlen($cryptWrappedFaxPayload))
    || (($cryptBoundaryEntry['ccitt_fax_decode_boundary']['effective_decode_parms']['end_of_block'] ?? null) !== true)
) {
    throw new RuntimeException('Identity-Crypt-wrapped CCITT Fax EOFB boundary smoke failed.');
}
if (
    $directBoundaryLines !== ['Before direct CCITT EOB import', 'Between direct CCITT EOB import', 'After direct CCITT EOB import']
    || str_contains($boundaryExtractor->extractPlainText($directBoundaryPdf), 'WordPress direct G4 CCITT leak')
    || str_contains($boundaryExtractor->extractPlainText($directBoundaryPdf), 'WordPress direct RTC CCITT leak')
    || (($directG4Entry['raw_length'] ?? null) !== strlen($directG4Payload))
    || (($directRtcEntry['raw_length'] ?? null) !== strlen($directRtcPayload))
    || (($directG4Entry['ccitt_fax_coding_boundary']['end_of_block_marker'] ?? null) !== 'eofb')
    || (($directRtcEntry['ccitt_fax_coding_boundary']['end_of_block_marker'] ?? null) !== 'rtc')
) {
    throw new RuntimeException('Direct CCITT Fax EOFB/RTC stream boundary smoke failed.');
}
if (
    ($inlineGeometryBoundary['effective_decode_parms']['end_of_block'] ?? null) !== true
    || ($inlineGeometryBoundary['dimension_mismatch'] ?? null) !== true
    || ($inlineCodingBoundary['coding_mode'] ?? null) !== 'group3_one_dimensional'
    || ($inlineCodingBoundary['end_of_block_marker'] ?? null) !== 'rtc'
    || ($defaultInlineCodingBoundary['decode_parms_present'] ?? null) !== false
    || ($defaultInlineCodingBoundary['coding_mode'] ?? null) !== 'group3_one_dimensional'
    || ($defaultInlineCodingBoundary['end_of_block_marker'] ?? null) !== 'rtc'
    || ($geometryBoundary['effective_width'] ?? null) !== 16
    || ($geometryBoundary['effective_height'] ?? null) !== 4
    || ($geometryBoundary['width_source'] ?? null) !== 'decodeparms_columns'
    || str_contains($boundaryExtractor->extractPlainText($geometryPdf), 'WordPress CCITT geometry payload noise')
) {
    throw new RuntimeException('CCITT effective DecodeParms geometry boundary smoke failed.');
}
if (
    ($escapedInlineParms['k'] ?? null) !== -1
    || ($escapedInlineParms['columns'] ?? null) !== 32
    || ($escapedInlineParms['rows'] ?? null) !== 3
    || ($escapedInlineParms['end_of_block'] ?? null) !== false
    || ($escapedInlineBoundary['dimension_mismatch'] ?? null) !== true
    || str_contains(json_encode($escapedInlineReview, JSON_UNESCAPED_SLASHES) ?: '', $escapedInlinePayload)
) {
    throw new RuntimeException('Escaped CCITT DecodeParms renderer boundary smoke failed.');
}
if (
    ($nullFilterInlineParms['k'] ?? null) !== -1
    || ($nullFilterInlineParms['columns'] ?? null) !== 16
    || ($nullFilterInlineParms['rows'] ?? null) !== 2
    || ($nullFilterInlineParms['end_of_block'] ?? null) !== false
    || ($nullFilterInlineBoundary['dimension_mismatch'] ?? null) !== false
    || str_contains(json_encode($nullFilterInlineReview, JSON_UNESCAPED_SLASHES) ?: '', $nullFilterInlinePayload)
) {
    throw new RuntimeException('Null-filter CCITT DecodeParms renderer boundary smoke failed.');
}
if (
    ($compactXobjectParms['k'] ?? null) !== -1
    || ($compactXobjectParms['columns'] ?? null) !== 24
    || ($compactXobjectParms['rows'] ?? null) !== 2
    || ($compactXobjectParms['end_of_block'] ?? null) !== false
    || ($compactXobjectFilterBoundary['declared_filter'] ?? null) !== 'CCF'
    || ($compactXobjectFilterBoundary['canonical_filter'] ?? null) !== 'CCITTFaxDecode'
    || ($compactXobjectFilterBoundary['alias_used'] ?? null) !== true
    || ($compactXobjectFilterBoundary['native_prefix_filters'] ?? null) !== ['ASCIIHexDecode']
    || ($compactXobjectBoundary['dimension_mismatch'] ?? null) !== false
    || ($compactXobjectPolarityBoundary['black_sample_value'] ?? null) !== 1
    || ($compactXobjectPolarityBoundary['white_sample_value'] ?? null) !== 0
    || ($compactXobjectPolarityBoundary['black_sample_opacity'] ?? null) !== 1.0
    || ($compactXobjectPolarityBoundary['white_sample_opacity'] ?? null) !== 0.0
    || ($compactXobjectPolarityBoundary['image_mask_decode_source'] ?? null) !== 'default'
    || str_contains($boundaryExtractor->extractPlainText($compactXobjectPdf), 'WordPress compact CCITT DecodeParms leak')
    || str_contains(json_encode($compactXobjectReview, JSON_UNESCAPED_SLASHES) ?: '', $compactXobjectPayload)
) {
    throw new RuntimeException('XObject compact CCITT DecodeParms boundary smoke failed.');
}
if (
    ($postCcittFilterBoundary['declared_filter'] ?? null) !== 'CCF'
    || ($postCcittFilterBoundary['filters_after_ccitt'] ?? null) !== ['ASCIIHexDecode', 'FlateDecode', 'DCTDecode']
    || ($postCcittFilterBoundary['native_filters_after_ccitt'] ?? null) !== ['ASCIIHexDecode', 'FlateDecode']
    || ($postCcittFilterBoundary['preview_only_filters_after_ccitt'] ?? null) !== ['DCTDecode']
    || ($postCcittFilterBoundary['ccitt_is_terminal_filter'] ?? null) !== false
    || ($postCcittFilterBoundary['post_ccitt_filters_block_native_decode'] ?? null) !== true
) {
    throw new RuntimeException('Post-CCITT filter-stack boundary smoke failed.');
}
if (
    ($softMaskPrefixBoundary['filters'] ?? null) !== ['ASCIIHexDecode', 'CCF']
    || ($softMaskPrefixBoundary['preview_only_filters'] ?? null) !== ['CCF']
    || ($softMaskPrefixBoundary['native_prefix_decoded'] ?? null) !== true
    || ($softMaskPrefixBoundary['native_prefix_decoded_length'] ?? null) !== strlen($softMaskPrefixFaxBytes)
    || ($softMaskPrefixBoundary['native_prefix_decoded_sha256'] ?? null) !== hash('sha256', $softMaskPrefixFaxBytes)
    || ($softMaskPrefixBoundary['stopped_before_filter'] ?? null) !== 'CCF'
    || !in_array('soft_mask_stream_native_prefix_decoded_before_preview_only', $softMaskPrefixReview['notes'] ?? [], true)
    || str_contains(json_encode($softMaskPrefixReview, JSON_UNESCAPED_SLASHES) ?: '', $softMaskPrefixPayload)
) {
    throw new RuntimeException('Soft-mask CCITT native-prefix boundary smoke failed.');
}
if (
    ($unresolvedXobjectParms['valid_decode_parms'] ?? null) !== false
    || ($unresolvedXobjectParms['decode_parms_review'] ?? null) !== 'unresolved_ccitt_decodeparms_fail_closed'
    || ($unresolvedXobjectBoundary['invalid_decode_parms'] ?? null) !== true
    || !in_array('decode_parms_operand', $unresolvedXobjectBoundary['invalid_decode_parms_fields'] ?? [], true)
    || str_contains($boundaryExtractor->extractPlainText($unresolvedXobjectPdf), 'WordPress unresolved CCITT DecodeParms leak')
    || str_contains(json_encode($unresolvedXobjectReview, JSON_UNESCAPED_SLASHES) ?: '', $unresolvedXobjectPayload)
) {
    throw new RuntimeException('XObject unresolved CCITT DecodeParms boundary smoke failed.');
}
if (
    ($extraDecodeParmsParms['valid_decode_parms'] ?? null) !== false
    || ($extraDecodeParmsParms['decode_parms_review'] ?? null) !== 'unaligned_ccitt_decodeparms_fail_closed'
    || ($extraDecodeParmsParms['decode_parms_alignment'] ?? null) !== 'unapplied_filter_slot'
    || ($extraDecodeParmsParms['unapplied_decode_parms_slots'] ?? null) !== [1]
    || ($extraDecodeParmsBoundary['invalid_decode_parms'] ?? null) !== true
    || !in_array('decode_parms_alignment', $extraDecodeParmsBoundary['invalid_decode_parms_fields'] ?? [], true)
    || str_contains($boundaryExtractor->extractPlainText($extraDecodeParmsPdf), 'WordPress extra CCITT DecodeParms leak')
    || str_contains(json_encode($extraDecodeParmsReview, JSON_UNESCAPED_SLASHES) ?: '', $extraDecodeParmsPayload)
) {
    throw new RuntimeException('XObject extra CCITT DecodeParms boundary smoke failed.');
}
if (
    $invalidOwnerLines !== ['Before invalid-owner CCITT import', 'After invalid-owner CCITT import']
    || str_contains($boundaryExtractor->extractPlainText($invalidOwnerPdf), 'WordPress invalid-owner CCITT leak')
    || (($invalidOwnerEntry['raw_length'] ?? null) !== strlen($invalidOwnerPayload))
    || (($invalidOwnerParms['valid_decode_parms'] ?? null) !== false)
    || (($invalidOwnerParms['decode_parms_review'] ?? null) !== 'invalid_ccitt_decodeparms_fail_closed')
    || !in_array('k', $invalidOwnerParms['invalid_decode_parms_fields'] ?? [], true)
    || str_contains(json_encode($invalidOwnerReview, JSON_UNESCAPED_SLASHES) ?: '', $invalidOwnerPayload)
) {
    throw new RuntimeException('Malformed CCITT owner-boundary smoke failed.');
}
if (
    $rowEolLines !== ['Before row-EOL CCITT import', 'After row-EOL CCITT import']
    || str_contains($boundaryExtractor->extractPlainText($rowEolPdf), 'WordPress row-EOL CCITT leak')
    || (($rowEolEntry['raw_length'] ?? null) !== strlen($rowEolPayload))
    || (($rowEolEntry['ccitt_fax_decode_boundary']['effective_decode_parms']['end_of_line'] ?? null) !== true)
    || (($rowEolEntry['ccitt_fax_decode_boundary']['effective_decode_parms']['end_of_block'] ?? null) !== false)
    || (($rowEolEntry['ccitt_fax_coding_boundary']['end_of_block_marker'] ?? null) !== null)
    || str_contains(json_encode($rowEolReview, JSON_UNESCAPED_SLASHES) ?: '', $rowEolPayload)
) {
    throw new RuntimeException('CCITT row-EOL no-EndOfBlock ownership smoke failed.');
}
if (
    $indirectHeightLines !== ['Before indirect-height CCITT import', 'After indirect-height CCITT import']
    || str_contains($boundaryExtractor->extractPlainText($indirectHeightPdf), 'WordPress indirect-height CCITT leak')
    || (($indirectHeightEntry['raw_length'] ?? null) !== strlen($indirectHeightPayload))
    || (($indirectHeightEntry['height'] ?? null) !== 2)
    || (($indirectHeightEntry['ccitt_fax_decode_boundary']['dictionary_height'] ?? null) !== 2)
    || (($indirectHeightEntry['ccitt_fax_decode_boundary']['effective_height'] ?? null) !== 2)
    || (($indirectHeightEntry['ccitt_fax_decode_boundary']['height_source'] ?? null) !== 'image_dictionary')
    || (($indirectHeightEntry['ccitt_fax_decode_boundary']['effective_decode_parms']['end_of_line'] ?? null) !== true)
    || (($indirectHeightEntry['ccitt_fax_decode_boundary']['effective_decode_parms']['end_of_block'] ?? null) !== false)
    || (($indirectHeightEntry['ccitt_fax_coding_boundary']['end_of_block_marker'] ?? null) !== null)
    || str_contains(json_encode($indirectHeightReview, JSON_UNESCAPED_SLASHES) ?: '', $indirectHeightPayload)
) {
    throw new RuntimeException('CCITT indirect-height row-EOL ownership smoke failed.');
}

echo '<!-- markerpdf:pdf-ccitt-fax-filter ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-stream-filter-boundary',
    'stream_filters' => ['CCITTFaxDecode', 'CCF'],
    'inline_image_filters' => $inlineReview['image_filters'] ?? [],
    'inline_review_only_filters' => $inlineReview['inline_image']['review_only_filters'] ?? [],
    'inline_ccitt_review_only' => $inlineReview['inline_image_review_only'] ?? null,
    'inline_ccitt_note' => 'inline_ccitt_fax_image_filter_review_only',
    'inline_ccitt_coding_mode' => $inlineCodingBoundary['coding_mode'] ?? null,
    'inline_ccitt_end_of_block_marker' => $inlineCodingBoundary['end_of_block_marker'] ?? null,
    'inline_ccitt_imagemask_polarity' => [
        'black_sample_value' => $inlinePolarityBoundary['black_sample_value'] ?? null,
        'white_sample_value' => $inlinePolarityBoundary['white_sample_value'] ?? null,
        'black_sample_opacity' => $inlinePolarityBoundary['black_sample_opacity'] ?? null,
        'white_sample_opacity' => $inlinePolarityBoundary['white_sample_opacity'] ?? null,
        'decode_source' => $inlinePolarityBoundary['image_mask_decode_source'] ?? null,
    ],
    'inline_default_decode_parms_present' => $defaultInlineCodingBoundary['decode_parms_present'] ?? null,
    'inline_default_coding_mode' => $defaultInlineCodingBoundary['coding_mode'] ?? null,
    'inline_default_end_of_block_marker' => $defaultInlineCodingBoundary['end_of_block_marker'] ?? null,
    'inline_invalid_decode_parms_valid' => $invalidInlineParms['valid_decode_parms'] ?? null,
    'inline_invalid_decode_parms_fields' => $invalidInlineParms['invalid_decode_parms_fields'] ?? [],
    'inline_invalid_payload_excluded_from_review' => !str_contains(json_encode($invalidInlineReview, JSON_UNESCAPED_SLASHES) ?: '', $invalidInlinePayload),
    'inline_unresolved_decode_parms_valid' => $unresolvedInlineParms['valid_decode_parms'] ?? null,
    'inline_unresolved_decode_parms_review' => $unresolvedInlineParms['decode_parms_review'] ?? null,
    'inline_unresolved_decode_parms_operand' => $unresolvedInlineParms['decode_parms_operand'] ?? null,
    'inline_unresolved_payload_excluded_from_review' => !str_contains(json_encode($unresolvedInlineReview, JSON_UNESCAPED_SLASHES) ?: '', $unresolvedInlinePayload),
    'inline_extra_decode_parms_valid' => $extraDecodeParmsInlineParms['valid_decode_parms'] ?? null,
    'inline_extra_decode_parms_review' => $extraDecodeParmsInlineParms['decode_parms_review'] ?? null,
    'inline_extra_decode_parms_alignment' => $extraDecodeParmsInlineParms['decode_parms_alignment'] ?? null,
    'inline_extra_decode_parms_unapplied_slots' => $extraDecodeParmsInlineParms['unapplied_decode_parms_slots'] ?? [],
    'inline_extra_decode_parms_fail_closed' => ($extraDecodeParmsInlineBoundary['invalid_decode_parms'] ?? null) === true,
    'inline_extra_payload_excluded_from_review' => !str_contains(json_encode($extraDecodeParmsInlineReview, JSON_UNESCAPED_SLASHES) ?: '', $extraDecodeParmsInlinePayload),
    'inline_effective_decode_parms' => $inlineGeometryBoundary['effective_decode_parms'] ?? [],
    'inline_geometry_dimension_mismatch' => $inlineGeometryBoundary['dimension_mismatch'] ?? null,
    'inline_geometry_defaults_applied' => $inlineGeometryBoundary['defaults_applied'] ?? [],
    'inline_escaped_decode_parms' => [
        'k' => $escapedInlineParms['k'] ?? null,
        'columns' => $escapedInlineParms['columns'] ?? null,
        'rows' => $escapedInlineParms['rows'] ?? null,
        'black_is_1' => $escapedInlineParms['black_is_1'] ?? null,
        'encoded_byte_align' => $escapedInlineParms['encoded_byte_align'] ?? null,
        'end_of_line' => $escapedInlineParms['end_of_line'] ?? null,
        'end_of_block' => $escapedInlineParms['end_of_block'] ?? null,
        'damaged_rows_before_error' => $escapedInlineParms['damaged_rows_before_error'] ?? null,
    ],
    'inline_escaped_decode_parms_nested_decoys_ignored' => ($escapedInlineParms['columns'] ?? null) === 32
        && ($escapedInlineParms['rows'] ?? null) === 3,
    'inline_escaped_payload_excluded_from_review' => !str_contains(json_encode($escapedInlineReview, JSON_UNESCAPED_SLASHES) ?: '', $escapedInlinePayload),
    'inline_escaped_dimension_mismatch' => $escapedInlineBoundary['dimension_mismatch'] ?? null,
    'inline_null_filter_decode_parms' => [
        'k' => $nullFilterInlineParms['k'] ?? null,
        'columns' => $nullFilterInlineParms['columns'] ?? null,
        'rows' => $nullFilterInlineParms['rows'] ?? null,
        'black_is_1' => $nullFilterInlineParms['black_is_1'] ?? null,
        'end_of_block' => $nullFilterInlineParms['end_of_block'] ?? null,
    ],
    'inline_null_filter_decode_parms_aligned' => ($nullFilterInlineParms['columns'] ?? null) === 16
        && ($nullFilterInlineParms['rows'] ?? null) === 2,
    'inline_null_filter_payload_excluded_from_review' => !str_contains(json_encode($nullFilterInlineReview, JSON_UNESCAPED_SLASHES) ?: '', $nullFilterInlinePayload),
    'inline_null_filter_dimension_mismatch' => $nullFilterInlineBoundary['dimension_mismatch'] ?? null,
    'xobject_compact_decode_parms' => [
        'k' => $compactXobjectParms['k'] ?? null,
        'columns' => $compactXobjectParms['columns'] ?? null,
        'rows' => $compactXobjectParms['rows'] ?? null,
        'black_is_1' => $compactXobjectParms['black_is_1'] ?? null,
        'encoded_byte_align' => $compactXobjectParms['encoded_byte_align'] ?? null,
        'end_of_line' => $compactXobjectParms['end_of_line'] ?? null,
        'end_of_block' => $compactXobjectParms['end_of_block'] ?? null,
        'damaged_rows_before_error' => $compactXobjectParms['damaged_rows_before_error'] ?? null,
    ],
    'xobject_compact_decode_parms_aligned' => ($compactXobjectParms['columns'] ?? null) === 24
        && ($compactXobjectParms['rows'] ?? null) === 2
        && ($compactXobjectParms['end_of_block'] ?? null) === false,
    'xobject_compact_filter_details' => $compactXobjectEntry['filter_details'] ?? [],
    'xobject_compact_ccf_alias_filter_boundary' => $compactXobjectFilterBoundary,
    'xobject_compact_declared_alias_preserved' => ($compactXobjectFilterBoundary['declared_filter'] ?? null) === 'CCF'
        && ($compactXobjectFilterBoundary['canonical_filter'] ?? null) === 'CCITTFaxDecode'
        && ($compactXobjectFilterBoundary['alias_used'] ?? null) === true,
    'xobject_compact_native_prefix_filters' => $compactXobjectFilterBoundary['native_prefix_filters'] ?? [],
    'post_ccitt_filter_boundary' => $postCcittFilterBoundary,
    'post_ccitt_filters_after_ccitt' => $postCcittFilterBoundary['filters_after_ccitt'] ?? [],
    'post_ccitt_native_filters_blocked' => $postCcittFilterBoundary['native_filters_after_ccitt'] ?? [],
    'post_ccitt_filters_block_native_decode' => $postCcittFilterBoundary['post_ccitt_filters_block_native_decode'] ?? null,
    'soft_mask_prefix_filters' => $softMaskPrefixBoundary['filters'] ?? [],
    'soft_mask_prefix_preview_only_filters' => $softMaskPrefixBoundary['preview_only_filters'] ?? [],
    'soft_mask_prefix_native_decoded' => $softMaskPrefixBoundary['native_prefix_decoded'] ?? null,
    'soft_mask_prefix_native_decoded_length' => $softMaskPrefixBoundary['native_prefix_decoded_length'] ?? null,
    'soft_mask_prefix_stopped_before_filter' => $softMaskPrefixBoundary['stopped_before_filter'] ?? null,
    'soft_mask_prefix_payload_excluded_from_review' => !str_contains(json_encode($softMaskPrefixReview, JSON_UNESCAPED_SLASHES) ?: '', $softMaskPrefixPayload),
    'xobject_compact_imagemask_polarity' => [
        'black_sample_value' => $compactXobjectPolarityBoundary['black_sample_value'] ?? null,
        'white_sample_value' => $compactXobjectPolarityBoundary['white_sample_value'] ?? null,
        'black_sample_opacity' => $compactXobjectPolarityBoundary['black_sample_opacity'] ?? null,
        'white_sample_opacity' => $compactXobjectPolarityBoundary['white_sample_opacity'] ?? null,
        'decode_source' => $compactXobjectPolarityBoundary['image_mask_decode_source'] ?? null,
    ],
    'xobject_compact_payload_excluded_from_review' => !str_contains(json_encode($compactXobjectReview, JSON_UNESCAPED_SLASHES) ?: '', $compactXobjectPayload),
    'xobject_compact_payload_excluded_from_text' => !str_contains($boundaryExtractor->extractPlainText($compactXobjectPdf), 'WordPress compact CCITT DecodeParms leak'),
    'xobject_compact_dimension_mismatch' => $compactXobjectBoundary['dimension_mismatch'] ?? null,
    'xobject_unresolved_decode_parms_valid' => $unresolvedXobjectParms['valid_decode_parms'] ?? null,
    'xobject_unresolved_decode_parms_review' => $unresolvedXobjectParms['decode_parms_review'] ?? null,
    'xobject_unresolved_decode_parms_operand' => $unresolvedXobjectParms['decode_parms_operand'] ?? null,
    'xobject_unresolved_payload_excluded_from_review' => !str_contains(json_encode($unresolvedXobjectReview, JSON_UNESCAPED_SLASHES) ?: '', $unresolvedXobjectPayload),
    'xobject_unresolved_payload_excluded_from_text' => !str_contains($boundaryExtractor->extractPlainText($unresolvedXobjectPdf), 'WordPress unresolved CCITT DecodeParms leak'),
    'xobject_extra_decode_parms_valid' => $extraDecodeParmsParms['valid_decode_parms'] ?? null,
    'xobject_extra_decode_parms_review' => $extraDecodeParmsParms['decode_parms_review'] ?? null,
    'xobject_extra_decode_parms_alignment' => $extraDecodeParmsParms['decode_parms_alignment'] ?? null,
    'xobject_extra_decode_parms_unapplied_slots' => $extraDecodeParmsParms['unapplied_decode_parms_slots'] ?? [],
    'xobject_extra_decode_parms_fail_closed' => ($extraDecodeParmsBoundary['invalid_decode_parms'] ?? null) === true,
    'xobject_extra_payload_excluded_from_review' => !str_contains(json_encode($extraDecodeParmsReview, JSON_UNESCAPED_SLASHES) ?: '', $extraDecodeParmsPayload),
    'xobject_extra_payload_excluded_from_text' => !str_contains($boundaryExtractor->extractPlainText($extraDecodeParmsPdf), 'WordPress extra CCITT DecodeParms leak'),
    'xobject_invalid_owner_decode_parms_valid' => $invalidOwnerParms['valid_decode_parms'] ?? null,
    'xobject_invalid_owner_decode_parms_review' => $invalidOwnerParms['decode_parms_review'] ?? null,
    'xobject_invalid_owner_invalid_fields' => $invalidOwnerParms['invalid_decode_parms_fields'] ?? [],
    'xobject_invalid_owner_boundary_repaired' => ($invalidOwnerEntry['raw_length'] ?? null) === strlen($invalidOwnerPayload),
    'xobject_invalid_owner_raw_length' => $invalidOwnerEntry['raw_length'] ?? null,
    'xobject_invalid_owner_payload_excluded_from_review' => !str_contains(json_encode($invalidOwnerReview, JSON_UNESCAPED_SLASHES) ?: '', $invalidOwnerPayload),
    'xobject_invalid_owner_payload_excluded_from_text' => !str_contains($boundaryExtractor->extractPlainText($invalidOwnerPdf), 'WordPress invalid-owner CCITT leak'),
    'xobject_row_eol_no_endblock_boundary_repaired' => ($rowEolEntry['raw_length'] ?? null) === strlen($rowEolPayload),
    'xobject_row_eol_no_endblock_raw_length' => $rowEolEntry['raw_length'] ?? null,
    'xobject_row_eol_no_endblock_end_of_line' => $rowEolEntry['ccitt_fax_decode_boundary']['effective_decode_parms']['end_of_line'] ?? null,
    'xobject_row_eol_no_endblock_end_of_block' => $rowEolEntry['ccitt_fax_decode_boundary']['effective_decode_parms']['end_of_block'] ?? null,
    'xobject_row_eol_no_endblock_marker' => $rowEolEntry['ccitt_fax_coding_boundary']['end_of_block_marker'] ?? null,
    'xobject_row_eol_no_endblock_payload_excluded_from_review' => !str_contains(json_encode($rowEolReview, JSON_UNESCAPED_SLASHES) ?: '', $rowEolPayload),
    'xobject_row_eol_no_endblock_payload_excluded_from_text' => !str_contains($boundaryExtractor->extractPlainText($rowEolPdf), 'WordPress row-EOL CCITT leak'),
    'xobject_indirect_height_row_eol_boundary_repaired' => ($indirectHeightEntry['raw_length'] ?? null) === strlen($indirectHeightPayload),
    'xobject_indirect_height_row_eol_height' => $indirectHeightEntry['height'] ?? null,
    'xobject_indirect_height_row_eol_effective_height' => $indirectHeightEntry['ccitt_fax_decode_boundary']['effective_height'] ?? null,
    'xobject_indirect_height_row_eol_height_source' => $indirectHeightEntry['ccitt_fax_decode_boundary']['height_source'] ?? null,
    'xobject_indirect_height_row_eol_end_of_line' => $indirectHeightEntry['ccitt_fax_decode_boundary']['effective_decode_parms']['end_of_line'] ?? null,
    'xobject_indirect_height_row_eol_end_of_block' => $indirectHeightEntry['ccitt_fax_decode_boundary']['effective_decode_parms']['end_of_block'] ?? null,
    'xobject_indirect_height_row_eol_payload_excluded_from_review' => !str_contains(json_encode($indirectHeightReview, JSON_UNESCAPED_SLASHES) ?: '', $indirectHeightPayload),
    'xobject_indirect_height_row_eol_payload_excluded_from_text' => !str_contains($boundaryExtractor->extractPlainText($indirectHeightPdf), 'WordPress indirect-height CCITT leak'),
    'xobject_geometry_effective_width' => $geometryBoundary['effective_width'] ?? null,
    'xobject_geometry_effective_height' => $geometryBoundary['effective_height'] ?? null,
    'xobject_geometry_width_source' => $geometryBoundary['width_source'] ?? null,
    'xobject_geometry_payload_excluded' => !str_contains($boundaryExtractor->extractPlainText($geometryPdf), 'WordPress CCITT geometry payload noise'),
    'flate_wrapped_ccitt_stale_length_repaired' => true,
    'flate_wrapped_ccitt_payload_excluded' => !str_contains($boundaryExtractor->extractPlainText($boundaryPdf), 'WordPress Flate CCITT prefix leak'),
    'flate_wrapped_ccitt_raw_length' => $boundaryReview['entries'][0]['raw_length'] ?? null,
    'identity_crypt_wrapped_ccitt_eofb_boundary_repaired' => ($cryptBoundaryEntry['raw_length'] ?? null) === strlen($cryptWrappedFaxPayload),
    'identity_crypt_wrapped_ccitt_payload_excluded' => !str_contains($boundaryExtractor->extractPlainText($cryptBoundaryPdf), 'WordPress Crypt CCITT prefix leak'),
    'identity_crypt_wrapped_ccitt_raw_length' => $cryptBoundaryEntry['raw_length'] ?? null,
    'identity_crypt_wrapped_ccitt_filters' => $cryptBoundaryEntry['filters'] ?? [],
    'identity_crypt_wrapped_ccitt_end_of_block' => $cryptBoundaryEntry['ccitt_fax_decode_boundary']['effective_decode_parms']['end_of_block'] ?? null,
    'direct_ccitt_eofb_boundary_repaired' => ($directG4Entry['raw_length'] ?? null) === strlen($directG4Payload),
    'direct_ccitt_rtc_boundary_repaired' => ($directRtcEntry['raw_length'] ?? null) === strlen($directRtcPayload),
    'direct_ccitt_payload_excluded' => !str_contains($boundaryExtractor->extractPlainText($directBoundaryPdf), 'WordPress direct G4 CCITT leak')
        && !str_contains($boundaryExtractor->extractPlainText($directBoundaryPdf), 'WordPress direct RTC CCITT leak'),
    'direct_ccitt_eofb_effective_k' => $directG4Entry['ccitt_fax_decode_boundary']['effective_decode_parms']['k'] ?? null,
    'direct_ccitt_rtc_effective_k' => $directRtcEntry['ccitt_fax_decode_boundary']['effective_decode_parms']['k'] ?? null,
    'direct_ccitt_eofb_marker' => $directG4Entry['ccitt_fax_coding_boundary']['end_of_block_marker'] ?? null,
    'direct_ccitt_rtc_marker' => $directRtcEntry['ccitt_fax_coding_boundary']['end_of_block_marker'] ?? null,
    'decode_parms' => [
        ['K' => -1, 'Columns' => 1728, 'Rows' => 1, 'BlackIs1' => true],
        ['K' => 0, 'Columns' => 8, 'Rows' => 1, 'EncodedByteAlign' => true],
    ],
    'paragraphs' => $lines,
    'image_only_filter_skipped' => true,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
