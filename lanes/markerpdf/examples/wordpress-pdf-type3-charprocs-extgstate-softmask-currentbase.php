<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$charProc = "650 0 d0\nq /Glyph#20Mask gs Q\n"
    . "BT /Ft3 9 Tf <47484F5354> Tj ET\n";
$visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
$softMaskGroupPayload = "q /NestedMaskPaint Do Q\n"
    . "BT /Fghost 9 Tf 0 0 Td (Type3 ExtGState soft mask group text leak) Tj ET\n";
$nestedMaskPayload = "BT /Fghost 9 Tf 0 0 Td (nested Type3 soft mask form text leak) Tj ET\n";
$transferFunctionPayload = "BT /Fghost 9 Tf 0 0 Td (Type3 soft mask transfer text leak) Tj ET\n";

$pdf = "%PDF-1.4\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3ExtGStateSoftMask "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding /WinAnsiEncoding /CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R "
    . "/G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R >> "
    . "/Resources << /ExtGState << /Glyph#20Mask 20 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /ExtGState /ca 0.5 /SMask 21 0 R >>\nendobj\n"
    . "21 0 obj\n<< /Type /Mask /S /Luminosity /G 22 0 R /TR 24 0 R >>\nendobj\n"
    . "22 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 12 12] "
    . "/Resources << /Font << /Fghost 30 0 R >> /XObject << /NestedMaskPaint 23 0 R >> >> "
    . "/Length " . strlen($softMaskGroupPayload) . " >>\nstream\n{$softMaskGroupPayload}\nendstream\nendobj\n"
    . "23 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 12 12] "
    . "/Resources << /Font << /Fghost 30 0 R >> >> /Length " . strlen($nestedMaskPayload) . " >>\n"
    . "stream\n{$nestedMaskPayload}\nendstream\nendobj\n"
    . "24 0 obj\n<< /FunctionType 4 /Domain [0 1] /Range [0 1] /Length " . strlen($transferFunctionPayload) . " >>\n"
    . "stream\n{$transferFunctionPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

$review = [
    'source' => 'native-pdf-type3-charprocs-extgstate-softmask-boundary',
    'font_text_sources' => [
        'Type3 /CharProcs glyph stream with ExtGState soft mask resource',
        'ExtGState /SMask /G transparency group Form XObject',
        'ExtGState soft-mask transfer-function stream',
        'stream-only fallback content stream without a page tree',
    ],
    'fallback_content_preserved' => $plainText === 'Visible fallback content',
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'GHOST'),
    'soft_mask_group_payload_excluded' => !str_contains($plainText, 'Type3 ExtGState soft mask group text leak'),
    'nested_soft_mask_form_payload_excluded' => !str_contains($plainText, 'nested Type3 soft mask form text leak'),
    'soft_mask_transfer_payload_excluded' => !str_contains($plainText, 'Type3 soft mask transfer text leak'),
    'extgstate_resource_names_excluded' => !str_contains($plainText, 'Glyph Mask') && !str_contains($plainText, 'NestedMaskPaint'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'fallback_content_preserved',
    'charproc_payload_visible_text_excluded',
    'soft_mask_group_payload_excluded',
    'nested_soft_mask_form_payload_excluded',
    'soft_mask_transfer_payload_excluded',
    'extgstate_resource_names_excluded',
] as $requiredFlag) {
    if ($review[$requiredFlag] !== true) {
        throw new RuntimeException("Type3 CharProcs ExtGState soft-mask boundary failed: {$requiredFlag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-extgstate-softmask-currentbase ' . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
