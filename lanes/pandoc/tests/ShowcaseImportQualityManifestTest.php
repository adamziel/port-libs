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
    'showcase manifest records required import quality gates per successful sample' => static function (TestRunner $t) use ($showcaseManifest): void {
        $manifest = $showcaseManifest();
        $records = is_array($manifest['records'] ?? null) ? $manifest['records'] : [];
        $requiredGates = [
            'text_completeness',
            'heading_count',
            'paragraph_merge_split',
            'list_count',
            'table_count',
            'image_count',
            'anchor_validity',
            'media_imported',
            'custom_html_percentage',
        ];
        $validStatuses = ['pass' => true, 'review' => true, 'fail' => true];
        $successful = 0;

        foreach ($records as $record) {
            if (!is_array($record) || (($record['wpBlocks']['ok'] ?? false) !== true)) {
                continue;
            }
            $successful++;
            $id = (string) ($record['id'] ?? 'unknown');
            $quality = is_array($record['importQuality'] ?? null) ? $record['importQuality'] : [];
            $gates = is_array($quality['gates'] ?? null) ? $quality['gates'] : [];
            foreach ($requiredGates as $gate) {
                $t->true(isset($gates[$gate]) && is_array($gates[$gate]), "{$id} should include {$gate} import-quality gate.");
                $status = (string) ($gates[$gate]['status'] ?? '');
                $t->true(isset($validStatuses[$status]), "{$id} {$gate} should report a known status.");
            }
        }

        $t->true($successful >= 40, 'The gate check should cover a substantial successful WordPress import corpus.');
    },
    'showcase manifest gates common import quality separately from exotic formats' => static function (TestRunner $t) use ($showcaseManifest): void {
        $manifest = $showcaseManifest();
        $summary = is_array($manifest['importQualitySegmentSummary'] ?? null) ? $manifest['importQualitySegmentSummary'] : [];
        $common = is_array($summary['common'] ?? null) ? $summary['common'] : [];
        $exotic = is_array($summary['exotic'] ?? null) ? $summary['exotic'] : [];

        $t->true($common !== [], 'Manifest should summarize common WordPress import formats separately.');
        $t->true($exotic !== [], 'Manifest should still summarize exotic formats without mixing them into common import quality.');
        $t->true(($common['samples'] ?? 0) >= 44, 'Common import segment should cover the current normal-user corpus size.');
        $t->same(0, $common['wpFailures'] ?? null, 'Common import formats should not have WordPress block conversion failures.');
        $t->true(($common['pass'] ?? 0) >= 7, 'Common import pass count should not regress from the current baseline.');
        $t->true(($common['passOrReview'] ?? 0) >= 23, 'Common import pass-or-review count should not regress from the current baseline.');
        $t->true(($common['fail'] ?? PHP_INT_MAX) <= 21, 'Common import fail count should not regress from the current baseline.');

        $formats = is_array($common['formats'] ?? null) ? $common['formats'] : [];
        foreach (['docx', 'pdf', 'html', 'xlsx'] as $format) {
            $t->true(isset($formats[$format]), "Common import quality summary should include {$format}.");
        }
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
];
