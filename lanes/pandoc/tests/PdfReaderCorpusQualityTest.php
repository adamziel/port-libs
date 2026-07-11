<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PdfReader;

$pdfWithContent = static function (string $content): string {
    return "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$pdfPageWithNoText = static function (): string {
    return "%PDF-1.4\n"
        . "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
        . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
        . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Resources<<>>>>endobj\n"
        . "trailer<</Root 1 0 R>>\n%%EOF";
};

$plainText = static function (string $html): string {
    return preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?? '';
};

$pdfSamplePaths = static function (): array {
    $root = dirname(__DIR__, 3);

    return [
        'archive-book' => $root . '/pandoc-showcase/samples/pdf-archive-motograph-book-motograph-moving-picture-book.pdf',
        'cdc-brochure' => $root . '/pandoc-showcase/samples/pdf-cdc-hand-hygiene-brochure-cdc-handhygiene-brochure.pdf',
        'grand-canyon-map' => $root . '/pandoc-showcase/samples/pdf-grand-canyon-north-rim-map-grand-canyon-north-rim-pocket-map.pdf',
        'irs-w4-form' => $root . '/pandoc-showcase/samples/pdf-irs-w4-irs-form-w4.pdf',
        'muir-brochure' => $root . '/pandoc-showcase/samples/pdf-muir-beach-brochure-muir-beach-brochure.pdf',
        'tracemonkey-paper' => $root . '/pandoc-showcase/samples/pdf-tracemonkey-tracemonkey.pdf',
    ];
};

$readPdfSample = static function (string $path, array $options = []): array {
    $document = (new PdfReader(array_replace([
        'maxTextBytes' => 100000,
        'pdfRepairProseText' => true,
        'pdfGeometryTables' => true,
    ], $options)))->read(file_get_contents($path) ?: '');
    $blocks = PandocConverter::write($document, 'blocks');

    return [
        'document' => $document,
        'meta' => $document->attr('meta'),
        'blocks' => $blocks,
        'paragraphs' => substr_count($blocks, '<!-- wp:paragraph'),
        'headings' => preg_match_all('/<!-- wp:heading\b/', $blocks),
        'lists' => substr_count($blocks, '<!-- wp:list'),
        'tables' => substr_count($blocks, '<!-- wp:table'),
    ];
};

