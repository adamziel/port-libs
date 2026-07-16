<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = static function (int $page, string $lead, string $tail): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'blocks' => [[
            'bbox' => [72.0, 96.0, 460.0, 112.0],
            'lines' => [[
                'bbox' => [72.0, 96.0, 460.0, 112.0],
                'spans' => [
                    [
                        'text' => $lead,
                        'bbox' => [72.0, 96.0, 206.0, 112.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                    [
                        'text' => 'pdftext cache link',
                        'bbox' => [206.0, 96.0, 330.0, 112.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                        'url' => 'https://example.com/pdftext-cache-boundary',
                    ],
                    [
                        'text' => $tail,
                        'bbox' => [330.0, 96.0, 460.0, 112.0],
                        'font' => ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ],
                ],
            ]],
        ]],
    ];
};

$path = sys_get_temp_dir() . '/markerpdf-pdftext-malformed-envelope-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% malformed pdftext envelope boundary\n%%EOF");

$converter = new SuppliedDocumentConverter();
$settings = new MarkerSettings(['EXTRACT_IMAGES' => false]);
$stalePage = $page(937, 'Stale malformed pdftext wrapper ', ' must not import.');
$malformedScalarRejected = false;
$malformedNullRejected = false;

try {
    try {
        $converter->convert(
            $path,
            [
                'pages' => [937 => $stalePage],
                'pdftext' => 'not a pdftext dictionary_output page list',
                'raw_adapter_payload' => 'malformed explicit pdftext scalar wrapper payload must not cross',
            ],
            ['metadata' => ['languages' => ['English']], 'max_pages' => 1],
            $settings
        );
    } catch (\InvalidArgumentException $exception) {
        $malformedScalarRejected = str_contains($exception->getMessage(), 'pdftext cache envelope');
    }

    try {
        $converter->convert(
            $path,
            [
                'pages' => [937 => $stalePage],
                'pdftext' => null,
                'raw_adapter_payload' => 'malformed explicit pdftext null wrapper payload must not cross',
            ],
            ['metadata' => ['languages' => ['English']], 'max_pages' => 1],
            $settings
        );
    } catch (\InvalidArgumentException $exception) {
        $malformedNullRejected = str_contains($exception->getMessage(), 'pdftext cache envelope');
    }

    $directPage = $page(938, 'Direct page ', ' stays importable.');
    $directPage['pdftext'] = 'non-page pdftext sidecar payload must stay private';
    $direct = $converter->convert(
        $path,
        $directPage,
        ['metadata' => ['languages' => ['English']], 'max_pages' => 1],
        $settings
    );
} finally {
    unlink($path);
}

$text = $direct['text'];
$encoded = json_encode($direct, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$directPageImported = str_contains($text, 'Direct page')
    && str_contains($text, 'stays importable')
    && str_contains($text, '[pdftext cache link](https://example.com/pdftext-cache-boundary)');
$directSidecarExcluded = !str_contains($encoded, 'non-page pdftext sidecar payload must stay private');
$staleWrapperExcluded = !str_contains($text, 'Stale malformed pdftext wrapper')
    && !str_contains($encoded, 'malformed explicit pdftext scalar wrapper payload')
    && !str_contains($encoded, 'malformed explicit pdftext null wrapper payload');

if (!$malformedScalarRejected || !$malformedNullRejected || !$directPageImported || !$directSidecarExcluded || !$staleWrapperExcluded) {
    throw new RuntimeException('Expected malformed explicit pdftext wrappers to fail closed while direct pdftext pages remain importable.');
}

foreach (preg_split('/\R{2,}/', trim($text)) ?: [] as $paragraph) {
    $paragraph = trim($paragraph);
    if ($paragraph === '') {
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false) . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf-pdftext-dictionary-pdftext-malformed-envelope-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-pdftext-malformed-envelope-currentbase',
    'source_truth' => 'markerPDF consumes the ordered page dictionaries returned by pdftext.dictionary_output before WordPress conversion; native caches that name that authoritative page list pdftext must be page-shaped and must not fall back to stale adapter pages when malformed',
    'support_component' => 'pdf-text-dictionary-core-boundary',
    'malformed_pdftext_scalar_rejected' => $malformedScalarRejected,
    'malformed_pdftext_null_rejected' => $malformedNullRejected,
    'stale_adapter_pages_excluded' => $staleWrapperExcluded,
    'direct_page_with_pdftext_sidecar_imported' => $directPageImported,
    'direct_pdftext_sidecar_excluded' => $directSidecarExcluded,
    'safe_span_link_promoted' => str_contains($text, '[pdftext cache link](https://example.com/pdftext-cache-boundary)'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_ocr' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
