<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class OcrRecognition
{
    private MarkerSettings $settings;
    private OcrHeuristics $heuristics;

    public function __construct(?MarkerSettings $settings = null, ?OcrHeuristics $heuristics = null)
    {
        $this->settings = $settings ?? new MarkerSettings();
        $this->heuristics = $heuristics ?? new OcrHeuristics();
    }

    public function batchSize(): int
    {
        $configured = $this->settings->get('RECOGNITION_BATCH_SIZE');
        if ($configured !== null) {
            return (int) $configured;
        }

        return 32;
    }

    /**
     * @param list<array<string, mixed>> $pages
     * @return list<int>
     */
    public function ocrPageIndexes(array $pages, ?bool $ocrAllPages = null): array
    {
        $noText = $this->heuristics->noTextFound($pages);
        $ocrAllPages ??= (bool) $this->settings->get('OCR_ALL_PAGES');
        $indexes = [];

        foreach ($pages as $index => $page) {
            if ($this->heuristics->shouldOcrPage($page, $noText, $ocrAllPages)) {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }

    /**
     * Native preprocessing boundary for marker.ocr.recognition::surya_recognition.
     *
     * Upstream scales detector polygons from SURYA_DETECTOR_DPI to SURYA_OCR_DPI
     * before invoking the Surya recognizer, and drops zero-area polygons.
     *
     * @param list<array<string, mixed>> $pages
     * @param list<int> $pageIndexes
     * @return array{page_indexes: list<int>, polygons: list<list<list<list<int>>>>, batch_size: int, box_scale: float}
     */
    public function suryaRecognitionPlan(array $pages, array $pageIndexes, int|float $batchMultiplier = 1): array
    {
        $detectorDpi = (float) $this->settings->get('SURYA_DETECTOR_DPI');
        $ocrDpi = (float) $this->settings->get('SURYA_OCR_DPI');
        $boxScale = $detectorDpi > 0.0 ? $ocrDpi / $detectorDpi : 1.0;
        $polygons = [];

        foreach ($pageIndexes as $pageIndex) {
            if (!isset($pages[$pageIndex]) || !is_array($pages[$pageIndex])) {
                throw new InvalidArgumentException('Missing page for OCR page index ' . $pageIndex . '.');
            }

            $pagePolygons = [];
            foreach ($this->textLineBoxes($pages[$pageIndex]) as $box) {
                $polygon = $this->polygon($box);
                if ($polygon === []) {
                    continue;
                }

                $scaled = [];
                foreach ($polygon as $point) {
                    $scaled[] = [
                        (int) ((float) $point[0] * $boxScale),
                        (int) ((float) $point[1] * $boxScale),
                    ];
                }

                if ($this->polygonAreaBbox($scaled) <= 0.0) {
                    continue;
                }

                $pagePolygons[] = $scaled;
            }
            $polygons[] = $pagePolygons;
        }

        return [
            'page_indexes' => array_values($pageIndexes),
            'polygons' => $polygons,
            'batch_size' => (int) ($this->batchSize() * (float) $batchMultiplier),
            'box_scale' => $boxScale,
        ];
    }

    /**
     * Native postprocessing boundary for marker.ocr.recognition::surya_recognition.
     *
     * @param list<array<string, mixed>> $pages
     * @param list<int> $pageIndexes
     * @param list<list<array{text?: string, bbox?: list<float|int>, confidence?: float|int}>> $recognizedTextLines
     * @param list<array{width?: int|float, height?: int|float}|list<int|float>> $imageSizes
     * @param list<string>|null $languages
     * @return list<array<string, mixed>>
     */
    public function buildSuryaRecognitionPages(
        array $pages,
        array $pageIndexes,
        array $recognizedTextLines,
        array $imageSizes,
        ?array $languages = null
    ): array {
        $count = count($pageIndexes);
        if (count($recognizedTextLines) !== $count || count($imageSizes) !== $count) {
            throw new InvalidArgumentException('OCR page indexes, recognized text lines, and image sizes must have matching counts.');
        }

        $newPages = [];
        foreach ($pageIndexes as $position => $pageIndex) {
            if (!isset($pages[$pageIndex]) || !is_array($pages[$pageIndex])) {
                throw new InvalidArgumentException('Missing page for OCR page index ' . $pageIndex . '.');
            }

            $oldPage = $pages[$pageIndex];
            $imageSize = $this->imageSize($imageSizes[$position]);
            $imageBbox = $this->bbox($oldPage['text_lines']['image_bbox'] ?? null)
                ?? $this->bbox($oldPage['bbox'] ?? null)
                ?? [0.0, 0.0, (float) $imageSize['width'], (float) $imageSize['height']];

            $blocks = [];
            $confidenceValues = [];
            foreach ($recognizedTextLines[$position] as $lineIndex => $line) {
                if (!is_array($line)) {
                    continue;
                }

                $sourceBbox = $this->bbox($line['bbox'] ?? null);
                if ($sourceBbox === null) {
                    continue;
                }

                $scaledBbox = $this->rescaleBbox(
                    [0.0, 0.0, (float) $imageSize['width'], (float) $imageSize['height']],
                    $imageBbox,
                    $sourceBbox
                );

                $confidence = $this->confidence($line['confidence'] ?? null);
                if ($confidence !== null) {
                    $confidenceValues[] = $confidence;
                }

                $span = [
                    'text' => (string) ($line['text'] ?? ''),
                    'bbox' => $scaledBbox,
                    'span_id' => (string) $pageIndex . '_' . $lineIndex,
                    'font' => '',
                    'font_weight' => 0,
                    'font_size' => 0,
                ];
                if ($confidence !== null) {
                    $span['confidence'] = $confidence;
                }

                $pageLine = [
                    'bbox' => $scaledBbox,
                    'spans' => [$span],
                ];
                if ($confidence !== null) {
                    $pageLine['confidence'] = $confidence;
                }

                $blocks[] = [
                    'bbox' => $scaledBbox,
                    'pnum' => (int) $pageIndex,
                    'lines' => [$pageLine],
                ];
            }

            $page = [
                'blocks' => $blocks,
                'pnum' => (int) $pageIndex,
                'bbox' => $imageBbox,
                'rotation' => 0,
                'text_lines' => $oldPage['text_lines'] ?? null,
                'ocr_method' => 'surya',
            ];
            if ($languages !== null) {
                $page['ocr_languages'] = array_values(array_map(static fn (mixed $language): string => (string) $language, $languages));
            }
            if ($confidenceValues !== []) {
                $page['ocr_confidence'] = $this->confidenceSummary($confidenceValues);
            }

            $newPages[] = $page;
        }

        return $newPages;
    }

    /**
     * Native boundary for marker.ocr.recognition::run_ocr. Recognition output
     * is supplied by the caller so this slice does not load Surya/Tesseract.
     *
     * @param list<array<string, mixed>> $pages
     * @param array<int, array<string, mixed>>|list<array<string, mixed>> $recognizedPages
     * @return array{pages: list<array<string, mixed>>, stats: array{ocr_pages: int, ocr_failed: int, ocr_success: int, ocr_engine: string}}
     */
    public function runWithSuppliedPages(
        array $pages,
        array $recognizedPages,
        ?string $ocrEngine = null,
        ?bool $ocrAllPages = null
    ): array {
        $ocrIndexes = $this->ocrPageIndexes($pages, $ocrAllPages);
        $ocrPages = count($ocrIndexes);

        if ($ocrPages === 0) {
            return [
                'pages' => array_values($pages),
                'stats' => $this->stats(0, 0, 0, 'none'),
            ];
        }

        $ocrMethod = $ocrEngine ?? $this->settings->get('OCR_ENGINE');
        if ($ocrMethod === null || $ocrMethod === 'None') {
            return [
                'pages' => array_values($pages),
                'stats' => $this->stats(0, 0, 0, 'none'),
            ];
        }
        if (!in_array($ocrMethod, ['surya', 'ocrmypdf'], true)) {
            throw new InvalidArgumentException('Unknown OCR method ' . $ocrMethod);
        }

        $recognizedByIndex = $this->recognizedPagesByIndex($recognizedPages, $ocrIndexes);
        $ocrSuccess = 0;
        $ocrFailed = 0;

        foreach ($ocrIndexes as $index) {
            $recognized = $recognizedByIndex[$index];
            $text = $this->pagePrelimText($recognized);

            if ($text === '' || $this->heuristics->detectBadOcr($text)) {
                $ocrFailed++;
                continue;
            }

            $recognized['ocr_method'] = $ocrMethod;
            $pages[$index] = $recognized;
            $ocrSuccess++;
        }

        return [
            'pages' => array_values($pages),
            'stats' => $this->stats($ocrPages, $ocrFailed, $ocrSuccess, $ocrMethod),
        ];
    }

    /**
     * @param array<int, array<string, mixed>>|list<array<string, mixed>> $recognizedPages
     * @param list<int> $ocrIndexes
     * @return array<int, array<string, mixed>>
     */
    private function recognizedPagesByIndex(array $recognizedPages, array $ocrIndexes): array
    {
        if (array_is_list($recognizedPages)) {
            if (count($recognizedPages) < count($ocrIndexes)) {
                throw new InvalidArgumentException('A supplied OCR page is required for every page selected for OCR.');
            }

            $byIndex = [];
            foreach ($ocrIndexes as $position => $pageIndex) {
                $page = $recognizedPages[$position];
                if (!is_array($page)) {
                    throw new InvalidArgumentException('Supplied OCR pages must be arrays.');
                }
                $byIndex[$pageIndex] = $page;
            }

            return $byIndex;
        }

        $byIndex = [];
        foreach ($ocrIndexes as $pageIndex) {
            if (!isset($recognizedPages[$pageIndex]) || !is_array($recognizedPages[$pageIndex])) {
                throw new InvalidArgumentException('A supplied OCR page is required for page index ' . $pageIndex . '.');
            }
            $byIndex[$pageIndex] = $recognizedPages[$pageIndex];
        }

        return $byIndex;
    }

    /**
     * @param array<string, mixed> $page
     * @return list<array<string, mixed>>
     */
    private function textLineBoxes(array $page): array
    {
        $textLines = $page['text_lines'] ?? null;
        if (!is_array($textLines)) {
            return [];
        }

        $boxes = $textLines['bboxes'] ?? $textLines['boxes'] ?? [];
        if (!is_array($boxes)) {
            return [];
        }

        return array_values(array_filter($boxes, static fn (mixed $box): bool => is_array($box)));
    }

    /**
     * @param array<string, mixed> $box
     * @return list<array{0: float, 1: float}>
     */
    private function polygon(array $box): array
    {
        if (isset($box['polygon']) && is_array($box['polygon'])) {
            $polygon = [];
            foreach ($box['polygon'] as $point) {
                if (!is_array($point) || count($point) < 2) {
                    continue;
                }
                $polygon[] = [(float) $point[0], (float) $point[1]];
            }

            return count($polygon) >= 2 ? $polygon : [];
        }

        $bbox = $this->bbox($box['bbox'] ?? $box);
        if ($bbox === null) {
            return [];
        }

        return [
            [$bbox[0], $bbox[1]],
            [$bbox[2], $bbox[1]],
            [$bbox[2], $bbox[3]],
            [$bbox[0], $bbox[3]],
        ];
    }

    /**
     * @param list<array{0: int, 1: int}> $polygon
     */
    private function polygonAreaBbox(array $polygon): float
    {
        if ($polygon === []) {
            return 0.0;
        }

        $xs = array_map(static fn (array $point): int => $point[0], $polygon);
        $ys = array_map(static fn (array $point): int => $point[1], $polygon);

        return (float) ((max($xs) - min($xs)) * (max($ys) - min($ys)));
    }

    /**
     * @param mixed $value
     * @return array{width: int, height: int}
     */
    private function imageSize(mixed $value): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('OCR image size must be an array.');
        }
        if (isset($value['width'], $value['height'])) {
            return ['width' => (int) $value['width'], 'height' => (int) $value['height']];
        }
        $values = array_values($value);
        if (count($values) >= 2) {
            return ['width' => (int) $values[0], 'height' => (int) $values[1]];
        }

        throw new InvalidArgumentException('OCR image size must provide width and height.');
    }

    private function confidence(mixed $value): ?float
    {
        if (!is_int($value) && !is_float($value)) {
            return null;
        }

        $confidence = (float) $value;
        if ($confidence < 0.0 || $confidence > 1.0) {
            return null;
        }

        return $confidence;
    }

    /**
     * @param non-empty-list<float> $values
     * @return array{count: int, min: float, max: float, average: float}
     */
    private function confidenceSummary(array $values): array
    {
        return [
            'count' => count($values),
            'min' => min($values),
            'max' => max($values),
            'average' => round(array_sum($values) / count($values), 6),
        ];
    }

    /**
     * @param mixed $value
     * @return list<float>|null
     */
    private function bbox(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        if (isset($value['bbox'])) {
            return $this->bbox($value['bbox']);
        }

        $values = array_values($value);
        if (count($values) !== 4) {
            return null;
        }
        foreach ($values as $item) {
            if (!is_int($item) && !is_float($item)) {
                return null;
            }
        }

        return array_map(static fn (int|float $item): float => (float) $item, $values);
    }

    /**
     * @param list<float> $from
     * @param list<float> $to
     * @param list<float> $bbox
     * @return list<float>
     */
    private function rescaleBbox(array $from, array $to, array $bbox): array
    {
        $fromWidth = $from[2] - $from[0];
        $fromHeight = $from[3] - $from[1];
        $toWidth = $to[2] - $to[0];
        $toHeight = $to[3] - $to[1];

        if ($fromWidth == 0.0 || $fromHeight == 0.0) {
            return $bbox;
        }

        return [
            round($to[0] + (($bbox[0] - $from[0]) / $fromWidth) * $toWidth, 10),
            round($to[1] + (($bbox[1] - $from[1]) / $fromHeight) * $toHeight, 10),
            round($to[0] + (($bbox[2] - $from[0]) / $fromWidth) * $toWidth, 10),
            round($to[1] + (($bbox[3] - $from[1]) / $fromHeight) * $toHeight, 10),
        ];
    }

    /**
     * @param array<string, mixed> $page
     */
    private function pagePrelimText(array $page): string
    {
        if (isset($page['prelim_text'])) {
            return (string) $page['prelim_text'];
        }
        if (isset($page['prelimText'])) {
            return (string) $page['prelimText'];
        }

        $parts = [];
        $blocks = $page['blocks'] ?? [];
        if (!is_array($blocks)) {
            return '';
        }

        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            if (isset($block['text'])) {
                $parts[] = (string) $block['text'];
                continue;
            }

            $lines = $block['lines'] ?? [];
            if (!is_array($lines)) {
                continue;
            }
            foreach ($lines as $line) {
                if (!is_array($line)) {
                    $parts[] = (string) $line;
                    continue;
                }
                if (isset($line['text'])) {
                    $parts[] = (string) $line['text'];
                    continue;
                }
                if (!isset($line['spans']) || !is_array($line['spans'])) {
                    continue;
                }

                $spanText = '';
                foreach ($line['spans'] as $span) {
                    if (is_array($span)) {
                        $spanText .= (string) ($span['text'] ?? '');
                    }
                }
                $parts[] = $spanText;
            }
        }

        return implode("\n", $parts);
    }

    /**
     * @return array{ocr_pages: int, ocr_failed: int, ocr_success: int, ocr_engine: string}
     */
    private function stats(int $ocrPages, int $ocrFailed, int $ocrSuccess, string $ocrEngine): array
    {
        return [
            'ocr_pages' => $ocrPages,
            'ocr_failed' => $ocrFailed,
            'ocr_success' => $ocrSuccess,
            'ocr_engine' => $ocrEngine,
        ];
    }
}
