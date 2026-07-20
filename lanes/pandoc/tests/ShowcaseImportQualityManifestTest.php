<?php

declare(strict_types=1);

$showcaseManifest = static function (): array {
    $path = dirname(__DIR__, 3) . '/pandoc-showcase/manifest.json';
    if (!is_file($path)) {
        throw new RuntimeException('Expected generated showcase manifest at ' . $path);
    }
    $manifest = json_decode(file_get_contents($path) ?: '', true);
    if (!is_array($manifest)) {
        throw new RuntimeException('Unable to decode showcase manifest JSON.');
    }

    return $manifest;
};

$recordsByFormat = static function (array $records): array {
    $byFormat = [];
    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }
        $format = (string) ($record['format'] ?? '');
        if ($format === '') {
            continue;
        }
        $byFormat[$format] ??= [];
        $byFormat[$format][] = $record;
    }

    return $byFormat;
};

$recordsById = static function (array $records): array {
    $byId = [];
    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }
        $id = (string) ($record['id'] ?? '');
        if ($id !== '') {
            $byId[$id] = $record;
        }
    }

    return $byId;
};

$sourceRawHtmlBlockCount = static function (\PortLibs\Pandoc\AstNode $document): int {
    $count = 0;
    $pending = [$document];
    while ($pending !== []) {
        $node = array_pop($pending);
        if (!$node instanceof \PortLibs\Pandoc\AstNode) {
            continue;
        }
        if ($node->type === 'raw_html') {
            $count++;
        } elseif ($node->type === 'raw_block') {
            $format = strtolower((string) $node->attr('format', ''));
            if (\PortLibs\Pandoc\MarkdownFormatProfile::rawFamily($format) === 'html') {
                $count++;
            }
        }
        foreach ($node->children as $child) {
            $pending[] = $child;
        }
    }

    return $count;
};