return [
    'pdf corpus gate reads article and brochure samples without crashing' => static function (TestRunner $t): void {
        $root = dirname(__DIR__, 3);
        $cases = [
            'article' => $root . '/pandoc-showcase/samples/pdf-tracemonkey-tracemonkey.pdf',
            'brochure' => $root . '/pandoc-showcase/samples/pdf-cdc-hand-hygiene-brochure-cdc-handhygiene-brochure.pdf',
        ];

        foreach ($cases as $kind => $path) {
            $document = (new PdfReader([
                'maxTextBytes' => 30000,
                'pdfRepairProseText' => true,
                'pdfGeometryTables' => true,
            ]))->read(file_get_contents($path) ?: '');
            $meta = $document->attr('meta');

            $t->true(count($document->children) > 0, "{$kind} PDF should produce document blocks.");
            $t->true(($meta['pdfTextLines'] ?? 0) > 0, "{$kind} PDF should expose searchable text lines.");
            $t->true(array_key_exists('pdfTableReconstruction', $meta), "{$kind} PDF should report table reconstruction mode.");
        }
    },
    'pdf corpus gate preserves invoice and bank statement tables as tables' => static function (TestRunner $t) use ($pdfWithContent): void {
        $invoice = $pdfWithContent(
            'BT /F1 12 Tf '
            . '1 0 0 1 72 720 Tm (Item) Tj 1 0 0 1 220 720 Tm (Qty) Tj 1 0 0 1 320 720 Tm (Total) Tj '
            . '1 0 0 1 72 704 Tm (Consulting) Tj 1 0 0 1 220 704 Tm (2) Tj 1 0 0 1 320 704 Tm ($400.00) Tj '
            . '1 0 0 1 72 688 Tm (Hosting) Tj 1 0 0 1 220 688 Tm (1) Tj 1 0 0 1 320 688 Tm ($50.00) Tj '
            . 'ET'
        );
        $statement = $pdfWithContent(
            'BT /F1 12 Tf '
            . '1 0 0 1 72 720 Tm (Date) Tj 1 0 0 1 160 720 Tm (Description) Tj 1 0 0 1 350 720 Tm (Amount) Tj 1 0 0 1 460 720 Tm (Balance) Tj '
            . '1 0 0 1 72 704 Tm (2026-01-02) Tj 1 0 0 1 160 704 Tm (Deposit) Tj 1 0 0 1 350 704 Tm ($100.00) Tj 1 0 0 1 460 704 Tm ($500.00) Tj '
            . '1 0 0 1 72 688 Tm (2026-01-03) Tj 1 0 0 1 160 688 Tm (Withdrawal) Tj 1 0 0 1 350 688 Tm (-$20.00) Tj 1 0 0 1 460 688 Tm ($480.00) Tj '
            . 'ET'
        );

        foreach (['invoice' => $invoice, 'bank statement' => $statement] as $kind => $pdf) {
            $document = (new PdfReader(['pdfGeometryTables' => true, 'pdfRepairProseText' => true]))->read($pdf);
            $blocks = PandocConverter::write($document, 'blocks');
            $meta = $document->attr('meta');

            $t->same(1, $meta['pdfDetectedTables'], "{$kind} should expose one table.");
            $t->contains('<!-- wp:table -->', $blocks);
        }
    },
    'pdf corpus gate warns and preserves low confidence geometry tables as text' => static function (TestRunner $t) use ($pdfWithContent, $plainText): void {
        $pdf = $pdfWithContent(
            'BT /F1 12 Tf '
            . '1 0 0 1 72 720 Tm (Background) Tj 1 0 0 1 300 720 Tm (Outcome) Tj '
            . '1 0 0 1 72 704 Tm (The intervention was reviewed) Tj 1 0 0 1 300 704 Tm (The final report stayed readable) Tj '
            . 'ET'
        );

        $document = (new PdfReader(['pdfGeometryTables' => true, 'pdfRepairProseText' => true]))->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $text = $plainText(PandocConverter::write($document, 'html'));
        $meta = $document->attr('meta');

        $t->same(0, $meta['pdfDetectedTables']);
        $t->true(($meta['pdfGeometryTableLowConfidenceCandidates'] ?? 0) > 0);
        $t->contains('PDF table-like geometry was preserved as text', implode("\n", $meta['pdfWarnings'] ?? []));
        $t->true(!str_contains($blocks, '<!-- wp:table -->'));
        $t->contains('Background', $text);
        $t->contains('final report stayed readable', $text);
    },
    'pdf corpus gate keeps resume and slide like PDFs readable without false tables' => static function (TestRunner $t) use ($pdfWithContent, $plainText): void {
        $resume = $pdfWithContent(
            'BT /F1 18 Tf 72 740 Td (Ada Example) Tj T* '
            . '/F1 12 Tf (Engineering Lead) Tj T* '
            . '(Experience) Tj T* '
            . '(• Led platform migration for publishing tools.) Tj T* '
            . '(• Improved importer reliability and observability.) Tj ET'
        );
        $slide = $pdfWithContent(
            'BT /F1 28 Tf 72 700 Td (Import Any Document) Tj T* '
            . '/F1 16 Tf (• Upload files) Tj T* '
            . '(• Review conversion notes) Tj T* '
            . '(• Edit the result in WordPress) Tj ET'
        );

        foreach (['resume' => $resume, 'slide-like' => $slide] as $kind => $pdf) {
            $document = (new PdfReader(['pdfGeometryTables' => true, 'pdfRepairProseText' => true]))->read($pdf);
            $blocks = PandocConverter::write($document, 'blocks');
            $text = $plainText(PandocConverter::write($document, 'html'));
            $meta = $document->attr('meta');

            $t->same(0, $meta['pdfDetectedTables'], "{$kind} should not become a table.");
            $t->true(!str_contains($blocks, '<!-- wp:table -->'), "{$kind} should not emit a table block.");
            $t->true(strlen($text) > 40, "{$kind} should preserve readable text.");
        }
    },
    'pdf corpus gate flags scanned image only PDFs as needing OCR' => static function (TestRunner $t) use ($pdfPageWithNoText): void {
        $document = (new PdfReader())->read($pdfPageWithNoText());
        $meta = $document->attr('meta');

        $t->same(0, $meta['pdfTextLines']);
        $t->true(($meta['pdfEstimatedPages'] ?? 0) > 0 || ($meta['pdfPageCount'] ?? 0) > 0);
    },
    'pdf corpus gate covers shipped real world samples' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfSample): void {
        foreach ($pdfSamplePaths() as $kind => $path) {
            $t->true(is_file($path), "{$kind} PDF fixture should exist.");
            $result = $readPdfSample($path);
            $meta = $result['meta'];

            $t->true(count($result['document']->children) > 0, "{$kind} should produce document blocks.");
            $t->true(($meta['pdfTextLines'] ?? 0) > 0, "{$kind} should expose searchable text lines.");
            $t->true(($meta['pdfTextBytes'] ?? 0) >= 300, "{$kind} should preserve a useful text payload.");
            $t->true(array_key_exists('pdfTableReconstruction', $meta), "{$kind} should report table reconstruction mode.");
            $t->true(array_key_exists('pdfDetectedTables', $meta), "{$kind} should report detected table count.");
        }
    },
    'pdf corpus gate preserves real layout tables without using text fragments' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfSample): void {
        $cases = [
            'tracemonkey-paper' => ['minGeometryTables' => 5, 'minTables' => 1, 'minLines' => 1000, 'mode' => 'text-fallback'],
        ];

        foreach ($cases as $kind => $expectation) {
            $result = $readPdfSample($pdfSamplePaths()[$kind]);
            $meta = $result['meta'];

            $t->true(($meta['pdfGeometryTables'] ?? 0) >= $expectation['minGeometryTables'], "{$kind} should retain geometry table candidates.");
            $t->true(($meta['pdfDetectedTables'] ?? 0) >= $expectation['minTables'], "{$kind} should expose layout tables as document tables.");
            $t->true($result['tables'] >= $expectation['minTables'], "{$kind} WordPress blocks should include table blocks.");
            $t->true(($meta['pdfTextLines'] ?? 0) >= $expectation['minLines'], "{$kind} should keep enough positioned text to avoid collapsed extraction.");
            $t->same($expectation['mode'], $meta['pdfTableReconstruction'], "{$kind} should report the selected table reconstruction mode.");
        }
    },
    'pdf corpus gate keeps text only retry prose oriented' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfSample): void {
        $cases = [
            'grand-canyon-map' => ['minParagraphs' => 40, 'minHeadings' => 25, 'minTables' => 0],
            'muir-brochure' => ['minParagraphs' => 25, 'minHeadings' => 9, 'minTables' => 0],
            'tracemonkey-paper' => ['minParagraphs' => 100, 'minHeadings' => 3, 'minTables' => 1],
        ];

        foreach ($cases as $kind => $expectation) {
            $result = $readPdfSample($pdfSamplePaths()[$kind], ['pdfGeometryTables' => false]);
            $meta = $result['meta'];

            $t->same(0, $meta['pdfGeometryTables'], "{$kind} text-only retry should skip geometry table extraction.");
            $t->same('text', $meta['pdfTableReconstruction'], "{$kind} text-only retry should report text reconstruction.");
            $t->true($result['tables'] >= $expectation['minTables'], "{$kind} text-only retry should preserve text-detected tables.");
            $t->true($result['paragraphs'] >= $expectation['minParagraphs'], "{$kind} text-only retry should keep prose split into readable paragraphs.");
            $t->true($result['headings'] >= $expectation['minHeadings'], "{$kind} text-only retry should retain heading-like line structure.");
        }
    },
    'pdf corpus gate keeps multi-column brochure flows separate' => static function (TestRunner $t) use ($pdfSamplePaths, $plainText): void {
        $document = (new PdfReader([
            'maxTextBytes' => 100000,
            'pdfRepairProseText' => true,
            'pdfGeometryTables' => false,
        ]))->read(file_get_contents($pdfSamplePaths()['cdc-brochure']) ?: '');
        $text = $plainText(PandocConverter::write($document, 'html'));
        $blocks = PandocConverter::write($document, 'blocks');

        $t->contains('Infections you get in the hospital can be life-threatening and hard to treat.', $text);
        $t->contains('After touching hospital surfaces such as bed rails, bedside tables, doorknobs, remote controls, or the phone.', $text);
        $t->contains('Use an alcohol-based hand rub:', $text);
        foreach ([
            'wash their hands. as bed rails',
            'forms a lather and then rub all over hand rub',
            'life-threatening and hard to treat. dressings or bandages.',
            'w if',
        ] as $artifact) {
            $t->true(!str_contains($text, $artifact), "CDC brochure must not splice parallel columns into '{$artifact}'.");
        }
        $t->true(!str_contains($blocks, '<p>aves Lives</p>'), 'CDC brochure must not retain a clipped decorative display fragment.');
    },
    'pdf corpus gate keeps multi-column map panels and labels as prose' => static function (TestRunner $t) use ($pdfSamplePaths, $plainText, $readPdfSample): void {
        $document = (new PdfReader([
            'maxTextBytes' => 100000,
            'pdfRepairProseText' => true,
            'pdfGeometryTables' => true,
        ]))->read(file_get_contents($pdfSamplePaths()['grand-canyon-map']) ?: '');
        $meta = $document->attr('meta');
        $blocks = PandocConverter::write($document, 'blocks');
        $text = $plainText(PandocConverter::write($document, 'html'));

        $t->same(0, $meta['pdfDetectedTables'], 'Narrative map panels must not be emitted as tables.');
        $t->same(0, $meta['pdfGeometryTables'], 'Narrative map panels must not count as geometry tables.');
        $t->same('text', $meta['pdfTableReconstruction'], 'The map should use source-reconciled prose after table rejection.');
        $t->true(!str_contains($blocks, '<!-- wp:table -->'));
        $t->contains('OR Hike the North Kaibab Trail to Coconino Overlook. Hiking into the Canyon offers a different perspective.', $text);
        $t->contains('The Pocket Map is published by Grand Canyon National Park with support from your entrance fees.', $text);
        $t->contains('Food service is available from mid-May to mid-October.', $text);
        $t->contains('For backcountry camping options (permit required) check with the Backcountry Information Center.', $text);
        foreach ([
            'Hiking into 22 feet (6.7 m) prohibited on the roads',
            'Trail south from Point Imperial for a half mile for an easy hike with Parking',
            'S o c n',
            'mid- May',
        ] as $artifact) {
            $t->true(!str_contains($text, $artifact), "Grand Canyon prose must not retain interleaved map-panel artifact '{$artifact}'.");
        }

        $muir = $readPdfSample($pdfSamplePaths()['muir-brochure']);
        $muirMeta = $muir['meta'];
        $t->same(0, $muirMeta['pdfDetectedTables'], 'Map label fragments must not become Muir brochure tables.');
        $t->same(0, $muirMeta['pdfGeometryTables'], 'Map label fragments must not count as Muir geometry tables.');
        $t->same('text', $muirMeta['pdfTableReconstruction'], 'Muir should use source-reconciled prose after map-label rejection.');
        $t->true(!str_contains($muir['blocks'], '<!-- wp:table -->'));
        $t->contains('<p>Horses and Hiking only</p>', $muir['blocks']);
        foreach ([
            '<td>e</td><td>O</td><td>R</td><td>r</td>',
            '<td>Lodging</td><td>PIRATES</td>',
        ] as $artifact) {
            $t->true(!str_contains($muir['blocks'], $artifact), "Muir must not retain fragmented map label table '{$artifact}'.");
        }
    },
    'pdf corpus gate keeps TraceMonkey wrapped prose word boundaries' => static function (TestRunner $t) use ($pdfSamplePaths, $plainText): void {
        $document = (new PdfReader([
            'maxTextBytes' => 100000,
            'pdfRepairProseText' => true,
            'pdfGeometryTables' => false,
        ]))->read(file_get_contents($pdfSamplePaths()['tracemonkey-paper']) ?: '');
        $text = $plainText(PandocConverter::write($document, 'html'));

        foreach (['oftheir', 'ofvalues', 'usingtrace', 'thefastest'] as $glued) {
            $t->true(!str_contains($text, $glued), "TraceMonkey text-only retry should not contain '{$glued}'.");
        }
        $t->contains('of their', $text);
        $t->contains('of values', $text);
        $t->contains('the fastest', $text);
        foreach (['ac-tual', 'discov-ered', 'sus-pend'] as $fragment) {
            $t->true(!str_contains($text, $fragment), "TraceMonkey should remove geometry-confirmed discretionary hyphen '{$fragment}'.");
        }
        $t->contains('actual dynamic types', $text);
        $t->contains('discovered alternative paths', $text);
        foreach (['mixed-mode execution approach', 'type-unstable loops', 'register-carried value', 'non-negligible runtime cost'] as $compound) {
            $t->contains($compound, $text, "TraceMonkey should preserve semantic compound '{$compound}'.");
        }
    },
    'pdf corpus gate repairs TraceMonkey TJ word gaps without fragment splits' => static function (TestRunner $t) use ($pdfSamplePaths, $plainText): void {
        $document = (new PdfReader([
            'maxTextBytes' => 100000,
            'pdfRepairProseText' => true,
            'pdfGeometryTables' => false,
        ]))->read(file_get_contents($pdfSamplePaths()['tracemonkey-paper']) ?: '');
        $text = $plainText(PandocConverter::write($document, 'html'));

        foreach (['completelycovertheloop', 'theVMmustrecord', 'acounterforeachsideexit', 'recordatracestarting', 'havebeentriedandfailed'] as $glued) {
            $t->true(!str_contains($text, $glued), "TraceMonkey text-only retry should not contain '{$glued}'.");
        }
        foreach (['completely cover the loop', 'the VM must record', 'a counter for each side exit', 'When the VM fails to finish a trace'] as $spaced) {
            $t->contains($spaced, $text);
        }
        $t->contains('When the VM fails to finish a trace starting at a given point, the VM records that a failure has occurred.', $text);
        foreach (['tra ce', 'co ver'] as $splitWord) {
            $t->true(!str_contains($text, $splitWord), "TraceMonkey text-only retry should not introduce '{$splitWord}'.");
        }
    },
    'pdf corpus gate removes clipped PDF line duplicates without truncating prose' => static function (TestRunner $t) use ($pdfSamplePaths): void {
        $document = (new PdfReader([
            'maxTextBytes' => 100000,
            'pdfRepairProseText' => true,
            'pdfGeometryTables' => false,
        ]))->read(file_get_contents($pdfSamplePaths()['tracemonkey-paper']) ?: '');
        $blocks = PandocConverter::write($document, 'blocks');

        $t->contains('a naïve implementation, inner loops would become hot first, and the VM would start tracing there.', $blocks);
        $t->contains('outer loop paths will not be duplicated.', $blocks);
        foreach ([
            'and a na the VM',
            'na¨ıve',
            'make the outer loop’s the inner loop’s trace tree',
            'make the outer loop’s</p>',
        ] as $artifact) {
            $t->true(!str_contains($blocks, $artifact), "TraceMonkey must not retain clipped glyph or duplicate-line artifact '{$artifact}'.");
        }
    },
    'pdf corpus gate preserves TraceMonkey bibliography dash url separators' => static function (TestRunner $t) use ($pdfSamplePaths, $plainText): void {
        $document = (new PdfReader([
            'maxTextBytes' => 100000,
            'pdfRepairProseText' => true,
            'pdfGeometryTables' => false,
        ]))->read(file_get_contents($pdfSamplePaths()['tracemonkey-paper']) ?: '');
        $text = $plainText(PandocConverter::write($document, 'html'));

        foreach (['client -http', 'Extreme -http'] as $glued) {
            $t->true(!str_contains($text, $glued), "TraceMonkey bibliography URLs should not contain '{$glued}'.");
        }
        $t->contains('client - http://www.mozilla.com', $text);
        $t->contains('Extreme - http://webkit.org', $text);
        $t->contains('SPECJVM98 - http://www.spec.org/jvm98/', $text);
        $t->contains('[4] SpiderMonkey (JavaScript-C) Engine- http://www.mozilla.org/js/spidermonkey/.', $text);
        $t->contains('http://lua-users.org/lists/lua-l/2008-02/msg00051.html', $text);
        $t->contains('[12] C. Garrett, J. Dean, D. Grove, and C. Chambers. Measurement and Application of Dynamic Receiver Class Distributions. 1994.', $text);
        foreach ([
            '[7] V. Bala, E. Duesterwald, and S. Banerjia. Dynamo: A transparent ACM Press, 2000.',
            '[18] T. Suganuma, T. Yasue, and T. Nakatani. A Region-Based Compila-',
        ] as $artifact) {
            $t->true(!str_contains($text, $artifact), "TraceMonkey must omit malformed reference fragment '{$artifact}'.");
        }
        $t->true(!str_contains($text, 'SPECJVM 98'), 'TraceMonkey acronym benchmark names should not be split before digits.');
        $t->true(!str_contains($text, 'jvm 98'), 'TraceMonkey URL path digits should not be split.');
        $t->true(!str_contains($text, 'msg 00051'), 'TraceMonkey URL path message identifiers should not be split.');
    },
    'pdf corpus gate preserves TraceMonkey data tables without promoting split diagram labels' => static function (TestRunner $t) use ($pdfSamplePaths, $plainText): void {
        $document = (new PdfReader([
            'maxTextBytes' => 100000,
            'pdfRepairProseText' => true,
            'pdfGeometryTables' => false,
        ]))->read(file_get_contents($pdfSamplePaths()['tracemonkey-paper']) ?: '');
        $blocks = PandocConverter::write($document, 'blocks');
        $text = $plainText(PandocConverter::write($document, 'html'));

        foreach (['objectpointer', 'numberpointer', 'stringpointer', 'booleanenumeration'] as $glued) {
            $t->true(!str_contains($blocks, $glued), "TraceMonkey table cells should not contain '{$glued}'.");
        }
        $t->same(1, substr_count($blocks, '<!-- wp:table -->'));
        $t->contains('<th>Tag</th><th>JS Type</th><th>Description</th>', $blocks);
        $t->contains('<td>000</td><td>object</td><td>pointer to JSObject handle</td>', $blocks);
        $t->contains('<td>110</td><td>boolean</td><td>enumeration for null, undefined, true, false', $blocks);
        $t->true(!str_contains($blocks, '<th>T</th><th>r</th><th>ace 2</th>'));
        $t->true(!str_contains($blocks, '<p>object pointer to JS Object handle</p>'));
        $t->contains('Testing tags, unboxing', $text);
    },
    'pdf corpus gate does not inject TraceMonkey positioned word fragment spaces' => static function (TestRunner $t) use ($pdfSamplePaths, $plainText): void {
        $document = (new PdfReader([
            'maxTextBytes' => 100000,
            'pdfRepairProseText' => true,
            'pdfGeometryTables' => false,
        ]))->read(file_get_contents($pdfSamplePaths()['tracemonkey-paper']) ?: '');
        $text = $plainText(PandocConverter::write($document, 'html'));

        foreach (['tra ce', 'ca ll', 'recordi ng', 'int erpreter', 'ef ficient', 'dif ferent', 'sim ply'] as $splitWord) {
            $t->true(!str_contains($text, $splitWord), "TraceMonkey text-only retry should not contain '{$splitWord}'.");
        }
        $t->contains('trace', $text);
        $t->contains('call', $text);
        $t->contains('recording', $text);
        $t->contains('interpreter', $text);
    },
    'pdf corpus gate keeps TraceMonkey figure fragments out of prose order' => static function (TestRunner $t) use ($pdfSamplePaths): void {
        $document = (new PdfReader([
            'maxTextBytes' => 100000,
            'pdfRepairProseText' => true,
            'pdfGeometryTables' => false,
        ]))->read(file_get_contents($pdfSamplePaths()['tracemonkey-paper']) ?: '');
        $blocks = PandocConverter::write($document, 'blocks');

        $t->true(!str_contains($blocks, 'that the path and 1 for'), 'TraceMonkey must not splice figure text into a nearby code sample.');
        $t->true(!str_contains($blocks, '?&gt;9@AJ'), 'TraceMonkey must not emit corrupted chart-font bytes as prose.');
        $t->same(1, substr_count($blocks, '<!-- wp:table -->'), 'TraceMonkey diagram labels must not become an extra table.');
        $t->contains('A tree with two traces, a trunk trace and one branch trace. The trunk trace contains a guard', $blocks);
        $t->contains('Figure 6. We handle type-unstable loops by allowing traces to compile that cannot loop back to themselves', $blocks);
        foreach ([
            '<p>compiled twice, and both copies must be retained in the trace cache.</p>',
            '<p>Later, the program might take the other branch at i</p>',
            '<h2>In general, if loops are nested to depthk</h2>',
            '<p>which can easily fill the trace cache.</p>',
            '<p>loops back to line 1. i=3.</p>',
            'tofaila guard and takea sideexit.',
            'Related work is discussed in</p>',
            'The LIR encodes</p>',
            'Most LIR instructions compile</p>',
            'guard that the function</p>',
            'Clearly, a point and type map',
            '<h2>Then the recorded</h2>',
            '<h2>The</h2>',
            'Trace trees we re originally proposed',
            'The αsymbol is used to',
            '<p>and record a branch</p>',
            'as Thus, we should not count such aborts',
            'In order to execute programs with nested loops efficiently, a The goal of nesting',
            'tractable. An important detail',
            'Stores to locations that are off Dead call-stack',
            'Trace Monkey can cover the entire program with 1 or 2 traces that operate on integers. Trace Mon-isforms one very long trace',
            'is dominated by regular expression matching, Some programs trace very well',
            'on 9 The closest area of related work',
            '<h2>Br</h2>',
            '<h2>Link</h2>',
            '<h2>In the rest of this section we discuss key areas of the Trace Mon-</h2>',
            '<p>PC and type map. Traces are compiled so that they may be</p>',
            '<p>with a</p>',
            '<p>to be replaced</p>',
            '<p>in registers immebe the last</p>',
            '<p>is machine word in which up to the 3 of the</p>',
            '<p>values are mappings of string-valued property</p>',
            '<p>is marked free for</p>',
            '<p>point to</p>',
            '<p>Trace trees were originally proposed by Gal et al. (11) in the context of Java, a statically typed language. Their trace trees actually inlined parts of outer loops within the inner loops (because</p>',
        ] as $artifact) {
            $t->true(!str_contains($blocks, $artifact), "TraceMonkey must not retain detached diagram or incomplete-flow fragment '{$artifact}'.");
        }
        $t->contains('There is at least one hot side exit for which the VM cannot complete a trace.', $blocks);
        $t->contains('The loop body is short.', $blocks);
        $t->contains('In this case, the VM will repeatedly pass the loop header, search for a trace, find it, execute it, and fall back to the interpreter.', $blocks);
        $t->true(!str_contains($blocks, 'objects’ rep-resentations'), 'TraceMonkey must remove a geometry-proven automatic hyphen across a page boundary.');
        $t->contains('Clearly, a JavaScript VM that wants to be fast must find a way to operate on integers directly and avoid these conversions.', $blocks);
        $t->contains('Figure 7 shows basic trace tree compilation (11) applied to a nested loop where the inner loop contains two paths. Usually, the inner loop (with header at i2) becomes hot first, and a trace tree is rooted at that point.', $blocks);
        $t->true(!str_contains($blocks, 'loop (with header at i at that point.'), 'TraceMonkey must not merge source fragments across omitted diagram content.');
        $t->contains('Thus, the outer loop is recorded and compiled twice, and both copies must be retained in the trace cache.', $blocks);
        $t->contains('Later, the program might take the other branch at i2 and then exit, recording another branch trace incorporating the outer loop:', $blocks);
        $t->contains('In general, if loops are nested to depth k, and each loop has n paths (on geometric average), this naïve strategy yields O(n k)traces, which can easily fill the trace cache.', $blocks);
        $t->contains('We solve the nested loop problem by recording nested trace trees. Our system traces the inner loop exactly as the naïve version.', $blocks);
        $t->contains('After compiling T 45, TraceMonkey returns to the interpreter and loops back to line 1. i=3. Now the loop header at line 1 has become hot, so TraceMonkey starts recording.', $blocks);
        $t->contains('nested trace T 45. T16 loops back to its own header', $blocks);
        $t->true(!str_contains($blocks, 'T 45.T16 loops back to its own header'), 'TraceMonkey must restore numeric sentence boundaries in reconstructed prose.');
        $t->contains('We call the resulting tracing VM TraceMonkey. TraceMonkey supports all the JavaScript features of SpiderMonkey', $blocks);
        $t->true(!str_contains($blocks, 'Trace- Monkey'), 'TraceMonkey must join geometry-confirmed repeated compounds across line-end hyphens.');
        $t->contains('each loop is entered with m different type maps (on geometric average)', $blocks);
        $t->contains('As long as m is close to 1, the resulting trace trees will be tractable.', $blocks);
        $t->true(
            !str_contains($blocks, '<p>is close to 1, the resulting trace trees will be tractable.</p>'),
            'TraceMonkey must not retain the second half of a sentence as detached prose.'
        );
        $t->contains('When the VM fails to finish a trace starting at a given point, the VM records that a failure has occurred.', $blocks);
        $t->contains('As future work, this situation could be avoided by detecting and blacklisting loops for which the average trace call executes few bytecodes before returning to the interpreter.', $blocks);
        $t->contains('An important detail is that the call to the inner trace tree must act', $blocks);
        $t->contains('This is the LIR recorded for line 5 of the sample program in Figure 1.', $blocks);
        $t->contains('This is the x 86 code compiled from the LIR snippet in Figure 3.', $blocks);
        $t->contains('Some operations on integers require guards.', $blocks);
        $t->contains('the interpreter’s standard call code.', $blocks);
        $t->true(!str_contains($blocks, 'operation in question. Representation specialization:'), 'TraceMonkey inline style boundaries must retain their paragraph break.');
        foreach ([
            'JavaScript, for example, is the de facto standard for client-side web programming and is used for the application logic of browser-based productivity applications',
            'In TraceMonkey, traces are recorded in trace-flavored SSA LIR (low-level intermediate representation).',
            'objects’ representations are assigned an integer key called the object shape. Thus, the guard is a simple equality check on the object shape.',
            'Clearly, a JavaScript VM that wants to be fast must find a way to operate on integers directly and avoid these conversions.',
            'See Figure 6 for details. All pointers contained in jsvals point to GC-controlled blocks aligned on 8-byte boundaries.',
        ] as $recovered) {
            $t->contains($recovered, $blocks, "TraceMonkey must retain verified source text for '{$recovered}'.");
        }
    },
    'pdf corpus gate infers sustained monospaced listings as code blocks' => static function (TestRunner $t) use ($pdfSamplePaths): void {
        $document = (new PdfReader([
            'maxTextBytes' => 100000,
            'pdfRepairProseText' => true,
            'pdfGeometryTables' => false,
        ]))->read(file_get_contents($pdfSamplePaths()['tracemonkey-paper']) ?: '');
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same(2, $meta['pdfDetectedCodeBlocks']);
        $t->same(2, substr_count($blocks, '<!-- wp:code -->'));
        $t->contains("v0 := ld state[748]      // load primes from the trace activation record\n", $blocks);
        $t->contains("mov edx, ebx(748)       // load primes from the trace activation record\n", $blocks);
        $t->contains('This is the LIR recorded for line 5', $blocks);
    },
    'pdf corpus gate preserves final prose repairs after geometry table fallback' => static function (TestRunner $t) use ($pdfSamplePaths): void {
        $document = (new PdfReader([
            'maxTextBytes' => 100000,
            'pdfRepairProseText' => true,
            'pdfGeometryTables' => true,
        ]))->read(file_get_contents($pdfSamplePaths()['tracemonkey-paper']) ?: '');
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');

        $t->same('text-fallback', $meta['pdfTableReconstruction']);
        $t->same(1, substr_count($blocks, '<!-- wp:table -->'));
        $t->same(2, substr_count($blocks, '<!-- wp:code -->'));
        $t->contains('TraceMonkey starts recording.', $blocks);
        $t->true(!str_contains($blocks, 'Trace-Monkey'), 'A geometry table fallback must retain the final repeated-compound repair.');
        $t->contains("v0 := ld state[748]      // load primes from the trace activation record\n", $blocks);
        $t->contains("mov edx, ebx(748)       // load primes from the trace activation record\n", $blocks);
    },
    'pdf corpus gate keeps TraceMonkey source geometry flow coherent around side tables' => static function (TestRunner $t) use ($pdfSamplePaths): void {
        $document = (new PdfReader([
            'maxTextBytes' => 100000,
            'pdfRepairProseText' => true,
            'pdfGeometryTables' => true,
        ]))->read(file_get_contents($pdfSamplePaths()['tracemonkey-paper']) ?: '');
        $blocks = PandocConverter::write($document, 'blocks');

        $t->contains('<h2>5.2 Register Allocation</h2>', $blocks);
        $t->contains('<h2>6.1 Calling Compiled Traces</h2>', $blocks);
        $t->contains('Then the heuristic selects v with minimum', $blocks);
        $t->contains('register-carried value', $blocks);
        $t->contains('stop-the-world mark-and-sweep collector.', $blocks);
        $t->contains('non-negligible runtime cost', $blocks);
        $t->contains('TraceMonkey implementation.', $blocks);
        $t->contains('The heuristic considers the set R of values v in registers immediately', $blocks);
        $t->contains('where each v is referred to.', $blocks);
        $t->contains('<li>CSE (constant subexpression elimination),</li>', $blocks);
        $t->contains('<li>Dead code elimination. This eliminates any operation that stores to a value that is never used.</li>', $blocks);
        foreach ([
            '<p>with a</p>',
            '<p>to be replaced</p>',
            '<p>in registers immebe the last</p>',
            '<p>is machine word in which up to the 3 of the</p>',
            '<p>values are mappings of string-valued property</p>',
            '<p>is marked free for</p>',
            '<p>point to</p>',
            'TraceMon-key',
            'registercarried',
            'stoptheworld',
            'nonnegligible',
            'Rof values vin',
            'each vis referred',
        ] as $artifact) {
            $t->true(!str_contains($blocks, $artifact), "TraceMonkey geometry flow must not retain '{$artifact}'.");
        }
        $t->same(1, substr_count($blocks, '<!-- wp:table -->'));
        $t->same(2, substr_count($blocks, '<!-- wp:code -->'));
    },
    'pdf corpus gate rejects damaged positioned prose streams' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfSample): void {
        $muir = $readPdfSample($pdfSamplePaths()['muir-brochure'], ['pdfGeometryTables' => false]);
        $meta = $muir['meta'];

        $t->same('text-geometry', $meta['pdfTextRepairSource'], 'Muir brochure text-only retry should use geometry only on pages with coherent coordinates.');
        $t->true($muir['paragraphs'] >= 25, 'Muir brochure text-only retry should keep readable prose blocks.');
        $t->true($muir['headings'] >= 9, 'Muir brochure text-only retry should retain its actual heading structure without map labels.');
        $t->contains('In collaboration with public agencies and nonprofit partners, the National Park Service implemented a multi-year', $muir['blocks']);
        $t->contains('<h2>Make a Difference</h2>', $muir['blocks']);
        $t->contains('Left, Top &amp; Bottom images: Traditional prayer', $muir['blocks']);
        $t->contains('Left, Top &amp; Bottom images: Traditional prayer led by a representative of the Coast Miwok at the annual Welcome Back Salmon ceremony at Muir Beach.', $muir['blocks']);
        $t->true(!str_contains($muir['blocks'], 'caused it to fill In collaboration'), 'Muir brochure columns must not be merged into one paragraph.');
        $t->true(!str_contains($muir['blocks'], 'caused it to fill</p>'), 'Muir brochure must not emit an unresolved source-only sentence tail.');
        $t->true(!str_contains($muir['blocks'], 'at y a representative of the Coast Miwok'), 'Muir brochure must not repeat a clipped source fragment after a positioned repair line.');
        $t->true(!str_contains($muir['blocks'], 'www.nps.gov/goga Horses and Hiking only'), 'Muir map labels must not continue an unrelated resource list.');
        $t->true(!str_contains($muir['blocks'], '<h2>Hiking only</h2>'), 'Muir map legend labels must not be promoted to document headings.');
        $t->contains('<p>Hiking only</p>', $muir['blocks']);
    },
    'pdf corpus gate repairs Muir wrapped hyphen word fragments' => static function (TestRunner $t) use ($pdfSamplePaths, $plainText): void {
        $document = (new PdfReader([
            'maxTextBytes' => 100000,
            'pdfRepairProseText' => true,
            'pdfGeometryTables' => false,
        ]))->read(file_get_contents($pdfSamplePaths()['muir-brochure']) ?: '');
        $text = $plainText(PandocConverter::write($document, 'html'));

        foreach (['includ -ing', 'red -wood'] as $artifact) {
            $t->true(!str_contains($text, $artifact), "Muir brochure should not contain '{$artifact}'.");
        }
        $t->contains('including the Muir Beach floodplain', $text);
        $t->contains('redwood forest', $text);
    },
    'pdf corpus gate preserves brochure lists and form baseline extraction' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfSample, $plainText): void {
        $cdc = $readPdfSample($pdfSamplePaths()['cdc-brochure']);
        $cdcMeta = $cdc['meta'];

        $t->same(0, $cdcMeta['pdfDetectedTables'], 'CDC brochure should not become a false table.');
        $t->same(0, $cdcMeta['pdfDetectedCodeBlocks'], 'CDC brochure columns should not become false code listings.');
        $t->true($cdc['lists'] >= 12, 'CDC brochure should preserve visible bullet lists.');
        $t->true($cdc['headings'] >= 6, 'CDC brochure should retain prominent heading-like text without splitting wrapped headings.');
        $t->true($cdc['paragraphs'] >= 24, 'CDC brochure should preserve its prose groups without emitting every visual line as a paragraph.');

        $cdcText = $plainText(PandocConverter::write($cdc['document'], 'html'));
        $t->contains('To prevent hospital infections.', $cdcText);
        $t->contains('You should practice hand hygiene:', $cdcText);
        $t->contains('Before preparing or eating food.', $cdcText);
        $t->contains('To prevent hospital infections.', $cdc['blocks']);
        $t->contains('You should practice hand hygiene:', $cdc['blocks']);
        $t->contains('Healthcare providers should practice hand hygiene:', $cdc['blocks']);
        $t->contains('You can take action by practicing hand hygiene regularly and by asking those around you to practice it as well.', $cdcText);
        $t->contains('Remember: Hand hygiene saves lives.', $cdcText);
        $t->contains('Remember: It only takes 15 seconds to protect yourself and others.', $cdcText);
        $t->contains('Hand hygiene is one of the most important ways to prevent the spread of infections, including the common cold, flu, and even hard-to-treat infections, such as methicillin-resistant Staphylococcus aureus, or MRSA.', $cdcText);
        $t->contains('Wet your hands with warm water.', $cdcText);
        $t->contains('Apply a nickel-or quarter-sized amount of soap to your hands.', $cdcText);
        $t->contains('You can make a difference in your own health:', $cdcText);
        foreach ([
            'To prevent hospital You should practice',
            'year. • Before touching',
            't he hospital',
            'at ris k',
            'take actio n',
            'war m water',
            'To make a difference gloves alone',
        ] as $artifact) {
            $t->true(!str_contains($cdcText, $artifact), "CDC brochure should not contain '{$artifact}'.");
        }
        $t->true(!str_contains($cdcText, 'Hand }'), 'CDC brochure should not expose malformed rotated display text as prose.');

        $w4 = $readPdfSample($pdfSamplePaths()['irs-w4-form']);
        $w4Meta = $w4['meta'];
        $w4Text = $plainText(PandocConverter::write($w4['document'], 'html'));

        $t->true(($w4Meta['pdfEstimatedPages'] ?? 0) >= 5, 'IRS W-4 form should report its page count.');
        $t->true(($w4Meta['pdfTextLines'] ?? 0) >= 800, 'IRS W-4 form should retain text from every linearized source page.');
        $t->true(($w4Meta['pdfTextBytes'] ?? 0) >= 20000, 'IRS W-4 form should retain the full multi-page text payload.');
        $t->contains('General Instructions', $w4Text);
        $t->contains('Multiple Jobs Worksheet', $w4Text);
        $t->contains('Married Filing Jointly', $w4Text);
        $t->contains('household salaries and enter that value on line 1. Then, skip to line 3.', $w4Text);
        $t->same(3, $w4Meta['pdfDetectedTables'], 'IRS W-4 should retain its three salary matrices without turning prose columns into tables.');
        $t->same(3, $w4['tables'], 'IRS W-4 WordPress output should retain each real salary matrix as a table.');
        $t->contains('Married Filing Jointly', $w4['blocks']);
        $t->contains('<td>$0 - 9,999</td>', $w4['blocks']);
        $t->contains('<td>33,990</td>', $w4['blocks']);
        $t->true($w4['headings'] >= 2, 'IRS W-4 form should retain heading-like form labels.');
    },
    'pdf corpus gate keeps scanned book bounded but readable' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfSample, $plainText): void {
        $result = $readPdfSample($pdfSamplePaths()['archive-book']);
        $meta = $result['meta'];
        $text = $plainText(PandocConverter::write($result['document'], 'html'));

        $t->true(($meta['pdfEstimatedPages'] ?? 0) >= 40, 'Archive book should report the multi-page source.');
        $t->true(($meta['pdfTextLines'] ?? 0) >= 20, 'Archive book should keep available OCR/search text lines.');
        $t->true(($meta['pdfTextBytes'] ?? 0) >= 2000, 'Archive book should keep available OCR/search text.');
        $t->same(0, $meta['pdfDetectedTables'], 'Archive book prose should not become a table.');
        $t->true($result['paragraphs'] >= 8, 'Archive book should keep sparse long OCR chunks grouped into reviewable paragraphs.');
        $t->contains('difficult to discover', $text);
        $t->contains('this file - a reminder', $text);
        $t->contains('these files for personal', $text);
        $t->contains('find additional materials', $text);
        $t->true(!str_contains($text, 'dif cult'), 'Archive book fi ligatures should not be lost as spaces.');
        $t->true(!str_contains($text, ' le - a reminder'), 'Archive book fi ligatures should not leave bare word fragments.');
        $t->true(!str_contains($result['blocks'], 'journey from the Google is proud'), 'Archive book source regions must not be fused across a missing OCR span.');
    },
];
