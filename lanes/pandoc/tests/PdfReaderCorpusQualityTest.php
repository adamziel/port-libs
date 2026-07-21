<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;
use PortLibs\Pandoc\PandocMediaExtractor;
use PortLibs\Pandoc\PdfReader;
use PortLibs\Pandoc\AstNode;

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

/** @return list<list<string>> */
$tableRows = static function (AstNode $table): array {
    $rows = [];
    foreach ($table->children() as $section) {
        foreach ($section->children() as $row) {
            $rows[] = array_map(
                static fn (AstNode $cell): string => (string) $cell->attr('text', ''),
                $row->children()
            );
        }
    }

    return $rows;
};

$pdfSamplePaths = static function (): array {
    $root = dirname(__DIR__, 3);

    return [
        'aircraft-handbook' => $root . '/pandoc-showcase/samples/pdf-layout-docling-aircraft-handbook-amt_handbook_sample.pdf',
        'archive-book' => $root . '/pandoc-showcase/samples/pdf-archive-motograph-book-motograph-moving-picture-book.pdf',
        'cdc-brochure' => $root . '/pandoc-showcase/samples/pdf-cdc-hand-hygiene-brochure-cdc-handhygiene-brochure.pdf',
        'emphasis' => $root . '/pandoc-showcase/samples/pdf-layout-unstructured-emphasis-emphasis-text.pdf',
        'grand-canyon-map' => $root . '/pandoc-showcase/samples/pdf-grand-canyon-north-rim-map-grand-canyon-north-rim-pocket-map.pdf',
        'irs-w4-form' => $root . '/pandoc-showcase/samples/pdf-irs-w4-irs-form-w4.pdf',
        'layout-lists' => $root . '/pandoc-showcase/samples/pdf-layout-unstructured-lists-list-item-example.pdf',
        'layout-multicolumn' => $root . '/pandoc-showcase/samples/pdf-layout-unstructured-multicolumn-multi-column-2p.pdf',
        'muir-brochure' => $root . '/pandoc-showcase/samples/pdf-muir-beach-brochure-muir-beach-brochure.pdf',
        'pictures-captions' => $root . '/pandoc-showcase/samples/pdf-layout-docling-pictures-captions-picture_classification.pdf',
        'quickbooks-invoice' => $root . '/pandoc-showcase/samples/pdf-quickbooks-invoice-template-quickbooks-invoice-template.pdf',
        'right-to-left' => $root . '/pandoc-showcase/samples/pdf-layout-docling-right-to-left-right_to_left_01.pdf',
        'spreadsheet-no-frame' => $root . '/pandoc-showcase/samples/pdf-tabula-spreadsheet-no-frame-crop-table-no-frame.pdf',
        'table-picture-boundary' => $root . '/pandoc-showcase/samples/pdf-layout-docling-table-picture-boundary-table_mislabeled_as_picture.pdf',
        'theatre-script' => $root . '/pandoc-showcase/samples/pdf-layout-vdl-theatre-script-ASC_script_format_example.pdf',
        'multicolumn-table' => $root . '/pandoc-showcase/samples/pdf-tabula-multicolumn-multi-column.pdf',
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

$readPdfCachedSample = static function (string $path, array $options = []) use ($readPdfSample): array {
    static $cache = [];

    ksort($options);
    $key = hash('sha256', $path . "\0" . serialize($options));

    return $cache[$key] ??= $readPdfSample($path, $options);
};

$readPdfMediaSample = static function (string $path, array $options = []): array {
    static $cache = [];

    ksort($options);
    $key = hash('sha256', $path . "\0" . serialize($options));
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $bytes = file_get_contents($path) ?: '';
    $document = (new PdfReader(array_replace([
        'maxTextBytes' => 100000,
        'pdfRepairProseText' => true,
        'pdfGeometryTables' => true,
        'pdfCollectImagePlacements' => true,
    ], $options)))->read($bytes);
    $media = (new PandocMediaExtractor())->extract(
        $document,
        $bytes,
        'pdf',
        ['destination' => 'media', 'imageMode' => 'important']
    );

    return $cache[$key] = [
        'document' => $media['document'],
        'blocks' => PandocConverter::write($media['document'], 'blocks'),
        'entries' => $media['entries'],
        'diagnostics' => $media['diagnostics'],
    ];
};

/** @return list<string> */
$documentHeadingTexts = static function (AstNode $document): array {
    return array_values(array_map(
        static fn (AstNode $heading): string => (string) $heading->attr('text', ''),
        array_filter(
            $document->children(),
            static fn (AstNode $block): bool => $block->type === 'heading'
        )
    ));
};

/** @return list<string> */
$traceMonkeyExpectedCodeListings = static function (): array {
    return [
        <<<'CODE'
1 for (var i = 2; i < 100; ++i) {
2 if (!primes[i])
3     continue;
4 for (var k = i + i; i < 100; k += i)
5     primes[k] = false;
6 }
CODE,
        <<<'CODE'
v0 := ld state[748]      // load primes from the trace activation record
      st sp[0], v0       // store primes to interpreter stack
v1 := ld state[764]      // load k from the trace activation record
v2 := i2f(v1)           // convert k from int to double
      st sp[8], v1       // store k to interpreter stack
      st sp[16], 0       // store false to interpreter stack
v3 := ld v0[4]          // load class word for primes
v4 := and v3, -4        // mask out object class tag for primes
v5 := eq v4, Array       // test whether primes is an array
      xf v5             // side exit if v5 is false
v6 := js_Array_set(v0, v2, false)   // call function to set array element
v7 := eq v6, 0          // test return value from call
      xt v7             // side exit if js_Array_set returns false.
CODE,
        <<<'CODE'
mov edx, ebx(748)       // load primes from the trace activation record
mov edi(0), edx        // (*) store primes to interpreter stack
mov esi, ebx(764)       // load k from the trace activation record
mov edi(8), esi        // (*) store k to interpreter stack
mov edi(16), 0         // (*) store false to interpreter stack
mov eax, edx(4)        // (*) load object class word for primes
and eax, -4            // (*) mask out object class tag for primes
cmp eax, Array         // (*) test whether primes is an array
jne side_exit_1        // (*) side exit if primes is not an array
sub esp, 8             // bump stack for call alignment convention
push false             // push last argument for call
push esi               // push first argument for call
call js_Array_set       // call function to set array element
add esp, 8             // clean up extra stack space
mov ecx, ebx           // (*) created by register allocator
test eax, eax          // (*) test return value of js_Array_set
je side_exit_2         // (*) side exit if call failed
...
side_exit_1:
mov ecx, ebp(-4)        // restore ecx
mov esp, ebp           // restore esp
jmp epilog             // jump to ret statement
CODE,
    ];
};

/** @return array{ast:list<string>,wordpress:list<string>,html:list<string>} */
$editableLineStreams = static function (AstNode $document, string $wordpress, string $html): array {
    $splitLines = static function (string $text): array {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return array_values(array_filter(
            array_map(
                static fn (string $line): string => preg_replace('/^\s+|\s+$/u', '', $line) ?? trim($line),
                explode("\n", $text)
            ),
            static fn (string $line): bool => $line !== ''
        ));
    };
    $serializedLines = static function (string $serialized) use ($splitLines): array {
        $serialized = preg_replace('/<br\b[^>]*\/?\s*>/iu', "\n", $serialized) ?? $serialized;
        $serialized = preg_replace(
            '/<\/?(?:p|pre|div|li|h[1-6]|blockquote|figcaption|td|th|aside|section|article|dt|dd)\b[^>]*>/iu',
            "\n",
            $serialized
        ) ?? $serialized;

        return $splitLines(html_entity_decode(
            strip_tags($serialized),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ));
    };
    $nodeText = static function (AstNode $node) use (&$nodeText): string {
        $text = $node->attr('text');
        if (is_string($text)) {
            return $text;
        }

        return implode('', array_map($nodeText, $node->children()));
    };
    $astLines = [];
    $appendAstLines = static function (AstNode $node) use (&$appendAstLines, &$astLines, $nodeText, $serializedLines, $splitLines): void {
        if ($node->type === 'line_block') {
            foreach ($node->children() as $line) {
                array_push($astLines, ...$splitLines($nodeText($line)));
            }

            return;
        }
        if ($node->type === 'code_block') {
            array_push($astLines, ...$splitLines($nodeText($node)));

            return;
        }
        if (in_array($node->type, ['paragraph', 'plain', 'heading', 'line'], true)) {
            array_push($astLines, ...$splitLines($nodeText($node)));

            return;
        }
        if (in_array($node->type, ['raw_html', 'raw_block'], true)) {
            $raw = (string) $node->attr('html', $node->attr('text', ''));
            array_push($astLines, ...$serializedLines($raw));

            return;
        }
        foreach ($node->children() as $child) {
            $appendAstLines($child);
        }
    };
    $appendAstLines($document);

    return [
        'ast' => $astLines,
        'wordpress' => $serializedLines($wordpress),
        'html' => $serializedLines($html),
    ];
};

$htmlAttributeValue = static function (string $tagOrAttributes, string $name): ?string {
    if (preg_match(
        '/\b' . preg_quote($name, '/') . '\s*=\s*(["\'])(.*?)\1/su',
        $tagOrAttributes,
        $match
    ) !== 1) {
        return null;
    }

    return html_entity_decode($match[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$muirSemanticHeadingTexts = [
    'Muir Beach',
    'A Biodiversity Hotspot',
    'The First Stewards',
    'Portuguese Dairymen',
    'Bygone Days at the Beach',
    'Restoring Ecological Integrity',
    'Make a Difference',
];

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
        foreach ([
            'archive-book',
            'cdc-brochure',
            'grand-canyon-map',
            'irs-w4-form',
            'muir-brochure',
            'quickbooks-invoice',
            'spreadsheet-no-frame',
            'multicolumn-table',
            'tracemonkey-paper',
        ] as $kind) {
            $path = $pdfSamplePaths()[$kind];
            $t->true(is_file($path), "{$kind} PDF fixture should exist.");
            $result = $readPdfSample($path);
            $meta = $result['meta'];

            $t->true(count($result['document']->children) > 0, "{$kind} should produce document blocks.");
            $t->true(($meta['pdfTextLines'] ?? 0) > 0, "{$kind} should expose searchable text lines.");
            $t->true(($meta['pdfTextBytes'] ?? 0) >= 300, "{$kind} should preserve a useful text payload.");
            $t->true(array_key_exists('pdfTableReconstruction', $meta), "{$kind} should report table reconstruction mode.");
            $t->true(array_key_exists('pdfDetectedTables', $meta), "{$kind} should report detected table count.");
            unset($result);
            gc_collect_cycles();
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
            unset($result);
            gc_collect_cycles();
        }
    },
    'pdf corpus gate preserves real invoice and borderless table structure' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfSample, $plainText, $tableRows): void {
        $invoice = $readPdfSample($pdfSamplePaths()['quickbooks-invoice']);
        $invoiceText = $plainText(PandocConverter::write($invoice['document'], 'html'));
        $invoiceTables = array_values(array_filter(
            $invoice['document']->children(),
            static fn (AstNode $node): bool => $node->type === 'table'
        ));
        $invoiceRows = array_map($tableRows, $invoiceTables);
        $t->same(7, $invoice['tables'], 'The two invoice templates should retain their seven editable table sections.');
        $t->same(7, $invoice['meta']['pdfDetectedTables'] ?? null);
        $t->same(7, $invoice['meta']['pdfGeometryTables'] ?? null);
        $t->same(1, $invoice['meta']['pdfLogicalTableCount'] ?? null);
        $t->same(1, $invoice['meta']['pdfLogicalTableFamilyCount'] ?? null);
        $t->same(2, $invoice['meta']['pdfLogicalTableInstanceCount'] ?? null);
        $t->same(7, $invoice['meta']['pdfLogicalTableFamilyPhysicalParts'] ?? null);
        $t->same('geometry', $invoice['meta']['pdfTableReconstruction'] ?? null);
        $t->same(0, $invoice['meta']['pdfDetectedCodeBlocks'] ?? null);
        $t->same([
            [
                ['Enter company name', 'Phone (02) 9999-9999'],
                ['Street address', 'Email name@company.com.au'],
                ['City', 'Website companyname.com.au'],
                ['State, postcode', 'ABN 123456789'],
            ],
            [
                ['Bill to', 'Ship to', 'Details'],
                ['Client name', 'Client name', 'Invoice# 12345'],
                ['Street address', 'Street address', 'Invoice date: dd/mm/yyyy'],
                ['City,', 'City,', 'Terms: Net 30'],
                ['State, postcode', 'State, postcode', 'Due date: dd/mm/yyyy'],
            ],
            [
                ['Enter your product or service description', '0', '0', '$0.00'],
                ['Enter your product or service description', '0', '0', '$0.00'],
            ],
            [
                ['Customer message', 'Subtotal', '$0.00'],
                ['Hi,', 'GST component', '$0.00'],
                ['Thank you for your purchase. Please pay this invoice using the following payment details.', 'Shipping', '$0.00'],
            ],
            [
                ['Bill to', 'Ship to', 'Details'],
                ['Client name', 'Client name', 'Invoice# 12345'],
                ['Street address', 'Street address', 'Invoice date: dd/mm/yyyy'],
                ['City,', 'City,', 'Terms: Net 30'],
                ['State, postcode', 'State, postcode', 'Due date: dd/mm/yyyy'],
            ],
            [
                ['Enter your product or service description', '0', '0', '$0.00'],
                ['Enter your product or service description', '0', '0', '$0.00'],
            ],
            [
                ['Hi,', 'Subtotal', '$0.00'],
                ['Thank you for your purchase. Please pay this invoice using the following payment details.', 'Shipping', '$0.00'],
            ],
        ], $invoiceRows, 'QuickBooks table cells or section order changed.');
        $families = $invoice['meta']['pdfLogicalTableFamilies'] ?? [];
        $t->same(1, count($families));
        $t->same([2, 3], $families[0]['pages'] ?? null);
        $t->same([4, 3], array_map(
            static fn (array $instance): int => (int) ($instance['physicalParts'] ?? 0),
            $families[0]['instances'] ?? []
        ));
        $familyId = $families[0]['id'] ?? null;
        $t->true(is_string($familyId) && preg_match('/^pdf-table-family-[a-f0-9]{20}$/', $familyId) === 1);
        $t->same([$familyId], array_values(array_unique(array_map(
            static fn (AstNode $table): mixed => $table->attr('pdfLogicalTableFamilyId'),
            $invoiceTables
        ), SORT_REGULAR)));
        $t->same([1, 2, 3, 4, 1, 2, 3], array_map(
            static fn (AstNode $table): int => (int) $table->attr('pdfLogicalTablePart'),
            $invoiceTables
        ));
        $t->contains('data-pdf-logical-table-family-id="' . $familyId . '"', $invoice['blocks']);
        $json = (new PandocJsonWriter())->toArray($invoice['document']);
        $jsonTables = array_values(array_filter(
            $json['blocks'] ?? [],
            static fn (mixed $block): bool => is_array($block) && ($block['t'] ?? null) === 'Table'
        ));
        $t->same(7, count($jsonTables));
        $jsonAttributes = json_encode($jsonTables[0]['c'][0][2] ?? [], JSON_UNESCAPED_SLASHES);
        $t->true(is_string($jsonAttributes));
        $t->contains('["data-pdf-logical-table-family-id","' . $familyId . '"]', $jsonAttributes);
        $jsonRoundTripBlocks = PandocConverter::write((new PandocJsonReader())->readPacket($json), 'blocks');
        $t->contains('data-pdf-logical-table-family-id="' . $familyId . '"', $jsonRoundTripBlocks);
        $t->contains('Invoice template', $invoiceText);
        $t->contains('Bill to', $invoiceText);

        $cropTable = $readPdfSample($pdfSamplePaths()['spreadsheet-no-frame']);
        $cropText = $plainText(PandocConverter::write($cropTable['document'], 'html'));
        $cropTables = array_values(array_filter(
            $cropTable['document']->children(),
            static fn (AstNode $node): bool => $node->type === 'table'
        ));
        $cropRows = $tableRows($cropTables[0]);
        $t->same(1, $cropTable['tables']);
        $t->same('HARVEST', $cropRows[0][1] ?? null);
        $t->same(['COTTON', '1.393,4'], array_slice($cropRows[5] ?? [], 0, 2));
        $t->contains('YIELD ESTIMATE', $cropText);
        $t->true(!str_contains($cropTable['blocks'], 'data-pdf-fill-color="#000000"'), 'Thin table rules must not become black cell backgrounds.');
        unset($invoice, $invoiceText, $cropTable, $cropText);
        gc_collect_cycles();
    },
    'pdf corpus gate keeps adjacent numeric table groups together' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfSample): void {
        $table = $readPdfSample($pdfSamplePaths()['multicolumn-table']);

        $t->same(1, $table['tables']);
        $t->same(0, $table['paragraphs']);
        $t->contains('<th>1</th><th>100</th><th>200</th><th>25</th><th>124</th><th>224</th>', $table['blocks']);
        $t->contains('<td>2</td><td>101</td><td>201</td><td>26</td><td>125</td><td>225</td>', $table['blocks']);
        $t->contains('<td>24</td><td>123</td><td>223</td><td></td><td></td><td></td>', $table['blocks']);
        unset($table);
        gc_collect_cycles();
    },
    'pdf corpus gate keeps text only retry prose oriented' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfSample, $documentHeadingTexts, $muirSemanticHeadingTexts): void {
        $cases = [
            'grand-canyon-map' => ['minParagraphs' => 40, 'minHeadings' => 25, 'minTables' => 0],
            'muir-brochure' => ['paragraphs' => 30, 'headingTexts' => $muirSemanticHeadingTexts, 'minTables' => 0],
            'tracemonkey-paper' => ['minParagraphs' => 100, 'minHeadings' => 3, 'minTables' => 1],
        ];

        foreach ($cases as $kind => $expectation) {
            $result = $readPdfSample($pdfSamplePaths()[$kind], ['pdfGeometryTables' => false]);
            $meta = $result['meta'];

            $t->same(0, $meta['pdfGeometryTables'], "{$kind} text-only retry should skip geometry table extraction.");
            $t->same('text', $meta['pdfTableReconstruction'], "{$kind} text-only retry should report text reconstruction.");
            $t->true($result['tables'] >= $expectation['minTables'], "{$kind} text-only retry should preserve text-detected tables.");
            if (isset($expectation['paragraphs'])) {
                $t->same(
                    $expectation['paragraphs'],
                    $result['paragraphs'],
                    "{$kind} text-only retry should retain its deterministic prose boundaries without map glyph inflation."
                );
            } else {
                $t->true($result['paragraphs'] >= $expectation['minParagraphs'], "{$kind} text-only retry should keep prose split into readable paragraphs.");
            }
            if (isset($expectation['headingTexts'])) {
                $t->same(
                    $expectation['headingTexts'],
                    $documentHeadingTexts($result['document']),
                    "{$kind} text-only retry should retain only its semantic document outline."
                );
                $t->same(
                    count($expectation['headingTexts']),
                    $result['headings'],
                    "{$kind} text-only retry should not promote map labels to headings."
                );
            } else {
                $t->true($result['headings'] >= $expectation['minHeadings'], "{$kind} text-only retry should retain heading-like line structure.");
            }
            unset($result);
            gc_collect_cycles();
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
    'pdf corpus gate keeps multi-column map panels and labels as prose' => static function (TestRunner $t) use ($pdfSamplePaths, $plainText, $readPdfSample, $documentHeadingTexts): void {
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
        $t->same([
            'Pocket Map',
            'North Rim Services Guide',
            'Visitor Info Station and Park Store',
            'Lost and Found',
            'Grand Canyon Lodge',
            'Dining Options',
            'Gift Shop',
            'Post Office',
            'Religious Services',
            'Canyon Trail Rides',
            'Services Outside the Park',
            'Kaibab Lodge',
            'North Rim Country Store',
            'Jacob Lake Inn',
            'Kaibab Plateau Visitor Center',
            'Free Park Ranger Programs',
            'North Rim Campground',
            'Service Station',
            'General Store',
            'Backcountry Information Center',
            'Camping Outside the Park',
            'DeMotte Campground',
            'Jacob Lake Campground',
            'Kaibab Camper Village',
            'Dispersed Camping',
            'Trip Planning',
            'Hiking',
            'Cape Royal Road',
            'Grand Canyon National Park Arizona',
            'Trip Planning Continued',
            'Half Day',
            'Hiking',
            'Cape Royal Road',
            'Scenic Drive',
            'Point Imperial',
            'Vista Encantada',
            'Roosevelt Point',
            'Cape Royal',
            'National Park Service',
            'Protect the Park, Protect Yourself',
            'Information',
            'Park Headquarters 928-638-7888',
            'Website nps.gov/grca',
            'Follow Us',
            'Grand Canyon National Park',
            'Emergencies call 911',
            'North Rim Day Hikes',
            'Bright Angel Point Trail',
            'Transept Trail',
            'Bridle Path',
            'Widforss Trail',
            'Uncle Jim Trail',
            'Ken Patrick Trail',
            'Arizona National Scenic Trail',
            'North Kaibab Trail',
            'Roosevelt Point Trail',
            'Cape Final Trail',
            'Cliff Spring Trail',
            'Cape Royal Trail',
        ], $documentHeadingTexts($document), 'Grand Canyon map fragments must not inflate the document outline.');
        foreach (['T', 'Ar', 'OR', 'o Manzanita and Cottonwood Campground'] as $falseHeading) {
            $t->true(
                !str_contains($blocks, '<h2>' . $falseHeading . '</h2>'),
                "Grand Canyon map fragment '{$falseHeading}' must not become a heading."
            );
        }
        foreach ([
            'NPS',
            'EXPERIENCE YOUR AMERICA',
            'To Manzanita and Cottonwood Campground',
            'Arizona National',
            'Scenic Trail',
        ] as $layoutLabel) {
            $t->true(
                !str_contains($blocks, '<h2>' . $layoutLabel . '</h2>'),
                "Grand Canyon layout label '{$layoutLabel}' must remain editable without entering the outline."
            );
        }
        $t->contains('OR Hike the North Kaibab Trail to Coconino Overlook. Hiking into the Canyon offers a different perspective.', $text);
        $t->contains('The Pocket Map is published by Grand Canyon National Park with support from your entrance fees.', $text);
        $t->contains('Food service is available from mid- May to mid-October.', $text);
        $t->contains('For backcountry camping options (permit required) check with the Backcountry Information Center.', $text);
        foreach ([
            'Hiking into 22 feet (6.7 m) prohibited on the roads',
            'Trail south from Point Imperial for a half mile for an easy hike with Parking',
            'S o c n',
        ] as $artifact) {
            $t->true(!str_contains($text, $artifact), "Grand Canyon prose must not retain interleaved map-panel artifact '{$artifact}'.");
        }

        $muir = $readPdfSample($pdfSamplePaths()['muir-brochure']);
        $muirMeta = $muir['meta'];
        $t->same(0, $muirMeta['pdfDetectedTables'], 'Map label fragments must not become Muir brochure tables.');
        $t->same(0, $muirMeta['pdfGeometryTables'], 'Map label fragments must not count as Muir geometry tables.');
        $t->same('text', $muirMeta['pdfTableReconstruction'], 'Muir should use source-reconciled prose after map-label rejection.');
        $t->true(!str_contains($muir['blocks'], '<!-- wp:table -->'));
        $muirText = $plainText(PandocConverter::write($muir['document'], 'html'));
        foreach (['Horses and Hiking only', 'Hiking only'] as $meaningfulMapLabel) {
            $t->contains($meaningfulMapLabel, $muirText, "Muir must retain meaningful map label '{$meaningfulMapLabel}' in an editable semantic carrier.");
        }
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
        $t->contains(
            'mixed- mode execution approach',
            $text,
            'TraceMonkey should retain the source-painted terminal hard hyphen when no exact occurrence disposition authorizes deleting it.'
        );
        $t->contains('register- carried value (6)', $text);
        $t->contains('non- negligible runtime cost', $text);
        foreach (['type-unstable loops'] as $compound) {
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
        $t->contains(
            '[18] T. Suganuma, T. Yasue, and T. Nakatani. A Region-Based Compila- tion Technique for Dynamic Compilers.',
            $text,
            'TraceMonkey must retain a bibliography occurrence when its line-break hyphen cannot be deleted with exact local evidence.'
        );
        foreach ([
            '[7] V. Bala, E. Duesterwald, and S. Banerjia. Dynamo: A transparent ACM Press, 2000.',
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
        $t->contains('After compiling T45, TraceMonkey returns to the interpreter and loops back to line 1. i=3. Now the loop header at line 1 has become hot, so TraceMonkey starts recording.', $blocks);
        $t->contains('nested trace T45. T16 loops back to its own header', $blocks);
        $t->true(!str_contains($blocks, 'T45.T16 loops back to its own header'), 'TraceMonkey must restore numeric sentence boundaries in reconstructed prose.');
        $t->contains('We call the resulting tracing VM TraceMonkey. TraceMonkey supports all the JavaScript features of SpiderMonkey', $blocks);
        $t->true(!str_contains($blocks, 'Trace- Monkey'), 'TraceMonkey must join geometry-confirmed repeated compounds across line-end hyphens.');
        $t->contains('each loop is entered with m different type maps (on geometric average)', $blocks);
        $t->true(!str_contains($blocks, 'each 2 loop'), 'A source-only footnote marker must not acquire fabricated body geometry.');
        $t->contains('As long as m is close to 1, the resulting trace trees will be tractable.', $blocks);
        $t->true(
            !str_contains($blocks, '<p>is close to 1, the resulting trace trees will be tractable.</p>'),
            'TraceMonkey must not retain the second half of a sentence as detached prose.'
        );
        $t->contains('When the VM fails to finish a trace starting at a given point, the VM records that a failure has occurred.', $blocks);
        $t->contains('As future work, this situation could be avoided by detecting and blacklisting loops for which the average trace call executes few bytecodes before returning to the interpreter.', $blocks);
        $t->contains('An important detail is that the call to the inner trace tree must act', $blocks);
        $t->contains('This is the LIR recorded for line 5 of the sample program in Figure 1.', $blocks);
        $t->contains('This is the x86 code compiled from the LIR snippet in Figure 3.', $blocks);
        $t->contains('Some operations on integers require guards.', $blocks);
        $t->contains('the interpreter’s standard call code.', $blocks);
        $t->true(!str_contains($blocks, 'operation in question. Representation specialization:'), 'TraceMonkey inline style boundaries must retain their paragraph break.');
        foreach ([
            'JavaScript, for example, is the de facto standard for client-side web programming and is used for the application logic of browser-based productivity applications',
            'In TraceMonkey, traces are recorded in trace-flavored SSA LIR (low-level intermediate representation).',
            'objects’ rep- resentations are assigned an integer key called the object shape. Thus, the guard is a simple equality check on the object shape.',
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

        $t->same(3, $meta['pdfDetectedCodeBlocks']);
        $t->same(3, substr_count($blocks, '<!-- wp:code -->'));
        $t->contains("1 for (var i = 2; i &lt; 100; ++i) {\n", $blocks);
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
        $t->same(3, substr_count($blocks, '<!-- wp:code -->'));
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
        $t->contains('register- carried value', $blocks);
        $t->contains('stop-the- world mark-and-sweep collector.', $blocks);
        $t->contains('non- negligible runtime cost', $blocks);
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
        $t->same(3, substr_count($blocks, '<!-- wp:code -->'));
    },
    'pdf corpus gate rejects damaged positioned prose streams' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfSample, $documentHeadingTexts, $muirSemanticHeadingTexts, $plainText): void {
        $muir = $readPdfSample($pdfSamplePaths()['muir-brochure'], ['pdfGeometryTables' => false]);
        $meta = $muir['meta'];

        $t->same('text-geometry', $meta['pdfTextRepairSource'], 'Muir brochure text-only retry should use geometry only on pages with coherent coordinates.');
        $muirHeadingTexts = $documentHeadingTexts($muir['document']);
        $t->same(
            $muirSemanticHeadingTexts,
            $muirHeadingTexts,
            'Muir brochure text-only retry should retain its seven semantic headings without splitting visual wraps.'
        );
        $t->same(7, $muir['headings'], 'Muir map labels must not inflate the document outline.');
        $t->same(
            [],
            array_values(array_intersect(['Horses and Hiking only', 'Hiking only'], $muirHeadingTexts)),
            'Muir map legend labels must remain outside the document outline.'
        );
        $t->same(true, $meta['pdfSourceBindingComplete'] ?? null, 'Muir page-local map classification should preserve exact source binding.');
        $t->same(null, $meta['pdfSourceBindingFailureReason'] ?? null);
        $t->same(480, $meta['pdfSourceDisposition']['sourceOccurrenceCount'] ?? null);
        $t->same(480, $meta['pdfSourceDisposition']['sourceEdgeCount'] ?? null);
        $t->same(0, $meta['pdfSourceDisposition']['unresolvedOccurrenceCount'] ?? null);
        $t->same(true, $meta['pdfSourceDisposition']['sourceEdgeMappingComplete'] ?? null);
        $t->same(true, $meta['pdfSourceDisposition']['orderedSignificantCharactersPreserved'] ?? null);
        $t->same(0, $meta['pdfSourceDisposition']['unclaimedEmittedSignificantCharacterCount'] ?? null);
        $t->contains('In collaboration with public agencies and nonprofit partners, the National Park Service implemented a multi-year', $muir['blocks']);
        $t->contains('<h2><strong>Make a Difference</strong></h2>', $muir['blocks']);
        $t->contains('Left, Top &amp; Bottom images: Traditional prayer', $muir['blocks']);
        $t->contains('Left, Top &amp; Bottom images: Traditional prayer led by a representative of the Coast Miwok at the annual Welcome Back Salmon ceremony at Muir Beach.', $muir['blocks']);
        $t->true(!str_contains($muir['blocks'], 'caused it to fill In collaboration'), 'Muir brochure columns must not be merged into one paragraph.');
        $t->true(!str_contains($muir['blocks'], 'caused it to fill</p>'), 'Muir brochure must not emit an unresolved source-only sentence tail.');
        $t->true(!str_contains($muir['blocks'], 'at y a representative of the Coast Miwok'), 'Muir brochure must not repeat a clipped source fragment after a positioned repair line.');
        $t->true(!str_contains($muir['blocks'], 'www.nps.gov/goga Horses and Hiking only'), 'Muir map labels must not continue an unrelated resource list.');
        $t->true(!str_contains($muir['blocks'], '<h2>Hiking only</h2>'), 'Muir map legend labels must not be promoted to document headings.');
        $muirText = $plainText(PandocConverter::write($muir['document'], 'html'));
        foreach (['Horses and Hiking only', 'Hiking only'] as $meaningfulMapLabel) {
            $t->contains($meaningfulMapLabel, $muirText, "Muir must retain meaningful map label '{$meaningfulMapLabel}' without requiring one serialization class.");
        }
        preg_match_all('/<p\b[^>]*>(.*?)<\/p>/su', $muir['blocks'], $paragraphMatches);
        foreach ($paragraphMatches[1] ?? [] as $paragraphHtml) {
            $paragraphText = trim(html_entity_decode(strip_tags($paragraphHtml), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            $t->true(
                mb_strlen($paragraphText, 'UTF-8') > 3,
                'Muir map glyph fragments must not survive as one-to-three-character paragraphs: ' . $paragraphText
            );
        }
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
        $cdc = $readPdfSample(
            $pdfSamplePaths()['cdc-brochure'],
            ['pdfCollectImagePlacements' => true]
        );
        $cdcMeta = $cdc['meta'];

        $t->same(0, $cdcMeta['pdfDetectedTables'], 'CDC brochure should not become a false table.');
        $t->same(0, $cdcMeta['pdfDetectedCodeBlocks'], 'CDC brochure columns should not become false code listings.');
        $t->same(true, $cdcMeta['pdfSourceBindingComplete'] ?? null, 'CDC brochure source ranges should bind exactly after visual column ordering.');
        $t->same(0, $cdcMeta['pdfSourceDisposition']['unresolvedOccurrenceCount'] ?? null, 'Every CDC source occurrence should have an exact output edge or explicit visual disposition.');
        $t->same(true, $cdcMeta['pdfSourceDisposition']['sourceEdgeMappingComplete'] ?? null);
        $t->same(160, $cdcMeta['pdfSourceDisposition']['sourceOccurrenceCount'] ?? null);
        $t->same(160, $cdcMeta['pdfSourceDisposition']['sourceEdgeCount'] ?? null);
        $t->same(8, $cdcMeta['pdfSourceDisposition']['dispositionCounts']['artifact'] ?? null);
        $clippedDisplayEdges = array_values(array_filter(
            $cdcMeta['pdfSourceDisposition']['sourceEdges'] ?? [],
            static fn (array $edge): bool =>
                ($edge['sourceOccurrenceId'] ?? null) === 'line-309ef35696bcb5ea818b4cee'
        ));
        $t->same(1, count($clippedDisplayEdges));
        $t->same('artifact', $clippedDisplayEdges[0]['disposition'] ?? null);
        $t->same('disposition', $clippedDisplayEdges[0]['target'] ?? null);
        $t->same('explicit-disposition', $clippedDisplayEdges[0]['mappingMode'] ?? null);
        $t->same([], $clippedDisplayEdges[0]['destinationNodeIds'] ?? null);
        $crossLaneDisplayEdges = [];
        foreach ([
            'line-c24b0c5904cf2922d7484802',
            'line-0db4ee00963ae0c245252253',
            'line-f277678a16b58440136fa27b',
        ] as $sourceOccurrenceId) {
            $matches = array_values(array_filter(
                $cdcMeta['pdfSourceDisposition']['sourceEdges'] ?? [],
                static fn (array $edge): bool =>
                    ($edge['sourceOccurrenceId'] ?? null) === $sourceOccurrenceId
            ));
            $t->same(1, count($matches));
            $crossLaneDisplayEdges[] = $matches[0];
        }
        $t->same(
            ['artifact', 'boundary-repair', 'boundary-repair'],
            array_column($crossLaneDisplayEdges, 'disposition'),
            'The rotated display lead and both interleaved display prefixes need explicit exact-source dispositions.'
        );
        $t->same(
            ['explicit-disposition', 'exact-authorized-scope', 'exact-authorized-scope'],
            array_column($crossLaneDisplayEdges, 'mappingMode'),
            'The decorative lead is suppressed while both right-lane prose suffixes remain exactly output-bound.'
        );
        $clippedArtifactMediaAnchorProofs = $cdcMeta['pdfClippedDisplayArtifactMediaAnchorProofs'] ?? [];
        $t->same(
            0,
            $cdcMeta['pdfClippedDisplayArtifactMediaAnchorProofTruncatedCount'] ?? null,
            'The CDC clipped-display media bridge proof inventory should be complete.'
        );
        $t->same(1, count($clippedArtifactMediaAnchorProofs), 'CDC should expose one clipped-display media bridge proof.');
        $t->same(
            'line-309ef35696bcb5ea818b4cee',
            $clippedArtifactMediaAnchorProofs[0]['artifactSourceOccurrenceId'] ?? null
        );
        $t->same(
            'line-6c1d07ed719933a6fba5984d',
            $clippedArtifactMediaAnchorProofs[0]['counterpartSourceOccurrenceId'] ?? null
        );
        $t->same(
            'pdf-source-node-c742667268f0e3978f53a94e19f835c5',
            $clippedArtifactMediaAnchorProofs[0]['counterpartDestinationNodeId'] ?? null
        );
        $clippedArtifactPlacementFacts = [];
        foreach ($cdcMeta['pdfImagePlacements'] ?? [] as $placement) {
            foreach (['preceding', 'following'] as $side) {
                if (($placement[$side . 'Text'] ?? null) !== 'aves Lives') {
                    continue;
                }
                $clippedArtifactPlacementFacts[] = [
                    'object' => $placement['object'] ?? null,
                    'side' => $side,
                    'sourceOccurrenceId' => $placement[$side . 'SourceOccurrenceId'] ?? null,
                    'projectionDigest' => $placement[$side . 'SourceProjectionDigest'] ?? null,
                ];
            }
        }
        usort(
            $clippedArtifactPlacementFacts,
            static fn (array $left, array $right): int => ($left['object'] ?? 0) <=> ($right['object'] ?? 0)
        );
        $t->same(7, count($clippedArtifactPlacementFacts), 'Every CDC image beside the clipped display text should retain its exact source anchor.');
        $t->same([
            [
                'object' => 213,
                'side' => 'preceding',
                'sourceOccurrenceId' => 'line-309ef35696bcb5ea818b4cee',
                'projectionDigest' => 'ba6c9c7d996c73810f0b209fb8f4e20b9e9a152bf8db46f55172516153875309',
            ],
            [
                'object' => 219,
                'side' => 'preceding',
                'sourceOccurrenceId' => 'line-309ef35696bcb5ea818b4cee',
                'projectionDigest' => 'ba6c9c7d996c73810f0b209fb8f4e20b9e9a152bf8db46f55172516153875309',
            ],
            [
                'object' => 220,
                'side' => 'preceding',
                'sourceOccurrenceId' => 'line-309ef35696bcb5ea818b4cee',
                'projectionDigest' => 'ba6c9c7d996c73810f0b209fb8f4e20b9e9a152bf8db46f55172516153875309',
            ],
            [
                'object' => 226,
                'side' => 'preceding',
                'sourceOccurrenceId' => 'line-309ef35696bcb5ea818b4cee',
                'projectionDigest' => 'ba6c9c7d996c73810f0b209fb8f4e20b9e9a152bf8db46f55172516153875309',
            ],
            [
                'object' => 243,
                'side' => 'following',
                'sourceOccurrenceId' => 'line-309ef35696bcb5ea818b4cee',
                'projectionDigest' => 'ba6c9c7d996c73810f0b209fb8f4e20b9e9a152bf8db46f55172516153875309',
            ],
            [
                'object' => 247,
                'side' => 'following',
                'sourceOccurrenceId' => 'line-309ef35696bcb5ea818b4cee',
                'projectionDigest' => 'ba6c9c7d996c73810f0b209fb8f4e20b9e9a152bf8db46f55172516153875309',
            ],
            [
                'object' => 248,
                'side' => 'following',
                'sourceOccurrenceId' => 'line-309ef35696bcb5ea818b4cee',
                'projectionDigest' => 'ba6c9c7d996c73810f0b209fb8f4e20b9e9a152bf8db46f55172516153875309',
            ],
        ], $clippedArtifactPlacementFacts, 'CDC clipped-display image anchors should retain their exact painted side and source proof.');

        $cdcBytes = file_get_contents($pdfSamplePaths()['cdc-brochure']) ?: '';
        $t->true($cdcBytes !== '', 'The CDC media bridge regression requires the original PDF bytes.');
        $mediaExtractor = new \PortLibs\Pandoc\PandocMediaExtractor();
        $validatedBridgeContext = (function (AstNode $document, string $bytes): array {
            return $this->validatedPdfClippedArtifactMediaAnchorContext($document, $bytes);
        })->bindTo($mediaExtractor, \PortLibs\Pandoc\PandocMediaExtractor::class);
        $validatedBridgeSide = (function (
            array $placement,
            int $page,
            array $context
        ): ?string {
            return $this->validatedPdfClippedArtifactMediaAnchorSide(
                $placement,
                $page,
                $context
            );
        })->bindTo($mediaExtractor, \PortLibs\Pandoc\PandocMediaExtractor::class);
        $sourceEdgeDigest = (function (array $edges): string {
            return $this->pdfSourceDispositionEdgeDigest($edges);
        })->bindTo($mediaExtractor, \PortLibs\Pandoc\PandocMediaExtractor::class);
        $runAnchoring = (function (
            AstNode $document,
            array $placements,
            string $bytes
        ): array {
            $diagnostics = [];
            $dispositions = [];
            $placements = $this->pdfImageOccurrencesWithStableIdentity($placements);
            $anchored = $this->anchoredPdfImagePlacements(
                $document,
                $placements,
                $diagnostics,
                $dispositions,
                'important',
                $bytes
            );

            return [
                'anchored' => $anchored,
                'diagnostics' => $diagnostics,
                'dispositions' => array_values($dispositions),
            ];
        })->bindTo($mediaExtractor, \PortLibs\Pandoc\PandocMediaExtractor::class);
        $documentWithMeta = static function (
            AstNode $document,
            array $meta,
            ?array $children = null
        ): AstNode {
            $attrs = $document->attrs;
            $attrs['meta'] = $meta;

            return new AstNode(
                $document->type,
                $attrs,
                $children ?? $document->children
            );
        };
        $resignBridgeProof = static function (array $proof): array {
            unset($proof['proofDigest']);
            $encoded = json_encode(
                $proof,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
            );
            $proof['proofDigest'] = hash(
                'sha256',
                is_string($encoded) ? $encoded : serialize($proof)
            );

            return $proof;
        };
        $t->true($validatedBridgeContext instanceof \Closure);
        $t->true($validatedBridgeSide instanceof \Closure);
        $t->true($sourceEdgeDigest instanceof \Closure);
        $t->true($runAnchoring instanceof \Closure);

        $bridgeContext = $validatedBridgeContext($cdc['document'], $cdcBytes);
        $t->same(1, count($bridgeContext), 'The complete CDC bridge graph should validate once for all placements.');
        $artifactPlacement = null;
        foreach ($cdcMeta['pdfImagePlacements'] ?? [] as $placement) {
            if (($placement['object'] ?? null) === 213) {
                $artifactPlacement = $placement;
                break;
            }
        }
        $t->true(is_array($artifactPlacement));
        $artifactPlacement = is_array($artifactPlacement) ? $artifactPlacement : [];
        $t->same(
            'preceding',
            $validatedBridgeSide($artifactPlacement, 1, $bridgeContext),
            'The consumer should bind the bridge to the exact selected placement side.'
        );

        $forgedAnchorText = $artifactPlacement;
        $forgedAnchorText['precedingText'] = 'completely forged text';
        $t->same(
            null,
            $validatedBridgeSide($forgedAnchorText, 1, $bridgeContext),
            'A carried occurrence ID and digest must not authorize different anchor text.'
        );
        $duplicatedAnchorSide = $artifactPlacement;
        foreach (['Text', 'SourceOccurrenceId', 'SourceProjectionDigest'] as $suffix) {
            $duplicatedAnchorSide['following' . $suffix] = $artifactPlacement['preceding' . $suffix];
        }
        $t->same(
            null,
            $validatedBridgeSide($duplicatedAnchorSide, 1, $bridgeContext),
            'Conflicting preceding and following claims must not select a bridge side.'
        );

        $missingTruncationMeta = $cdcMeta;
        unset($missingTruncationMeta['pdfClippedDisplayArtifactMediaAnchorProofTruncatedCount']);
        $t->same(
            [],
            $validatedBridgeContext(
                $documentWithMeta($cdc['document'], $missingTruncationMeta),
                $cdcBytes
            ),
            'A bridge proof inventory without an explicit zero truncation count must fail closed.'
        );
        $duplicateProofMeta = $cdcMeta;
        $duplicateProofMeta['pdfClippedDisplayArtifactMediaAnchorProofs'][] =
            $duplicateProofMeta['pdfClippedDisplayArtifactMediaAnchorProofs'][0];
        $t->same(
            [],
            $validatedBridgeContext($documentWithMeta($cdc['document'], $duplicateProofMeta), $cdcBytes),
            'A duplicate bridge key must invalidate the complete proof inventory.'
        );
        $staleSourceMeta = $cdcMeta;
        $staleProof = $staleSourceMeta['pdfClippedDisplayArtifactMediaAnchorProofs'][0];
        $staleProof['sourceSha256'] = str_repeat('0', 64);
        $staleSourceMeta['pdfClippedDisplayArtifactMediaAnchorProofs'][0] =
            $resignBridgeProof($staleProof);
        $t->same(
            [],
            $validatedBridgeContext($documentWithMeta($cdc['document'], $staleSourceMeta), $cdcBytes),
            'A self-consistent proof for different PDF bytes must not be replayed.'
        );
        $duplicateEdgeMeta = $cdcMeta;
        $duplicateEdgeMeta['pdfSourceDisposition']['sourceEdges'][] = $clippedDisplayEdges[0];
        $duplicateEdgeCount = count($duplicateEdgeMeta['pdfSourceDisposition']['sourceEdges']);
        $duplicateEdgeMeta['pdfSourceDisposition']['sourceEdgeCount'] = $duplicateEdgeCount;
        $duplicateEdgeMeta['pdfSourceDisposition']['sourceOccurrenceCount'] = $duplicateEdgeCount;
        $duplicateEdgeMeta['pdfSourceDisposition']['sourceEdgeDigest'] = $sourceEdgeDigest(
            $duplicateEdgeMeta['pdfSourceDisposition']['sourceEdges']
        );
        $t->same(
            [],
            $validatedBridgeContext($documentWithMeta($cdc['document'], $duplicateEdgeMeta), $cdcBytes),
            'A duplicate source occurrence must fail even when ledger counts and digest are recomputed.'
        );

        $proof = $clippedArtifactMediaAnchorProofs[0];
        $counterpartNodeId = $proof['counterpartDestinationNodeId'];
        $counterpartSourceId = $proof['counterpartSourceOccurrenceId'];
        $counterpartIndex = null;
        $patientGuideIndex = null;
        foreach ($cdc['document']->children as $index => $block) {
            if ($block->attr('sourceNodeId') === $counterpartNodeId) {
                $counterpartIndex = $index;
            }
            if ($block->attr('text') === 'A Patient’s Guide') {
                $patientGuideIndex = $index;
            }
        }
        $t->true(is_int($counterpartIndex));
        $t->true(is_int($patientGuideIndex));
        $t->true(
            is_int($counterpartIndex)
                && is_int($patientGuideIndex)
                && $counterpartIndex < $patientGuideIndex,
            'The complete counterpart is remote from the page-region insertion boundary.'
        );
        $counterpartIndex = is_int($counterpartIndex) ? $counterpartIndex : 0;
        $patientGuideIndex = is_int($patientGuideIndex) ? $patientGuideIndex : 0;

        $forgedDestinationChildren = $cdc['document']->children;
        $destination = $forgedDestinationChildren[$counterpartIndex];
        $destinationAttrs = $destination->attrs;
        $destinationAttrs['sourceLineIds'][] = 'forged-source-claim';
        $forgedDestinationChildren[$counterpartIndex] = new AstNode(
            $destination->type,
            $destinationAttrs,
            $destination->children
        );
        $t->same(
            [],
            $validatedBridgeContext(
                $documentWithMeta($cdc['document'], $cdcMeta, $forgedDestinationChildren),
                $cdcBytes
            ),
            'Destination sourceLineIds must be derived exactly from its signed sourceLineEdges.'
        );

        $duplicateClaimChildren = $cdc['document']->children;
        $claimant = $duplicateClaimChildren[$patientGuideIndex];
        $claimantAttrs = $claimant->attrs;
        $claimantEdges = $claimant->attr('sourceLineEdges', []);
        $claimantEdges[] = [
            'sourceLineId' => $counterpartSourceId,
            'startByte' => 0,
            'endByte' => 1,
        ];
        $claimantSourceIds = $claimant->attr('sourceLineIds', []);
        $claimantSourceIds[] = $counterpartSourceId;
        $claimantIdentity = json_encode(
            ['type' => $claimant->type, 'sourceLineEdges' => $claimantEdges],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $claimantAttrs['sourceNodeId'] = 'pdf-source-node-' . substr(hash(
            'sha256',
            is_string($claimantIdentity) ? $claimantIdentity : serialize($claimantEdges)
        ), 0, 32);
        $claimantAttrs['sourceLineIds'] = $claimantSourceIds;
        $claimantAttrs['sourceLineEdges'] = $claimantEdges;
        $duplicateClaimChildren[$patientGuideIndex] = new AstNode(
            $claimant->type,
            $claimantAttrs,
            $claimant->children
        );
        $t->same(
            [],
            $validatedBridgeContext(
                $documentWithMeta($cdc['document'], $cdcMeta, $duplicateClaimChildren),
                $cdcBytes
            ),
            'A second top-level block claiming the complete counterpart must invalidate the bridge.'
        );

        $nestedDestinationChildren = $cdc['document']->children;
        $nestedDestinationChildren[$counterpartIndex] = new AstNode(
            'div',
            [],
            [$nestedDestinationChildren[$counterpartIndex]]
        );
        $t->same(
            [],
            $validatedBridgeContext(
                $documentWithMeta($cdc['document'], $cdcMeta, $nestedDestinationChildren),
                $cdcBytes
            ),
            'A counterpart destination available only below the top level must not authorize placement.'
        );

        $withoutRegionEvidence = $runAnchoring(
            $cdc['document'],
            [$artifactPlacement],
            $cdcBytes
        );
        $t->same([], $withoutRegionEvidence['anchored']);
        $t->same(
            ['image-mode-no-semantic-region-anchor'],
            array_column($withoutRegionEvidence['dispositions'], 'reason'),
            'A valid text disposition bridge must not place an image without independent page-region evidence.'
        );

        $uniqueCounterpartChildren = array_values(array_filter(
            $cdc['document']->children,
            static fn (AstNode $block): bool =>
                $block->attr('text') !== 'Remember: Hand hygiene saves lives.'
        ));
        $uniqueCounterpartDocument = $documentWithMeta(
            $cdc['document'],
            $cdcMeta,
            $uniqueCounterpartChildren
        );
        $uniqueCounterpartAnchoring = $runAnchoring(
            $uniqueCounterpartDocument,
            $cdcMeta['pdfImagePlacements'],
            $cdcBytes
        );
        $uniqueTargetPlacements = array_values(array_filter(
            $uniqueCounterpartAnchoring['anchored'],
            static fn (array $placement): bool =>
                in_array($placement['object'] ?? null, [213, 219, 220, 226], true)
        ));
        $t->same([213, 219, 220, 226], array_column($uniqueTargetPlacements, 'object'));
        $t->same(
            array_fill(0, 4, 'clipped-artifact-counterpart-page-region-y-paint'),
            array_column($uniqueTargetPlacements, 'anchorEvidence'),
            'The sole counterpart substring must be suppressed as a semantic anchor and use region evidence.'
        );
        $t->same(
            array_fill(0, 4, $patientGuideIndex),
            array_column($uniqueTargetPlacements, 'anchorIndex'),
            'The remote complete counterpart must not become the image insertion anchor.'
        );

        $mediaResult = $mediaExtractor->extract(
            $cdc['document'],
            $cdcBytes,
            'pdf',
            ['destination' => 'media', 'imageMode' => 'important']
        );
        $mediaMeta = $mediaResult['document']->attr('meta', []);
        $targetDispositions = array_values(array_filter(
            $mediaMeta['pdfMediaOccurrenceDispositions'] ?? [],
            static fn (array $disposition): bool =>
                in_array($disposition['object'] ?? null, [213, 219, 220, 226], true)
        ));
        $t->same([213, 219, 220, 226], array_column($targetDispositions, 'object'));
        $t->same(array_fill(0, 4, 'resolved'), array_column($targetDispositions, 'disposition'));
        $t->same(
            array_fill(0, 4, $patientGuideIndex),
            array_column($targetDispositions, 'anchorIndex')
        );
        $t->true(
            !in_array($counterpartIndex, array_column($targetDispositions, 'anchorIndex'), true),
            'Resolved media occurrences must not use the remote acknowledgement node as their anchor.'
        );
        $bridgeDiagnostics = array_values(array_filter(
            $mediaResult['diagnostics'],
            static fn (string $diagnostic): bool =>
                str_starts_with($diagnostic, 'extract-media-pdf-image-clipped-artifact-anchor:')
        ));
        $t->same([
            'extract-media-pdf-image-clipped-artifact-anchor:213:page-1',
            'extract-media-pdf-image-clipped-artifact-anchor:219:page-1',
            'extract-media-pdf-image-clipped-artifact-anchor:220:page-1',
            'extract-media-pdf-image-clipped-artifact-anchor:226:page-1',
        ], $bridgeDiagnostics);
        $targetMediaSources = array_values(array_filter(
            array_column($mediaResult['entries'], 'source'),
            static fn (string $source): bool => in_array($source, [
                'pdf/image-213.jpg',
                'pdf/image-219.jp2',
                'pdf/image-220.jpg',
                'pdf/image-226.jpg',
            ], true)
        ));
        $t->same([
            'pdf/image-213.jpg',
            'pdf/image-219.jp2',
            'pdf/image-220.jpg',
            'pdf/image-226.jpg',
        ], $targetMediaSources);
        $targetOutputObjects = [];
        $counterpartOutputIndex = null;
        $patientGuideOutputIndex = null;
        foreach ($mediaResult['document']->children as $index => $block) {
            if ($block->attr('sourceNodeId') === $counterpartNodeId) {
                $counterpartOutputIndex = $index;
            }
            if ($block->attr('text') === 'A Patient’s Guide') {
                $patientGuideOutputIndex = $index;
            }
            $attributes = $block->attr('attributes', []);
            $object = is_array($attributes)
                && is_string($attributes['data-pandoc-pdf-image-object'] ?? null)
                    ? (int) $attributes['data-pandoc-pdf-image-object']
                    : null;
            if (is_int($object) && in_array($object, [213, 219, 220, 226], true)) {
                $targetOutputObjects[$index] = $object;
            }
        }
        $t->same([213, 219, 220, 226], array_values($targetOutputObjects));
        $t->true(
            is_int($counterpartOutputIndex)
                && is_int($patientGuideOutputIndex)
                && $counterpartOutputIndex < $patientGuideOutputIndex
                && $targetOutputObjects !== []
                && $patientGuideOutputIndex < min(array_keys($targetOutputObjects)),
            'The extracted tiles should remain after the local guide boundary, not beside the remote counterpart.'
        );
        $pageTwoRangeProofs = array_values(array_filter(
            $cdcMeta['pdfSourceOrderProofDiagnostics'] ?? [],
            static fn (array $diagnostic): bool =>
                ($diagnostic['method'] ?? null) === 'exact-positioned-source-subrange-order'
                    && ($diagnostic['page'] ?? null) === 2
                    && ($diagnostic['projectionMatches'] ?? false) === true
        ));
        $t->same(1, count($pageTwoRangeProofs), 'CDC page two should retain one exact positioned source-subrange order proof.');
        $t->true(($pageTwoRangeProofs[0]['mappedSourceRangeCount'] ?? 0) > 111, 'Interleaved CDC source records should map through finer-than-occurrence ranges.');
        $t->true($cdc['lists'] >= 12, 'CDC brochure should preserve visible bullet lists.');
        $t->true($cdc['headings'] >= 6, 'CDC brochure should retain prominent heading-like text without splitting wrapped headings.');
        $t->true($cdc['paragraphs'] >= 24, 'CDC brochure should preserve its prose groups without emitting every visual line as a paragraph.');

        $cdcText = $plainText(PandocConverter::write($cdc['document'], 'html'));
        $t->contains('To prevent hospital infections.', $cdcText);
        $t->contains('You should practice hand hygiene:', $cdcText);
        $t->contains('Before preparing or eating food.', $cdcText);
        // The source text layer paints this ordinary sentence boundary as
        // `restroom.Your`. It must be recovered by the general punctuation
        // boundary path, not a vocabulary entry for the following word.
        $t->contains('after using the restroom. Your healthcare provider should practice hand hygiene', $cdcText);
        $t->true(!str_contains($cdcText, 'restroom.Your healthcare provider'));
        $t->contains('To prevent hospital infections.', $cdc['blocks']);
        $t->contains('You should practice hand hygiene:', $cdc['blocks']);
        $t->contains('Healthcare providers should practice hand hygiene:', $cdc['blocks']);
        $t->contains('You can take action by practicing hand hygiene regularly and by asking those around you to practice it as well.', $cdcText);
        $t->contains('Remember: Hand hygiene saves lives.', $cdcText);
        $t->contains('Remember: It only takes 15 seconds to protect yourself and others.', $cdcText);
        $t->contains('Hand hygiene is one of the most important ways to prevent the spread of infections, including the common cold, flu, and even hard-to-treat infections, such as methicillin-resistant Staphylococcus aureus, or MRSA.', $cdcText);
        $t->contains('Wet your hands with warm water.', $cdcText);
        $t->contains('Apply a nickel-or quarter-sized amount of soap to your hands.', $cdcText);
        $cdcOrderedLists = array_values(array_filter(
            $cdc['document']->children(),
            static fn (AstNode $block): bool => $block->type === 'ordered_list'
        ));
        $t->same(3, count($cdcOrderedLists), 'CDC brochure should retain its three visible ordered-list runs.');
        $t->same(
            [1, 2, 1],
            array_map(static fn (AstNode $list): int => (int) $list->attr('start', 1), $cdcOrderedLists),
            'A split list beginning with visible marker 2 must not restart at 1.'
        );
        $t->contains('<!-- wp:list {"ordered":true,"start":2} -->', $cdc['blocks']);
        $t->contains('<ol start="2"><li>Rub your hands together until soap forms a lather', $cdc['blocks']);
        $cdcHeadingTexts = array_values(array_map(
            static fn (AstNode $heading): string => (string) $heading->attr('text', ''),
            array_filter(
                $cdc['document']->children(),
                static fn (AstNode $block): bool => $block->type === 'heading'
            )
        ));
        $t->true(!in_array('they examine you. }', $cdcHeadingTexts, true));
        $t->true(!str_contains($cdc['blocks'], '<h2>they examine you.'));
        $t->contains(
            '<p>Remember: Ask your doctors and nurses to clean their hands before they examine you.</p>',
            $cdc['blocks']
        );
        $t->true(!str_contains($cdc['blocks'], 'they examine you. }</p>'));
        $t->contains('Cleansing hands using an alcohol-based hand rub.', $cdcText);
        $t->true(!str_contains($cdcText, 'alcoholbased'), 'A wrapped semantic compound must retain its document-proven separator.');
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
        $w4HeadingTexts = array_values(array_map(
            static fn (AstNode $heading): string => (string) $heading->attr('text', ''),
            array_filter(
                $w4['document']->children(),
                static fn (AstNode $block): bool => $block->type === 'heading'
            )
        ));
        $t->same([
            'Form W-4',
            'General Instructions',
            'Future Developments',
            'Purpose of Form',
            'Specific Instructions',
        ], $w4HeadingTexts, 'IRS form fields and running furniture must not inflate the document outline.');
        $t->same(5, $w4['headings'], 'Only the five semantic W-4 document headings should remain.');
        $w4ParagraphTexts = array_values(array_map(
            static fn (AstNode $paragraph): string => (string) $paragraph->attr('text', ''),
            array_filter(
                $w4['document']->children(),
                static fn (AstNode $block): bool => $block->type === 'paragraph'
            )
        ));
        $t->same(
            138,
            count($w4ParagraphTexts),
            'W-4 prose, editable labels, one folded caution, and 34 source-proven form rows should retain deterministic boundaries.'
        );
        $t->same(
            34,
            count(array_filter(
                $w4ParagraphTexts,
                static fn (string $text): bool => str_ends_with(trim($text), '$')
            )),
            'Each repeated standalone currency field should attach backward to exactly one form row.'
        );
        $t->same(
            [],
            array_values(array_filter(
                $w4ParagraphTexts,
                static fn (string $text): bool => preg_match('/^\p{Sc}$/u', trim($text)) === 1
            )),
            'A proved form currency field must not remain as a one-character paragraph.'
        );
        $t->same([
            'Step 2:',
            'Step 3:',
            'Step 4:',
            'Step 5:',
            'Step 4.',
        ], array_values(array_filter(
            $w4ParagraphTexts,
            static fn (string $text): bool => preg_match('/^Step (?:[2-5]:|4\.)$/u', trim($text)) === 1
        )), 'Form-row repair must not absorb instructional Step boundaries.');
        $t->same([], array_values(array_filter(
            $w4ParagraphTexts,
            static fn (string $text): bool => in_array(trim($text), ['4(c)', '1b', '3b', '6c', '10', '12', '14'], true)
        )), 'A detached row identifier should attach backward only inside its exact dotted-row/currency triad.');
        $t->same(
            1,
            count(array_filter(
                $w4ParagraphTexts,
                static fn (string $text): bool => str_starts_with(
                    $text,
                    'Privacy Act and Paperwork Reduction Act Notice.'
                )
            )),
            'The Privacy Act notice should begin its own paragraph after the final form field.'
        );
        $t->same(true, $w4Meta['pdfSourceBindingComplete'] ?? null);
        $t->same(true, $w4Meta['pdfSemanticTextComplete'] ?? null);
    },
    'pdf pre-fix regression preserves all three TraceMonkey listings as complete semantic code blocks' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample, $traceMonkeyExpectedCodeListings): void {
        // Exercise the same geometry-enabled path used by the published
        // showcase. The text-only fallback has separate coverage above, but
        // it cannot protect the production composition path from dropping a
        // complete listing while retaining only its caption.
        $result = $readPdfCachedSample($pdfSamplePaths()['tracemonkey-paper']);
        $document = $result['document'];
        $blocks = $result['blocks'];
        preg_match_all('/<pre class="wp-block-code"><code>(.*?)<\/code><\/pre>/su', $blocks, $matches);
        $codeListings = array_map(
            static fn (string $code): string => str_replace(
                ["\r\n", "\r"],
                "\n",
                html_entity_decode($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            ),
            $matches[1] ?? []
        );

        $t->same(
            $traceMonkeyExpectedCodeListings(),
            $codeListings,
            'Figures 1, 3, and 4 must retain source-grounded newlines, indentation, and comment-column alignment.'
        );
        $t->same(3, $document->attr('meta')['pdfDetectedCodeBlocks'] ?? null);
        $t->same(3, substr_count($blocks, '<!-- wp:code -->'));
    },
    'pdf pre-fix regression keeps the six-line TraceMonkey Figure 1 listing adjacent to its caption' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample, $traceMonkeyExpectedCodeListings): void {
        $result = $readPdfCachedSample($pdfSamplePaths()['tracemonkey-paper']);
        $blocks = $result['blocks'];
        $figureOneHtml = htmlspecialchars($traceMonkeyExpectedCodeListings()[0], ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $t->true(
            preg_match(
                '/<pre class="wp-block-code"><code>' . preg_quote($figureOneHtml, '/') . '<\/code><\/pre>\s*<!-- \/wp:code -->\s*<!-- wp:paragraph -->\s*<p>Figure 1\. Sample program: sieve of Eratosthenes\./su',
                $blocks
            ) === 1,
            'The exact six source rows, including indentation, must be one code block immediately followed by the Figure 1 caption.'
        );
    },
    'pdf pre-fix regression removes TraceMonkey chart-font cipher paragraphs' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample, $plainText): void {
        $result = $readPdfCachedSample($pdfSamplePaths()['tracemonkey-paper']);
        $text = $plainText(PandocConverter::write($result['document'], 'html'));

        foreach ([
            '? >9@AJ',
            '/8A>98 FG8E',
            '1@>8:',
            '#3$4',
            'KA>29:92># L<ID2#',
            '!"# $!"# %!"#',
            '! "# $! "# %! "#',
            'K? </676/<# L5? ><56#',
        ] as $cipher) {
            $t->true(!str_contains($text, $cipher), "TraceMonkey chart-font cipher '{$cipher}' must not enter editable prose.");
        }
        $meta = $result['document']->attr('meta');
        $disposition = is_array($meta['pdfSourceDisposition'] ?? null)
            ? $meta['pdfSourceDisposition']
            : [];
        $t->same(true, $meta['pdfSourceBindingComplete'] ?? null);
        $t->same(true, $meta['pdfSemanticTextComplete'] ?? null);
        $t->same(0, $disposition['unresolvedOccurrenceCount'] ?? null);
        $t->same(0, $disposition['unclaimedEmittedTokenCount'] ?? null);
    },
    'pdf pre-fix regression removes detached TraceMonkey author-symbol paragraphs' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample): void {
        $result = $readPdfCachedSample($pdfSamplePaths()['tracemonkey-paper'], ['pdfGeometryTables' => false]);
        $blocks = $result['blocks'];

        $t->true(
            preg_match('/<p>\s*(?:∗|#|\$|\+)\s*<\/p>/u', $blocks) === 0,
            'Author affiliation marks must stay attached to the author or affiliation instead of becoming lone paragraphs.'
        );
    },
    'pdf pre-fix regression keeps the full TraceMonkey title in one H1 and affiliations out of the outline' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample, $documentHeadingTexts): void {
        $result = $readPdfCachedSample($pdfSamplePaths()['tracemonkey-paper'], ['pdfGeometryTables' => false]);
        $blocks = $result['blocks'];
        $headings = $documentHeadingTexts($result['document']);

        $t->same(1, substr_count($blocks, '<!-- wp:heading {"level":1} -->'), 'TraceMonkey should have exactly one document-title H1.');
        $t->contains('<h1>Trace-based Just-in-Time Type Specialization for Dynamic Languages</h1>', $blocks);
        foreach (['Languages', 'Mozilla Corporation', 'Adobe Corporation', 'Intel Corporation'] as $falseHeading) {
            $t->true(!in_array($falseHeading, $headings, true), "TraceMonkey affiliation fragment '{$falseHeading}' must not enter the outline.");
        }
    },
    'pdf pre-fix regression preserves all TraceMonkey affiliation email at-signs' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample, $plainText): void {
        $result = $readPdfCachedSample($pdfSamplePaths()['tracemonkey-paper'], ['pdfGeometryTables' => false]);
        $text = $plainText(PandocConverter::write($result['document'], 'html'));

        foreach ([
            '{gal, brendan, shaver, danderson, dmandelin, mrbkap, graydon, bz, jorendorff, jruderman}@mozilla.com',
            '{edwsmith, rreitmai}@adobe.com',
            '{mohammad.r.haghighat}@intel.com',
            '{mbebenit, changm, franz}@uci.edu',
        ] as $email) {
            $t->contains($email, $text, "TraceMonkey must preserve the exact affiliation address '{$email}'.");
        }
    },
    'pdf pre-fix regression suppresses the Muir vertical bicycle glyph ladder while retaining useful labels' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample, $editableLineStreams, $plainText): void {
        $muir = $readPdfCachedSample($pdfSamplePaths()['muir-brochure']);
        $html = PandocConverter::write($muir['document'], 'html');
        $text = $plainText($html);
        $lineStreams = $editableLineStreams($muir['document'], $muir['blocks'], $html);

        foreach (['Zen Center', 'Green Gulch Farm', 'MUIR BEACH', 'Horses and Hiking only', 'Hiking only', 'Restoring Ecological Integrity', 'Make a Difference'] as $meaningfulLabel) {
            $t->contains($meaningfulLabel, $text, "Muir must retain meaningful label '{$meaningfulLabel}' somewhere in editable content.");
        }
        $ladderSurfaces = array_keys(array_filter(
            $lineStreams,
            static fn (array $lines): bool => str_contains(
                implode("\n", $lines),
                "1\n1\n1\nU\nP\nH\nI\nL\nL\nO\nN\nL\nY"
            )
        ));
        $t->same(
            [],
            $ladderSurfaces,
            'The rotated “UPHILL ONLY” marking must not survive in any editable AST, WordPress, or HTML line carrier.'
        );
    },
    'pdf pre-fix regression bounds consecutive tiny Muir map-label lines' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample, $editableLineStreams): void {
        $muir = $readPdfCachedSample($pdfSamplePaths()['muir-brochure']);
        $html = PandocConverter::write($muir['document'], 'html');
        $maxShortRuns = [];
        foreach ($editableLineStreams($muir['document'], $muir['blocks'], $html) as $surface => $lines) {
            $maxShortRuns[$surface] = 0;
            $shortRun = 0;
            foreach ($lines as $line) {
                if (mb_strlen($line, 'UTF-8') <= 3) {
                    $shortRun++;
                    $maxShortRuns[$surface] = max($maxShortRuns[$surface], $shortRun);
                    continue;
                }
                $shortRun = 0;
            }
        }
        $maxShortRun = max($maxShortRuns ?: [0]);

        $t->true(
            $maxShortRun <= 16,
            'Muir editable output may contain at most 16 consecutive one-to-three-character structural lines; observed '
                . json_encode($maxShortRuns, JSON_UNESCAPED_SLASHES) . '.'
        );
        $t->contains('Dogs on leash allowed', $muir['blocks']);
    },
    'pdf pre-fix regression removes adjacent duplicate Muir resource URLs' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample, $plainText): void {
        $muir = $readPdfCachedSample($pdfSamplePaths()['muir-brochure']);
        $text = $plainText(PandocConverter::write($muir['document'], 'html'));

        $t->true(
            preg_match('/www\.nps\.gov\/goga\s+www\.nps\.gov\/goga/u', $text) === 0,
            'Repeated source paintings of the Muir resource URL must collapse across whitespace and HTML block boundaries.'
        );
        $t->contains('www.nps.gov/goga', $muir['blocks']);
    },
    'pdf pre-fix regression removes the CDC rotated make-a-difference fragment' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample, $plainText): void {
        $result = $readPdfCachedSample($pdfSamplePaths()['cdc-brochure']);
        $text = $plainText(PandocConverter::write($result['document'], 'html'));

        $t->true(!str_contains($text, 'o T make a difference'), 'Rotated CDC display letters must not become editable prose.');
        $t->contains('You can make a difference in your own health:', $text);
    },
    'pdf pre-fix regression does not emit tiny or extreme CDC filler rasters as standalone images' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfMediaSample, $htmlAttributeValue): void {
        $cdc = $readPdfMediaSample($pdfSamplePaths()['cdc-brochure']);
        $root = dirname(__DIR__, 3);
        $publishedBlocks = file_get_contents(
            $root . '/pandoc-showcase/outputs/pdf-cdc-hand-hygiene-brochure/wordpress-blocks.html'
        ) ?: '';

        foreach (['runtime extraction' => $cdc['blocks'], 'published showcase' => $publishedBlocks] as $surface => $blocks) {
            preg_match_all('/<img\b[^>]*>/u', $blocks, $images);
            $decorative = [];
            foreach ($images[0] ?? [] as $image) {
                $objectValue = $htmlAttributeValue($image, 'data-pandoc-pdf-image-object');
                $bytesValue = $htmlAttributeValue($image, 'data-pandoc-pdf-image-bytes');
                $widthValue = $htmlAttributeValue($image, 'data-pandoc-pdf-image-width');
                $heightValue = $htmlAttributeValue($image, 'data-pandoc-pdf-image-height');
                $diagnostics = [
                    'object' => $objectValue,
                    'bytes' => $bytesValue,
                    'width' => $widthValue,
                    'height' => $heightValue,
                ];
                $invalidDiagnostics = array_keys(array_filter(
                    $diagnostics,
                    static fn (?string $value): bool => $value === null
                        || preg_match('/^\d+$/D', $value) !== 1
                        || (int) $value <= 0
                ));
                $identity = $htmlAttributeValue($image, 'data-pandoc-pdf-occurrence-id')
                    ?? $htmlAttributeValue($image, 'src')
                    ?? 'unidentified-image';
                if ($invalidDiagnostics !== []) {
                    $decorative[] = [
                        'identity' => $identity,
                        'reason' => 'missing-or-invalid-pdf-image-diagnostics',
                        'fields' => $invalidDiagnostics,
                    ];
                    continue;
                }
                $object = (int) $objectValue;
                $bytes = (int) $bytesValue;
                $width = (int) $widthValue;
                $height = (int) $heightValue;
                $shortSide = min($width, $height);
                $longSide = max($width, $height);
                if ($bytes >= 200 && $longSide / $shortSide < 8.0) {
                    continue;
                }
                $decorative[] = [
                    'identity' => $identity,
                    'reason' => 'tiny-or-extreme-decorative-raster',
                    'object' => $object,
                    'bytes' => $bytes,
                    'width' => $width,
                    'height' => $height,
                ];
            }

            $t->same(
                [],
                $decorative,
                "Every standalone CDC raster in {$surface} must have complete diagnostics and must not be a sub-200-byte mask or extreme decorative strip."
            );
        }
    },
    'pdf pre-fix regression keeps multicolumn citation years in prose instead of ordered-list starts' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample, $plainText): void {
        $result = $readPdfCachedSample($pdfSamplePaths()['layout-multicolumn']);
        $text = $plainText(PandocConverter::write($result['document'], 'html'));
        $orderedStarts = array_values(array_map(
            static fn (AstNode $list): int => (int) $list->attr('start', 1),
            array_filter(
                $result['document']->children(),
                static fn (AstNode $block): bool => $block->type === 'ordered_list'
            )
        ));

        $t->same([], array_values(array_intersect([1999, 2019], $orderedStarts)), 'Citation years must never be parsed as ordered-list starts.');
        $t->contains('(Voorhees, 1999) is a task', $text);
        $t->contains('(Devlin et al., 2019) and a dual-encoder architecture', $text);
    },
    'pdf pre-fix regression preserves all three multicolumn affiliation email addresses' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample, $plainText): void {
        $result = $readPdfCachedSample($pdfSamplePaths()['layout-multicolumn']);
        $text = $plainText(PandocConverter::write($result['document'], 'html'));

        foreach ([
            '{vladk, barlaso, plewis, ledell, edunov, scottyih}@fb.com',
            'sewon@cs.washington.edu',
            'danqic@cs.princeton.edu',
        ] as $email) {
            $t->contains($email, $text, "Multicolumn front matter must preserve '{$email}'.");
        }
    },
    'pdf pre-fix regression keeps multicolumn retrieval formulas out of orphan paragraphs' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample): void {
        $result = $readPdfCachedSample($pdfSamplePaths()['layout-multicolumn']);

        foreach ([
            '<p>, ···, w(i)</p>',
            '<p>|p i|. Given a question q,</p>',
            '<p>e from one of the passages p</p>',
            '<p>i that can answer the question.</p>',
        ] as $orphanFormula) {
            $t->true(!str_contains($result['blocks'], $orphanFormula), "Multicolumn formula fragment '{$orphanFormula}' must stay in its logical sentence.");
        }
        $t->contains('contains D documents, d1, d2, ···, dD. We first split each of the documents', $result['blocks']);
        $t->contains('total passages in our corpus C = {p1, p2, ..., pM }', $result['blocks']);
    },
    'pdf pre-fix regression preserves bold italic and bold-italic emphasis spans' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample): void {
        $result = $readPdfCachedSample($pdfSamplePaths()['emphasis']);
        $blocks = $result['blocks'];

        $t->contains('Test <strong>bold.</strong>', $blocks);
        $t->contains('<em>Italic.</em>', $blocks);
        $t->true(
            str_contains($blocks, '<strong><em>Italic and bold.</em></strong>')
                || str_contains($blocks, '<em><strong>Italic and bold.</strong></em>'),
            'The combined emphasis sample must remain both bold and italic.'
        );
    },
    'pdf pre-fix regression restores inline right-to-left mixed-script order' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample): void {
        $result = $readPdfCachedSample($pdfSamplePaths()['right-to-left']);

        $t->true(!str_contains($result['blocks'], 'Python يف pandas'), 'The Arabic preposition في must not be reversed to يف.');
        preg_match_all('/<p\b([^>]*)>(.*?)<\/p>/su', $result['blocks'], $paragraphMatches, PREG_SET_ORDER);
        $rtlParagraphFound = false;
        foreach ($paragraphMatches as $paragraphMatch) {
            $attributes = $paragraphMatch[1];
            $paragraphText = trim(html_entity_decode(strip_tags($paragraphMatch[2]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            if (preg_match('/\bdir\s*=\s*(["\'])rtl\1/u', $attributes) === 1
                && str_contains($paragraphText, 'pandas في Python')) {
                $rtlParagraphFound = true;
                break;
            }
        }
        $t->true($rtlParagraphFound, 'The same-baseline pandas phrase must retain logical text order inside RTL prose.');
    },
    'pdf pre-fix regression joins right-to-left orphan fragments into logical prose' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample, $plainText): void {
        $result = $readPdfCachedSample($pdfSamplePaths()['right-to-left']);
        $html = PandocConverter::write($result['document'], 'html');
        $text = $plainText($html);

        foreach ([
            'مجموعة واسعة من التطبيقات، من التحليل البياني إلى التعلم الآلي',
            'على سبيل المثال، يمكن استخدام مكتبة pandas في Python لإدارة البيانات بكفاءة',
            'بينما توفر R أدوات قوية للرسم البياني والتحليل الإحصائي',
            'حلول مبتكرة للمشكلات المعقدة',
        ] as $logicalPhrase) {
            $t->contains($logicalPhrase, $text, "RTL logical phrase must remain contiguous: '{$logicalPhrase}'.");
        }
        foreach (['واسعة م ', 'مبتكرة ا '] as $orphan) {
            $t->true(!str_contains($text, $orphan), "RTL output must not retain orphan fragment '{$orphan}'.");
        }
        foreach (['حصائي،', 'سبيل المثال،'] as $orphan) {
            $t->true(
                preg_match('/<(?:p|li|h[1-6]|blockquote)\b[^>]*>\s*' . preg_quote($orphan, '/') . '/u', $html) !== 1,
                "RTL output must not begin a semantic block with orphan fragment '{$orphan}'."
            );
        }
    },
    'pdf pre-fix regression excludes page numbers and checkbox prefixes from the table-picture fixture' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample): void {
        $result = $readPdfCachedSample($pdfSamplePaths()['table-picture-boundary']);

        $t->contains('<h1>Global Study on Legal Aid — Global Report Annex</h1>', $result['blocks']);
        $t->true(!str_contains($result['blocks'], '<h1>208 209 '), 'Facing page numbers must not prefix the document title.');
        $t->true(preg_match('/<p>yy\s+/u', $result['blocks']) === 0, 'Checkbox font glyphs must not prefix population labels.');
        $t->contains('Persons with disabilities', $result['blocks']);
    },
    'pdf pre-fix regression removes lone table-picture checkbox-star list items' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample): void {
        $result = $readPdfCachedSample($pdfSamplePaths()['table-picture-boundary']);

        $t->true(
            !str_contains($result['blocks'], '<ul><li>*</li></ul>'),
            'A checkbox companion glyph must not become a one-item list containing only an asterisk.'
        );
    },
    'pdf pre-fix regression keeps QuickBooks form labels out of the document outline' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample, $documentHeadingTexts): void {
        $result = $readPdfCachedSample($pdfSamplePaths()['quickbooks-invoice']);
        $headings = $documentHeadingTexts($result['document']);

        foreach (['Invoice template', 'Tax Invoice', 'Invoice'] as $title) {
            $t->true(in_array($title, $headings, true), "The complete title '{$title}' should remain in the outline.");
        }
        $unexpectedHeadings = array_values(array_filter(
            $headings,
            static fn (string $heading): bool => !in_array($heading, ['Invoice template', 'Tax Invoice', 'Invoice'], true)
        ));
        $t->same([], $unexpectedHeadings, 'Only the fixture title and the two complete invoice-page titles may enter the outline.');
        foreach (['Description', 'Qty/Hrs', 'Rate', 'Amount', 'Total AUD', 'Phone (02) 9999-9999', 'Enter company name Street address City State, postcode'] as $formLabel) {
            $t->true(!in_array($formLabel, $headings, true), "QuickBooks form label '{$formLabel}' must not enter the outline.");
        }
    },
    'pdf pre-fix regression restores complete Motograph Google notice phrases' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample, $plainText): void {
        $result = $readPdfCachedSample($pdfSamplePaths()['archive-book']);
        $text = $plainText(PandocConverter::write($result['document'], 'html'));

        foreach ([
            "scanned by Google as part of a project to make the world's books discoverable online",
            'Public domain books belong to the public and we are merely their custodians.',
            'we have taken steps to prevent abuse by commercial parties',
            'we request that you use these files for personal, non-commercial purposes.',
            'research on machine translation, optical character recognition',
            'please contact us. We encourage the use of public domain materials',
            'helping them find additional materials through Google Book Search',
        ] as $noticePhrase) {
            $t->contains($noticePhrase, $text, "Motograph page-one notice must preserve '{$noticePhrase}'.");
        }
    },
    'pdf pre-fix regression separates Module Two and Module Three list ownership' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample): void {
        $result = $readPdfCachedSample($pdfSamplePaths()['layout-lists']);
        $lists = array_values(array_filter(
            $result['document']->children(),
            static fn (AstNode $block): bool => $block->type === 'bullet_list'
        ));
        $itemCounts = array_map(static fn (AstNode $list): int => count($list->children()), $lists);

        $t->same([7, 12, 11], $itemCounts, 'Module Two owns 12 bullets and Module Three owns 11; neither list may cross the module boundary.');
        $t->true(
            preg_match('/Module Two:.*?<ul>(?:(?!<\/ul>).)*Technology and tools selection<\/li><\/ul>.*?Module Three:.*?<ul>(?:(?!<\/ul>).)*Determining root challenges: market factors<\/li><\/ul>/su', $result['blocks']) === 1,
            'Module list blocks must remain attached to their own module headings and terminal items.'
        );
        $meta = $result['meta'];
        $t->same(true, $meta['pdfSourceBindingComplete'] ?? null);
        $t->same(true, $meta['pdfSemanticTextComplete'] ?? null);
        $t->same(
            'mapped-occurrence-exact',
            $meta['pdfSourceDisposition']['orderProofStrength'] ?? null,
            'The cross-page list move must retain one exact occurrence-order proof.'
        );
        $t->same(
            0,
            $meta['pdfSourceDisposition']['rejectedOrderChangeOccurrenceCount'] ?? null
        );
    },
    'pdf pre-fix regression associates picture captions with their figures' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfMediaSample, $htmlAttributeValue): void {
        $result = $readPdfMediaSample($pdfSamplePaths()['pictures-captions']);
        $figures = array_values(array_filter(
            $result['document']->children(),
            static fn (AstNode $block): bool => $block->type === 'figure'
        ));
        $captions = array_map(static fn (AstNode $figure): string => trim((string) $figure->attr('caption', '')), $figures);

        $t->same([
            'Figure 1: This is an example image.',
            'Figure 2: This is an example image.',
        ], $captions, 'Each extracted picture must have a nonempty structural caption association.');
        preg_match_all('/<figcaption\b[^>]*>(.*?)<\/figcaption>/su', $result['blocks'], $captionMatches);
        $serializedCaptions = array_values(array_map(
            static fn (string $caption): string => trim(
                html_entity_decode(strip_tags($caption), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            ),
            $captionMatches[1] ?? []
        ));
        $t->same([
            'Figure 1: This is an example image.',
            'Figure 2: This is an example image.',
        ], $serializedCaptions, 'Both structural captions must serialize as figcaption elements, allowing accessibility attributes.');
        preg_match_all('/<figure\b([^>]*)>(.*?)<\/figure>/su', $result['blocks'], $figureMatches, PREG_SET_ORDER);
        $accessibleFigures = [];
        foreach ($figureMatches as $figureMatch) {
            if (
                preg_match('/<img\b[^>]*>/su', $figureMatch[2], $imageMatch) !== 1
                || preg_match('/<figcaption\b([^>]*)>(.*?)<\/figcaption>/su', $figureMatch[2], $captionMatch) !== 1
            ) {
                continue;
            }
            $caption = trim(html_entity_decode(strip_tags($captionMatch[2]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            $captionDescription = preg_replace('/^Figure\s+\d+:\s*/u', '', $caption) ?? $caption;
            $candidateNames = array_values(array_filter([
                $htmlAttributeValue($imageMatch[0], 'alt'),
                $htmlAttributeValue($imageMatch[0], 'aria-label'),
                $htmlAttributeValue($figureMatch[1], 'aria-label'),
            ], static fn (?string $name): bool => is_string($name) && trim($name) !== ''));
            $hasCaptionDerivedName = count(array_filter(
                $candidateNames,
                static fn (string $name): bool => in_array(trim($name), [$caption, $captionDescription], true)
            )) > 0;
            $captionId = $htmlAttributeValue($captionMatch[1], 'id');
            if (is_string($captionId) && $captionId !== '') {
                foreach ([$imageMatch[0], $figureMatch[1]] as $labelOwner) {
                    $labelledBy = preg_split(
                        '/\s+/u',
                        trim((string) $htmlAttributeValue($labelOwner, 'aria-labelledby'))
                    ) ?: [];
                    $hasCaptionDerivedName = $hasCaptionDerivedName || in_array($captionId, $labelledBy, true);
                }
            }
            $accessibleFigures[$caption] = $hasCaptionDerivedName;
        }
        foreach ([
            'Figure 1: This is an example image.',
            'Figure 2: This is an example image.',
        ] as $caption) {
            $t->same(
                true,
                $accessibleFigures[$caption] ?? false,
                "The image associated with '{$caption}' needs a nonempty caption-derived alt/ARIA accessible name."
            );
        }
        $t->true(!str_contains($result['blocks'], '<p>Figure 1: This is an example image.</p>'));
        $t->true(!str_contains($result['blocks'], '<p>Figure 2: This is an example image.</p>'));
    },
    'pdf pre-fix regression keeps aircraft diagram callouts out of H2 and retains both captions' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample, $documentHeadingTexts, $plainText): void {
        $result = $readPdfCachedSample($pdfSamplePaths()['aircraft-handbook']);
        $headings = $documentHeadingTexts($result['document']);
        $text = $plainText(PandocConverter::write($result['document'], 'html'));

        $t->same([
            'Boots Self-Locking Nut',
            'Stainless Steel Self-Locking Nut',
            'Elastic Stop Nut',
        ], $headings, 'Only the three prose section titles should enter the aircraft outline.');
        foreach ([
            'Tightened nut',
            'Untightened nut',
            'Nut case',
            'Threaded nut core',
            'Locking shoulder',
            'Keyway',
            'Boots aircraft nut',
            'Flexloc nut',
            'Fiber locknut',
            'Elastic stop nut',
            'Elastic anchor nut',
        ] as $callout) {
            $t->true(
                !in_array($callout, $headings, true),
                "Aircraft diagram callout '{$callout}' must not enter the document outline."
            );
            $t->contains(
                $callout,
                $text,
                "Aircraft diagram callout '{$callout}' must remain editable text."
            );
        }
        $t->contains('Figure 7-26. Self-locking nuts.', $text);
        $t->contains('Figure 7-27. Stainless steel self-locking nut.', $text);
    },
    'pdf pre-fix regression keeps theatre running page labels out of semantic headings' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample, $documentHeadingTexts): void {
        $result = $readPdfCachedSample($pdfSamplePaths()['theatre-script']);
        $headings = $documentHeadingTexts($result['document']);

        foreach (['Script formatting example p.1', 'Script formatting example p.2'] as $runningLabel) {
            $t->true(!in_array($runningLabel, $headings, true), "Theatre running label '{$runningLabel}' must not enter the outline.");
        }
        $t->contains('<h2>SCRIPT FORMAT EXAMPLE</h2>', $result['blocks']);
        $t->contains('<h2>ACT ONE</h2>', $result['blocks']);
    },
    'pdf pre-fix regression removes standalone IRS caution glyph paragraphs' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample): void {
        $result = $readPdfCachedSample($pdfSamplePaths()['irs-w4-form']);
        $meta = $result['document']->attr('meta');

        $t->true(preg_match('/<p>\s*(?:!|▲)\s*<\/p>/u', $result['blocks']) === 0, 'IRS caution icon glyphs must not become standalone editable paragraphs.');
        $t->same(true, $meta['pdfSourceBindingComplete'] ?? null);
        $t->same(0, $meta['pdfSourceDisposition']['unresolvedOccurrenceCount'] ?? null);
        $t->same(0, $meta['pdfSourceDisposition']['unclaimedEmittedSignificantCharacterCount'] ?? null);
        $t->same(2, $meta['pdfSourceDisposition']['dispositionCounts']['artifact'] ?? null);
        $iconEdges = array_values(array_filter(
            $meta['pdfSourceDisposition']['sourceEdges'] ?? [],
            static fn (array $edge): bool =>
                ($edge['page'] ?? null) === 2
                    && ($edge['disposition'] ?? null) === 'artifact'
                    && ($edge['target'] ?? null) === 'disposition'
                    && ($edge['mappingMode'] ?? null) === 'explicit-disposition'
                    && ($edge['destinationNodeIds'] ?? null) === []
        ));
        $t->same(2, count($iconEdges), 'Both exact icon atoms need explicit artifact edges with no editable destination.');
    },
    'pdf pre-fix regression keeps the IRS caution label and warning sentence contiguous' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample): void {
        $result = $readPdfCachedSample($pdfSamplePaths()['irs-w4-form']);
        $html = PandocConverter::write($result['document'], 'html');
        $expectedWarning = 'CAUTION Multiple jobs. Complete Steps 3 through 4(b) on only one Form W-4. Withholding will be most accurate if you do this on the Form W-4 for the highest paying job.';
        preg_match_all('/<(p|aside)\b[^>]*>(.*?)<\/\1>/su', $html, $unitMatches, PREG_SET_ORDER);
        $matchingUnits = array_values(array_filter(
            array_map(
                static fn (array $match): string => trim(
                    preg_replace(
                        '/\s+/u',
                        ' ',
                        html_entity_decode(strip_tags($match[2]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    ) ?? ''
                ),
                $unitMatches
            ),
            static fn (string $unit): bool => str_contains($unit, $expectedWarning)
        ));

        $t->same(
            [$expectedWarning],
            $matchingUnits,
            'The IRS caution label and complete warning must occupy one paragraph or aside, not adjacent flattened blocks.'
        );
        $matchingNodes = array_values(array_filter(
            $result['document']->children(),
            static fn (AstNode $block): bool =>
                in_array($block->type, ['paragraph', 'aside'], true)
                    && str_contains((string) $block->attr('text', ''), $expectedWarning)
        ));
        $t->same(1, count($matchingNodes));
        $sourceLineIds = $matchingNodes[0]->attr('sourceLineIds', []);
        $sourceLineEdges = $matchingNodes[0]->attr('sourceLineEdges', []);
        $t->same(4, count($sourceLineIds), 'The folded warning must bind the label and all three prose source occurrences.');
        $t->same($sourceLineIds, array_column($sourceLineEdges, 'sourceLineId'));
        foreach ($sourceLineEdges as $edge) {
            $t->same(0, $edge['startByte'] ?? null);
            $t->true(($edge['endByte'] ?? 0) > 0);
        }
    },
    'pdf pre-fix regression removes Grand Canyon map-letter and reversed dimension fragments' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample): void {
        $result = $readPdfCachedSample($pdfSamplePaths()['grand-canyon-map']);

        foreach (['<p>Ar</p>', '<p>o n</p>', '<p>ic Trail</p>', '<p>prohibited. 6.7</p>', '<p>6.7 prohibited.</p>'] as $artifact) {
            $t->true(!str_contains($result['blocks'], $artifact), "Grand Canyon map fragment '{$artifact}' must not enter editable prose.");
        }
        $t->contains('Vehicles longer than 22 feet (6.7 m) prohibited on the roads to Cape Royal, Point Imperial, and Widforss Trail.', $result['blocks']);
        $t->contains('Arizona National Scenic Trail', $result['blocks']);
    },
    'pdf pre-fix regression keeps the Tabula yield label as a heading and removes page six furniture' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfCachedSample, $documentHeadingTexts): void {
        $result = $readPdfCachedSample($pdfSamplePaths()['spreadsheet-no-frame']);

        $t->true(!str_contains($result['blocks'], '<ol start="3">'), '“3. YIELD ESTIMATE” is a section heading, not an ordered list starting at 3.');
        $t->true(
            in_array(
                '3. YIELD ESTIMATE (184.30 million tons)',
                $documentHeadingTexts($result['document']),
                true
            ),
            'The Tabula yield label must remain semantic heading text even when inline emphasis is retained.'
        );
        $t->true(!str_contains($result['blocks'], '<p>6</p>'), 'The source page number 6 must not survive as a standalone paragraph.');
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
        $t->contains('additional materials through Google Book Search', $text);
        $t->same(true, $meta['pdfTextVisibilityComplete'] ?? null);
        $t->same(true, $meta['pdfTextVisibility']['complete'] ?? null);
        $t->same([], $meta['pdfTextVisibility']['unresolvedReasons'] ?? null);
        $t->same(0, $meta['pdfTextVisibility']['unresolvedRuns'] ?? null);
        $t->same(0, $meta['pdfTextVisibility']['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(0, $meta['pdfTextVisibility']['laterPaintRiskCount'] ?? null);
        $t->same([], $meta['pdfTextVisibility']['laterPaintRisks'] ?? null);
        $t->true(
            (int) ($meta['pdfTextVisibility']['suppressedOutsidePageRuns'] ?? 0) > 0,
            'Archive book text painted wholly beyond the MediaBox must stay out of the visible source ledger.'
        );
        $t->same(true, $meta['pdfSourceBindingComplete'] ?? null);
        $t->same(true, $meta['pdfSemanticTextComplete'] ?? null);
        $t->same(range(2, 47), $meta['pdfPagesNeedingImageRepresentation'] ?? null);
        $t->same(false, $meta['pdfPageRepresentationComplete'] ?? null);
        $t->same(true, $meta['pdfNeedsOcr'] ?? null);
        $t->true(!str_contains($text, 'dif cult'), 'Archive book fi ligatures should not be lost as spaces.');
        $t->true(!str_contains($text, ' le - a reminder'), 'Archive book fi ligatures should not leave bare word fragments.');
        $t->true(!str_contains($result['blocks'], 'journey from the Google is proud'), 'Archive book source regions must not be fused across a missing OCR span.');
    },
];
