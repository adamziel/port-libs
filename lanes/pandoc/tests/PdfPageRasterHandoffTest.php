<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PandocMediaExtractor;

function pandoc_page_raster_handoff_image_object(): string
{
    $jpeg = base64_decode(
        '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////'
        . '2wBDAf//////////////////////////////////////////////////////////////////////////////////////'
        . 'wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBAB'
        . 'AAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/a'
        . 'AAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIA'
        . 'AwAAABAf/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPxB//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPxB//8QAFBABAAAAAAAA'
        . 'AAAAAAAAAAAAAP/aAAgBAQABPxB//9k=',
        true
    );
    if (!is_string($jpeg)) {
        throw new RuntimeException('Unable to build whole-page raster handoff image fixture.');
    }

    return '<< /Type /XObject /Subtype /Image /Width 100 /Height 100'
        . ' /ColorSpace /DeviceRGB /BitsPerComponent 8'
        . ' /Filter /DCTDecode /Length ' . strlen($jpeg) . ">>\nstream\n"
        . $jpeg . "\nendstream";
}

function pandoc_page_raster_handoff_pdf(int $pageTwoRotation = 0): string
{
    $pageOne = 'BT /F1 12 Tf 72 720 Td (First physical page) Tj ET';
    $pageTwo = "q 612 0 0 792 0 0 cm /Scan Do Q\n"
        . "0 0 612 792 re f\n"
        . 'BT /F1 12 Tf 3 Tr 72 720 Td (HIDDEN-PAGE-TWO-OCR) Tj ET';
    $pageThree = 'BT /F1 12 Tf 72 720 Td (Third physical page) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R 5 0 R 7 0 R] /Count 3 /MediaBox [0 0 612 792] '
            . '/Resources << /Font << /F1 9 0 R >> /XObject << /Scan 10 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>',
        4 => '<< /Length ' . strlen($pageOne) . ">>\nstream\n{$pageOne}\nendstream",
        5 => '<< /Type /Page /Parent 2 0 R /Contents 6 0 R'
            . ($pageTwoRotation === 0 ? '' : ' /Rotate ' . $pageTwoRotation) . ' >>',
        6 => '<< /Length ' . strlen($pageTwo) . ">>\nstream\n{$pageTwo}\nendstream",
        7 => '<< /Type /Page /Parent 2 0 R /Contents 8 0 R >>',
        8 => '<< /Length ' . strlen($pageThree) . ">>\nstream\n{$pageThree}\nendstream",
        9 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        10 => pandoc_page_raster_handoff_image_object(),
    ];
    $pdf = "%PDF-1.4\n";
    foreach ($objects as $number => $body) {
        $pdf .= $number . " 0 obj\n{$body}\nendobj\n";
    }

    return $pdf . "%%EOF\n";
}

function pandoc_page_raster_handoff_two_missing_pdf(): string
{
    $pageOne = 'BT /F1 12 Tf 72 720 Td (First boundary page) Tj ET';
    $pageTwo = "q 612 0 0 792 0 0 cm /Scan Do Q\n"
        . "0 0 612 792 re f\n"
        . 'BT /F1 12 Tf 3 Tr 72 720 Td (HIDDEN-PAGE-TWO) Tj ET';
    $pageThree = "q 612 0 0 792 0 0 cm /Scan Do Q\n"
        . "0 0 612 792 re f\n"
        . 'BT /F1 12 Tf 3 Tr 72 720 Td (HIDDEN-PAGE-THREE) Tj ET';
    $pageFour = 'BT /F1 12 Tf 72 720 Td (Fourth boundary page) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R 5 0 R 7 0 R 9 0 R] /Count 4'
            . ' /MediaBox [0 0 612 792]'
            . ' /Resources << /Font << /F1 11 0 R >> /XObject << /Scan 12 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>',
        4 => '<< /Length ' . strlen($pageOne) . ">>\nstream\n{$pageOne}\nendstream",
        5 => '<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>',
        6 => '<< /Length ' . strlen($pageTwo) . ">>\nstream\n{$pageTwo}\nendstream",
        7 => '<< /Type /Page /Parent 2 0 R /Contents 8 0 R >>',
        8 => '<< /Length ' . strlen($pageThree) . ">>\nstream\n{$pageThree}\nendstream",
        9 => '<< /Type /Page /Parent 2 0 R /Contents 10 0 R >>',
        10 => '<< /Length ' . strlen($pageFour) . ">>\nstream\n{$pageFour}\nendstream",
        11 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        12 => pandoc_page_raster_handoff_image_object(),
    ];
    $pdf = "%PDF-1.4\n";
    foreach ($objects as $number => $body) {
        $pdf .= $number . " 0 obj\n{$body}\nendobj\n";
    }

    return $pdf . "%%EOF\n";
}

