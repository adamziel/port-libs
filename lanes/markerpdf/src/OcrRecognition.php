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
