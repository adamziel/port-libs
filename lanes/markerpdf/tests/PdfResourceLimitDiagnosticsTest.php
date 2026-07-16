<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\NativePdfFactsProvider;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\Pandoc\PdfReader;

$pdfWithContent = static function (string $content): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "%%EOF";
};

$pdfWithFilteredContent = static function (string $encoded, string $filter, string $decodeParms = ''): string {
    $decodeParms = trim($decodeParms);

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Filter {$filter} "
        . ($decodeParms === '' ? '' : "/DecodeParms {$decodeParms} ")
        . '/Length ' . strlen($encoded) . ">>\nstream\n{$encoded}\nendstream\nendobj\n"
        . "%%EOF";
};

$runLengthEncode = static function (string $bytes): string {
    $encoded = '';
    foreach (str_split($bytes, 128) as $chunk) {
        $encoded .= chr(strlen($chunk) - 1) . $chunk;
    }

    return $encoded . chr(128);
};

$lzwEncode = static function (string $bytes, int $earlyChange = 1): string {
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
    $growCodeSize = static function () use (&$codeSize, &$nextCode, $earlyChange): void {
        if ($codeSize < 12 && $nextCode + $earlyChange >= (1 << $codeSize)) {
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
            $growCodeSize();
        }
        $word = $char;
    }
    if ($word !== '') {
        $writeCode($dictionary[$word], $codeSize);
    }
    $writeCode(257, $codeSize);
    if ($bufferBits > 0) {
        $encoded .= chr($buffer << (8 - $bufferBits));
    }

    return $encoded;
};