function pandoc_page_raster_handoff_png(int $width, int $height): string
{
    $chunk = static function (string $type, string $data): string {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    };
    $scanline = "\x00" . str_repeat("\x00", $width * 3);

    return "\x89PNG\r\n\x1a\n"
        . $chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
        . $chunk('IDAT', gzcompress(str_repeat($scanline, $height)))
        . $chunk('IEND', '');
}

/** @param array<string,mixed> $request */
function pandoc_page_raster_handoff_response(array $request, string $contents): array
{
    $sha256 = hash('sha256', $contents);
    $byteLength = strlen($contents);
    $proofDigest = hash('sha256', implode("\n", [
        'pdf-page-raster-proof-v1',
        'requestDigest=' . (string) $request['requestDigest'],
        'byteLength=' . $byteLength,
        'sha256=' . $sha256,
    ]));

    return [
        'version' => 1,
        'method' => $request['method'],
        'requestId' => $request['id'],
        'sourceSha256' => $request['sourceSha256'],
        'page' => $request['page'],
        'pageObject' => $request['pageObject'],
        'pageBox' => $request['pageBox'],
        'pageBoxSource' => $request['pageBoxSource'],
        'pageRotation' => $request['pageRotation'],
        'width' => $request['width'],
        'height' => $request['height'],
        'mimeType' => 'image/png',
        'byteLength' => $byteLength,
        'sha256' => $sha256,
        'requestDigest' => $request['requestDigest'],
        'proofDigest' => $proofDigest,
        'contents' => $contents,
    ];
}

/** @param array<string,mixed> $request */
function pandoc_page_raster_handoff_fallback(
    array $request,
    string $error = 'The PDF page renderer is unavailable.'
): array {
    return [
        'requestId' => $request['id'],
        'error' => $error,
    ];
}

