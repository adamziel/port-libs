<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$rawObjectsMethod = new ReflectionMethod(PdfTextExtractor::class, 'rawPdfObjects');
$streamRangesMethod = new ReflectionMethod(PdfTextExtractor::class, 'streamPayloadRanges');

return [
    'ignores binary stream payload object decoys without copying the payload into scanner matches' => static function (TestRunner $t) use ($rawObjectsMethod, $streamRangesMethod): void {
        $payload = "\x00\xff\x80binary\n"
            . "17 4 obj\n(fake object)\nendobj\n"
            . "endstreamish endstream_ endstream9\n"
            . "tail\x00\xfe";
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Length " . strlen($payload) . " >>\r\nstream\r\n"
            . $payload
            . "\r\nendstream\r\nendobj\r\n"
            . "2 0 obj\n(real object)\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $ranges = $streamRangesMethod->invoke($extractor, $pdf);
        $objects = $rawObjectsMethod->invoke($extractor, $pdf);

        $t->same(1, count($ranges));
        $t->same($payload, substr($pdf, $ranges[0]['start'], $ranges[0]['end'] - $ranges[0]['start']));
        $t->same([1, 2], array_column($objects, 'objectNumber'));
        $t->contains($payload, $objects[0]['body']);
        $t->same("\n(real object)\n", $objects[1]['body']);
    },

    'preserves CRLF LF and CR stream payload boundaries' => static function (TestRunner $t) use ($streamRangesMethod): void {
        $fixtures = [
            ["alpha\x00", "\r\n", "\r\n"],
            ["beta\xff", "\n", "\n"],
            ["gamma\x80", "\r", "\r"],
        ];
        $pdf = "%PDF-1.4\n";
        foreach ($fixtures as $index => [$payload, $headerEol, $terminatorEol]) {
            $objectNumber = $index + 1;
            $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($payload) . " >> \tstream"
                . $headerEol
                . $payload
                . $terminatorEol
                . "endstream\nendobj\n";
        }
        $pdf .= '%%EOF';

        $ranges = $streamRangesMethod->invoke(new PdfTextExtractor(), $pdf);

        $t->same(count($fixtures), count($ranges));
        foreach ($ranges as $index => $range) {
            $t->same(
                $fixtures[$index][0],
                substr($pdf, $range['start'], $range['end'] - $range['start'])
            );
        }
    },

    'requires the same trailing word boundary for endstream-like payload bytes' => static function (TestRunner $t) use ($streamRangesMethod): void {
        $payload = "first endstreamish\nsecond endstream_more\nthird endstream7\nfourth";
        $pdf = "1 0 obj\n<<>>stream\n{$payload}\nendstream\nendobj";

        $ranges = $streamRangesMethod->invoke(new PdfTextExtractor(), $pdf);

        $t->same(1, count($ranges));
        $t->same($payload, substr($pdf, $ranges[0]['start'], $ranges[0]['end'] - $ranges[0]['start']));
    },

    'preserves cross-object recovery for a malformed stream followed by a terminator' => static function (TestRunner $t) use ($rawObjectsMethod, $streamRangesMethod): void {
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<<>>stream\nunterminated first payload\n"
            . "2 0 obj\n<<>>stream\nsecond payload\nendstream\nendobj\n"
            . "3 0 obj\n(real tail)\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $ranges = $streamRangesMethod->invoke($extractor, $pdf);
        $objects = $rawObjectsMethod->invoke($extractor, $pdf);

        $t->same(1, count($ranges));
        $t->same([1, 3], array_column($objects, 'objectNumber'));
        $t->contains("2 0 obj\n<<>>stream\nsecond payload", $objects[0]['body']);
        $t->same("\n(real tail)\n", $objects[1]['body']);
    },

    'does not invent a payload range for an unclosed final stream tail' => static function (TestRunner $t) use ($streamRangesMethod): void {
        $pdf = "%PDF-1.4\n1 0 obj\n<<>>stream\n"
            . "binary\x00tail with 20 0 obj and endobj but no stream terminator";

        $ranges = $streamRangesMethod->invoke(new PdfTextExtractor(), $pdf);

        $t->same([], $ranges);
    },

    'scans many stream objects without retaining document-global match captures' => static function (TestRunner $t) use ($rawObjectsMethod): void {
        $count = 2_000;
        $pdf = "%PDF-1.7\n";
        for ($index = 1; $index <= $count; $index++) {
            $decoy = 100_000 + $index;
            $payload = "payload {$index}\x00 {$decoy} 9 obj fake endobj endstreamish";
            $pdf .= "{$index} 0 obj\n<< /Length " . strlen($payload) . " >>\nstream\n"
                . $payload
                . "\nendstream\nendobj\n";
        }
        $pdf .= '%%EOF';

        $objects = $rawObjectsMethod->invoke(new PdfTextExtractor(), $pdf);

        $t->same($count, count($objects));
        $t->same(1, $objects[0]['objectNumber']);
        $t->same($count, $objects[$count - 1]['objectNumber']);
    },

    'keeps stream range scan peak memory independent of a large payload capture' => static function (TestRunner $t) use ($streamRangesMethod): void {
        $streamCount = 128;
        $payload = str_repeat("\x00\xffpayload-block-", 8_192);
        $payloadLength = strlen($payload);
        $pdf = "%PDF-1.7\n";
        for ($objectNumber = 1; $objectNumber <= $streamCount; $objectNumber++) {
            $pdf .= "{$objectNumber} 0 obj\n<< /Length {$payloadLength} >>\nstream\n"
                . $payload
                . "\nendstream\nendobj\n";
        }
        $pdf .= '%%EOF';
        unset($payload);
        gc_collect_cycles();
        memory_reset_peak_usage();
        $memoryBefore = memory_get_usage(false);

        $ranges = $streamRangesMethod->invoke(new PdfTextExtractor(), $pdf);
        $peakGrowth = memory_get_peak_usage(false) - $memoryBefore;

        $t->same($streamCount, count($ranges));
        $t->same($payloadLength, $ranges[0]['end'] - $ranges[0]['start']);
        $t->same($payloadLength, $ranges[$streamCount - 1]['end'] - $ranges[$streamCount - 1]['start']);
        $t->true(
            $peakGrowth < 2 * 1_024 * 1_024,
            'Offset scan allocated ' . $peakGrowth . ' bytes while indexing '
                . ($streamCount * $payloadLength) . ' bytes of stream payloads'
        );
    },
];
