<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PdfReader;

return [
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
