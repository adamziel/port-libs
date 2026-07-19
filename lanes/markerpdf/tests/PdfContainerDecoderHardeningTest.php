<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\NativePdfFactsProvider;
use PortLibs\MarkerPDF\PdfTextExtractor;

/** @param array<int,string> $objects */
$containerPdf = static function (array $objects): string {
    ksort($objects, SORT_NUMERIC);
    $pdf = "%PDF-1.7\n";
    foreach ($objects as $number => $body) {
        $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
    }

    return $pdf . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";
};

$pngSubRow = static function (string $bytes, int $bytesPerPixel = 1): string {
    $encoded = "\x01";
    for ($index = 0, $length = strlen($bytes); $index < $length; $index++) {
        $left = $index < $bytesPerPixel ? 0 : ord($bytes[$index - $bytesPerPixel]);
        $encoded .= chr((ord($bytes[$index]) - $left) & 0xff);
    }

    return $encoded;
};

$lzwEncode = static function (string $bytes, bool $includeEod): string {
    $dictionary = [];
    for ($code = 0; $code < 256; $code++) {
        $dictionary[chr($code)] = $code;
    }
    $encoded = '';
    $buffer = 0;
    $bufferBits = 0;
    $nextCode = 258;
    $codeSize = 9;
    $writeCode = static function (int $code, int $width) use (&$encoded, &$buffer, &$bufferBits): void {
        for ($bit = $width - 1; $bit >= 0; $bit--) {
            $buffer = ($buffer << 1) | (($code >> $bit) & 1);
            $bufferBits++;
            if ($bufferBits === 8) {
                $encoded .= chr($buffer);
                $buffer = 0;
                $bufferBits = 0;
            }
        }
    };
    $grow = static function () use (&$codeSize, &$nextCode): void {
        if ($codeSize < 12 && $nextCode + 1 >= (1 << $codeSize)) {
            $codeSize++;
        }
    };

    $writeCode(256, $codeSize);
    $word = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset++) {
        $char = $bytes[$offset];
        if ($word === '') {
            $word = $char;
            continue;
        }
        $candidate = $word . $char;
        if (isset($dictionary[$candidate])) {
            $word = $candidate;
            continue;
        }
        $writeCode($dictionary[$word], $codeSize);
        if ($nextCode < 4096) {
            $dictionary[$candidate] = $nextCode++;
            $grow();
        }
        $word = $char;
    }
    if ($word !== '') {
        $writeCode($dictionary[$word], $codeSize);
    }
    if ($includeEod) {
        $writeCode(257, $codeSize);
    }
    if ($bufferBits > 0) {
        $encoded .= chr($buffer << (8 - $bufferBits));
    }

    return $encoded;
};

