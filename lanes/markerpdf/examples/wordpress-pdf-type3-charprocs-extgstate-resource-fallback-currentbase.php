<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$charProc = "650 0 d0\nq /GlyphTransfer gs /StreamHalftone gs Q\n"
    . "BT /Ft3 9 Tf <47484F5354> Tj ET\n";
$visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
$transferPayload = 'BT /Fghost 7 Tf 0 0 Td (Type3 ExtGState transfer function text leak) Tj ET';
$halftonePayload = 'BT /Fghost 7 Tf 0 0 Td (Type3 ExtGState halftone stream text leak) Tj ET';
$blackGenerationPayload = 'BT /Fghost 7 Tf 0 0 Td (Type3 ExtGState black generation text leak) Tj ET';
$streamTransferPayload = 'BT /Fghost 7 Tf 0 0 Td (Type3 stream ExtGState transfer text leak) Tj ET';

$pdf = "%PDF-1.4\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3ExtGStateResourceFallback "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding /WinAnsiEncoding /CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R "
    . "/G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R >> "
    . "/Resources << /ExtGState << /GlyphTransfer 20 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Resources << /ExtGState << /StreamHalftone 30 0 R >> >> /Length "
    . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /ExtGState /TR 21 0 R /BG2 22 0 R >>\nendobj\n"
    . "21 0 obj\n<< /FunctionType 4 /Domain [0 1] /Range [0 1] /Length " . strlen($transferPayload) . " >>\n"
    . "stream\n{$transferPayload}\nendstream\nendobj\n"
    . "22 0 obj\n<< /FunctionType 4 /Domain [0 1] /Range [0 1] /Length "
    . strlen($blackGenerationPayload) . " >>\nstream\n{$blackGenerationPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /ExtGState /HT 31 0 R /TR2 32 0 R >>\nendobj\n"
    . "31 0 obj\n<< /HalftoneType 1 /Frequency 60 /Angle 45 /SpotFunction 33 0 R /Length "
    . strlen($halftonePayload) . " >>\nstream\n{$halftonePayload}\nendstream\nendobj\n"
    . "32 0 obj\n<< /FunctionType 4 /Domain [0 1] /Range [0 1] /Length "
    . strlen($streamTransferPayload) . " >>\nstream\n{$streamTransferPayload}\nendstream\nendobj\n"
    . "33 0 obj\n<< /FunctionType 4 /Domain [0 1 0 1] /Range [0 1] /Length 0 >>\n"
    . "stream\n\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

$review = [
    'source' => 'native-pdf-type3-charprocs-extgstate-resource-fallback-boundary',
    'font_private_sources' => [
        'Type3 font /Resources /ExtGState transfer function stream',
        'Type3 font /Resources /ExtGState black-generation function stream',
        'Type3 CharProc stream /Resources /ExtGState halftone stream',
        'Type3 CharProc stream /Resources /ExtGState transfer function stream',
    ],
    'fallback_content_preserved' => $lines === ['Visible fallback content'],
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'GHOST'),
    'transfer_function_payload_excluded' => !str_contains($plainText, 'Type3 ExtGState transfer function text leak'),
    'halftone_payload_excluded' => !str_contains($plainText, 'Type3 ExtGState halftone stream text leak'),
    'black_generation_payload_excluded' => !str_contains($plainText, 'Type3 ExtGState black generation text leak'),
    'stream_transfer_payload_excluded' => !str_contains($plainText, 'Type3 stream ExtGState transfer text leak'),
    'extgstate_resource_names_excluded' => !str_contains($plainText, 'GlyphTransfer')
        && !str_contains($plainText, 'StreamHalftone'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'fallback_content_preserved',
    'charproc_payload_visible_text_excluded',
    'transfer_function_payload_excluded',
    'halftone_payload_excluded',
    'black_generation_payload_excluded',
    'stream_transfer_payload_excluded',
    'extgstate_resource_names_excluded',
] as $requiredFlag) {
    if ($review[$requiredFlag] !== true) {
        throw new RuntimeException("Type3 CharProcs ExtGState resource fallback boundary failed: {$requiredFlag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-extgstate-resource-fallback-currentbase '
    . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