return [
    'showcase manifest keeps a normal user document corpus' => static function (TestRunner $t) use ($showcaseManifest, $recordsByFormat): void {
        $manifest = $showcaseManifest();
        $records = is_array($manifest['records'] ?? null) ? $manifest['records'] : [];
        $byFormat = $recordsByFormat($records);

        $t->true(count($records) >= 40, 'Showcase should include at least a normal-user-sized corpus.');

        $expectedMinimums = [
            'doc' => 2,
            'docx' => 9,
            'pdf' => 9,
            'html' => 4,
            'epub' => 5,
            'pptx' => 4,
            'odt' => 3,
            'rtf' => 3,
            'xlsx' => 3,
            'csv' => 2,
            'tsv' => 2,
        ];

        $normalUserRecordCount = 0;
        foreach ($expectedMinimums as $format => $minimum) {
            $actual = count($byFormat[$format] ?? []);
            $normalUserRecordCount += $actual;
            $t->true($actual >= $minimum, "{$format} corpus should have at least {$minimum} samples.");
        }

        $t->true($normalUserRecordCount >= 40, 'Office, PDF, web, ebook, slide, spreadsheet, and tabular samples should reach the normal user corpus target.');
    },
    'showcase manifest includes normal user docx report resume and policy samples' => static function (TestRunner $t) use ($showcaseManifest, $recordsById): void {
        $manifest = $showcaseManifest();
        $records = is_array($manifest['records'] ?? null) ? $manifest['records'] : [];
        $byId = $recordsById($records);

        foreach ([
            'docx-quarterly-operations-report',
            'docx-support-specialist-resume',
            'docx-remote-work-policy',
        ] as $id) {
            $t->true(isset($byId[$id]), "{$id} should be present in the showcase manifest.");
            $t->same('docx', $byId[$id]['format'] ?? null, "{$id} should be a DOCX sample.");
            $t->same(true, $byId[$id]['wpBlocks']['ok'] ?? null, "{$id} should convert to WordPress blocks.");
            $gates = $byId[$id]['importQuality']['gates'] ?? [];
            $t->true(isset($gates['text_completeness']), "{$id} should have import-quality gates.");
        }
    },
    'showcase manifest covers every supported input format and fails closed on known PDF boundaries' => static function (TestRunner $t) use ($showcaseManifest): void {
        $manifest = $showcaseManifest();
        $records = is_array($manifest['records'] ?? null) ? $manifest['records'] : [];
        $formats = is_array($manifest['formats'] ?? null) ? $manifest['formats'] : [];
        $coveredFormats = is_array($manifest['coveredFormats'] ?? null) ? $manifest['coveredFormats'] : [];
        $qualitySummary = is_array($manifest['importQualitySummary'] ?? null) ? $manifest['importQualitySummary'] : [];
        $qualityGate = is_array($manifest['importQualityGate'] ?? null) ? $manifest['importQualityGate'] : [];
        $expectedReviewIds = [
            'pdf-layout-unstructured-ocr-overlay',
            'pdf-muir-beach-brochure',
        ];
        $expectedFailureIds = [
            'pdf-archive-motograph-book',
            'pdf-grand-canyon-north-rim-map',
            'pdf-layout-docling-aircraft-handbook',
            'pdf-layout-docling-right-to-left',
            'pdf-layout-mineru-small-ocr',
            'pdf-layout-vdl-theatre-script',
            'pdf-quickbooks-invoice-template',
            'pdf-tabula-spreadsheet-no-frame',
        ];
        $expectedConversionFailureIds = ['pdf-layout-mineru-small-ocr'];

        $t->same([], $manifest['missingFormats'] ?? null);
        $t->same($formats, $coveredFormats);
        $t->true(count($records) >= 95, 'The complete supported-format corpus should not shrink.');
        $t->same(count($records), $qualitySummary['samples'] ?? null);
        $t->same(count($records) - count($expectedReviewIds) - count($expectedFailureIds), $qualitySummary['pass'] ?? null);
        $t->same(count($expectedReviewIds), $qualitySummary['review'] ?? null);
        $t->same(count($expectedFailureIds), $qualitySummary['fail'] ?? null);
        $t->same(0, $qualitySummary['unbenchmarked'] ?? null);
        $t->same('fail', $qualityGate['status'] ?? null);
        $t->same('fail', $qualityGate['trackedStatus'] ?? null);

        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $id = (string) ($record['id'] ?? 'unknown');
            $expectedConversionOk = !in_array($id, $expectedConversionFailureIds, true);
            $t->same($expectedConversionOk, $record['phpHtml']['ok'] ?? null, "{$id} should retain its expected PHP HTML conversion boundary.");
            $t->same($expectedConversionOk, $record['wpBlocks']['ok'] ?? null, "{$id} should retain its expected WordPress conversion boundary.");
            $quality = is_array($record['importQuality'] ?? null) ? $record['importQuality'] : [];
            $expectedQualityStatus = in_array($id, $expectedFailureIds, true)
                ? 'fail'
                : (in_array($id, $expectedReviewIds, true) ? 'review' : 'pass');
            $t->same($expectedQualityStatus, $quality['status'] ?? null, "{$id} should retain its measured import-quality status.");
            $anchor = is_array($quality['gates']['anchor_validity'] ?? null)
                ? $quality['gates']['anchor_validity']
                : [];
            if ($expectedConversionOk) {
                $t->same('pass', $anchor['status'] ?? null, "{$id} should not contain broken local fragment links.");
            } else {
                $t->same('fail', $quality['gates']['conversion']['status'] ?? null, "{$id} should expose its typed conversion refusal.");
            }
        }
    },
    'showcase manifest records required import quality gates per successful sample' => static function (TestRunner $t) use ($showcaseManifest): void {
        $manifest = $showcaseManifest();
        $records = is_array($manifest['records'] ?? null) ? $manifest['records'] : [];
        $documentGates = [
            'text_completeness',
            'heading_count',
            'paragraph_merge_split',
            'list_count',
            'definition_list_count',
            'table_count',
            'image_count',
            'anchor_validity',
            'media_imported',
            'custom_html_percentage',
        ];
        $bibliographyGates = [
            'citeproc_content_coverage',
            'citeproc_entry_count',
            'media_imported',
            'anchor_validity',
            'custom_html_percentage',
        ];
        $pdfGates = [
            'text_completeness',
            'native_source_coverage',
            'pdf_geometry_reference',
            'paragraph_merge_split',
            'pdf_source_integrity',
            'media_imported',
            'anchor_validity',
            'custom_html_percentage',
        ];
        $validStatuses = ['pass' => true, 'review' => true, 'fail' => true, 'unbenchmarked' => true];
        $successful = 0;

        foreach ($records as $record) {
            if (!is_array($record) || (($record['wpBlocks']['ok'] ?? false) !== true)) {
                continue;
            }
            $successful++;
            $id = (string) ($record['id'] ?? 'unknown');
            $quality = is_array($record['importQuality'] ?? null) ? $record['importQuality'] : [];
            $gates = is_array($quality['gates'] ?? null) ? $quality['gates'] : [];
            $requiredGates = (string) ($record['format'] ?? '') === 'pdf'
                ? $pdfGates
                : ((($record['bibliographyComparison']['available'] ?? false) === true)
                    ? $bibliographyGates
                    : $documentGates);
            foreach ($requiredGates as $gate) {
                $t->true(isset($gates[$gate]) && is_array($gates[$gate]), "{$id} should include {$gate} import-quality gate.");
                $status = (string) ($gates[$gate]['status'] ?? '');
                $t->true(isset($validStatuses[$status]), "{$id} {$gate} should report a known status.");
            }
        }

        $t->true($successful >= 40, 'The gate check should cover a substantial successful WordPress import corpus.');
    },
    'showcase manifest proves the valid LaTeX table import against Pandoc' => static function (TestRunner $t) use ($showcaseManifest, $recordsById): void {
        $manifest = $showcaseManifest();
        $records = is_array($manifest['records'] ?? null) ? $manifest['records'] : [];
        $byId = $recordsById($records);
        $record = $byId['latex-table'] ?? null;

        $t->true(is_array($record), 'latex-table should be present in the showcase manifest.');
        $quality = is_array($record['importQuality'] ?? null) ? $record['importQuality'] : [];
        $gates = is_array($quality['gates'] ?? null) ? $quality['gates'] : [];
        $t->same('haskell', $record['faithfulness']['baseline'] ?? null, 'The valid LaTeX table should have a Haskell baseline.');
        $t->same('pass', $quality['status'] ?? null, 'The valid LaTeX table should have measured passing import quality.');
        $t->same('pass', $gates['text_completeness']['status'] ?? null, 'LaTeX table text should be preserved.');
        $t->same('pass', $gates['table_count']['status'] ?? null, 'LaTeX table structure should be preserved.');
    },
    'showcase retains a Haskell baseline for the large ODT schema document' => static function (TestRunner $t) use ($showcaseManifest, $recordsById): void {
        $manifest = $showcaseManifest();
        $records = is_array($manifest['records'] ?? null) ? $manifest['records'] : [];
        $byId = $recordsById($records);
        $record = $byId['odt-oasis-opendocument-schema'] ?? null;

        $t->true(is_array($record), 'The large ODT schema document should be present in the showcase manifest.');
        $quality = is_array($record['importQuality'] ?? null) ? $record['importQuality'] : [];
        $gates = is_array($quality['gates'] ?? null) ? $quality['gates'] : [];
        $t->same(true, $record['haskell']['ok'] ?? null, 'Large ODT documents should not lose their external baseline to a fixed short timeout.');
        $t->same('haskell', $record['faithfulness']['baseline'] ?? null, 'The large ODT import should be compared to Haskell Pandoc.');
        $t->same('pass', $gates['text_completeness']['status'] ?? null, 'Large ODT text should remain externally measured.');
        $t->same('pass', $gates['visual_structure']['status'] ?? null, 'Large ODT structure should remain externally measured.');
    },
    'showcase compares legacy DOC imports with the format-native TextUtil reference' => static function (TestRunner $t) use ($showcaseManifest, $recordsById): void {
        $manifest = $showcaseManifest();
        $records = is_array($manifest['records'] ?? null) ? $manifest['records'] : [];
        $byId = $recordsById($records);

        foreach (['doc-poi-47304', 'doc-poi-57843'] as $id) {
            $record = $byId[$id] ?? null;
            $t->true(is_array($record), "{$id} should be present in the showcase manifest.");
            $reference = is_array($record['externalReference'] ?? null) ? $record['externalReference'] : [];
            $quality = is_array($record['importQuality'] ?? null) ? $record['importQuality'] : [];
            $gates = is_array($quality['gates'] ?? null) ? $quality['gates'] : [];

            $t->same(true, $reference['ok'] ?? null, "{$id} should retain an external legacy DOC reference.");
            $t->same('macos-textutil-html', $reference['kind'] ?? null, "{$id} should use macOS TextUtil instead of a PHP self-baseline.");
            $t->same('externalReference', $record['faithfulness']['baseline'] ?? null, "{$id} should compare WordPress blocks with the external reference.");
            $t->same('pass', $gates['text_completeness']['status'] ?? null, "{$id} should preserve external-reference text.");
            $t->same('pass', $gates['visual_structure']['status'] ?? null, "{$id} should preserve non-empty paragraph structure.");
        }
    },
    'showcase compares generic XML imports with the independent libxml2 libxslt reference' => static function (TestRunner $t) use ($showcaseManifest, $recordsById): void {
        $manifest = $showcaseManifest();
        $records = is_array($manifest['records'] ?? null) ? $manifest['records'] : [];
        $byId = $recordsById($records);

        foreach (['xml-docbook-generic', 'xml-outline-generic'] as $id) {
            $record = $byId[$id] ?? null;
            $t->true(is_array($record), "{$id} should be present in the showcase manifest.");
            $reference = is_array($record['externalReference'] ?? null) ? $record['externalReference'] : [];
            $quality = is_array($record['importQuality'] ?? null) ? $record['importQuality'] : [];
            $gates = is_array($quality['gates'] ?? null) ? $quality['gates'] : [];

            $t->same(true, $reference['ok'] ?? null, "{$id} should retain an external generic XML reference.");
            $t->same('libxml2-libxslt-generic-xml-html', $reference['kind'] ?? null, "{$id} should use libxml2/libxslt instead of a PHP self-baseline.");
            $t->same('externalReference', $record['faithfulness']['baseline'] ?? null, "{$id} should compare WordPress blocks with the XML source reference.");
            $t->same('pass', $gates['text_completeness']['status'] ?? null, "{$id} should preserve XML source text.");
            $t->same('pass', $gates['visual_structure']['status'] ?? null, "{$id} should preserve common XML structure.");
        }

        $docbook = $byId['xml-docbook-generic'] ?? [];
        $t->same('pass', $docbook['importQuality']['gates']['list_count']['status'] ?? null, 'DocBook itemized lists should become WordPress lists.');
    },
    'showcase compares PDF imports with native PDFKit text and geometry evidence' => static function (TestRunner $t) use ($showcaseManifest, $recordsById): void {
        $manifest = $showcaseManifest();
        $records = is_array($manifest['records'] ?? null) ? $manifest['records'] : [];
        $byId = $recordsById($records);

        $expectations = [
            'pdf-irs-w4' => ['quality' => 'pass', 'text' => 'pass', 'geometry' => 'pass', 'source' => 'pass'],
            'pdf-tracemonkey' => ['quality' => 'pass', 'text' => 'pass', 'geometry' => 'pass', 'source' => 'pass'],
            'pdf-cdc-hand-hygiene-brochure' => ['quality' => 'pass', 'text' => 'pass', 'geometry' => 'pass', 'source' => 'pass'],
            'pdf-grand-canyon-north-rim-map' => ['quality' => 'fail', 'text' => 'pass', 'geometry' => 'pass', 'source' => 'fail'],
            'pdf-archive-motograph-book' => ['quality' => 'fail', 'text' => 'pass', 'geometry' => 'review', 'source' => 'fail'],
            'pdf-muir-beach-brochure' => ['quality' => 'review', 'text' => 'review', 'geometry' => 'pass', 'source' => 'pass'],
            'pdf-quickbooks-invoice-template' => ['quality' => 'fail', 'text' => 'pass', 'geometry' => 'pass', 'source' => 'fail'],
            'pdf-tabula-spreadsheet-no-frame' => ['quality' => 'fail', 'text' => 'pass', 'geometry' => 'pass', 'source' => 'fail'],
            'pdf-tabula-multicolumn' => ['quality' => 'pass', 'text' => 'pass', 'geometry' => 'pass', 'source' => 'pass'],
        ];
        foreach ($expectations as $id => $expected) {
            $record = $byId[$id] ?? null;
            $t->true(is_array($record), "{$id} should be present in the showcase manifest.");
            $reference = is_array($record['externalReference'] ?? null) ? $record['externalReference'] : [];
            $quality = is_array($record['importQuality'] ?? null) ? $record['importQuality'] : [];
            $gates = is_array($quality['gates'] ?? null) ? $quality['gates'] : [];
            $comparison = is_array($record['faithfulness']['comparisons']['wpBlocks'] ?? null)
                ? $record['faithfulness']['comparisons']['wpBlocks']
                : [];
            $metrics = is_array($reference['metrics'] ?? null) ? $reference['metrics'] : [];

            $t->same(true, $reference['ok'] ?? null, "{$id} should retain a native PDF reference.");
            $t->same('macos-pdfkit-text-geometry', $reference['kind'] ?? null, "{$id} should use macOS PDFKit instead of a PHP self-baseline.");
            $t->same('externalReference', $record['faithfulness']['baseline'] ?? null, "{$id} should compare WordPress blocks with the external reference.");
            $t->same('not_applicable', $comparison['visualStatus'] ?? null, "{$id} should not claim an HTML semantic visual baseline from an untagged PDF.");
            $t->true((int) ($metrics['pageCount'] ?? 0) > 0, "{$id} should retain native page evidence.");
            $t->true((int) ($metrics['lineCount'] ?? 0) > 0, "{$id} should retain native line-geometry evidence.");
            $t->same($expected['quality'], $quality['status'] ?? null, "{$id} should retain its measured PDF import-quality status.");
            $t->same($expected['text'], $gates['text_completeness']['status'] ?? null, "{$id} should retain its measured PDFKit text status.");
            $t->same('pass', $gates['native_source_coverage']['status'] ?? null, "{$id} should retain native source-token coverage.");
            $t->same($expected['geometry'], $gates['pdf_geometry_reference']['status'] ?? null, "{$id} should retain its measured native page and line geometry status.");
            $t->same('pass', $gates['paragraph_merge_split']['status'] ?? null, "{$id} should avoid visual-line paragraph drift.");
            $t->same($expected['source'], $gates['pdf_source_integrity']['status'] ?? null, "{$id} should retain its fail-closed semantic source-integrity status.");
        }
    },
    'showcase keeps raw PDFKit diagnostics out of converted preview panes' => static function (TestRunner $t): void {
        $indexPath = dirname(__DIR__, 3) . '/pandoc-showcase/index.html';
        $index = file_get_contents($indexPath);
        $t->true(is_string($index), 'Expected generated showcase index.');

        foreach ([
            'pdf-irs-w4',
            'pdf-tracemonkey',
            'pdf-cdc-hand-hygiene-brochure',
            'pdf-grand-canyon-north-rim-map',
            'pdf-archive-motograph-book',
            'pdf-muir-beach-brochure',
            'pdf-quickbooks-invoice-template',
            'pdf-tabula-spreadsheet-no-frame',
            'pdf-tabula-multicolumn',
        ] as $id) {
            $t->true(
                !str_contains($index, 'id="' . $id . '-externalReference"'),
                "{$id} should not render raw PDFKit text as a conversion pane."
            );
            $t->true(
                !str_contains($index, 'src="outputs/' . $id . '/pdfkit.html"'),
                "{$id} should keep PDFKit diagnostics out of the side-by-side preview."
            );
        }
    },
    'showcase manifest proves bibliography imports against Pandoc Citeproc' => static function (TestRunner $t) use ($showcaseManifest, $recordsById): void {
        $manifest = $showcaseManifest();
        $records = is_array($manifest['records'] ?? null) ? $manifest['records'] : [];
        $byId = $recordsById($records);

        foreach (['bibtex-biblio', 'biblatex-online', 'csljson-book', 'endnotexml-reader', 'ris-texbook'] as $id) {
            $record = $byId[$id] ?? null;
            $t->true(is_array($record), "{$id} should be present in the showcase manifest.");
            $comparison = is_array($record['bibliographyComparison'] ?? null) ? $record['bibliographyComparison'] : [];
            $gates = is_array($record['importQuality']['gates'] ?? null) ? $record['importQuality']['gates'] : [];
            $t->same(true, $record['haskell']['renderedWithCiteproc'] ?? null, "{$id} should use a Citeproc Haskell baseline.");
            $t->same(true, $comparison['available'] ?? null, "{$id} should expose an available bibliography comparison.");
            $t->same($comparison['expectedEntryCount'] ?? null, $comparison['actualEntryCount'] ?? null, "{$id} should preserve reference count.");
            $t->true((float) ($comparison['contentCoverage'] ?? 0.0) >= 0.80, "{$id} should preserve Citeproc reference content.");
            $t->same('pass', $gates['citeproc_content_coverage']['status'] ?? null, "{$id} should pass Citeproc content coverage.");
            $t->same('pass', $gates['citeproc_entry_count']['status'] ?? null, "{$id} should pass Citeproc entry count.");
        }
    },
    'showcase converts anchored JPEG 2000 media while missing Motograph pages remain fail closed' => static function (TestRunner $t) use ($showcaseManifest, $recordsById): void {
        $manifest = $showcaseManifest();
        $records = is_array($manifest['records'] ?? null) ? $manifest['records'] : [];
        $byId = $recordsById($records);
        $record = $byId['pdf-archive-motograph-book'] ?? null;

        $t->true(is_array($record), 'The scanned PDF media fixture should be present in the showcase manifest.');
        $quality = is_array($record['importQuality'] ?? null) ? $record['importQuality'] : [];
        $mediaGate = is_array($quality['gates']['media_imported'] ?? null) ? $quality['gates']['media_imported'] : [];
        $sourceGate = is_array($quality['gates']['pdf_source_integrity'] ?? null) ? $quality['gates']['pdf_source_integrity'] : [];
        $diagnostics = is_array($record['wpBlocks']['mediaDiagnostics'] ?? null) ? $record['wpBlocks']['mediaDiagnostics'] : [];
        $webMedia = array_values(array_filter($record['wpBlocks']['media'] ?? [], static function (mixed $media): bool {
            return is_array($media) && (string) ($media['mimeType'] ?? '') === 'image/avif';
        }));

        $t->same('fail', $quality['status'] ?? null, 'The scanned PDF must remain blocked while pages 2 through 47 lack representations.');
        $t->same('fail', $sourceGate['status'] ?? null, 'The missing page representations must remain an explicit source-integrity failure.');
        $t->same('review', $mediaGate['status'] ?? null, 'Unanchored scanned-page media should remain visible as a media review boundary.');
        $t->same(1, count($webMedia), 'The important JPEG 2000 wordmark should be represented as one extracted web image.');
        $t->same('outputs/pdf-archive-motograph-book/media/pdf/image-3.avif', $webMedia[0]['path'] ?? null, 'The JPX object should retain a stable canonical web-safe media path.');
        $t->true(in_array('extract-media-pdf-image-raster-loaded:3:important', $diagnostics, true), 'The anchored JPEG 2000 image should be recorded as a raster media import.');
        $t->same(false, array_filter($record['wpBlocks']['media'] ?? [], static function (mixed $media): bool {
            return is_array($media) && str_ends_with((string) ($media['path'] ?? ''), '.jp2');
        }) !== [], 'The generated showcase should not expose a raw JPEG 2000 image as a broken browser preview.');
    },
    'showcase keeps the OCR overlay fixture at the typed no-text boundary with its page represented' => static function (TestRunner $t) use ($showcaseManifest, $recordsById): void {
        $manifest = $showcaseManifest();
        $records = is_array($manifest['records'] ?? null) ? $manifest['records'] : [];
        $record = $recordsById($records)['pdf-layout-unstructured-ocr-overlay'] ?? null;

        $t->true(is_array($record), 'The OCR-overlay boundary fixture should be present.');
        $integrity = is_array($record['wpBlocks']['sourceIntegrity'] ?? null)
            ? $record['wpBlocks']['sourceIntegrity']
            : [];
        $quality = is_array($record['importQuality'] ?? null) ? $record['importQuality'] : [];
        $ocrGate = is_array($quality['gates']['ocr_boundary'] ?? null)
            ? $quality['gates']['ocr_boundary']
            : [];
        $blocksPath = dirname(__DIR__, 3) . '/pandoc-showcase/'
            . ltrim((string) ($record['wpBlocks']['path'] ?? ''), '/');
        $blocks = is_file($blocksPath) ? (string) file_get_contents($blocksPath) : '';

        $t->same(true, $record['wpBlocks']['ok'] ?? null);
        $t->same('unsupported_no_text', $integrity['pdfTextLayerStatus'] ?? null);
        $t->same(true, $integrity['pdfNeedsOcr'] ?? null);
        $t->same([1], $integrity['pdfRepresentedPageNumbers'] ?? null);
        $t->same(true, $integrity['pdfPageRepresentationComplete'] ?? null);
        $t->same('review', $quality['status'] ?? null);
        $t->same('review', $ocrGate['status'] ?? null);
        $t->same(1, $record['wpBlockCounts']['image'] ?? null);
        $t->contains('data-pandoc-pdf-image-placement="page"', $blocks);
        $t->true(!str_contains($blocks, 'Foundation'), 'Non-painting OCR overlay text must not be restored as body text.');
    },
    'showcase keeps the classified MinerU scan as a typed no-text failed conversion pending page representation' => static function (TestRunner $t) use ($showcaseManifest, $recordsById): void {
        $manifest = $showcaseManifest();
        $records = is_array($manifest['records'] ?? null) ? $manifest['records'] : [];
        $record = $recordsById($records)['pdf-layout-mineru-small-ocr'] ?? null;

        $t->true(is_array($record), 'The MinerU no-native-text fixture should be present.');
        $integrity = is_array($record['wpBlocks']['sourceIntegrity'] ?? null)
            ? $record['wpBlocks']['sourceIntegrity']
            : [];
        $quality = is_array($record['importQuality'] ?? null) ? $record['importQuality'] : [];
        $conversionGate = is_array($quality['gates']['conversion'] ?? null)
            ? $quality['gates']['conversion']
            : [];

        $t->same(false, $record['phpHtml']['ok'] ?? null);
        $t->same(false, $record['wpBlocks']['ok'] ?? null);
        $t->same('unsupported_no_text', $integrity['pdfTextLayerStatus'] ?? null);
        $t->same(true, $integrity['pdfNeedsOcr'] ?? null);
        $t->same(true, $integrity['pdfTextComplete'] ?? null);
        $t->same(true, $integrity['pdfNoTextClassificationComplete'] ?? null);
        $t->same(true, $integrity['pdfDocumentComplete'] ?? null);
        $t->same(true, $integrity['pdfSemanticTextComplete'] ?? null);
        $t->same(true, $integrity['pdfSourceBindingComplete'] ?? null);
        $t->same(true, $integrity['pdfSourceEdgeMappingComplete'] ?? null);
        $t->same(true, $integrity['pdfOrderedSignificantCharactersPreserved'] ?? null);
        $t->same(0, $integrity['pdfUnresolvedSourceOccurrences'] ?? null);
        $t->same([1, 2, 3, 4, 5, 6, 7, 8], $integrity['pdfPagesNeedingImageRepresentation'] ?? null);
        $t->same([], $integrity['pdfRepresentedPageNumbers'] ?? null);
        $t->same(false, $integrity['pdfPageRepresentationComplete'] ?? null);
        $t->same(false, $integrity['complete'] ?? null);
        $t->same('fail', $quality['status'] ?? null);
        $t->same('fail', $conversionGate['status'] ?? null);
        $t->contains('unsupported_no_text:', (string) ($record['wpBlocks']['error'] ?? ''));
        $t->same(true, $record['pdfFormRenders']['ok'] ?? null);
        $t->same(8, $record['pdfFormRenders']['count'] ?? null);
    },
    'showcase manifest distinguishes source raw HTML from Custom HTML fallback' => static function (TestRunner $t) use ($showcaseManifest, $recordsById, $sourceRawHtmlBlockCount): void {
        $manifest = $showcaseManifest();
        $records = is_array($manifest['records'] ?? null) ? $manifest['records'] : [];
        $byId = $recordsById($records);
        $record = $byId['markdown-github-rendered-syntax'] ?? null;

        $t->true(is_array($record), 'The rich GitHub Markdown sample should be present in the showcase manifest.');
        $quality = is_array($record['importQuality'] ?? null) ? $record['importQuality'] : [];
        $customHtml = is_array($quality['gates']['custom_html_percentage'] ?? null)
            ? $quality['gates']['custom_html_percentage']
            : [];
        $samplePath = dirname(__DIR__, 3) . '/pandoc-showcase/' . ltrim((string) ($record['samplePath'] ?? ''), '/');
        $t->true(is_file($samplePath), 'The rich GitHub Markdown source sample should be available for direct evidence checks.');
        $sourceDocument = \PortLibs\Pandoc\PandocConverter::readFile($samplePath, (string) ($record['format'] ?? 'markdown_github'));
        $sourceRawBlocks = $sourceRawHtmlBlockCount($sourceDocument);

        $t->same('pass', $quality['status'] ?? null, 'Source-preserved GitHub HTML should not lower import quality.');
        $t->true($sourceRawBlocks >= 9, 'The full GFM syntax sample should retain its rich raw HTML packet.');
        $t->same($sourceRawBlocks, $record['sourceRawHtmlBlockCount'] ?? null, 'The manifest source raw HTML count should be generated from the current source document.');
        $t->same($sourceRawBlocks, $record['wpBlockCounts']['html'] ?? null, 'Every retained source raw HTML block should map to one expected WordPress Custom HTML block.');
        $t->same('pass', $customHtml['status'] ?? null, 'Source raw HTML should not count as an unsupported Custom HTML fallback.');
        $t->same(0.0, isset($customHtml['actual']) ? (float) $customHtml['actual'] : null, 'No unexpected Custom HTML fallback should remain.');
    },
    'showcase manifest gates common import quality separately and fails closed on current PDF blockers' => static function (TestRunner $t) use ($showcaseManifest): void {
        $manifest = $showcaseManifest();
        $summary = is_array($manifest['importQualitySegmentSummary'] ?? null) ? $manifest['importQualitySegmentSummary'] : [];
        $gate = is_array($manifest['importQualityGate'] ?? null) ? $manifest['importQualityGate'] : [];
        $common = is_array($summary['common'] ?? null) ? $summary['common'] : [];
        $exotic = is_array($summary['exotic'] ?? null) ? $summary['exotic'] : [];

        $t->true($common !== [], 'Manifest should summarize common WordPress import formats separately.');
        $t->true($exotic !== [], 'Manifest should still summarize exotic formats without mixing them into common import quality.');
        $t->true(($common['samples'] ?? 0) >= 44, 'Common import segment should cover the current normal-user corpus size.');
        $t->same(1, $common['wpFailures'] ?? null, 'The typed MinerU refusal should remain visible as the sole common WordPress conversion failure.');
        $t->true(($common['pass'] ?? 0) >= 29, 'Common import pass count should not regress from the current baseline.');
        $t->true(($common['passReviewOrUnbenchmarked'] ?? 0) >= 39, 'Common import quality coverage should not regress from the current baseline.');
        $t->same(7, $common['fail'] ?? null, 'Known common-format PDF blockers must remain fail closed until repaired.');

        $formats = is_array($common['formats'] ?? null) ? $common['formats'] : [];
        foreach (['docx', 'pdf', 'html', 'xlsx'] as $format) {
            $t->true(isset($formats[$format]), "Common import quality summary should include {$format}.");
        }

        $segments = is_array($gate['segments'] ?? null) ? $gate['segments'] : [];
        $commonGate = is_array($segments['common'] ?? null) ? $segments['common'] : [];
        $exoticGate = is_array($segments['exotic'] ?? null) ? $segments['exotic'] : [];
        $t->same('fail', $gate['status'] ?? null, 'Blocking import quality gate must reject the current common PDF corpus.');
        $t->same(['common'], $gate['blockingSegments'] ?? null, 'The common-format segment should remain the explicit blocking segment.');
        $t->same('fail', $commonGate['status'] ?? null, 'Common import threshold gate should fail separately.');
        $t->same(true, $commonGate['required'] ?? null, 'Common import threshold gate should be blocking.');
        $t->same('pass', $exoticGate['status'] ?? null, 'Exotic import threshold gate should be evaluated separately.');
        $t->same(false, $exoticGate['required'] ?? null, 'Exotic import threshold gate should be tracked without blocking common-format progress.');
        $t->same(29, $commonGate['thresholds']['minPass'] ?? null, 'Common pass threshold should be explicit and ratchetable.');
        $t->same(39, $commonGate['thresholds']['minPassReviewOrUnbenchmarked'] ?? null, 'Common quality-coverage threshold should be explicit and ratchetable.');
        $t->same(5, $commonGate['thresholds']['maxFail'] ?? null, 'Common fail threshold should be explicit and ratchetable.');
        $t->same(0, $commonGate['thresholds']['maxWpFailures'] ?? null, 'Common WordPress conversion failures should be a blocking threshold.');
        $t->same(18, $exoticGate['thresholds']['minPass'] ?? null, 'Exotic pass threshold should be explicit but separate.');
        $t->same(39, $exoticGate['thresholds']['minPassReviewOrUnbenchmarked'] ?? null, 'Exotic quality-coverage threshold should be explicit but separate.');
        $t->same(2, $exoticGate['thresholds']['maxFail'] ?? null, 'Exotic fail threshold should be explicit but separate.');
    },
    'showcase manifest compares simple html samples against visible body text' => static function (TestRunner $t) use ($showcaseManifest, $recordsById): void {
        $manifest = $showcaseManifest();
        $records = is_array($manifest['records'] ?? null) ? $manifest['records'] : [];
        $byId = $recordsById($records);

        foreach (['html-remote-work-policy', 'html-project-status-update'] as $id) {
            $t->true(isset($byId[$id]), "{$id} should be present in the showcase manifest.");
            $gates = $byId[$id]['importQuality']['gates'] ?? [];
            $t->same('pass', $gates['text_completeness']['status'] ?? null, "{$id} should compare visible article text, not generated HTML boilerplate.");
            $t->same('pass', $gates['anchor_validity']['status'] ?? null, "{$id} should keep local fragment links valid.");
            $t->same('pass', $gates['custom_html_percentage']['status'] ?? null, "{$id} should not require Custom HTML fallback blocks.");
        }
    },
    'showcase manifest imports text-only html assets as normal paragraph content' => static function (TestRunner $t) use ($showcaseManifest, $recordsById): void {
        $manifest = $showcaseManifest();
        $records = is_array($manifest['records'] ?? null) ? $manifest['records'] : [];
        $byId = $recordsById($records);
        $record = $byId['html-template'] ?? null;
        $t->true(is_array($record), 'html-template should be present in the showcase manifest.');

        $gates = is_array($record['importQuality']['gates'] ?? null) ? $record['importQuality']['gates'] : [];
        $blocks = is_array($record['wpBlockCounts'] ?? null) ? $record['wpBlockCounts'] : [];
        $t->same('pass', $record['importQuality']['status'] ?? null, 'Text-only HTML assets should be high-confidence imports.');
        $t->same('pass', $gates['text_completeness']['status'] ?? null, 'Text-only HTML asset text should be preserved.');
        $t->same('pass', $gates['visual_structure']['status'] ?? null, 'Paragraph wrapping should be accepted for text-only HTML baselines.');
        $t->same('pass', $gates['custom_html_percentage']['status'] ?? null, 'Text-only HTML assets should not require Custom HTML fallback.');
        $t->true(($blocks['paragraph'] ?? 0) >= 1, 'Text-only HTML assets should produce paragraph blocks.');
        $t->same(0, $blocks['html'] ?? 0, 'Text-only HTML assets should not produce Custom HTML blocks.');
    },
    'showcase manifest scores docx table imports by table content not html chrome' => static function (TestRunner $t) use ($showcaseManifest, $recordsById): void {
        $manifest = $showcaseManifest();
        $records = is_array($manifest['records'] ?? null) ? $manifest['records'] : [];
        $byId = $recordsById($records);
        $record = $byId['docx-tables'] ?? null;
        $t->true(is_array($record), 'docx-tables should be present in the showcase manifest.');

        $gates = is_array($record['importQuality']['gates'] ?? null) ? $record['importQuality']['gates'] : [];
        $t->same('pass', $record['importQuality']['status'] ?? null, 'DOCX table fixture should be a high-confidence WordPress import.');
        $t->same('pass', $gates['text_completeness']['status'] ?? null, 'DOCX table text should preserve cell boundaries when scored.');
        $t->same('pass', $gates['heading_count']['status'] ?? null, 'Generated Pandoc title chrome should not count as an imported document heading.');
        $t->same('pass', $gates['table_count']['status'] ?? null, 'DOCX table count should remain faithful.');
    },
    'showcase manifest treats xlsx as native tables without synthetic sheet navigation' => static function (TestRunner $t) use ($showcaseManifest, $recordsById): void {
        $manifest = $showcaseManifest();
        $records = is_array($manifest['records'] ?? null) ? $manifest['records'] : [];
        $byId = $recordsById($records);

        foreach (['xlsx-basic', 'xlsx-census-tax-parameter-workbook'] as $id) {
            $record = $byId[$id] ?? null;
            $t->true(is_array($record), "{$id} should be present in the showcase manifest.");
            $blocks = is_array($record['wpBlockCounts'] ?? null) ? $record['wpBlockCounts'] : [];
            $gates = is_array($record['importQuality']['gates'] ?? null) ? $record['importQuality']['gates'] : [];

            $t->same('pass', $record['importQuality']['status'] ?? null, "{$id} should remain a high-confidence spreadsheet import.");
            $t->true(($blocks['table'] ?? 0) >= 1, "{$id} should produce native WordPress table blocks.");
            $t->same(0, $blocks['list'] ?? 0, "{$id} should not inject a synthetic workbook sheet navigation list.");
            $t->same(0, $blocks['html'] ?? 0, "{$id} should not need Custom HTML fallback.");
            $t->same('pass', $gates['heading_count']['status'] ?? null, "{$id} sheet headings should not count as a heading regression.");
            $t->same('pass', $gates['list_count']['status'] ?? null, "{$id} should not add a list-count regression.");
            $t->same('pass', $gates['table_count']['status'] ?? null, "{$id} table count should remain faithful.");
            $t->same('pass', $gates['anchor_validity']['status'] ?? null, "{$id} local anchors should remain valid.");
        }
    },
];
