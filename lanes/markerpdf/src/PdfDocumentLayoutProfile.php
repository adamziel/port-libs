<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

/**
 * Compact, mergeable document-wide evidence used by bounded semantic ranges.
 *
 * This profile intentionally stops before semantic classification. It keeps
 * only per-page histograms and small candidate samples, so page chunks can be
 * merged without retaining every glyph. The final profile is invariant to
 * extraction chunk size and can therefore be supplied to every PdfReader
 * segment.
 */
final class PdfDocumentLayoutProfile
{
    public const SCHEMA_VERSION = 1;
    private const MAX_EDGE_CANDIDATES_PER_PAGE = 24;
    private const MAX_TEXT_SAMPLE = 160;

    /**
     * @param list<PdfPageFacts> $pages
     * @param array<string,mixed> $inventory
     * @param array<string,mixed> $structure
     * @return array<string,mixed>
     */
    public static function fromPages(
        string $sourceSha256,
        array $pages,
        array $inventory,
        array $structure = []
    ): array {
        if (preg_match('/^[a-f0-9]{64}$/', $sourceSha256) !== 1) {
            throw new InvalidArgumentException('A PDF layout profile requires its source SHA-256 digest.');
        }
        $pageEvidence = [];
        foreach ($pages as $page) {
            if (!$page instanceof PdfPageFacts) {
                throw new InvalidArgumentException('PDF layout profile pages must contain PdfPageFacts values.');
            }
            $pageEvidence[(string) $page->pageNumber()] = self::pageEvidence($page);
        }

        return self::finalize(
            $sourceSha256,
            max(0, (int) ($inventory['totalPages'] ?? count($pageEvidence))),
            $pageEvidence,
            self::taggedRoleInventory($structure)
        );
    }

    /**
     * @param list<array<string,mixed>> $profiles
     * @return array<string,mixed>
     */
    public static function merge(array $profiles, ?int $totalPages = null): array
    {
        if ($profiles === []) {
            throw new InvalidArgumentException('No PDF layout profiles were available to merge.');
        }
        $sourceSha256 = '';
        $pageEvidence = [];
        $taggedRoles = [];
        $resolvedTotalPages = max(0, (int) ($totalPages ?? 0));
        foreach ($profiles as $profile) {
            if (($profile['schemaVersion'] ?? null) !== self::SCHEMA_VERSION
                || !is_string($profile['sourceSha256'] ?? null)
                || !is_array($profile['pageEvidence'] ?? null)) {
                throw new InvalidArgumentException('A serialized PDF layout profile was invalid.');
            }
            if ($sourceSha256 === '') {
                $sourceSha256 = $profile['sourceSha256'];
            } elseif (!hash_equals($sourceSha256, $profile['sourceSha256'])) {
                throw new InvalidArgumentException('PDF layout profiles came from different source documents.');
            }
            $resolvedTotalPages = max($resolvedTotalPages, (int) ($profile['totalPages'] ?? 0));
            foreach ($profile['pageEvidence'] as $page => $evidence) {
                $pageNumber = (int) $page;
                if ($pageNumber < 1 || !is_array($evidence)) {
                    throw new InvalidArgumentException('PDF layout profile page evidence was invalid.');
                }
                if (isset($pageEvidence[(string) $pageNumber])) {
                    if ($pageEvidence[(string) $pageNumber] !== $evidence) {
                        throw new InvalidArgumentException('Overlapping PDF layout profiles disagreed on page evidence.');
                    }
                    continue;
                }
                $pageEvidence[(string) $pageNumber] = $evidence;
            }
            foreach ($profile['taggedRoleInventory'] ?? [] as $role => $count) {
                if (is_string($role) && is_int($count)) {
                    $taggedRoles[$role] = max($taggedRoles[$role] ?? 0, $count);
                }
            }
        }

        return self::finalize($sourceSha256, $resolvedTotalPages, $pageEvidence, $taggedRoles);
    }

