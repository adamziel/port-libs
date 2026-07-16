<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$renderer = new PdfImageRenderer();
$plan = $renderer->imageColorSpaceSoftMaskPlan(
    '<< /Subtype /Image /Width 16 /Height 2 /ImageMask true /BitsPerComponent 1 /Filter /CCF /DecodeParms 30 1 R /Decode [1 0] >>',
    [
        30 => '<< /K /Bad /Columns 4 /Rows 99 /BlackIs1 false /EncodedByteAlign false /EndOfLine false /EndOfBlock true /DamagedRowsBeforeError 7 >>',
        '30:1' => '<< /K -1 /Columns 16 /Rows 2 /BlackIs1 40 2 R /EncodedByteAlign 42 2 R /EndOfLine 43 2 R /EndOfBlock 41 2 R /DamagedRowsBeforeError 0 >>',
        40 => 'false',
        '40:2' => 'true',
        41 => 'true',
        '41:2' => 'false',
        42 => 'false',
        '42:2' => 'true',
        43 => 'false',
        '43:2' => 'true',
    ]
);

$decodeParms = $plan['image_filter_details'][0]['decode_parms'] ?? [];
$boundary = $plan['ccitt_fax_decode_boundary'] ?? [];
$coding = $plan['ccitt_fax_coding_boundary'] ?? [];
$polarity = $plan['ccitt_fax_imagemask_polarity_boundary'] ?? [];

if (
    ($decodeParms['black_is_1'] ?? null) !== true
    || ($decodeParms['encoded_byte_align'] ?? null) !== true
    || ($decodeParms['end_of_line'] ?? null) !== true
    || ($decodeParms['end_of_block'] ?? null) !== false
    || ($boundary['invalid_decode_parms'] ?? null) !== false
    || ($coding['coding_mode'] ?? null) !== 'group4_two_dimensional'
    || !array_key_exists('end_of_block_marker', $coding)
    || $coding['end_of_block_marker'] !== null
    || ($polarity['black_is_1'] ?? null) !== true
    || ($plan['image_filter_boundary']['native_raster_decode'] ?? null) !== false
) {
    throw new RuntimeException('CCITT DecodeParms generation boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-fax-decodeparms-generation-currentbase',
    'upstream_boundary' => 'PDF indirect references are object-and-generation qualified before marker.pdf image review',
    'filters' => $plan['image_filters'] ?? [],
    'preview_only_filters' => $plan['image_filter_boundary']['preview_only_filters'] ?? [],
    'decodeparms_generation_exact' => true,
    'stale_generation_zero_ignored' => true,
    'black_is_1' => $decodeParms['black_is_1'] ?? null,
    'encoded_byte_align' => $decodeParms['encoded_byte_align'] ?? null,
    'end_of_line' => $decodeParms['end_of_line'] ?? null,
    'end_of_block' => $decodeParms['end_of_block'] ?? null,
    'coding_mode' => $coding['coding_mode'] ?? null,
    'end_of_block_marker' => array_key_exists('end_of_block_marker', $coding) ? $coding['end_of_block_marker'] : null,
    'imagemask_black_is_visible' => $polarity['black_sample_is_visible'] ?? null,
    'native_raster_decode' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-decodeparms-generation-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n";
echo "<p>CCITT Fax image metadata reviewed with generation-exact DecodeParms before import.</p>\n";
echo "<!-- /wp:paragraph -->\n";