return [
    'pdf diagnostics identify an oversized tokenized stream as a recoverable typed limit' => static function (
        TestRunner $t
    ) use ($pdfWithContent): void {
        $content = 'BT 72 720 Td (' . str_repeat('This content stream is deliberately larger. ', 8) . ') Tj ET';
        $pdf = $pdfWithContent($content);
        $options = ['pdfMaxTokenizedContentStreamBytes' => 128];
        $diagnostics = (new PdfTextExtractor($options))->diagnostics($pdf);
        $issues = array_values(array_filter(
            $diagnostics['pageExtractionIssues'],
            static fn (array $issue): bool => ($issue['reason'] ?? null) === 'tokenized_content_stream_byte_limit'
        ));

        $t->same(1, count($issues));
        $t->same(128, $issues[0]['limit']);
        $t->same(strlen($content), $issues[0]['actual']);
        $t->same(true, $issues[0]['recoverable']);
        $t->same($issues, $diagnostics['resourceLimitIssues']);
        $t->contains('recoverable parser resource limit', implode("\n", $diagnostics['warnings']));

        $facts = (new NativePdfFactsProvider())->extract($pdf, $options);
        $t->same('tokenized_content_stream_byte_limit', $facts->page(1)?->issues()[0]['reason'] ?? null);
    },

    'pdf diagnostics identify content token exhaustion without treating it as corrupt decoding' => static function (
        TestRunner $t
    ) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Alpha) Tj 0 -18 Td (Beta) Tj 0 -18 Td (Gamma) Tj ET';
        $pdf = $pdfWithContent($content);
        $options = ['pdfMaxContentTokens' => 6, 'pdfMaxTokenizedContentStreamBytes' => 4096];
        $diagnostics = (new PdfTextExtractor($options))->diagnostics($pdf);
        $issue = $diagnostics['resourceLimitIssues'][0] ?? [];

        $t->same('content_stream_token_limit', $issue['reason'] ?? null);
        $t->same(6, $issue['limit'] ?? null);
        $t->true(($issue['actual'] ?? 0) > 6);
        $t->same(0, $diagnostics['failedStreams']);

        $document = (new PdfReader($options))->read($pdf);
        $meta = $document->attr('meta', []);
        $t->same(false, $meta['pdfTextComplete']);
        $t->true(in_array('page-extraction', $meta['pdfLimitReasons'], true));
        $t->same('content_stream_token_limit', $meta['pdfPageExtractionIssues'][0]['reason'] ?? null);
    },

    'durable page facts expose the exact page affected by a positioned run cap' => static function (
        TestRunner $t
    ) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (One) Tj 20 0 Td (Two) Tj 20 0 Td (Three) Tj 20 0 Td (Four) Tj ET';
        $pdf = $pdfWithContent($content);
        $facts = (new NativePdfFactsProvider())->extract($pdf, ['pdfMaxPositionedTextRuns' => 2]);
        $issues = array_values(array_filter(
            $facts->page(1)?->issues() ?? [],
            static fn (array $issue): bool => ($issue['reason'] ?? null) === 'positioned_text_run_limit'
        ));

        $t->same(1, count($issues));
        $t->same(1, $issues[0]['provenance']['page']);
        $t->same(2, $issues[0]['limit']);
        $t->same(true, $issues[0]['recoverable']);
        $t->same(true, $facts->page(1)?->text()['positionedRunsLimited'] ?? false);
    },

    'run length decoding stops incrementally at the configured decoded byte cap' => static function (
        TestRunner $t
    ) use ($pdfWithFilteredContent): void {
        // Nine two-byte repeat instructions would expand to 1,152 bytes. The
        // ninth instruction must be rejected before its 128-byte allocation.
        $encoded = str_repeat(chr(129) . 'A', 9) . chr(128);
        $pdf = $pdfWithFilteredContent($encoded, '/RunLengthDecode');
        $diagnostics = (new PdfTextExtractor(['pdfMaxDecodedStreamBytes' => 1024]))->diagnostics($pdf);
        $issue = $diagnostics['resourceLimitIssues'][0] ?? [];

        $t->same('decoded_stream_byte_limit', $issue['reason'] ?? null);
        $t->same(1024, $issue['limit'] ?? null);
        $t->same(1152, $issue['actual'] ?? null);
        $t->same('RunLengthDecode', $issue['limitFilter'] ?? null);
        $t->same(0, $issue['filterIndex'] ?? null);
        $t->same(true, $issue['recoverable'] ?? null);
        $t->same(0, $diagnostics['failedStreams'], 'A typed resource refusal is not a corrupt-stream failure.');
    },

    'lzw decoding stops incrementally at the configured decoded byte cap' => static function (
        TestRunner $t
    ) use ($pdfWithFilteredContent, $lzwEncode): void {
        $encoded = $lzwEncode(str_repeat('A', 4096));
        $pdf = $pdfWithFilteredContent($encoded, '/LZWDecode');
        $facts = (new NativePdfFactsProvider())->extract($pdf, ['pdfMaxDecodedStreamBytes' => 1024]);
        $issue = $facts->page(1)?->issues()[0] ?? [];

        $t->same('decoded_stream_byte_limit', $issue['reason'] ?? null);
        $t->same(1024, $issue['limit'] ?? null);
        $t->true(($issue['actual'] ?? 0) > 1024);
        $t->same('LZWDecode', $issue['limitFilter'] ?? null);
        $t->same('filter-output', $issue['stage'] ?? null);
        $t->same(true, $issue['recoverable'] ?? null);
    },

    'decoded byte cap applies to an expanding later filter in a multi-filter chain' => static function (
        TestRunner $t
    ) use ($pdfWithFilteredContent): void {
        $runLength = str_repeat(chr(129) . 'B', 9) . chr(128);
        $pdf = $pdfWithFilteredContent(strtoupper(bin2hex($runLength)) . '>', '[/ASCIIHexDecode /RunLengthDecode]');
        $issue = (new PdfTextExtractor(['pdfMaxDecodedStreamBytes' => 1024]))
            ->diagnostics($pdf)['resourceLimitIssues'][0] ?? [];

        $t->same('decoded_stream_byte_limit', $issue['reason'] ?? null);
        $t->same(['ASCIIHexDecode', 'RunLengthDecode'], $issue['filters'] ?? null);
        $t->same('RunLengthDecode', $issue['limitFilter'] ?? null);
        $t->same(1, $issue['filterIndex'] ?? null);
        $t->same(1152, $issue['actual'] ?? null);
    },

    'run length and lzw streams below the cap retain their decoded text' => static function (
        TestRunner $t
    ) use ($pdfWithFilteredContent, $runLengthEncode, $lzwEncode): void {
        foreach ([
            '/RunLengthDecode' => $runLengthEncode('BT (RunLength bounded success) Tj ET'),
            '/LZWDecode' => $lzwEncode('BT (LZW bounded success) Tj ET'),
        ] as $filter => $encoded) {
            $pdf = $pdfWithFilteredContent($encoded, $filter);
            $extractor = new PdfTextExtractor(['pdfMaxDecodedStreamBytes' => 1024]);
            $t->same([], $extractor->diagnostics($pdf)['resourceLimitIssues']);
            $t->contains(str_contains($filter, 'RunLength') ? 'RunLength bounded success' : 'LZW bounded success', implode(' ', $extractor->extractTextRuns($pdf)));
        }
    },
];
