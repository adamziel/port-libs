<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class OcrDetection
{
    private MarkerSettings $settings;

    public function __construct(?MarkerSettings $settings = null)
    {
        $this->settings = $settings ?? new MarkerSettings();
    }

    public function batchSize(float $batchMultiplier = 1.0): int
    {
        $configured = $this->settings->get('DETECTOR_BATCH_SIZE');
        $base = $configured !== null ? (int) $configured : 4;

        return (int) ($base * $batchMultiplier);
    }

    /**
     * Native boundary for marker.ocr.detection::surya_detection. Detection
     * predictions are supplied by the caller so this slice does not load Surya.
     *
     * @param list<mixed> $images
     * @param list<array<string, mixed>> $pages
     * @param list<array<string, mixed>> $predictions
     * @return array{
     *     pages: list<array<string, mixed>>,
     *     plan: array{image_count: int, page_count: int, prediction_count: int, assigned_pages: int, batch_size: int}
     * }
     */
    public function runWithSuppliedPredictions(
        array $images,
        array $pages,
        array $predictions,
        float $batchMultiplier = 1.0
    ): array {
        $pages = array_values($pages);
        $predictions = array_values($predictions);
        $assignedPages = min(count($pages), count($predictions));

        for ($index = 0; $index < $assignedPages; $index++) {
            if (!is_array($predictions[$index])) {
                throw new InvalidArgumentException('Supplied OCR detection predictions must be arrays.');
            }
            $pages[$index]['text_lines'] = $predictions[$index];
        }

        return [
            'pages' => $pages,
            'plan' => [
                'image_count' => count($images),
                'page_count' => count($pages),
                'prediction_count' => count($predictions),
                'assigned_pages' => $assignedPages,
                'batch_size' => $this->batchSize($batchMultiplier),
            ],
        ];
    }
}
