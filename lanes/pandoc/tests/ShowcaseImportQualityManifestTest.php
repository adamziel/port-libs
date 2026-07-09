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

return [
    'showcase manifest keeps a normal user document corpus' => static function (TestRunner $t) use ($showcaseManifest, $recordsByFormat): void {
        $manifest = $showcaseManifest();
        $records = is_array($manifest['records'] ?? null) ? $manifest['records'] : [];
        $byFormat = $recordsByFormat($records);

        $t->true(count($records) >= 40, 'Showcase should include at least a normal-user-sized corpus.');

        $expectedMinimums = [
            'doc' => 2,
            'docx' => 9,
            'pdf' => 6,
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
    'showcase manifest proves complete PHP HTML and WordPress coverage for every supported input format' => static function (TestRunner $t) use ($showcaseManifest): void {
        $manifest = $showcaseManifest();
        $records = is_array($manifest['records'] ?? null) ? $manifest['records'] : [];
        $formats = is_array($manifest['formats'] ?? null) ? $manifest['formats'] : [];
        $coveredFormats = is_array($manifest['coveredFormats'] ?? null) ? $manifest['coveredFormats'] : [];
        $qualitySummary = is_array($manifest['importQualitySummary'] ?? null) ? $manifest['importQualitySummary'] : [];
        $qualityGate = is_array($manifest['importQualityGate'] ?? null) ? $manifest['importQualityGate'] : [];

        $t->same([], $manifest['missingFormats'] ?? null);
        $t->same($formats, $coveredFormats);
        $t->same(90, count($records));
        $t->same(90, $qualitySummary['samples'] ?? null);
        $t->same(90, $qualitySummary['pass'] ?? null);
        $t->same(0, $qualitySummary['review'] ?? null);
        $t->same(0, $qualitySummary['fail'] ?? null);
        $t->same(0, $qualitySummary['unbenchmarked'] ?? null);
        $t->same('pass', $qualityGate['status'] ?? null);
        $t->same('pass', $qualityGate['trackedStatus'] ?? null);

        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $id = (string) ($record['id'] ?? 'unknown');
            $t->same(true, $record['phpHtml']['ok'] ?? null, "{$id} should convert to PHP HTML.");
            $t->same(true, $record['wpBlocks']['ok'] ?? null, "{$id} should convert to WordPress blocks.");
            $quality = is_array($record['importQuality'] ?? null) ? $record['importQuality'] : [];
            $t->same('pass', $quality['status'] ?? null, "{$id} should have passing measured import-quality evidence.");
            $anchor = is_array($quality['gates']['anchor_validity'] ?? null)
                ? $quality['gates']['anchor_validity']
                : [];
            $t->same('pass', $anchor['status'] ?? null, "{$id} should not contain broken local fragment links.");
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
            $requiredGates = (($record['bibliographyComparison']['available'] ?? false) === true)
                ? $bibliographyGates
                : $documentGates;
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
    'showcase uses validated PNG rasters for JBIG2 PDF image objects' => static function (TestRunner $t) use ($showcaseManifest, $recordsById): void {
        $manifest = $showcaseManifest();
        $records = is_array($manifest['records'] ?? null) ? $manifest['records'] : [];
        $byId = $recordsById($records);
        $record = $byId['pdf-archive-motograph-book'] ?? null;

        $t->true(is_array($record), 'The scanned PDF media fixture should be present in the showcase manifest.');
        $quality = is_array($record['importQuality'] ?? null) ? $record['importQuality'] : [];
        $mediaGate = is_array($quality['gates']['media_imported'] ?? null) ? $quality['gates']['media_imported'] : [];
        $diagnostics = is_array($record['wpBlocks']['mediaDiagnostics'] ?? null) ? $record['wpBlocks']['mediaDiagnostics'] : [];
        $pngMedia = array_values(array_filter($record['wpBlocks']['media'] ?? [], static function (mixed $media): bool {
            return is_array($media) && str_ends_with((string) ($media['path'] ?? ''), '.png');
        }));

        $t->same('pass', $quality['status'] ?? null, 'The scanned PDF should no longer remain a review-only import.');
        $t->same('pass', $mediaGate['status'] ?? null, 'Browser-compatible raster media should satisfy the media gate.');
        $t->same(42, count($pngMedia), 'The important JBIG2 image objects should be represented as extracted PNG media.');
        $t->true(in_array('extract-media-pdf-image-raster-loaded:00017:important', $diagnostics, true), 'A JBIG2Globals-backed page image should be recorded as a raster media import.');
        $t->same(false, in_array('extract-media-pdf-image-skipped:JBIG2Decode', $diagnostics, true), 'No supported JBIG2 image should be silently skipped.');
    },
    'showcase manifest distinguishes source raw HTML from Custom HTML fallback' => static function (TestRunner $t) use ($showcaseManifest, $recordsById): void {
        $manifest = $showcaseManifest();
        $records = is_array($manifest['records'] ?? null) ? $manifest['records'] : [];
        $byId = $recordsById($records);
        $record = $byId['markdown-github-rendered-syntax'] ?? null;

        $t->true(is_array($record), 'The rich GitHub Markdown sample should be present in the showcase manifest.');
        $quality = is_array($record['importQuality'] ?? null) ? $record['importQuality'] : [];
        $customHtml = is_array($quality['gates']['custom_html_percentage'] ?? null)
            ? $quality['gates']['custom_html_percentage']
            : [];
        $t->same('pass', $quality['status'] ?? null, 'Source-preserved GitHub HTML should not lower import quality.');
        $t->same(8, $record['sourceRawHtmlBlockCount'] ?? null, 'The source raw HTML block count should remain evidence-backed.');
        $t->same('pass', $customHtml['status'] ?? null, 'Source raw HTML should not count as an unsupported Custom HTML fallback.');
        $t->same(0.0, isset($customHtml['actual']) ? (float) $customHtml['actual'] : null, 'No unexpected Custom HTML fallback should remain.');
    },
    'showcase manifest gates common import quality separately from exotic formats' => static function (TestRunner $t) use ($showcaseManifest): void {
        $manifest = $showcaseManifest();
        $summary = is_array($manifest['importQualitySegmentSummary'] ?? null) ? $manifest['importQualitySegmentSummary'] : [];
        $gate = is_array($manifest['importQualityGate'] ?? null) ? $manifest['importQualityGate'] : [];
        $common = is_array($summary['common'] ?? null) ? $summary['common'] : [];
        $exotic = is_array($summary['exotic'] ?? null) ? $summary['exotic'] : [];

        $t->true($common !== [], 'Manifest should summarize common WordPress import formats separately.');
        $t->true($exotic !== [], 'Manifest should still summarize exotic formats without mixing them into common import quality.');
        $t->true(($common['samples'] ?? 0) >= 44, 'Common import segment should cover the current normal-user corpus size.');
        $t->same(0, $common['wpFailures'] ?? null, 'Common import formats should not have WordPress block conversion failures.');
        $t->true(($common['pass'] ?? 0) >= 29, 'Common import pass count should not regress from the current baseline.');
        $t->true(($common['passReviewOrUnbenchmarked'] ?? 0) >= 39, 'Common import quality coverage should not regress from the current baseline.');
        $t->true(($common['fail'] ?? PHP_INT_MAX) <= 5, 'Common import fail count should not regress from the current baseline.');

        $formats = is_array($common['formats'] ?? null) ? $common['formats'] : [];
        foreach (['docx', 'pdf', 'html', 'xlsx'] as $format) {
            $t->true(isset($formats[$format]), "Common import quality summary should include {$format}.");
        }

        $segments = is_array($gate['segments'] ?? null) ? $gate['segments'] : [];
        $commonGate = is_array($segments['common'] ?? null) ? $segments['common'] : [];
        $exoticGate = is_array($segments['exotic'] ?? null) ? $segments['exotic'] : [];
        $t->same('pass', $gate['status'] ?? null, 'Blocking import quality gate should pass for the current checked-in common corpus.');
        $t->same([], $gate['blockingSegments'] ?? null, 'No common-format segment should be below its blocking threshold.');
        $t->same('pass', $commonGate['status'] ?? null, 'Common import threshold gate should pass separately.');
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