    /** @return array<string,mixed> */
    private static function pageEvidence(PdfPageFacts $page): array
    {
        $geometry = $page->geometry();
        $bbox = self::normalizedBox($geometry['bbox'] ?? null);
        $spans = [];
        foreach ($page->text()['spans'] ?? [] as $span) {
            if (!is_array($span) || !is_string($span['text'] ?? null) || trim($span['text']) === '') {
                continue;
            }
            $spans[] = $span;
        }
        $lineSegments = self::lineSegments($spans);
        $fontSizes = [];
        $lineStarts = [];
        $numericStarts = [];
        $orientations = [];
        $cueCandidates = [];
        $edgeCandidates = [];
        foreach ($lineSegments as $line) {
            $fontKey = self::quantized((float) $line['fontSize'], 0.5);
            $fontSizes[$fontKey] = ($fontSizes[$fontKey] ?? 0) + 1;
            $startKey = self::quantized((float) $line['x1'], 12.0);
            $lineStarts[$startKey] = ($lineStarts[$startKey] ?? 0) + 1;
            $orientation = (string) $line['orientation'];
            $orientations[$orientation] = ($orientations[$orientation] ?? 0) + 1;
            if (preg_match('/(?:\p{N}[\p{N}\p{P}\p{S}]*){1,}/u', (string) $line['text']) === 1) {
                $numericStarts[$startKey] = ($numericStarts[$startKey] ?? 0) + 1;
            }
            $cue = self::cueCandidate((string) $line['text']);
            if ($cue !== null) {
                $cueCandidates[$cue] = ($cueCandidates[$cue] ?? 0) + 1;
            }
            $edge = self::edgeForLine($line, $bbox);
            if ($edge !== null && count($edgeCandidates) < self::MAX_EDGE_CANDIDATES_PER_PAGE) {
                $edgeCandidates[] = [
                    'key' => self::comparableText((string) $line['text']),
                    'text' => self::sampleText((string) $line['text']),
                    'edge' => $edge,
                    'position' => $edge === 'top' || $edge === 'bottom'
                        ? self::quantized((float) $line['y1'], 6.0)
                        : self::quantized((float) $line['x1'], 6.0),
                    'orientation' => $orientation,
                ];
            }
        }
        ksort($fontSizes, SORT_NUMERIC);
        ksort($lineStarts, SORT_NUMERIC);
        ksort($numericStarts, SORT_NUMERIC);
        ksort($orientations);
        ksort($cueCandidates);
        $graphics = $page->graphics();
        $annotations = $page->annotations();

        return [
            'pageNumber' => $page->pageNumber(),
            'label' => $page->label(),
            'bbox' => $bbox,
            'rotation' => (int) ($geometry['rotation'] ?? 0),
            'lineCount' => count($lineSegments),
            'fontSizes' => $fontSizes,
            'lineStarts' => $lineStarts,
            'numericStarts' => $numericStarts,
            'orientations' => $orientations,
            'cueCandidates' => $cueCandidates,
            'edgeCandidates' => $edgeCandidates,
            'filledRectangleCount' => count(is_array($graphics['filledRectangles'] ?? null) ? $graphics['filledRectangles'] : []),
            'visualCounts' => [
                'images' => count(is_array($graphics['images'] ?? null) ? $graphics['images'] : []),
                'forms' => count(is_array($graphics['forms'] ?? null) ? $graphics['forms'] : []),
            ],
            'annotationCount' => array_sum(array_map(
                static fn (mixed $records): int => is_array($records) ? count($records) : 0,
                $annotations
            )),
            'issueCount' => count($page->issues()),
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $pageEvidence
     * @param array<string,int> $taggedRoles
     * @return array<string,mixed>
     */
    private static function finalize(
        string $sourceSha256,
        int $totalPages,
        array $pageEvidence,
        array $taggedRoles
    ): array {
        uksort($pageEvidence, static fn (string $left, string $right): int => (int) $left <=> (int) $right);
        ksort($taggedRoles);
        $fontSizes = [];
        $orientations = [];
        $cuePages = [];
        $cueCounts = [];
        $furniture = [];
        $visuals = ['images' => 0, 'forms' => 0];
        $columns = [];
        $numericColumns = [];
        $pagesWithRectangles = 0;
        foreach ($pageEvidence as $page => $evidence) {
            foreach ($evidence['fontSizes'] ?? [] as $key => $count) {
                $fontSizes[$key] = ($fontSizes[$key] ?? 0) + (int) $count;
            }
            foreach ($evidence['orientations'] ?? [] as $key => $count) {
                $orientations[$key] = ($orientations[$key] ?? 0) + (int) $count;
            }
            foreach ($evidence['cueCandidates'] ?? [] as $key => $count) {
                $cueCounts[$key] = ($cueCounts[$key] ?? 0) + (int) $count;
                $cuePages[$key][(int) $page] = true;
            }
            foreach ($evidence['edgeCandidates'] ?? [] as $candidate) {
                if (!is_array($candidate) || ($candidate['key'] ?? '') === '') {
                    continue;
                }
                $key = (string) $candidate['edge'] . "\0"
                    . (string) $candidate['orientation'] . "\0"
                    . (string) $candidate['key'];
                $furniture[$key]['candidate'] = $candidate;
                $furniture[$key]['pages'][(int) $page] = true;
            }
            foreach ($evidence['visualCounts'] ?? [] as $kind => $count) {
                if (isset($visuals[$kind])) {
                    $visuals[$kind] += (int) $count;
                }
            }
            if ((int) ($evidence['filledRectangleCount'] ?? 0) > 0) {
                $pagesWithRectangles++;
            }
            $activeStarts = [];
            foreach ($evidence['lineStarts'] ?? [] as $start => $count) {
                if ((int) $count >= 2) {
                    $activeStarts[] = (float) $start;
                }
            }
            sort($activeStarts, SORT_NUMERIC);
            $columns[] = [
                'page' => (int) $page,
                'activeStarts' => $activeStarts,
                'gutters' => self::gaps($activeStarts, 36.0),
            ];
            $activeNumeric = [];
            foreach ($evidence['numericStarts'] ?? [] as $start => $count) {
                if ((int) $count >= 2) {
                    $activeNumeric[] = (float) $start;
                }
            }
            if (count($activeNumeric) >= 2) {
                sort($activeNumeric, SORT_NUMERIC);
                $numericColumns[] = ['page' => (int) $page, 'starts' => $activeNumeric];
            }
        }
        ksort($fontSizes, SORT_NUMERIC);
        ksort($orientations);
        ksort($cueCounts);
        $recurringFurniture = [];
        foreach ($furniture as $group) {
            $pages = array_keys($group['pages'] ?? []);
            sort($pages, SORT_NUMERIC);
            if (count($pages) < 2) {
                continue;
            }
            $recurringFurniture[] = $group['candidate'] + [
                'pages' => $pages,
                'pageCount' => count($pages),
            ];
        }
        usort($recurringFurniture, static fn (array $left, array $right): int =>
            strcmp((string) $left['edge'], (string) $right['edge'])
                ?: strcmp((string) $left['key'], (string) $right['key'])
        );
        $cueProfile = [];
        foreach ($cueCounts as $cue => $count) {
            $pages = array_keys($cuePages[$cue] ?? []);
            sort($pages, SORT_NUMERIC);
            $cueProfile[] = compact('cue', 'count', 'pages');
        }
        $coveredPages = array_values(array_map('intval', array_keys($pageEvidence)));
        $profile = [
            'schemaVersion' => self::SCHEMA_VERSION,
            'sourceSha256' => $sourceSha256,
            'totalPages' => $totalPages,
            'coveredPages' => $coveredPages,
            'complete' => $totalPages === 0 || $coveredPages === range(1, $totalPages),
            'pageEvidence' => $pageEvidence,
            'fontSizeHistogram' => $fontSizes,
            'writingModeHistogram' => $orientations,
            'recurringFurniture' => $recurringFurniture,
            'cueProfile' => $cueProfile,
            'columnProfiles' => $columns,
            'taggedRoleInventory' => $taggedRoles,
            'tableEvidence' => [
                'taggedTableRoleCount' => (int) ($taggedRoles['Table'] ?? 0),
                'pagesWithFilledRectangles' => $pagesWithRectangles,
                'numericColumnPages' => $numericColumns,
            ],
            'visualInventory' => $visuals,
        ];
        $encoded = json_encode($profile, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        $profile['profileDigest'] = hash('sha256', is_string($encoded) ? $encoded : serialize($profile));

        return $profile;
    }

    /**
     * Reconstruct small visual line segments from positioned spans. A wide
     * gap starts another segment so simultaneous text in independent columns
     * does not become one synthetic line.
     *
     * @param list<array<string,mixed>> $spans
     * @return list<array{text:string,x1:float,y1:float,x2:float,y2:float,fontSize:float,orientation:string}>
     */
    private static function lineSegments(array $spans): array
    {
        $baselines = [];
        foreach ($spans as $span) {
            $rotation = (int) round((float) ($span['rotation'] ?? 0));
            $baseline = (float) ($span['textY1'] ?? $span['y1'] ?? 0.0);
            $key = $rotation . ':' . self::quantized($baseline, 2.5);
            $baselines[$key][] = $span;
        }
        $segments = [];
        foreach ($baselines as $lineSpans) {
            usort($lineSpans, static fn (array $left, array $right): int =>
                ((float) ($left['x1'] ?? 0.0)) <=> ((float) ($right['x1'] ?? 0.0))
            );
            $current = null;
            foreach ($lineSpans as $span) {
                $text = trim((string) ($span['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $fontSize = max(1.0, (float) ($span['fontSize'] ?? 1.0));
                $x1 = (float) ($span['x1'] ?? 0.0);
                $x2 = (float) ($span['x2'] ?? $x1);
                $gap = is_array($current) ? $x1 - (float) $current['x2'] : 0.0;
                if (is_array($current) && $gap > max(24.0, $fontSize * 4.0)) {
                    $segments[] = $current;
                    $current = null;
                }
                if (!is_array($current)) {
                    $current = [
                        'text' => $text,
                        'x1' => $x1,
                        'y1' => (float) ($span['y1'] ?? 0.0),
                        'x2' => $x2,
                        'y2' => (float) ($span['y2'] ?? 0.0),
                        'fontSize' => $fontSize,
                        'orientation' => self::spanOrientation($span),
                    ];
                    continue;
                }
                $separator = (($span['wordBoundaryBefore'] ?? false) === true || $gap > $fontSize * 0.18) ? ' ' : '';
                $current['text'] .= $separator . $text;
                $current['x2'] = max((float) $current['x2'], $x2);
                $current['y1'] = min((float) $current['y1'], (float) ($span['y1'] ?? $current['y1']));
                $current['y2'] = max((float) $current['y2'], (float) ($span['y2'] ?? $current['y2']));
                $current['fontSize'] = max((float) $current['fontSize'], $fontSize);
            }
            if (is_array($current)) {
                $segments[] = $current;
            }
        }
        usort($segments, static fn (array $left, array $right): int =>
            ((float) $right['y1'] <=> (float) $left['y1'])
                ?: ((float) $left['x1'] <=> (float) $right['x1'])
        );

        return $segments;
    }

    /** @param array<string,mixed> $span */
    private static function spanOrientation(array $span): string
    {
        if (is_string($span['direction'] ?? null) && $span['direction'] !== '') {
            return $span['direction'];
        }
        $rotation = ((int) round((float) ($span['rotation'] ?? 0)) % 360 + 360) % 360;

        return $rotation === 0 ? 'horizontal' : 'rotated-' . $rotation;
    }

    /** @param mixed $value @return list<float> */
    private static function normalizedBox(mixed $value): array
    {
        if (!is_array($value) || count($value) < 4) {
            return [0.0, 0.0, 612.0, 792.0];
        }
        $values = array_values($value);

        return [
            (float) $values[0],
            (float) $values[1],
            (float) $values[2],
            (float) $values[3],
        ];
    }

    /** @param array<string,mixed> $line @param list<float> $bbox */
    private static function edgeForLine(array $line, array $bbox): ?string
    {
        [$x1, $y1, $x2, $y2] = $bbox;
        $width = max(1.0, $x2 - $x1);
        $height = max(1.0, $y2 - $y1);
        $centerX = ((float) $line['x1'] + (float) $line['x2']) / 2.0;
        $centerY = ((float) $line['y1'] + (float) $line['y2']) / 2.0;
        $distances = [
            'left' => ($centerX - $x1) / $width,
            'right' => ($x2 - $centerX) / $width,
            'bottom' => ($centerY - $y1) / $height,
            'top' => ($y2 - $centerY) / $height,
        ];
        asort($distances, SORT_NUMERIC);
        $edge = (string) array_key_first($distances);

        return ($distances[$edge] ?? 1.0) <= 0.12 ? $edge : null;
    }

    private static function cueCandidate(string $text): ?string
    {
        if (preg_match('/^\h*([\p{Lu}\p{Lt}\p{N}][\p{Lu}\p{Lt}\p{N}\p{M}\'’._-]*(?:\h+[\p{Lu}\p{Lt}\p{N}][\p{Lu}\p{Lt}\p{N}\p{M}\'’._-]*){0,3})\h*:\h*\S/u', $text, $match) !== 1) {
            return null;
        }

        return self::comparableText((string) $match[1]);
    }

    /** @param array<string,mixed> $structure @return array<string,int> */
    private static function taggedRoleInventory(array $structure): array
    {
        $roles = [];
        foreach ($structure['taggedStructureRoles'] ?? [] as $role) {
            if (is_string($role) && $role !== '') {
                $roles[$role] = ($roles[$role] ?? 0) + 1;
            }
        }
        foreach ($structure['taggedStructureItems'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $role = (string) ($item['resolvedRole'] ?? $item['role'] ?? '');
            if ($role !== '') {
                $roles[$role] = ($roles[$role] ?? 0) + 1;
            }
        }
        ksort($roles);

        return $roles;
    }

    /** @param list<float> $values @return list<float> */
    private static function gaps(array $values, float $minimum): array
    {
        $gaps = [];
        for ($index = 1, $count = count($values); $index < $count; $index++) {
            $gap = $values[$index] - $values[$index - 1];
            if ($gap >= $minimum) {
                $gaps[] = $gap;
            }
        }

        return $gaps;
    }

    private static function quantized(float $value, float $step): string
    {
        return (string) (round($value / $step) * $step);
    }

    private static function comparableText(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $text = $normalized;
            }
        }

        return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    }

    private static function sampleText(string $text): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, self::MAX_TEXT_SAMPLE, 'UTF-8');
        }

        return substr($text, 0, self::MAX_TEXT_SAMPLE);
    }
}
