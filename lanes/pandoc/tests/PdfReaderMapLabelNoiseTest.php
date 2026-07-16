<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PdfReader;
use PortLibs\Pandoc\AstNode;

return [
    'never exposes nested internal PDF semantic markers as document text' => static function (TestRunner $t): void {
        $record = "\x1E";
        $unit = "\x1F";
        $prefix = static fn (string $role): string => $record . 'PDF-' . $role . $unit;
        $reader = new PdfReader();
        $mergeRecords = new ReflectionMethod(PdfReader::class, 'mergeRepairedPdfRecords');
        $atomicMapLabel = $prefix('MAP-LABEL') . '29';
        $merged = $mergeRecords->invoke($reader, [
            ['text' => $atomicMapLabel, 'layout' => null],
            ['text' => 'A regular paragraph follows.', 'layout' => null],
        ]);
        $t->same($atomicMapLabel, $merged[0] ?? null, 'An already-typed record must not enter prose or heading repair.');

        $blocksFromLines = new ReflectionMethod(PdfReader::class, 'blocksFromLines');
        $blocks = $blocksFromLines->invoke($reader, [
            $prefix('NUMBERED-HEADING') . $prefix('MAP-LABEL') . '29',
            $prefix('DISPLAY-HEADING') . $prefix('FRONT-MATTER') . 'Magazine section',
            $prefix('MAP-LABEL') . $prefix('NUMBERED-HEADING') . 'Legend',
            $prefix('FORMULA') . $prefix('MAP-LABEL') . 'x = 1',
            $prefix('CODE') . $prefix('MAP-LABEL') . 'sample()',
            $prefix('LINE-BLOCK') . json_encode([
                'SPEAKER',
                $prefix('MAP-LABEL') . 'A spoken line.',
            ], JSON_THROW_ON_ERROR),
        ]);
        $document = new AstNode('document', [], $blocks);
        $html = PandocConverter::write($document, 'html');

        $t->contains('<h1>29</h1>', $html);
        $t->contains('<h2>Magazine section</h2>', $html);
        $t->contains('<p>Legend</p>', $html);
        $t->contains('x = 1', $html);
        $t->contains('sample()', $html);
        $t->contains('A spoken line.', $html);
        $t->true(!str_contains($html, 'PDF-MAP-LABEL'), 'Internal role names must never become reader output.');
        $t->same(0, preg_match_all('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $html), 'Internal C0 delimiters must never become reader output.');
    },
    'filters sustained map label noise from positioned PDF prose repair' => static function (TestRunner $t): void {
        $path = dirname(__DIR__, 3) . '/pandoc-showcase/samples/pdf-muir-beach-brochure-muir-beach-brochure.pdf';
        $t->true(is_file($path), 'Expected Muir Beach brochure sample to be available in the showcase corpus.');
        $pdf = file_get_contents($path);
        $t->true(is_string($pdf) && $pdf !== '', 'Expected readable PDF sample bytes.');

        $document = (new PdfReader([
            'pdfRepairProseText' => true,
            'pdfGeometryTables' => false,
            'maxTextBytes' => 80000,
        ]))->read($pdf);
        $html = PandocConverter::write($document, 'html');

        $t->contains('Lend a hand in the ongoing effort to restore', $html);
        $t->contains('Get involved in the campaign to improve air quality in the San Francisco Bay Area.', $html);
        $t->contains('www.nps.gov/goga', $html);
        $t->contains('<p>Muir Beach is halfway between Stinson Beach and the Marin Headlands, where Shoreline Highway (Hwy. 1) meets Muir Woods Road. The Coastal, Redwood Creek, and Dias Ridge Trails connect with Muir Beach.</p>', $html);
        $t->true(!str_contains($html, 'wwwww.wnp'), 'Overprinted footer text must not be interleaved into corrupted URLs.');
        $t->true(!str_contains($html, 'Plhaaanrrd'), 'Garbled map prose must not be imported.');
        $t->true(!str_contains($html, 'PIRATES'), 'Map labels must not be imported as body prose.');
        $t->true(!str_contains($html, '<h2>Muir Beach is halfway'), 'Wrapped paragraph rows must not become headings.');
    },
];
