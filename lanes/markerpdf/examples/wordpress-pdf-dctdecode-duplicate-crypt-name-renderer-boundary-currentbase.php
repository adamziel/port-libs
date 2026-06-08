<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$segment = static fn (int $marker, string $payload): string => "\xff" . chr($marker) . pack('n', strlen($payload) + 2) . $payload;
$sofPayload = "\x08" . pack('n', 1) . pack('n', 1) . "\x03"
    . "\x01\x11\x00"
    . "\x02\x11\x00"
    . "\x03\x11\x00";
$sosPayload = "\x03"
    . "\x01\x00"
    . "\x02\x11"
    . "\x03\x11"
    . "\x00\x3f\x00";
$jpeg = "\xff\xd8"
    . $segment(0xc0, $sofPayload)
    . $segment(0xda, $sosPayload)
    . "WordPress duplicate Crypt Name DCT scan bytes before stuffed ff \xff\x00 and restart \xff\xd0"
    . "\xff\xd9";

$objects = [
    30 => '[ /ICCBased 31 0 R ]',
    31 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
];
$imageForDecodeParms = static function (string $cryptDecodeParms) use ($jpeg): string {
    $dictionary = '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace 30 0 R /BitsPerComponent 8 /Filter [/Crypt /DCTDecode]'
        . ' /DecodeParms [' . $cryptDecodeParms . ' null]'
        . ' /Length ' . strlen($jpeg) . ' >>';

    return $dictionary . "\nstream\n{$jpeg}\nendstream";
};

$renderer = new PdfImageRenderer();
$identityPreview = $renderer->iccBasedImageStreamPreviewRows($imageForDecodeParms('<< /Name /Identity >>'), $objects);
$identityStream = $identityPreview['image_stream'] ?? [];
if (
    ($identityStream['unsupported_filters'] ?? null) !== ['DCTDecode']
    || ($identityStream['native_prefix_decoded'] ?? null) !== true
    || ($identityStream['stopped_before_filter'] ?? null) !== 'DCTDecode'
    || ($identityStream['decode_failed'] ?? null) !== false
) {
    throw new RuntimeException('Explicit Identity Crypt DCTDecode renderer boundary did not remain supported.');
}

$duplicatePreviews = [];
foreach ([
    'identity_then_private' => '<< /Name /Identity /Name /PrivateCF >>',
    'private_then_identity' => '<< /Name /PrivateCF /Name /Identity >>',
    'duplicate_identity' => '<< /Name /Identity /Name /Identity >>',
] as $name => $decodeParms) {
    $preview = $renderer->iccBasedImageStreamPreviewRows($imageForDecodeParms($decodeParms), $objects);
    $stream = $preview['image_stream'] ?? [];
    if (
        ($stream['unsupported_filters'] ?? null) !== ['Crypt', 'DCTDecode']
        || ($stream['decode_failed'] ?? null) !== true
        || array_key_exists('native_prefix_decoded', $stream)
    ) {
        throw new RuntimeException("Duplicate Crypt Name DCTDecode renderer boundary did not fail closed for {$name}.");
    }

    $duplicatePreviews[$name] = [
        'filters' => $stream['filters'] ?? [],
        'unsupported_filters' => $stream['unsupported_filters'] ?? [],
        'decode_failed' => $stream['decode_failed'] ?? null,
        'native_prefix_decoded' => $stream['native_prefix_decoded'] ?? false,
        'review_only_image_stream' => $preview['review_only_image_stream'] ?? null,
    ];
}

echo '<!-- markerpdf:pdf-dctdecode-duplicate-crypt-name-renderer-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-duplicate-crypt-name-renderer-boundary',
    'upstream_boundary' => 'marker.pdf.images.render_image review-only DCTDecode handoff',
    'identity_supported' => true,
    'duplicate_crypt_name_cases' => $duplicatePreviews,
    'duplicate_crypt_names_fail_closed' => true,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n";
echo "<p>DCTDecode image review rejects duplicate Crypt Name decode parameters without native raster execution.</p>\n";
echo "<!-- /wp:paragraph -->\n";
