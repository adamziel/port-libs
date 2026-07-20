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

$pdfWithContentMembers = static function (array $members): string {
    $references = [];
    $streams = '';
    foreach (array_values($members) as $index => $content) {
        $objectNumber = 4 + $index;
        $references[] = $objectNumber . ' 0 R';
        $streams .= $objectNumber . " 0 obj\n<< /Length " . strlen($content) . ">>\nstream\n"
            . $content . "\nendstream\nendobj\n";
    }

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
        . "/Resources << >> /Contents [" . implode(' ', $references) . "] >>\nendobj\n"
        . $streams
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

    'logical Contents members share page byte and token ceilings' => static function (
        TestRunner $t
    ) use ($pdfWithContentMembers): void {
        // Keep the configured byte ceiling above every structural PDF
        // dictionary while making each decoded member independently fit and
        // their ordered logical program exceed it only after concatenation.
        $memberA = str_pad('BT (Alpha) Tj ET', 100);
        $memberB = str_pad('BT (Beta) Tj ET', 100);
        $pdf = $pdfWithContentMembers([$memberA, $memberB]);

        foreach ([
            [
                'options' => [
                    'pdfMaxTokenizedContentStreamBytes' => 160,
                    'pdfMaxContentTokens' => 4096,
                ],
                'reason' => 'page_contents_combined_byte_limit',
                'limit' => 160,
                'actual' => strlen($memberA) + 1 + strlen($memberB),
            ],
            [
                'options' => [
                    'pdfMaxTokenizedContentStreamBytes' => 4096,
                    'pdfMaxContentTokens' => 5,
                ],
                'reason' => 'page_contents_combined_token_limit',
                'limit' => 5,
                'actual' => 6,
            ],
        ] as $case) {
            $diagnostics = (new PdfTextExtractor($case['options']))->diagnostics($pdf);
            $expectedIssue = [
                'page' => 1,
                'pageObject' => 3,
                'contentReference' => 4,
                'contentObject' => null,
                'reason' => $case['reason'],
                'filters' => [],
                'limit' => $case['limit'],
                'actual' => $case['actual'],
                'recoverable' => true,
            ];

            $t->same(false, $diagnostics['textVisibility']['complete'] ?? null);
            $t->same(1, $diagnostics['textVisibility']['unresolvedRuns'] ?? null);
            $t->same(
                ['page-contents-combined-limit'],
                $diagnostics['textVisibility']['unresolvedReasons'] ?? null
            );
            $t->same([$expectedIssue], $diagnostics['pageExtractionIssues'] ?? null);
            $t->same([$expectedIssue], $diagnostics['resourceLimitIssues'] ?? null);
        }
    },

    'logical Contents tokenization spans a split dictionary before applying the token ceiling' => static function (
        TestRunner $t
    ) use ($pdfWithContent, $pdfWithContentMembers): void {
        $prefix = '/Span << /MCID ';
        $suffix = '7 ' . implode(' ', array_map(
            static fn (int $index): string => '/K' . $index . ' ' . $index,
            range(0, 11)
        )) . ' >> BDC BT (LOGICAL-TEXT) Tj ET EMC';
        $options = [
            'pdfMaxContentTokens' => 20,
            'pdfMaxTokenizedContentStreamBytes' => 4_096,
        ];

        // Alone, the unmatched suffix is tokenized as more than 20 separate
        // operands. Joined to the prefix, the dictionary is one valid token
        // and the complete logical page program remains below the ceiling.
        $suffixOnly = (new PdfTextExtractor($options))->diagnostics($pdfWithContent($suffix));
        $t->same('content_stream_token_limit', $suffixOnly['resourceLimitIssues'][0]['reason'] ?? null);

        $pdf = $pdfWithContentMembers([$prefix, $suffix]);
        $extractor = new PdfTextExtractor($options);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['LOGICAL-TEXT'], $extractor->extractTextLines($pdf));
        $t->same([], $diagnostics['pageExtractionIssues'] ?? null);
        $t->same([], $diagnostics['resourceLimitIssues'] ?? null);
        $t->same(true, $diagnostics['textVisibility']['complete'] ?? null);
        $t->same([], $extractor->extractVisualOccurrences($pdf));
    },

    'Form diagnostics preserve Do-member attribution after a low-cap logical dictionary split' => static function (
        TestRunner $t
    ): void {
        $prefix = '/Span << /MCID ';
        $suffix = '7 ' . implode(' ', array_map(
            static fn (int $index): string => '/K' . $index . ' ' . $index,
            range(0, 11)
        )) . ' >> BDC BT (LOGICAL-FORM-TEXT) Tj ET EMC /Fm';
        $operator = 'Do';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
            . "/Resources << /XObject << /Fm 7 0 R >> >> /Contents [4 0 R 5 0 R 6 0 R] >>\nendobj\n"
            . '4 0 obj' . "\n<< /Length " . strlen($prefix) . ">>\nstream\n"
            . $prefix . "\nendstream\nendobj\n"
            . '5 0 obj' . "\n<< /Length " . strlen($suffix) . ">>\nstream\n"
            . $suffix . "\nendstream\nendobj\n"
            . '6 0 obj' . "\n<< /Length " . strlen($operator) . ">>\nstream\n"
            . $operator . "\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 10 10] >>\nendobj\n"
            . "%%EOF";

        $issuesByTokenLimit = [];
        foreach ([20, 100] as $tokenLimit) {
            $extractor = new PdfTextExtractor([
                'pdfMaxContentTokens' => $tokenLimit,
                'pdfMaxTokenizedContentStreamBytes' => 4_096,
            ]);
            $diagnostics = $extractor->diagnostics($pdf);
            $issuesByTokenLimit[$tokenLimit] = $diagnostics['pageExtractionIssues'] ?? [];

            $t->same(['LOGICAL-FORM-TEXT'], $extractor->extractTextLines($pdf), (string) $tokenLimit);
            $t->same([], array_values(array_filter(
                $issuesByTokenLimit[$tokenLimit],
                static fn (array $issue): bool => ($issue['reason'] ?? null) === 'unresolved_xobject_resource'
            )), (string) $tokenLimit);
        }

        $t->same($issuesByTokenLimit[100], $issuesByTokenLimit[20]);
        $t->same(1, count($issuesByTokenLimit[20]));
        $issue = $issuesByTokenLimit[20][0] ?? [];
        $t->same('unresolved_form_xobject_stream', $issue['reason'] ?? null);
        $t->same(6, $issue['contentReference'] ?? null);
        $t->same(6, $issue['contentObject'] ?? null);
        $t->same('Fm', $issue['xObjectName'] ?? null);
        $t->same(7, $issue['xObjectObject'] ?? null);
        $t->same('Form', $issue['xObjectSubtype'] ?? null);

        $incompletePdf = str_replace(
            '/Contents [4 0 R 5 0 R 6 0 R]',
            '/Contents [4 0 R 5 0 R 6 0 R 99 0 R]',
            $pdf
        );
        $incompleteExtractor = new PdfTextExtractor([
            'pdfMaxContentTokens' => 20,
            'pdfMaxTokenizedContentStreamBytes' => 4_096,
        ]);
        $incompleteIssues = $incompleteExtractor->diagnostics($incompletePdf)['pageExtractionIssues'] ?? [];
        $t->same(['LOGICAL-FORM-TEXT'], $incompleteExtractor->extractTextLines($incompletePdf));
        $t->same([], array_values(array_filter(
            $incompleteIssues,
            static fn (array $candidate): bool => ($candidate['reason'] ?? null)
                === 'unresolved_xobject_resource'
        )));
        $t->same(
            ['unresolved_content_reference', 'unresolved_form_xobject_stream'],
            array_column($incompleteIssues, 'reason')
        );
        $incompleteFormIssue = $incompleteIssues[1] ?? [];
        $t->same(6, $incompleteFormIssue['contentReference'] ?? null);
        $t->same(6, $incompleteFormIssue['contentObject'] ?? null);
        $t->same('Fm', $incompleteFormIssue['xObjectName'] ?? null);
    },

    'ignored XObject diagnostics tokenize split Contents as one bounded program' => static function (
        TestRunner $t
    ): void {
        $prefix = '/Span << /MCID 7 ';
        $suffix = implode(' ', array_map(
            static fn (int $index): string => '/K' . $index . ' ' . $index,
            range(0, 11)
        )) . ' >> BDC BT (LOGICAL-TEXT) Tj ET EMC /Ps';
        $operator = 'Do';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
            . "/Resources << /XObject << /Ps 7 0 R >> >> /Contents [4 0 R 5 0 R 6 0 R] >>\nendobj\n"
            . '4 0 obj' . "\n<< /Length " . strlen($prefix) . ">>\nstream\n"
            . $prefix . "\nendstream\nendobj\n"
            . '5 0 obj' . "\n<< /Length " . strlen($suffix) . ">>\nstream\n"
            . $suffix . "\nendstream\nendobj\n"
            . '6 0 obj' . "\n<< /Length " . strlen($operator) . ">>\nstream\n"
            . $operator . "\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /PS >>\nendobj\n"
            . "%%EOF";

        $factsByTokenLimit = [];
        foreach ([20, 100] as $tokenLimit) {
            $diagnostics = (new PdfTextExtractor([
                'pdfMaxContentTokens' => $tokenLimit,
                'pdfMaxTokenizedContentStreamBytes' => 4_096,
            ]))->diagnostics($pdf);
            $factsByTokenLimit[$tokenLimit] = [
                'ignoredXObjectCount' => $diagnostics['ignoredXObjectCount'] ?? null,
                'ignoredXObjectSubtypes' => $diagnostics['ignoredXObjectSubtypes'] ?? null,
            ];
            $t->same([], $diagnostics['pageExtractionIssues'] ?? null, (string) $tokenLimit);
            $t->same([], $diagnostics['resourceLimitIssues'] ?? null, (string) $tokenLimit);
        }

        $t->same([
            'ignoredXObjectCount' => 1,
            'ignoredXObjectSubtypes' => ['PS'],
        ], $factsByTokenLimit[20]);
        $t->same($factsByTokenLimit[100], $factsByTokenLimit[20]);
    },

    'empty Contents members charge lexical separators to every bounded consumer' => static function (
        TestRunner $t
    ) use ($pdfWithContentMembers): void {
        $options = [
            'pdfMaxTokenizedContentStreamBytes' => 160,
            'pdfMaxContentTokens' => 4_096,
        ];
        $pdf = $pdfWithContentMembers(array_fill(0, 200, ''));
        $extractor = new PdfTextExtractor($options);
        $diagnostics = $extractor->diagnostics($pdf);
        $pageIssues = array_values(array_filter(
            $diagnostics['pageExtractionIssues'],
            static fn (array $issue): bool => ($issue['reason'] ?? null) === 'page_contents_combined_byte_limit'
        ));
        $visualIssues = array_values(array_filter(
            $extractor->extractVisualOccurrences($pdf),
            static fn (array $issue): bool => ($issue['dispositionReason'] ?? null)
                === 'page_contents_combined_byte_limit'
        ));

        $t->same(1, count($pageIssues));
        $t->same(160, $pageIssues[0]['limit'] ?? null);
        $t->same(161, $pageIssues[0]['actual'] ?? null);
        $t->same($pageIssues, $diagnostics['resourceLimitIssues'] ?? null);
        $t->same(false, $diagnostics['textVisibility']['complete'] ?? null);
        $t->same(
            ['page-contents-combined-limit'],
            $diagnostics['textVisibility']['unresolvedReasons'] ?? null
        );
        $t->same(1, count($visualIssues));
        $t->same('inspection-issue', $visualIssues[0]['kind'] ?? null);
        $t->same('page_contents_combined_byte_limit', $visualIssues[0]['resourceLimit']['reason'] ?? null);
        $t->same(160, $visualIssues[0]['resourceLimit']['limit'] ?? null);
        $t->same(161, $visualIssues[0]['resourceLimit']['actual'] ?? null);
    },

    'a malformed Contents member retains sibling source text without certifying its visibility' => static function (
        TestRunner $t
    ): void {
        $malformed = 'BT (UNDECODABLE MEMBER) Tj ET';
        $visible = 'BT (Visible Sibling) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
            . "/Resources << >> /Contents [4 0 R 5 0 R] >>\nendobj\n"
            . '4 0 obj' . "\n<< /Filter /RunLengthDecode /Length " . strlen($malformed) . ">>\n"
            . "stream\n" . $malformed . "\nendstream\nendobj\n"
            . '5 0 obj' . "\n<< /Length " . strlen($visible) . ">>\n"
            . "stream\n" . $visible . "\nendstream\nendobj\n"
            . "%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $decodeIssues = array_values(array_filter(
            $diagnostics['pageExtractionIssues'],
            static fn (array $issue): bool => ($issue['reason'] ?? null) === 'failed_content_decode'
        ));

        $t->same(['Visible Sibling'], $extractor->extractTextLines($pdf));
        $t->same([], $extractor->extractPositionedTextRuns($pdf));
        $t->same(1, count($decodeIssues));
        $t->same(4, $decodeIssues[0]['contentObject'] ?? null);
        $t->same(false, $diagnostics['textVisibility']['complete'] ?? null);
        $t->same(1, $diagnostics['textVisibility']['unresolvedRuns'] ?? null);
        $t->same(
            ['page-contents-decode-unresolved'],
            $diagnostics['textVisibility']['unresolvedReasons'] ?? null
        );
        $t->same(0, $diagnostics['textVisibility']['visibleRuns'] ?? null);
        $t->same(0, $diagnostics['textVisibility']['pages'][0]['visibleOutputRuns'] ?? null);
        $t->same(1, $diagnostics['textVisibility']['pages'][0]['unresolvedRuns'] ?? null);
    },

    'shared Form diagnostic traversal stops at one typed visit limit' => static function (
        TestRunner $t
    ): void {
        $content = str_repeat('/Fm Do ', 4_097);
        $form = "<< /Type /XObject /Subtype /Form /BBox [0 0 1 1] /Length 0 >>\n"
            . "stream\n\nendstream";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
            . "/Resources << /XObject << /Fm 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . ">>\nstream\n"
            . $content . "\nendstream\nendobj\n"
            . "5 0 obj\n{$form}\nendobj\n%%EOF";
        $diagnostics = (new PdfTextExtractor())->diagnostics($pdf);
        $expectedIssue = [
            'page' => 1,
            'pageObject' => 3,
            'contentReference' => 4,
            'contentObject' => 4,
            'xObjectName' => 'Fm',
            'xObjectObject' => 5,
            'xObjectSubtype' => 'Form',
            'reason' => 'form_xobject_diagnostic_visit_limit',
            'limit' => 4_096,
            'actual' => 4_097,
            'filters' => [],
            'recoverable' => true,
        ];

        $t->same([$expectedIssue], $diagnostics['pageExtractionIssues'] ?? null);
        $t->same([$expectedIssue], $diagnostics['resourceLimitIssues'] ?? null);
        $t->same(true, $diagnostics['textVisibility']['complete'] ?? null);
        $t->same(0, $diagnostics['ignoredXObjectCount'] ?? null);
    },

    'default content token ceiling accepts its exact boundary and rejects the next token' => static function (
        TestRunner $t
    ) use ($pdfWithContent): void {
        $ceiling = 393_216;
        $accepted = (new PdfTextExtractor())->diagnostics(
            $pdfWithContent(str_repeat("n\n", $ceiling))
        );
        $acceptedTokenIssues = array_values(array_filter(
            $accepted['resourceLimitIssues'],
            static fn (array $issue): bool => ($issue['reason'] ?? null) === 'content_stream_token_limit'
        ));

        $t->same([], $acceptedTokenIssues, 'The fixed ceiling itself must remain processable.');

        unset($accepted, $acceptedTokenIssues);
        gc_collect_cycles();

        $rejected = (new PdfTextExtractor())->diagnostics(
            $pdfWithContent(str_repeat("n\n", $ceiling + 1))
        );
        $rejectedTokenIssues = array_values(array_filter(
            $rejected['resourceLimitIssues'],
            static fn (array $issue): bool => ($issue['reason'] ?? null) === 'content_stream_token_limit'
        ));
        $issue = $rejectedTokenIssues[0] ?? [];

        $t->same(1, count($rejectedTokenIssues));
        $t->same($ceiling, $issue['limit'] ?? null);
        $t->same($ceiling + 1, $issue['actual'] ?? null);
        $t->same(true, $issue['recoverable'] ?? null);
        $t->same(0, $rejected['failedStreams']);
        $t->contains('recoverable parser resource limit', implode("\n", $rejected['warnings']));
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
        $t->same(false, $diagnostics['textVisibility']['complete'] ?? null);
        $t->same(
            ['page-contents-decode-limit'],
            $diagnostics['textVisibility']['unresolvedReasons'] ?? null
        );
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
