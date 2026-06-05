<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\MarkerPDF\PdfImageRenderer;

$renderer = new PdfImageRenderer();
$dictionary = '<< /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 '
    . '/Filter [ /ASCIIHexDecode % ] comment delimiter must remain comment text'
    . "\n /CCF ] "
    . '/DecodeParms [ null << /K -1 /Columns 16 /Rows 1 /BlackIs1 true /EndOfBlock false '
    . '% >> comment delimiter must remain comment text'
    . "\n >> ] /Decode [1 0] >>";
$payload = "fax bytes EI BT /F1 12 Tf 72 640 Td (WordPress comment CCITT payload noise) Tj ET final";

$plan = $renderer->inlineImageReviewPlan(
    '/W 16 /H 1 /IM true /F [ /AHx % ] comment delimiter must remain comment text'
    . "\n /CCF ] /DP [ null << /K -1 /Columns 16 /Rows 1 /BlackIs1 true /EndOfBlock false "
    . '% >> comment delimiter must remain comment text'
    . "\n >> ] /D [1 0]",
    $payload
);
$xobjectPlan = $renderer->imageColorSpaceSoftMaskPlan($dictionary);

$commentBoundariesIgnored = ($xobjectPlan['image_filters'] ?? []) === ['ASCIIHexDecode', 'CCF']
    && ($xobjectPlan['ccitt_fax_decode_boundary']['effective_decode_parms']['columns'] ?? null) === 16
    && ($xobjectPlan['ccitt_fax_decode_boundary']['effective_decode_parms']['end_of_block'] ?? null) === false
    && ($plan['image_filters'] ?? []) === ['ASCIIHexDecode', 'CCITTFaxDecode']
    && ($plan['ccitt_fax_decode_boundary']['effective_decode_parms']['k'] ?? null) === -1;

$payloadExcluded = !str_contains(json_encode($plan, JSON_UNESCAPED_SLASHES) ?: '', 'WordPress comment CCITT payload noise');

if (!$commentBoundariesIgnored || !$payloadExcluded || ($plan['inline_image']['native_raster_decode'] ?? true) !== false) {
    throw new RuntimeException('CCITT Fax comment filter boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-fax-comment-filter-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image review handoff',
    'xobject_filters' => $xobjectPlan['image_filters'] ?? [],
    'inline_filters' => $plan['image_filters'] ?? [],
    'ccitt_columns' => $plan['ccitt_fax_decode_boundary']['effective_decode_parms']['columns'] ?? null,
    'ccitt_rows' => $plan['ccitt_fax_decode_boundary']['effective_decode_parms']['rows'] ?? null,
    'end_of_block' => $plan['ccitt_fax_decode_boundary']['effective_decode_parms']['end_of_block'] ?? null,
    'comment_boundaries_ignored' => $commentBoundariesIgnored,
    'payload_excluded_from_review' => $payloadExcluded,
    'native_raster_decode' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-comment-filter-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n";
echo "<p>CCITT fax image review metadata preserved PDF comments inside filter operands.</p>\n";
echo "<!-- /wp:paragraph -->\n";