return [
    'decodes valid packed one two and four bit PNG predictor rows' => static function (TestRunner $t) use (
        $containerPdf,
        $pngSubRow
    ): void {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792]'
                . ' /Resources << /Font << /F1 20 0 R >> >> /Contents [4 0 R 6 0 R 8 0 R] >>',
            20 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];
        $expected = [];
        foreach ([1 => 4, 2 => 6, 4 => 8] as $bits => $objectNumber) {
            $label = 'Packed PNG predictor ' . $bits . ' bit';
            $expected[] = $label;
            $content = 'BT /F1 12 Tf 72 ' . (740 - ($bits * 20)) . ' Td (' . $label . ') Tj ET';
            $columns = intdiv(strlen($content) * 8, $bits);
            $compressed = gzcompress($pngSubRow($content));
            if (!is_string($compressed)) {
                throw new RuntimeException('Unable to compress low-bit PNG predictor fixture.');
            }
            $objects[$objectNumber] = '<< /Filter /FlateDecode /DecodeParms << /Predictor 12'
                . ' /Colors 1 /BitsPerComponent ' . $bits . ' /Columns ' . $columns
                . ' >> /Length ' . strlen($compressed) . ">>\nstream\n" . $compressed . "\nendstream";
        }
        $pdf = $containerPdf($objects);
        $extractor = new PdfTextExtractor();

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same([], $extractor->diagnostics($pdf)['pageExtractionIssues']);
        $t->same(0, $extractor->diagnostics($pdf)['failedStreams']);
    },

    'rejects truncated LZW without EOD as a typed incomplete page stream' => static function (TestRunner $t) use (
        $containerPdf,
        $lzwEncode
    ): void {
        $truncatedText = 'BT /F1 12 Tf 72 720 Td (TRUNCATED LZW MUST NOT IMPORT) Tj ET';
        $encoded = $lzwEncode($truncatedText, false);
        $visible = 'BT /F1 12 Tf 72 700 Td (Visible After Truncated LZW) Tj ET';
        $pdf = $containerPdf([
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792]'
                . ' /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R] >>',
            4 => '<< /Filter /LZWDecode /Length ' . strlen($encoded) . ">>\nstream\n" . $encoded . "\nendstream",
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            6 => '<< /Length ' . strlen($visible) . ">>\nstream\n" . $visible . "\nendstream",
        ]);
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $issues = array_values(array_filter(
            $diagnostics['pageExtractionIssues'],
            static fn (array $issue): bool => ($issue['reason'] ?? null) === 'lzw_eod_missing'
        ));

        $t->same(['Visible After Truncated LZW'], $extractor->extractTextLines($pdf));
        $t->same(1, count($issues));
        $t->same(1, $issues[0]['page'] ?? null);
        $t->same(4, $issues[0]['contentObject'] ?? null);
        $t->same('LZWDecode', $issues[0]['decodeFilter'] ?? null);
        $t->same(false, $issues[0]['recoverable'] ?? null);
        $t->same(1, $diagnostics['pagesWithExtractionIssues']);
        $t->true(($diagnostics['failedStreams'] ?? 0) >= 1);

        $factIssues = (new NativePdfFactsProvider())->extract($pdf)->page(1)?->issues() ?? [];
        $t->true(count(array_filter(
            $factIssues,
            static fn (array $issue): bool => ($issue['reason'] ?? null) === 'lzw_eod_missing'
        )) === 1);
    },

    'does not terminate an unfiltered inline image at whitespace EI bytes before its sample floor' => static function (
        TestRunner $t
    ) use ($containerPdf): void {
        $payloadPrefix = 'abc EI BT /F1 12 Tf 72 660 Td (INLINE PAYLOAD TEXT MUST NOT LEAK) Tj ET rawtail';
        $payload = str_pad($payloadPrefix, 96, "\x7f");
        $content = "BT /F1 12 Tf 72 720 Td (Before Embedded EI Bytes) Tj ET\n"
            . "BI /W 96 /H 1 /CS /G /BPC 8 ID\n" . $payload . "\nEI\n"
            . 'BT /F1 12 Tf 72 700 Td (After Embedded EI Bytes) Tj ET';
        $pdf = $containerPdf([
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792]'
                . ' /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            4 => '<< /Length ' . strlen($content) . ">>\nstream\n" . $content . "\nendstream",
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ]);
        $extractor = new PdfTextExtractor();
        $occurrences = array_values(array_filter(
            $extractor->extractVisualOccurrences($pdf),
            static fn (array $item): bool => ($item['kind'] ?? null) === 'inline-image'
        ));

        $t->same(['Before Embedded EI Bytes', 'After Embedded EI Bytes'], $extractor->extractTextLines($pdf));
        $t->same(1, count($occurrences));
        $t->same(true, $occurrences[0]['inlineImage']['complete'] ?? null);
        $t->same(hash('sha256', $payload), $occurrences[0]['inlineImage']['payloadSha256'] ?? null);
        $t->same('pending', $occurrences[0]['disposition'] ?? null);
        $t->same([], array_values(array_filter(
            $extractor->diagnostics($pdf)['pageExtractionIssues'],
            static fn (array $issue): bool => str_starts_with((string) ($issue['reason'] ?? ''), 'inline_image_')
        )));
    },

    'bounds invalid page boxes UserUnit and content transforms with exact page issues' => static function (TestRunner $t) use (
        $containerPdf
    ): void {
        $content = '1000001 0 0 1 0 0 cm BT /F1 12 Tf 72 720 Td (Bounded Geometry Fallback) Tj ET';
        $pdf = $containerPdf([
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 1000001 792] /UserUnit 75001'
                . ' /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            4 => '<< /Length ' . strlen($content) . ">>\nstream\n" . $content . "\nendstream",
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ]);
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $byReason = [];
        foreach ($diagnostics['pageExtractionIssues'] as $issue) {
            $byReason[(string) ($issue['reason'] ?? '')][] = $issue;
        }

        $t->same(3, $byReason['invalid_page_box'][0]['geometryObject'] ?? null);
        $t->same('MediaBox', $byReason['invalid_page_box'][0]['boxName'] ?? null);
        $t->same(3, $byReason['invalid_user_unit'][0]['geometryObject'] ?? null);
        $t->same(4, $byReason['invalid_content_transform'][0]['contentObject'] ?? null);
        $t->same('cm', $byReason['invalid_content_transform'][0]['operator'] ?? null);
        $t->same(1, $diagnostics['pagesWithExtractionIssues']);
        $t->same(true, $extractor->extractPageGeometry($pdf)[0]['bboxInferred'] ?? null);
        $t->contains('Bounded Geometry Fallback', $extractor->extractPlainText($pdf));
    },

    'restores scoped content transforms before validating a later graphics state' => static function (
        TestRunner $t
    ) use ($containerPdf): void {
        $content = 'q 2000 0 0 2000 0 0 cm Q '
            . 'q 2000 0 0 2000 0 0 cm Q '
            . 'BT /F1 12 Tf 72 720 Td (Scoped Transform) Tj ET';
        $pdf = $containerPdf([
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792]'
                . ' /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            4 => '<< /Length ' . strlen($content) . ">>\nstream\n" . $content . "\nendstream",
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ]);
        $issues = (new PdfTextExtractor())->diagnostics($pdf)['pageExtractionIssues'];
        $transformIssues = array_values(array_filter(
            $issues,
            static fn (array $issue): bool => in_array(
                $issue['reason'] ?? null,
                ['invalid_content_transform', 'content_transform_range_exceeded'],
                true
            )
        ));

        $t->same([], $transformIssues);
    },

    'carries graphics state across ordered page Contents stream members' => static function (
        TestRunner $t
    ) use ($containerPdf): void {
        $first = 'q 2000 0 0 2000 0 0 cm ';
        $second = 'Q q 2000 0 0 2000 0 0 cm Q '
            . 'BT /F1 12 Tf 72 720 Td (Cross Stream State) Tj ET';
        $pdf = $containerPdf([
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792]'
                . ' /Resources << /Font << /F1 7 0 R >> >> /Contents [4 0 R 6 0 R] >>',
            4 => '<< /Length ' . strlen($first) . ">>\nstream\n" . $first . "\nendstream",
            6 => '<< /Length ' . strlen($second) . ">>\nstream\n" . $second . "\nendstream",
            7 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ]);
        $issues = (new PdfTextExtractor())->diagnostics($pdf)['pageExtractionIssues'];

        $t->same([], array_values(array_filter(
            $issues,
            static fn (array $issue): bool => str_starts_with(
                (string) ($issue['reason'] ?? ''),
                'graphics_state_'
            )
        )));
    },

    'resets text and diagnostic graphics state at unknown Contents member gaps' => static function (
        TestRunner $t
    ) use ($containerPdf): void {
        $prefix = '1000000 0 0 1 0 0 cm BT /F1 12 Tf 72 720 Td';
        $suffix = '(MUST NOT CROSS UNKNOWN CONTENT) Tj ET 2 0 0 1 0 0 cm '
            . 'BT /F1 12 Tf 72 700 Td (Recovered after unknown content) Tj ET 0 0 20 20 re f';
        $overDecodedByteLimit = '2 0 0 2 0 0 cm';
        $overDecodedByteLimit .= str_repeat(' ', 257 - strlen($overDecodedByteLimit));
        foreach ([
            'failed Flate decode' => [
                'body' => "<< /Filter /FlateDecode /Length 4>>\nstream\nnope\nendstream",
                'reason' => 'failed_content_decode',
            ],
            'unsupported filter' => [
                'body' => "<< /Filter /DCTDecode /Length 4>>\nstream\nnope\nendstream",
                'reason' => 'unsupported_content_filter',
            ],
            'decoded byte refusal' => [
                'body' => '<< /Length ' . strlen($overDecodedByteLimit) . ">>\nstream\n"
                    . $overDecodedByteLimit . "\nendstream",
                'reason' => 'decoded_stream_byte_limit',
                'options' => ['pdfMaxDecodedStreamBytes' => 256],
            ],
        ] as $label => $gap) {
            $pdf = $containerPdf([
                1 => '<< /Type /Catalog /Pages 2 0 R >>',
                2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
                3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792]'
                    . ' /Resources << /Font << /F1 7 0 R >> >> /Contents [4 0 R 5 0 R 6 0 R] >>',
                4 => '<< /Length ' . strlen($prefix) . ">>\nstream\n" . $prefix . "\nendstream",
                5 => $gap['body'],
                6 => '<< /Length ' . strlen($suffix) . ">>\nstream\n" . $suffix . "\nendstream",
                7 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            ]);
            $extractor = new PdfTextExtractor($gap['options'] ?? []);
            $diagnostics = $extractor->diagnostics($pdf);
            $issues = $diagnostics['pageExtractionIssues'] ?? [];
            $streamedText = [];
            $streamedPositioned = [];
            $streamedRectangles = [];
            foreach ($extractor->streamImportFacts($pdf) as $facts) {
                array_push($streamedText, ...array_column($facts['textLineItems'], 'text'));
                array_push($streamedPositioned, ...$facts['positionedTextRuns']);
                array_push($streamedRectangles, ...$facts['filledRectangles']);
            }

            $t->same(['Recovered after unknown content'], $extractor->extractTextLines($pdf), $label);
            $t->same(['Recovered after unknown content'], $streamedText, $label);
            $t->same([], $extractor->extractPositionedTextRuns($pdf), $label);
            $t->same([], $streamedPositioned, $label);
            $t->same([], $extractor->extractFilledRectangles($pdf), $label);
            $t->same([], $streamedRectangles, $label);
            $t->same(1, count(array_filter(
                $issues,
                static fn (array $issue): bool => ($issue['reason'] ?? null) === $gap['reason']
            )), $label);
            $t->same([], array_values(array_filter(
                $issues,
                static fn (array $issue): bool => ($issue['reason'] ?? null)
                    === 'content_transform_range_exceeded'
            )), $label);
            $t->same(0, $diagnostics['textVisibility']['visibleRuns'] ?? null, $label);
            $t->same(false, $diagnostics['textVisibility']['complete'] ?? null, $label);
            $t->same(1, count($diagnostics['textVisibility']['pages'] ?? []), $label);
            $t->same(0, $diagnostics['textVisibility']['pages'][0]['visibleOutputRuns'] ?? null, $label);
            $t->same(1, $diagnostics['textVisibility']['pages'][0]['unresolvedRuns'] ?? null, $label);
            $t->same([], $diagnostics['textVisibility']['laterPaintRisks'] ?? null, $label);
            $t->same([], $extractor->extractImagePlacements($pdf), $label);
            $t->same([], $extractor->extractFormXObjectPlacements($pdf), $label);
            $t->same([], $extractor->extractPageVectorRegions($pdf), $label);
        }
    },

    'reports a graphics state restore still unmatched after page Contents composition' => static function (
        TestRunner $t
    ) use ($containerPdf): void {
        $content = 'Q BT /F1 12 Tf 72 720 Td (Recovered Underflow) Tj ET';
        $pdf = $containerPdf([
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792]'
                . ' /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            4 => '<< /Length ' . strlen($content) . ">>\nstream\n" . $content . "\nendstream",
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ]);
        $issues = (new PdfTextExtractor())->diagnostics($pdf)['pageExtractionIssues'];
        $underflow = array_values(array_filter(
            $issues,
            static fn (array $issue): bool => ($issue['reason'] ?? null) === 'graphics_state_stack_underflow'
        ));

        $t->same(1, count($underflow));
        $t->same(4, $underflow[0]['contentObject'] ?? null);
        $t->same('Q', $underflow[0]['operator'] ?? null);
    },

    'resolves nested multi-stream Contents arrays while preserving repeated execution' => static function (
        TestRunner $t
    ) use ($containerPdf): void {
        $first = 'BT /F1 12 Tf 72 720 Td (First) Tj ET';
        $second = 'BT /F1 12 Tf 72 700 Td (Second) Tj ET';
        $pdf = $containerPdf([
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792]'
                . ' /Resources << /Font << /F1 8 0 R >> >> /Contents 4 0 R >>',
            4 => '[5 0 R 6 0 R]',
            5 => '<< /Length ' . strlen($first) . ">>\nstream\n" . $first . "\nendstream",
            6 => '[7 0 R 5 0 R]',
            7 => '<< /Length ' . strlen($second) . ">>\nstream\n" . $second . "\nendstream",
            8 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ]);
        $extractor = new PdfTextExtractor();
        $issues = $extractor->diagnostics($pdf)['pageExtractionIssues'];

        $t->same(['First', 'Second', 'First'], $extractor->extractTextLines($pdf));
        $t->same([], array_values(array_filter(
            $issues,
            static fn (array $issue): bool => ($issue['reason'] ?? null) === 'unresolved_content_reference'
        )));
    },

    'rejects cyclic dangling and non-array Contents carriers with typed reasons' => static function (
        TestRunner $t
    ) use ($containerPdf): void {
        foreach ([
            4 => ['[4 0 R]', 'content-reference-cycle'],
            5 => ['[99 0 R]', 'content-reference-missing'],
            6 => ['<< /Not /AnArray >>', 'content-carrier-not-array'],
        ] as $carrierObject => [$carrier, $reason]) {
            $pdf = $containerPdf([
                1 => '<< /Type /Catalog /Pages 2 0 R >>',
                2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
                3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792]'
                    . ' /Contents ' . $carrierObject . ' 0 R >>',
                $carrierObject => $carrier,
            ]);
            $issues = (new PdfTextExtractor())->diagnostics($pdf)['pageExtractionIssues'];
            $unresolved = array_values(array_filter(
                $issues,
                static fn (array $issue): bool => ($issue['reason'] ?? null) === 'unresolved_content_reference'
            ));

            $t->same(1, count($unresolved));
            $t->same($reason, $unresolved[0]['resolutionReason'] ?? null);
        }
    },

    'reports cyclic and overdeep Form resources without recursive exhaustion' => static function (TestRunner $t) use (
        $containerPdf
    ): void {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200]'
                . ' /Resources << /XObject << /Cycle 5 0 R /Deep 10 0 R >> >> /Contents 4 0 R >>',
            4 => "<< /Length 20 >>\nstream\n/Cycle Do /Deep Do\nendstream",
            5 => "<< /Type /XObject /Subtype /Form /BBox [0 0 10 10] /Resources << /XObject << /Self 5 0 R >> >> /Length 8 >>\nstream\n/Self Do\nendstream",
        ];
        for ($depth = 0; $depth <= 33; $depth++) {
            $objectNumber = 10 + $depth;
            $next = $objectNumber + 1;
            $stream = $depth === 33 ? '' : '/Next Do';
            $resources = $depth === 33 ? '<< >>' : '<< /XObject << /Next ' . $next . ' 0 R >> >>';
            $objects[$objectNumber] = '<< /Type /XObject /Subtype /Form /BBox [0 0 10 10]'
                . ' /Resources ' . $resources . ' /Length ' . strlen($stream) . ">>\nstream\n"
                . $stream . "\nendstream";
        }
        $pdf = $containerPdf($objects);
        $diagnostics = (new PdfTextExtractor())->diagnostics($pdf);
        $cycle = array_values(array_filter(
            $diagnostics['pageExtractionIssues'],
            static fn (array $issue): bool => ($issue['reason'] ?? null) === 'form_xobject_cycle'
        ));
        $depth = array_values(array_filter(
            $diagnostics['pageExtractionIssues'],
            static fn (array $issue): bool => ($issue['reason'] ?? null) === 'form_xobject_depth_limit'
        ));

        $t->same(1, count($cycle));
        $t->same(5, $cycle[0]['xObjectObject'] ?? null);
        $t->same(1, count($depth));
        $t->same(32, $depth[0]['limit'] ?? null);
        $t->same(true, $depth[0]['recoverable'] ?? null);
        $t->same(1, $diagnostics['pagesWithExtractionIssues']);
    },

    'bounds a direct page Contents array at 4096 stream references' => static function (
        TestRunner $t
    ) use ($containerPdf): void {
        $references = implode(' ', array_fill(0, 4_097, '4 0 R'));
        $pdf = $containerPdf([
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792]'
                . ' /Resources << >> /Contents [' . $references . '] >>',
            4 => "<< /Length 0 >>\nstream\n\nendstream",
        ]);
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $issues = array_values(array_filter(
            $diagnostics['pageExtractionIssues'],
            static fn (array $issue): bool => ($issue['reason'] ?? null) === 'page_contents_stream_count_limit'
        ));

        $t->same([], $extractor->extractTextLines($pdf));
        $t->same(1, count($issues));
        $t->same('content-stream-count-limit', $issues[0]['resolutionReason'] ?? null);
        $t->same(4_096, $issues[0]['limit'] ?? null);
        $t->same(4_097, $issues[0]['actual'] ?? null);
        $t->same(true, $issues[0]['recoverable'] ?? null);
        $t->same(false, $diagnostics['textVisibility']['complete'] ?? null);
        $t->same(
            ['page-contents-resolution-limit'],
            $diagnostics['textVisibility']['unresolvedReasons'] ?? null
        );
    },

    'retains an indirect Contents prefix but refuses its 4097th stream' => static function (
        TestRunner $t
    ) use ($containerPdf): void {
        $missedText = 'BT /F1 12 Tf 72 720 Td (MUST NOT CROSS THE CONTENTS STREAM LIMIT) Tj ET';
        $references = array_fill(0, 4_096, '5 0 R');
        $references[] = '6 0 R';
        $pdf = $containerPdf([
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792]'
                . ' /Resources << /Font << /F1 7 0 R >> >> /Contents 4 0 R >>',
            4 => '[' . implode(' ', $references) . ']',
            5 => "<< /Length 0 >>\nstream\n\nendstream",
            6 => '<< /Length ' . strlen($missedText) . ">>\nstream\n" . $missedText . "\nendstream",
            7 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ]);
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $issues = array_values(array_filter(
            $diagnostics['pageExtractionIssues'],
            static fn (array $issue): bool => ($issue['reason'] ?? null) === 'page_contents_stream_count_limit'
        ));

        $t->same([], $extractor->extractTextLines($pdf));
        $t->same(1, count($issues));
        $t->same(4, $issues[0]['contentReference'] ?? null);
        $t->same('content-stream-count-limit', $issues[0]['resolutionReason'] ?? null);
        $t->same(4_096, $issues[0]['limit'] ?? null);
        $t->same(4_097, $issues[0]['actual'] ?? null);
        $t->same(true, $issues[0]['recoverable'] ?? null);
        $t->same(false, $diagnostics['textVisibility']['complete'] ?? null);
        $t->same(
            ['page-contents-resolution-limit'],
            $diagnostics['textVisibility']['unresolvedReasons'] ?? null
        );
    },

    'carries a Form operand across Contents members for stream diagnostics' => static function (
        TestRunner $t
    ) use ($containerPdf): void {
        $first = '/Fm';
        $second = 'Do';
        $pdf = $containerPdf([
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792]'
                . ' /Resources << /XObject << /Fm 6 0 R >> >> /Contents [4 0 R 5 0 R] >>',
            4 => '<< /Length ' . strlen($first) . ">>\nstream\n" . $first . "\nendstream",
            5 => '<< /Length ' . strlen($second) . ">>\nstream\n" . $second . "\nendstream",
            6 => '<< /Type /XObject /Subtype /Form /BBox [0 0 10 10] >>',
        ]);
        $issues = array_values(array_filter(
            (new PdfTextExtractor())->diagnostics($pdf)['pageExtractionIssues'],
            static fn (array $issue): bool => ($issue['reason'] ?? null) === 'unresolved_form_xobject_stream'
        ));

        $t->same(1, count($issues));
        $t->same(5, $issues[0]['contentReference'] ?? null);
        $t->same(5, $issues[0]['contentObject'] ?? null);
        $t->same('Fm', $issues[0]['xObjectName'] ?? null);
        $t->same(6, $issues[0]['xObjectObject'] ?? null);
        $t->same('Form', $issues[0]['xObjectSubtype'] ?? null);
    },

    'keeps post-gap Form text source-only without carrying a split Do operand' => static function (
        TestRunner $t
    ) use ($containerPdf): void {
        $prefix = '/Fm';
        $suffix = 'Do BT /F1 12 Tf 72 700 Td (Recovered sibling text) Tj ET';
        $form = 'BT /F1 12 Tf 0 0 Td (Uncertain Form source text) Tj ET 0 0 20 20 re f';
        foreach ([
            'failed Flate decode' => "<< /Filter /FlateDecode /Length 4>>\nstream\nnope\nendstream",
            'unsupported filter' => "<< /Filter /DCTDecode /Length 4>>\nstream\nnope\nendstream",
        ] as $label => $gapBody) {
            $pdf = $containerPdf([
                1 => '<< /Type /Catalog /Pages 2 0 R >>',
                2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
                3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792]'
                    . ' /Resources << /Font << /F1 7 0 R >> /XObject << /Fm 8 0 R >> >>'
                    . ' /Contents [4 0 R 5 0 R 6 0 R] >>',
                4 => '<< /Length ' . strlen($prefix) . ">>\nstream\n" . $prefix . "\nendstream",
                5 => $gapBody,
                6 => '<< /Length ' . strlen($suffix) . ">>\nstream\n" . $suffix . "\nendstream",
                7 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
                8 => '<< /Type /XObject /Subtype /Form /BBox [0 0 20 20]'
                    . ' /Resources << /Font << /F1 7 0 R >> >> /Length ' . strlen($form) . ">>\n"
                    . "stream\n" . $form . "\nendstream",
            ]);
            $extractor = new PdfTextExtractor();
            $diagnostics = $extractor->diagnostics($pdf);
            $streamedText = [];
            $streamedRectangles = [];
            foreach ($extractor->streamImportFacts($pdf) as $facts) {
                array_push($streamedText, ...array_column($facts['textLineItems'], 'text'));
                array_push($streamedRectangles, ...$facts['filledRectangles']);
            }

            $t->same(['Recovered sibling text'], $extractor->extractTextLines($pdf), $label);
            $t->same(['Recovered sibling text'], $streamedText, $label);
            $t->same([], $extractor->extractPositionedTextRuns($pdf), $label);
            $t->same([], $extractor->extractFilledRectangles($pdf), $label);
            $t->same([], $streamedRectangles, $label);
            $t->same(0, $diagnostics['textVisibility']['visibleRuns'] ?? null, $label);
            $t->same([], $diagnostics['textVisibility']['laterPaintRisks'] ?? null, $label);

            $selfContainedSuffix = '/Fm Do BT /F1 12 Tf 72 700 Td (Recovered sibling text) Tj ET';
            $selfContainedPdf = $containerPdf([
                1 => '<< /Type /Catalog /Pages 2 0 R >>',
                2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
                3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792]'
                    . ' /Resources << /Font << /F1 7 0 R >> /XObject << /Fm 8 0 R >> >>'
                    . ' /Contents [4 0 R 5 0 R 6 0 R] >>',
                4 => "<< /Length 1>>\nstream\nq\nendstream",
                5 => $gapBody,
                6 => '<< /Length ' . strlen($selfContainedSuffix) . ">>\nstream\n"
                    . $selfContainedSuffix . "\nendstream",
                7 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
                8 => '<< /Type /XObject /Subtype /Form /BBox [0 0 20 20]'
                    . ' /Resources << /Font << /F1 7 0 R >> >> /Length ' . strlen($form) . ">>\n"
                    . "stream\n" . $form . "\nendstream",
            ]);
            $selfContainedExtractor = new PdfTextExtractor();
            $selfContainedDiagnostics = $selfContainedExtractor->diagnostics($selfContainedPdf);
            $t->same(
                ['Uncertain Form source text', 'Recovered sibling text'],
                $selfContainedExtractor->extractTextLines($selfContainedPdf),
                $label
            );
            $t->same([], $selfContainedExtractor->extractPositionedTextRuns($selfContainedPdf), $label);
            $t->same([], $selfContainedExtractor->extractFilledRectangles($selfContainedPdf), $label);
            $t->same(0, $selfContainedDiagnostics['textVisibility']['visibleRuns'] ?? null, $label);
            $t->same([], $selfContainedDiagnostics['textVisibility']['laterPaintRisks'] ?? null, $label);
        }
    },

    'uses only the top-level Contents entry despite lexical decoys' => static function (
        TestRunner $t
    ) use ($containerPdf): void {
        $visible = 'BT /F1 12 Tf 72 720 Td (Top Level Contents) Tj ET';
        $decoy = 'BT /F1 12 Tf 72 700 Td (Decoy Contents) Tj ET';
        $entries = [
            'nested dictionary' => '/PieceInfo << /Contents 6 0 R >> /Contents 4 0 R',
            'literal string' => '/Label (/Contents 6 0 R) /Contents 4 0 R',
            'comment' => "% /Contents 6 0 R\n/Contents 4 0 R",
            'encoded top-level name' => '/PieceInfo << /Contents 6 0 R >> /Cont#65nts 4 0 R',
        ];

        foreach ($entries as $label => $contentsEntry) {
            $pdf = $containerPdf([
                1 => '<< /Type /Catalog /Pages 2 0 R >>',
                2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
                3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792]'
                    . ' /Resources << /Font << /F1 5 0 R >> >> ' . $contentsEntry . ' >>',
                4 => '<< /Length ' . strlen($visible) . ">>\nstream\n" . $visible . "\nendstream",
                5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
                6 => '<< /Length ' . strlen($decoy) . ">>\nstream\n" . $decoy . "\nendstream",
            ]);
            $extractor = new PdfTextExtractor();

            $t->same(['Top Level Contents'], $extractor->extractTextLines($pdf), $label);
            $t->same([], array_values(array_filter(
                $extractor->diagnostics($pdf)['pageExtractionIssues'],
                static fn (array $issue): bool => str_contains(
                    (string) ($issue['reason'] ?? ''),
                    'content_reference'
                )
            )), $label);
        }
    },
];