return [
    'binds one validated whole-page raster to its exact physical PDF page boundary' => static function (TestRunner $t): void {
        $pdf = pandoc_page_raster_handoff_pdf();
        $document = PandocConverter::read($pdf, 'pdf', ['pdfCollectImagePlacements' => true]);
        $meta = $document->attr('meta', []);
        $requests = $meta['pdfPageRasterRequests'] ?? [];

        $t->same([2], $meta['pdfPagesNeedingImageRepresentation'] ?? null);
        $t->same(1, count($requests));
        $t->same('pdfjs-whole-page-raster', $requests[0]['method'] ?? null);
        $t->same(hash('sha256', $pdf), $requests[0]['sourceSha256'] ?? null);
        $t->same(2, $requests[0]['page'] ?? null);
        $t->same(5, $requests[0]['pageObject'] ?? null);
        $t->same([0.0, 0.0, 612.0, 792.0], $requests[0]['pageBox'] ?? null);
        $t->same('MediaBox', $requests[0]['pageBoxSource'] ?? null);
        $t->same(0, $requests[0]['pageRotation'] ?? null);
        $t->same(1224, $requests[0]['width'] ?? null);
        $t->same(1584, $requests[0]['height'] ?? null);
        $t->true(preg_match('/^[a-f0-9]{64}$/D', (string) ($requests[0]['requestDigest'] ?? '')) === 1);
        $rotatedDocument = PandocConverter::read(
            pandoc_page_raster_handoff_pdf(90),
            'pdf',
            ['pdfCollectImagePlacements' => true]
        );
        $rotatedRequest = ($rotatedDocument->attr('meta', [])['pdfPageRasterRequests'] ?? [])[0] ?? [];
        $t->same(90, $rotatedRequest['pageRotation'] ?? null);
        $t->same(1584, $rotatedRequest['width'] ?? null);
        $t->same(1224, $rotatedRequest['height'] ?? null);

        $png = pandoc_page_raster_handoff_png($requests[0]['width'], $requests[0]['height']);
        $response = pandoc_page_raster_handoff_response($requests[0], $png);
        $result = PandocConverter::convertWithMedia($pdf, 'pdf', 'wordpress', [
            'extractMedia' => [
                'destination' => 'media',
                'imageMode' => 'important',
                'pdfPageRasters' => [$response],
            ],
        ]);
        $first = strpos($result['output'], 'First physical page');
        $raster = strpos($result['output'], 'data-pandoc-pdf-page-raster="browser-pdfjs"');
        $third = strpos($result['output'], 'Third physical page');

        $t->same(1, count($result['media']));
        $t->true($first !== false && $raster !== false && $third !== false);
        $t->true($first < $raster && $raster < $third, 'The page raster must occupy page two, not a trailing gallery slot.');
        $t->same(1, substr_count($result['output'], '<img'));
        $t->true(!str_contains($result['output'], 'image-10.jpg'), 'The constituent Image XObject must be suppressed.');
        $t->true(!str_contains($result['output'], 'HIDDEN-PAGE-TWO-OCR'));
        $t->true(in_array('extract-media-pdf-page-raster-validated:page-2', $result['diagnostics'], true));
        $t->true(in_array('extract-media-pdf-page-raster-loaded:page-2', $result['diagnostics'], true));
        $t->true(array_filter(
            $result['diagnostics'],
            static fn (string $diagnostic): bool => str_ends_with(
                $diagnostic,
                ':intentional_omission:whole-page-raster-replacement'
            )
        ) !== []);
        $t->same(true, $result['sourceIntegrity']['complete'] ?? null);
        $t->same([1, 2, 3], $result['sourceIntegrity']['pdfRepresentedPageNumbers'] ?? null);
        $t->same(true, $result['sourceIntegrity']['pdfPageRepresentationComplete'] ?? null);
        $t->same('visible_text', $result['sourceIntegrity']['pdfTextLayerStatus'] ?? null);
        $t->same(true, $result['sourceIntegrity']['pdfNeedsOcr'] ?? null);

        $extracted = (new PandocMediaExtractor())->extract(
            $document,
            $pdf,
            'pdf',
            [
                'destination' => 'media',
                'imageMode' => 'important',
                'pdfPageRasters' => [$response],
            ]
        );
        $extractedMeta = $extracted['document']->attr('meta', []);
        $t->same([2], $extractedMeta['pdfPageRasterRepresentedPageNumbers'] ?? null);
        $t->same([2], $extractedMeta['pdfPageRasterSuppressedVisualPageNumbers'] ?? null);
        $t->same($response['proofDigest'], $extractedMeta['pdfPageRasterProofs'][0]['proofDigest'] ?? null);
        $t->true(!array_key_exists('contents', $extractedMeta['pdfPageRasterProofs'][0] ?? []));
    },
    'places renderer unavailable fallback at its physical page and attaches the original once' => static function (TestRunner $t): void {
        $pdf = pandoc_page_raster_handoff_pdf();
        $document = PandocConverter::read($pdf, 'pdf', ['pdfCollectImagePlacements' => true]);
        $request = ($document->attr('meta', [])['pdfPageRasterRequests'] ?? [])[0] ?? [];
        $fallback = pandoc_page_raster_handoff_fallback($request);
        $result = PandocConverter::convertWithMedia($pdf, 'pdf', 'wordpress', [
            'extractMedia' => [
                'destination' => 'media',
                'imageMode' => 'important',
                'pdfPageRasterFallbacks' => [$fallback],
            ],
        ]);
        $first = strpos($result['output'], 'First physical page');
        $notice = strpos($result['output'], 'PDF page 2 image is unavailable.');
        $third = strpos($result['output'], 'Third physical page');

        $t->true($first !== false && $notice !== false && $third !== false);
        $t->true($first < $notice && $notice < $third);
        $t->same(1, substr_count($result['output'], 'data-pandoc-pdf-page-raster-fallback="renderer-unavailable"'));
        $t->same(1, substr_count($result['output'], 'data-pandoc-pdf-page-raster-fallback-download="true"'));
        $t->same(1, count($result['media']));
        $t->same('application/pdf', $result['media'][0]['mimeType'] ?? null);
        $t->same(sha1($pdf), $result['media'][0]['sha1'] ?? null);
        $t->same(strlen($pdf), $result['media'][0]['byteLength'] ?? null);
        $t->true(str_ends_with((string) ($result['media'][0]['path'] ?? ''), '.pdf'));
        $t->true(str_contains($result['output'], (string) ($result['media'][0]['path'] ?? '')));
        $t->true(in_array(
            'extract-media-pdf-page-raster-fallback-validated:page-2',
            $result['diagnostics'],
            true
        ));
        $t->true(in_array(
            'extract-media-pdf-page-raster-fallback-inserted:page-2',
            $result['diagnostics'],
            true
        ));
        $t->same(1, count(array_filter(
            $result['diagnostics'],
            static fn (string $diagnostic): bool => $diagnostic
                === 'extract-media-pdf-page-raster-fallback-original-attached'
        )));
        $t->same(false, $result['sourceIntegrity']['complete'] ?? null);
        $t->same([1, 3], $result['sourceIntegrity']['pdfRepresentedPageNumbers'] ?? null);
        $t->same(false, $result['sourceIntegrity']['pdfPageRepresentationComplete'] ?? null);
        $t->same('visible_text', $result['sourceIntegrity']['pdfTextLayerStatus'] ?? null);
        $t->same(true, $result['sourceIntegrity']['pdfNeedsOcr'] ?? null);

        $extracted = (new PandocMediaExtractor())->extract($document, $pdf, 'pdf', [
            'destination' => 'media',
            'imageMode' => 'important',
            'pdfPageRasterFallbacks' => [$fallback],
        ]);
        $meta = $extracted['document']->attr('meta', []);
        $t->same(1, count($extracted['entries']));
        $t->same($pdf, $extracted['entries'][0]['contents'] ?? null);
        $t->same([2], $meta['pdfPageRasterFallbackPageNumbers'] ?? null);
        $t->same(1, $meta['pdfPageRasterFallbackCount'] ?? null);
        $t->same(false, $meta['pdfPageRasterFallbackRepresentationGranted'] ?? null);
        $t->same(true, $meta['pdfPageRasterFallbackOriginalAttached'] ?? null);
        $t->same($request['id'], $meta['pdfPageRasterFallbacks'][0]['requestId'] ?? null);
        $t->same(2, $meta['pdfPageRasterFallbacks'][0]['page'] ?? null);
        $t->same($fallback['error'], $meta['pdfPageRasterFallbacks'][0]['error'] ?? null);
        $t->same($request['requestDigest'], $meta['pdfPageRasterFallbacks'][0]['requestDigest'] ?? null);
        $t->true(!array_key_exists('proofDigest', $meta['pdfPageRasterFallbacks'][0] ?? []));
        $t->same([1, 3], $meta['pdfRepresentedPageNumbers'] ?? null);
        $t->same(false, $meta['pdfPageRepresentationComplete'] ?? null);
        $t->true(array_filter(
            $meta['pdfMediaOccurrenceDispositions'] ?? [],
            static fn (mixed $disposition): bool => is_array($disposition)
                && ($disposition['page'] ?? null) === 2
                && ($disposition['reason'] ?? null) !== 'whole-page-raster-replacement'
        ) !== []);
    },
    'keeps successful and unavailable page siblings in exact order with one original download' => static function (TestRunner $t): void {
        $pdf = pandoc_page_raster_handoff_two_missing_pdf();
        $document = PandocConverter::read($pdf, 'pdf', ['pdfCollectImagePlacements' => true]);
        $requests = $document->attr('meta', [])['pdfPageRasterRequests'] ?? [];
        $requestsByPage = [];
        foreach ($requests as $request) {
            $requestsByPage[$request['page']] = $request;
        }
        $t->same([2, 3], array_keys($requestsByPage));
        $fallback = pandoc_page_raster_handoff_fallback($requestsByPage[2]);
        $png = pandoc_page_raster_handoff_png(
            (int) $requestsByPage[3]['width'],
            (int) $requestsByPage[3]['height']
        );
        $response = pandoc_page_raster_handoff_response($requestsByPage[3], $png);
        $options = [
            'destination' => 'media',
            'imageMode' => 'important',
            'pdfPageRasters' => [$response],
            'pdfPageRasterFallbacks' => [$fallback],
        ];
        $result = PandocConverter::convertWithMedia($pdf, 'pdf', 'wordpress', [
            'extractMedia' => $options,
        ]);
        $first = strpos($result['output'], 'First boundary page');
        $fallbackPosition = strpos($result['output'], 'PDF page 2 image is unavailable.');
        $rasterPosition = strpos($result['output'], 'data-pandoc-pdf-page-raster="browser-pdfjs"');
        $fourth = strpos($result['output'], 'Fourth boundary page');

        $t->true(
            $first !== false
                && $fallbackPosition !== false
                && $rasterPosition !== false
                && $fourth !== false
        );
        $t->true($first < $fallbackPosition);
        $t->true($fallbackPosition < $rasterPosition);
        $t->true($rasterPosition < $fourth);
        $t->same(1, substr_count($result['output'], 'data-pandoc-pdf-page-raster-fallback-download="true"'));
        $t->same(1, substr_count($result['output'], 'data-pandoc-pdf-page-raster="browser-pdfjs"'));
        $t->same(2, count($result['media']));
        $t->same(1, count(array_filter(
            $result['media'],
            static fn (array $entry): bool => ($entry['mimeType'] ?? null) === 'application/pdf'
        )));
        $t->same(1, count(array_filter(
            $result['media'],
            static fn (array $entry): bool => ($entry['mimeType'] ?? null) === 'image/png'
        )));
        $t->same([1, 3, 4], $result['sourceIntegrity']['pdfRepresentedPageNumbers'] ?? null);
        $t->same(false, $result['sourceIntegrity']['pdfPageRepresentationComplete'] ?? null);
        $t->same(false, $result['sourceIntegrity']['complete'] ?? null);
        $t->true(array_filter(
            $result['diagnostics'],
            static fn (string $diagnostic): bool => str_contains($diagnostic, ':page-3:')
                && str_ends_with($diagnostic, ':intentional_omission:whole-page-raster-replacement')
        ) !== []);
        $t->same([], array_values(array_filter(
            $result['diagnostics'],
            static fn (string $diagnostic): bool => str_contains($diagnostic, ':page-2:')
                && str_ends_with($diagnostic, ':intentional_omission:whole-page-raster-replacement')
        )));

        $extracted = (new PandocMediaExtractor())->extract($document, $pdf, 'pdf', $options);
        $meta = $extracted['document']->attr('meta', []);
        $t->same([2], $meta['pdfPageRasterFallbackPageNumbers'] ?? null);
        $t->same([3], $meta['pdfPageRasterRepresentedPageNumbers'] ?? null);
        $t->same([3], $meta['pdfPageRasterSuppressedVisualPageNumbers'] ?? null);
        $t->same([1, 3, 4], $meta['pdfRepresentedPageNumbers'] ?? null);
        $t->same(false, $meta['pdfPageRepresentationComplete'] ?? null);
        $t->same(1, count(array_filter(
            $extracted['entries'],
            static fn (array $entry): bool => ($entry['mimeType'] ?? null) === 'application/pdf'
        )));
        $t->true(array_filter(
            $meta['pdfMediaOccurrenceDispositions'] ?? [],
            static fn (mixed $disposition): bool => is_array($disposition)
                && ($disposition['page'] ?? null) === 2
                && ($disposition['reason'] ?? null) !== 'whole-page-raster-replacement'
        ) !== []);
        $t->true(array_filter(
            $meta['pdfMediaOccurrenceDispositions'] ?? [],
            static fn (mixed $disposition): bool => is_array($disposition)
                && ($disposition['page'] ?? null) === 3
                && ($disposition['reason'] ?? null) === 'whole-page-raster-replacement'
        ) !== []);

        $bothFallbacks = PandocConverter::convertWithMedia($pdf, 'pdf', 'wordpress', [
            'extractMedia' => [
                'destination' => 'media',
                'imageMode' => 'important',
                'pdfPageRasterFallbacks' => [
                    $fallback,
                    pandoc_page_raster_handoff_fallback($requestsByPage[3]),
                ],
            ],
        ]);
        $pageTwoNotice = strpos($bothFallbacks['output'], 'PDF page 2 image is unavailable.');
        $pageThreeNotice = strpos($bothFallbacks['output'], 'PDF page 3 image is unavailable.');
        $bothFourth = strpos($bothFallbacks['output'], 'Fourth boundary page');
        $t->true($pageTwoNotice !== false && $pageThreeNotice !== false && $bothFourth !== false);
        $t->true($pageTwoNotice < $pageThreeNotice && $pageThreeNotice < $bothFourth);
        $t->same(2, substr_count(
            $bothFallbacks['output'],
            'data-pandoc-pdf-page-raster-fallback="renderer-unavailable"'
        ));
        $t->same(1, substr_count(
            $bothFallbacks['output'],
            'data-pandoc-pdf-page-raster-fallback-download="true"'
        ));
        $t->same(1, count($bothFallbacks['media']));
        $t->same('application/pdf', $bothFallbacks['media'][0]['mimeType'] ?? null);
        $t->same([1, 4], $bothFallbacks['sourceIntegrity']['pdfRepresentedPageNumbers'] ?? null);
        $t->same(false, $bothFallbacks['sourceIntegrity']['pdfPageRepresentationComplete'] ?? null);
    },
    'rejects tampered duplicate unknown boundaryless and oversized renderer fallbacks' => static function (TestRunner $t): void {
        $pdf = pandoc_page_raster_handoff_pdf();
        $document = PandocConverter::read($pdf, 'pdf', ['pdfCollectImagePlacements' => true]);
        $request = ($document->attr('meta', [])['pdfPageRasterRequests'] ?? [])[0] ?? [];
        $valid = pandoc_page_raster_handoff_fallback($request);
        $cases = [
            'proof-shaped-tamper' => [
                [[...$valid, 'proofDigest' => str_repeat('a', 64)]],
                'extract-media-pdf-page-raster-fallback-rejected:response-shape',
            ],
            'duplicate' => [
                [$valid, $valid],
                'extract-media-pdf-page-raster-fallback-rejected:duplicate',
            ],
            'unknown' => [
                [[...$valid, 'requestId' => 'pdf-page-raster-unknown']],
                'extract-media-pdf-page-raster-fallback-rejected:unknown-request',
            ],
            'empty-error' => [
                [[...$valid, 'error' => '   ']],
                'extract-media-pdf-page-raster-fallback-rejected:error',
            ],
            'oversized-error' => [
                [[...$valid, 'error' => str_repeat('x', 2049)]],
                'extract-media-pdf-page-raster-fallback-rejected:error',
            ],
        ];
        foreach ($cases as $name => [$fallbacks, $diagnostic]) {
            $result = (new PandocMediaExtractor())->extract($document, $pdf, 'pdf', [
                'destination' => 'media',
                'imageMode' => 'important',
                'pdfPageRasterFallbacks' => $fallbacks,
            ]);
            $meta = $result['document']->attr('meta', []);
            $output = PandocConverter::write($result['document'], 'wordpress');
            $t->same([], $result['entries'], $name . ' must not attach the original PDF.');
            $t->true(
                !str_contains($output, 'data-pandoc-pdf-page-raster-fallback'),
                $name . ' must not insert a visible fallback.'
            );
            $t->true(!array_key_exists('pdfPageRasterFallbacks', $meta));
            $t->same([1, 3], $meta['pdfRepresentedPageNumbers'] ?? null);
            $t->same(false, $meta['pdfPageRepresentationComplete'] ?? null);
            $t->true(in_array($diagnostic, $result['diagnostics'], true));
        }

        $attrs = $document->attrs;
        $attrs['meta']['pdfPageRasterRequests'][0]['sourceSha256'] = str_repeat('0', 64);
        $tamperedRequestDocument = new \PortLibs\Pandoc\AstNode(
            $document->type,
            $attrs,
            $document->children
        );
        $tamperedRequest = (new PandocMediaExtractor())->extract(
            $tamperedRequestDocument,
            $pdf,
            'pdf',
            [
                'destination' => 'media',
                'imageMode' => 'important',
                'pdfPageRasterFallbacks' => [$valid],
            ]
        );
        $t->same([], $tamperedRequest['entries']);
        $t->true(in_array(
            'extract-media-pdf-page-raster-fallback-rejected:request-metadata',
            $tamperedRequest['diagnostics'],
            true
        ));

        $attrs = $document->attrs;
        $attrs['meta']['pdfSourceDisposition']['sourceEdgeDigest'] = str_repeat('0', 64);
        $boundarylessDocument = new \PortLibs\Pandoc\AstNode(
            $document->type,
            $attrs,
            $document->children
        );
        $boundaryless = (new PandocMediaExtractor())->extract(
            $boundarylessDocument,
            $pdf,
            'pdf',
            [
                'destination' => 'media',
                'imageMode' => 'important',
                'pdfPageRasterFallbacks' => [$valid],
            ]
        );
        $boundaryMeta = $boundaryless['document']->attr('meta', []);
        $t->same([], $boundaryless['entries']);
        $t->true(!array_key_exists('pdfPageRasterFallbacks', $boundaryMeta));
        $t->same([1, 3], $boundaryMeta['pdfRepresentedPageNumbers'] ?? null);
        $t->same(false, $boundaryMeta['pdfPageRepresentationComplete'] ?? null);
        $t->true(in_array(
            'extract-media-pdf-page-raster-fallback-rejected:page-boundary:page-2',
            $boundaryless['diagnostics'],
            true
        ));

        $oversizedSource = str_repeat('x', 25165825);
        $oversized = (new PandocMediaExtractor())->extract(
            $document,
            $oversizedSource,
            'pdf',
            [
                'destination' => 'media',
                'imageMode' => 'important',
                'pdfPageRasterFallbacks' => [$valid],
            ]
        );
        unset($oversizedSource);
        $t->same([], $oversized['entries']);
        $t->true(in_array(
            'extract-media-pdf-page-raster-fallback-rejected:source-byte-limit',
            $oversized['diagnostics'],
            true
        ));

        $png = pandoc_page_raster_handoff_png((int) $request['width'], (int) $request['height']);
        $success = pandoc_page_raster_handoff_response($request, $png);
        $successWins = (new PandocMediaExtractor())->extract($document, $pdf, 'pdf', [
            'destination' => 'media',
            'imageMode' => 'important',
            'pdfPageRasters' => [$success],
            'pdfPageRasterFallbacks' => [$valid],
        ]);
        $successMeta = $successWins['document']->attr('meta', []);
        $t->same(1, count($successWins['entries']));
        $t->same('image/png', $successWins['entries'][0]['mimeType'] ?? null);
        $t->same([2], $successMeta['pdfPageRasterRepresentedPageNumbers'] ?? null);
        $t->true(!array_key_exists('pdfPageRasterFallbacks', $successMeta));
        $t->true(in_array(
            'extract-media-pdf-page-raster-fallback-rejected:successful-response:page-2',
            $successWins['diagnostics'],
            true
        ));
    },
    'rejects missing duplicate stale tampered and placeholder whole-page rasters fail closed' => static function (TestRunner $t): void {
        $pdf = pandoc_page_raster_handoff_pdf();
        $document = PandocConverter::read($pdf, 'pdf', ['pdfCollectImagePlacements' => true]);
        $request = ($document->attr('meta', [])['pdfPageRasterRequests'] ?? [])[0] ?? [];
        $png = pandoc_page_raster_handoff_png((int) $request['width'], (int) $request['height']);
        $valid = pandoc_page_raster_handoff_response($request, $png);
        $wrongSource = $valid;
        $wrongSource['sourceSha256'] = str_repeat('0', 64);
        $wrongPage = $valid;
        $wrongPage['page'] = 1;
        $wrongBox = $valid;
        $wrongBox['pageBox'][2] = 611.0;
        $wrongHash = $valid;
        $wrongHash['sha256'] = str_repeat('f', 64);
        $wrongDimensions = $valid;
        $wrongDimensions['width']--;
        $wrongRotation = $valid;
        $wrongRotation['pageRotation'] = 90;
        $wrongPageObject = $valid;
        $wrongPageObject['pageObject']++;
        $wrongRequestDigest = $valid;
        $wrongRequestDigest['requestDigest'] = str_repeat('b', 64);
        $wrongByteLength = $valid;
        $wrongByteLength['byteLength']--;
        $wrongProof = $valid;
        $wrongProof['proofDigest'] = str_repeat('a', 64);
        $corruptPng = $png;
        $corruptPng[40] = chr(ord($corruptPng[40]) ^ 1);
        $invalidPng = pandoc_page_raster_handoff_response($request, $corruptPng);
        $wrongPngDimensions = pandoc_page_raster_handoff_response(
            $request,
            pandoc_page_raster_handoff_png((int) $request['width'] - 1, (int) $request['height'])
        );
        $oversize = pandoc_page_raster_handoff_response(
            $request,
            str_repeat('x', 16777217)
        );
        $cases = [
            'missing' => [],
            'duplicate' => [$valid, $valid],
            'stale-source' => [$wrongSource],
            'wrong-page' => [$wrongPage],
            'wrong-box' => [$wrongBox],
            'wrong-hash' => [$wrongHash],
            'wrong-dimensions' => [$wrongDimensions],
            'wrong-rotation' => [$wrongRotation],
            'wrong-page-object' => [$wrongPageObject],
            'wrong-request-digest' => [$wrongRequestDigest],
            'wrong-byte-length' => [$wrongByteLength],
            'invalid-png-crc' => [$invalidPng],
            'wrong-png-dimensions' => [$wrongPngDimensions],
            'wrong-proof' => [$wrongProof],
            'oversize' => [$oversize],
            'placeholder' => [[
                'requestId' => $request['id'],
                'error' => 'The browser renderer was unavailable.',
            ]],
        ];

        foreach ($cases as $name => $responses) {
            $result = PandocConverter::convertWithMedia($pdf, 'pdf', 'wordpress', [
                'extractMedia' => [
                    'destination' => 'media',
                    'imageMode' => 'important',
                    'pdfPageRasters' => $responses,
                ],
            ]);

            $t->same([], $result['media'], $name . ' must not become hosted page media.');
            $t->true(
                !str_contains($result['output'], 'data-pandoc-pdf-page-raster'),
                $name . ' must not insert a page raster block.'
            );
            $t->same(false, $result['sourceIntegrity']['complete'] ?? null, $name . ' must not make integrity complete.');
            $t->same([1, 3], $result['sourceIntegrity']['pdfRepresentedPageNumbers'] ?? null, $name . ' must leave page two absent.');
            $t->same(false, $result['sourceIntegrity']['pdfPageRepresentationComplete'] ?? null, $name . ' must fail page coverage.');
            $t->same('visible_text', $result['sourceIntegrity']['pdfTextLayerStatus'] ?? null, $name . ' must preserve text-layer classification.');
            $t->same(true, $result['sourceIntegrity']['pdfNeedsOcr'] ?? null, $name . ' must preserve the OCR boundary.');
        }
    },
    'recomputes request and live source-edge proofs before page-raster insertion' => static function (TestRunner $t): void {
        $pdf = pandoc_page_raster_handoff_pdf();
        $document = PandocConverter::read($pdf, 'pdf', ['pdfCollectImagePlacements' => true]);
        $request = ($document->attr('meta', [])['pdfPageRasterRequests'] ?? [])[0] ?? [];
        $png = pandoc_page_raster_handoff_png((int) $request['width'], (int) $request['height']);
        $response = pandoc_page_raster_handoff_response($request, $png);

        $attrs = $document->attrs;
        $attrs['meta']['pdfPageRasterRequests'][0]['pageBox'][2] = 611.0;
        $forgedRequest = new \PortLibs\Pandoc\AstNode($document->type, $attrs, $document->children);
        $requestResult = (new PandocMediaExtractor())->extract(
            $forgedRequest,
            $pdf,
            'pdf',
            [
                'destination' => 'media',
                'imageMode' => 'important',
                'pdfPageRasters' => [$response],
            ]
        );
        $requestMeta = $requestResult['document']->attr('meta', []);
        $t->same([], $requestResult['entries']);
        $t->same([1, 3], $requestMeta['pdfRepresentedPageNumbers'] ?? null);
        $t->same(false, $requestMeta['pdfPageRepresentationComplete'] ?? null);
        $t->true(in_array(
            'extract-media-pdf-page-raster-rejected:request-metadata',
            $requestResult['diagnostics'],
            true
        ));

        $attrs = $document->attrs;
        $attrs['meta']['pdfSourceDisposition']['sourceEdgeDigest'] = str_repeat('0', 64);
        $forgedEdges = new \PortLibs\Pandoc\AstNode($document->type, $attrs, $document->children);
        $edgeResult = (new PandocMediaExtractor())->extract(
            $forgedEdges,
            $pdf,
            'pdf',
            [
                'destination' => 'media',
                'imageMode' => 'important',
                'pdfPageRasters' => [$response],
            ]
        );
        $edgeMeta = $edgeResult['document']->attr('meta', []);
        $t->same([], $edgeResult['entries']);
        $t->same([1, 3], $edgeMeta['pdfRepresentedPageNumbers'] ?? null);
        $t->same(false, $edgeMeta['pdfPageRepresentationComplete'] ?? null);
        $t->true(in_array(
            'extract-media-pdf-page-raster-rejected:page-boundary:page-2',
            $edgeResult['diagnostics'],
            true
        ));
    },
];
