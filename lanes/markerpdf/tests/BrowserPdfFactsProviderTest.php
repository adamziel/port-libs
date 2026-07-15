<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BrowserPdfFactsProvider;

$browserFactsPdf = static function (): string {
    $first = 'BT /F1 12 Tf 72 520 Td (Native alpha) Tj ET';
    $second = 'BT /F1 12 Tf 72 520 Td (Native beta) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 5 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 400 600] /Resources << /Font << /F1 7 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($first) . " >>\nstream\n{$first}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 400 600] /Resources << /Font << /F1 7 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($second) . " >>\nstream\n{$second}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$browserHandoff = static function (string $pdf, array $pageNumbers = [1, 2]): array {
    $pages = [];
    foreach ($pageNumbers as $pageNumber) {
        $pages[] = [
            'pageNumber' => $pageNumber,
            'viewport' => [
                'width' => 400.0,
                'height' => 600.0,
                'rotation' => 0,
                'viewBox' => [0.0, 0.0, 400.0, 600.0],
            ],
            'spans' => [[
                'text' => $pageNumber === 1 ? 'Browser alpha' : 'Browser beta',
                'direction' => 'ltr',
                'transform' => [12.0, 0.0, 0.0, 12.0, 72.0, 520.0],
                'width' => 80.0,
                'height' => 12.0,
                'fontName' => 'g_d0_f1',
                'hasEol' => true,
            ]],
            'markedContent' => [['type' => 'beginMarkedContent', 'id' => 'p' . $pageNumber]],
            'styles' => ['g_d0_f1' => ['fontFamily' => 'sans-serif', 'vertical' => false]],
            'structure' => [
                'role' => 'Document',
                'children' => [['role' => 'P', 'id' => 'p' . $pageNumber]],
            ],
        ];
    }

    return [
        'schemaVersion' => 1,
        'provider' => 'pdfjs-v1',
        'sourceSha256' => hash('sha256', $pdf),
        'pageCount' => 2,
        'pages' => $pages,
        'failures' => [],
    ];
};

return [
    'attaches bounded PDF js text and structure facts without replacing native evidence' => static function (
        TestRunner $t
    ) use ($browserFactsPdf, $browserHandoff): void {
        $pdf = $browserFactsPdf();
        $facts = (new BrowserPdfFactsProvider())->extract($pdf, [
            'browserFacts' => $browserHandoff($pdf),
        ]);

        $t->same('native-php-v1+pdfjs-v1', $facts->provider());
        $page = $facts->page(1);
        $t->same(['Native alpha'], array_column($page->text()['lines'], 'text'));
        $t->same('Browser alpha', $page->text()['browser']['spans'][0]['text']);
        $t->same('pdfjs-v1', $page->text()['browser']['spans'][0]['provenance']['provider']);
        $t->same('Document', $page->toArray()['structure']['browser']['tree']['role']);
        $t->same('applied', $facts->diagnostics()['browserFacts']['status']);
        $t->same(2, $facts->diagnostics()['browserFacts']['appliedPages']);
    },
    'keeps native facts and records a graceful fallback when no browser is available' => static function (
        TestRunner $t
    ) use ($browserFactsPdf): void {
        $facts = (new BrowserPdfFactsProvider())->extract($browserFactsPdf());

        $t->same('native-php-v1', $facts->provider());
        $t->same(['Native alpha'], array_column($facts->page(1)->text()['lines'], 'text'));
        $t->same(false, isset($facts->page(1)->text()['browser']));
        $t->same('unavailable', $facts->diagnostics()['browserFacts']['status']);
    },
    'rejects stale browser facts instead of applying them to another source' => static function (
        TestRunner $t
    ) use ($browserFactsPdf, $browserHandoff): void {
        $pdf = $browserFactsPdf();
        $handoff = $browserHandoff($pdf);
        $handoff['sourceSha256'] = str_repeat('0', 64);
        $facts = (new BrowserPdfFactsProvider())->extract($pdf, ['browserFacts' => $handoff]);

        $t->same('native-php-v1', $facts->provider());
        $t->same('rejected', $facts->diagnostics()['browserFacts']['status']);
        $t->contains('did not match', $facts->diagnostics()['browserFacts']['reason']);
        $t->same(false, isset($facts->page(1)->text()['browser']));
    },
    'accepts a partial handoff and leaves uncovered pages native only' => static function (
        TestRunner $t
    ) use ($browserFactsPdf, $browserHandoff): void {
        $pdf = $browserFactsPdf();
        $facts = (new BrowserPdfFactsProvider())->extract($pdf, [
            'browserFacts' => $browserHandoff($pdf, [2]),
        ]);

        $t->same('partial', $facts->diagnostics()['browserFacts']['status']);
        $t->same(false, isset($facts->page(1)->text()['browser']));
        $t->same('Browser beta', $facts->page(2)->text()['browser']['spans'][0]['text']);
    },
    'rejects non finite browser geometry and keeps deterministic browser span IDs' => static function (
        TestRunner $t
    ) use ($browserFactsPdf, $browserHandoff): void {
        $pdf = $browserFactsPdf();
        $provider = new BrowserPdfFactsProvider();
        $handoff = $browserHandoff($pdf, [1]);
        $first = $provider->extract($pdf, ['browserFacts' => $handoff]);
        $second = $provider->extract($pdf, ['browserFacts' => $handoff]);
        $t->same(
            $first->page(1)->text()['browser']['spans'][0]['id'],
            $second->page(1)->text()['browser']['spans'][0]['id']
        );

        $handoff['pages'][0]['spans'][0]['width'] = INF;
        $rejected = $provider->extract($pdf, ['browserFacts' => $handoff]);
        $t->same('rejected', $rejected->diagnostics()['browserFacts']['status']);
        $t->same(false, isset($rejected->page(1)->text()['browser']));
    },
];
