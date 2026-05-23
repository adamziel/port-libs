<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class DebugRenderPlanner
{
    /**
     * Native operation-plan boundary for marker.debug.render::render_on_image.
     *
     * @param list<array<int, float|int>> $bboxes
     * @param list<string>|null $labels
     * @param string|list<string> $color
     * @return array{label_font_size: int, operations: list<array<string, mixed>>}
     */
    public function renderOnImagePlan(
        array $bboxes,
        ?array $labels = null,
        int $labelOffset = 1,
        int $labelFontSize = 10,
        string|array $color = 'red',
        bool $drawBbox = true,
        ?callable $textSizer = null
    ): array {
        if ($labelFontSize <= 0) {
            throw new InvalidArgumentException('labelFontSize must be greater than zero.');
        }

        $operations = [];
        foreach (array_values($bboxes) as $index => $rawBbox) {
            $bbox = $this->intBbox($rawBbox);

            if ($drawBbox) {
                $operations[] = [
                    'type' => 'rectangle',
                    'role' => 'bbox',
                    'bbox' => $bbox,
                    'outline' => $this->colorAt($color, $index),
                    'width' => 1,
                ];
            }

            if ($labels === null) {
                continue;
            }
            if (!array_key_exists($index, $labels)) {
                throw new InvalidArgumentException('Missing debug render label for bbox index ' . $index . '.');
            }

            $label = (string) $labels[$index];
            [$textWidth, $textHeight] = $this->textSize($label, $labelFontSize, $textSizer);
            if ($textWidth <= 0 || $textHeight <= 0) {
                continue;
            }

            $itemColor = $this->colorAt($color, $index);
            $textPosition = [$bbox[0] + $labelOffset, $bbox[1] + $labelOffset];
            $labelBox = [
                $textPosition[0],
                $textPosition[1],
                $textPosition[0] + $textWidth,
                $textPosition[1] + $textHeight,
            ];

            $operations[] = [
                'type' => 'rectangle',
                'role' => 'label_background',
                'bbox' => $labelBox,
                'fill' => 'white',
            ];
            $operations[] = [
                'type' => 'text',
                'role' => 'label',
                'position' => $textPosition,
                'text' => $label,
                'fill' => $itemColor,
                'font_size' => $labelFontSize,
            ];
        }

        return [
            'label_font_size' => $labelFontSize,
            'operations' => $operations,
        ];
    }

    /**
     * @param array<int, float|int> $bbox
     * @return list<int>
     */
    private function intBbox(array $bbox): array
    {
        if (count($bbox) !== 4) {
            throw new InvalidArgumentException('Debug render bboxes must have 4 elements.');
        }

        return array_map(static fn (float|int $point): int => (int) $point, array_values($bbox));
    }

    /**
     * @param string|list<string> $color
     */
    private function colorAt(string|array $color, int $index): string
    {
        if (is_string($color)) {
            return $color;
        }
        if (!array_key_exists($index, $color)) {
            throw new InvalidArgumentException('Missing debug render color for bbox index ' . $index . '.');
        }

        return (string) $color[$index];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function textSize(string $label, int $labelFontSize, ?callable $textSizer): array
    {
        $size = $textSizer === null
            ? [$this->estimatedTextWidth($label, $labelFontSize), $label === '' ? 0 : $labelFontSize]
            : $textSizer($label, $labelFontSize);

        if (!is_array($size) || count($size) < 2) {
            throw new InvalidArgumentException('Debug render textSizer must return [width, height].');
        }

        return [(int) $size[0], (int) $size[1]];
    }

    private function estimatedTextWidth(string $label, int $labelFontSize): int
    {
        if ($label === '') {
            return 0;
        }

        return (int) ceil(strlen($label) * $labelFontSize * 0.6);
    }
}
