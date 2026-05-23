<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class DebugPageImagePlanner
{
    private MarkerSettings $settings;
    private DebugRenderPlanner $renderer;
    private BboxGeometry $geometry;

    public function __construct(
        ?MarkerSettings $settings = null,
        ?DebugRenderPlanner $renderer = null,
        ?BboxGeometry $geometry = null
    ) {
        $this->settings = $settings ?? new MarkerSettings();
        $this->renderer = $renderer ?? new DebugRenderPlanner();
        $this->geometry = $geometry ?? new BboxGeometry();
    }

    /**
     * Native operation-plan boundary for marker.debug.data::draw_page_debug_images.
     *
     * Upstream only creates these artifacts when DEBUG is enabled; the PHP port
     * returns deterministic review metadata instead of mutating PIL images.
     *
     * @param list<array<string, mixed>> $pages
     * @return array{debug_folder: string, artifacts: list<array<string, mixed>>}
     */
    public function drawPageDebugImagePlans(string $filename, array $pages, ?callable $textSizer = null): array
    {
        if (!$this->settings->get('DEBUG')) {
            return [
                'debug_folder' => $this->debugFolder($filename),
                'artifacts' => [],
            ];
        }

        return $this->layoutPageDebugImagePlans($filename, $pages, $textSizer);
    }

    /**
     * Native operation-plan boundary for marker.debug.data::draw_layout_page_debug_images.
     *
     * @param list<array<string, mixed>> $pages
     * @return array{debug_folder: string, artifacts: list<array<string, mixed>>}
     */
    public function layoutPageDebugImagePlans(string $filename, array $pages, ?callable $textSizer = null): array
    {
        $debugFolder = $this->debugFolder($filename);
        $artifacts = [];

        foreach (array_values($pages) as $pageIndex => $page) {
            if (!is_array($page)) {
                throw new InvalidArgumentException('Debug pages must be arrays.');
            }

            $imageSize = $this->debugImageSize($page);
            $textLineImageBbox = $this->textLineImageBbox($page);

            $pdfLineBoxes = [];
            $pdfLineLabels = [];
            foreach ($this->blocks($page) as $block) {
                foreach ($this->lines($block) as $line) {
                    $pdfLineBoxes[] = $this->geometry->rescaleBbox(
                        $this->pageBbox($page),
                        $textLineImageBbox,
                        $this->lineBbox($line)
                    );
                    $pdfLineLabels[] = $this->lineText($line);
                }
            }

            $layoutOperations = [];
            $layoutOperations = array_merge(
                $layoutOperations,
                $this->renderer->renderOnImagePlan(
                    $pdfLineBoxes,
                    $pdfLineLabels,
                    color: 'black',
                    drawBbox: false,
                    textSizer: $textSizer
                )['operations']
            );

            $detectedLineBoxes = $this->detectedLineBboxes($page);
            $layoutOperations = array_merge(
                $layoutOperations,
                $this->renderer->renderOnImagePlan($detectedLineBoxes, color: 'blue', textSizer: $textSizer)['operations']
            );

            [$layoutBoxes, $layoutLabels] = $this->layoutBoxesAndLabels($page, $textLineImageBbox);
            $layoutOperations = array_merge(
                $layoutOperations,
                $this->renderer->renderOnImagePlan($layoutBoxes, $layoutLabels, color: 'red', textSizer: $textSizer)['operations']
            );

            $orderLabels = array_map(static fn (int $index): string => (string) $index, array_keys($layoutBoxes));
            $layoutOperations = array_merge(
                $layoutOperations,
                $this->renderer->renderOnImagePlan(
                    $layoutBoxes,
                    $orderLabels,
                    labelOffset: 5,
                    color: 'green',
                    drawBbox: false,
                    textSizer: $textSizer
                )['operations']
            );

            $blockBoxes = [];
            $blockLabels = [];
            foreach ($this->blocks($page) as $block) {
                $blockBoxes[] = $this->geometry->rescaleBbox(
                    $this->pageBbox($page),
                    $textLineImageBbox,
                    $this->blockBbox($block)
                );
                $blockLabels[] = $this->blockType($block);
            }

            $pdfOperations = [];
            $pdfOperations = array_merge(
                $pdfOperations,
                $this->renderer->renderOnImagePlan(
                    $pdfLineBoxes,
                    $pdfLineLabels,
                    color: 'black',
                    drawBbox: false,
                    textSizer: $textSizer
                )['operations']
            );
            $pdfOperations = array_merge(
                $pdfOperations,
                $this->renderer->renderOnImagePlan($blockBoxes, $blockLabels, color: 'red', textSizer: $textSizer)['operations']
            );

            $blockOrderLabels = array_map(static fn (int $index): string => (string) $index, array_keys($blockBoxes));
            $pdfOperations = array_merge(
                $pdfOperations,
                $this->renderer->renderOnImagePlan(
                    $blockBoxes,
                    $blockOrderLabels,
                    labelOffset: 5,
                    color: 'green',
                    drawBbox: false,
                    textSizer: $textSizer
                )['operations']
            );

            $artifacts[] = [
                'type' => 'layout',
                'page_index' => $pageIndex,
                'path' => $debugFolder . DIRECTORY_SEPARATOR . 'layout_page_' . $pageIndex . '.png',
                'image_size' => $imageSize,
                'background' => 'white',
                'operations' => $layoutOperations,
            ];
            $artifacts[] = [
                'type' => 'pdf',
                'page_index' => $pageIndex,
                'path' => $debugFolder . DIRECTORY_SEPARATOR . 'pdf_page_' . $pageIndex . '.png',
                'image_size' => $imageSize,
                'background' => 'white',
                'operations' => $pdfOperations,
            ];
        }

        return [
            'debug_folder' => $debugFolder,
            'artifacts' => $artifacts,
        ];
    }

    private function debugFolder(string $filename): string
    {
        $debugRoot = (string) $this->settings->get('DEBUG_DATA_FOLDER');
        if ($debugRoot === '') {
            $debugRoot = '.';
        }

        return rtrim($debugRoot, '/\\') . DIRECTORY_SEPARATOR . $this->stripFinalExtension(basename($filename));
    }

    /**
     * @param array<string, mixed> $page
     * @return list<int>
     */
    private function debugImageSize(array $page): array
    {
        $bbox = $this->textLineImageBbox($page);

        return [(int) ceil($bbox[2]), (int) ceil($bbox[3])];
    }

    /**
     * @param array<string, mixed> $page
     * @return list<float>
     */
    private function pageBbox(array $page): array
    {
        if (!isset($page['bbox']) || !is_array($page['bbox'])) {
            throw new InvalidArgumentException('Debug page is missing a page bbox.');
        }

        return $this->bbox($page['bbox']);
    }

    /**
     * @param array<string, mixed> $page
     * @return list<float>
     */
    private function textLineImageBbox(array $page): array
    {
        if (!isset($page['text_lines']) || !is_array($page['text_lines'])) {
            throw new InvalidArgumentException('Debug page is missing text line data.');
        }
        if (!isset($page['text_lines']['image_bbox']) || !is_array($page['text_lines']['image_bbox'])) {
            throw new InvalidArgumentException('Debug page text line data is missing image_bbox.');
        }

        return $this->bbox($page['text_lines']['image_bbox']);
    }

    /**
     * @param array<string, mixed> $page
     * @return list<array<string, mixed>>
     */
    private function blocks(array $page): array
    {
        if (!isset($page['blocks']) || !is_array($page['blocks'])) {
            throw new InvalidArgumentException('Debug page is missing blocks.');
        }

        return array_values(array_filter(
            $page['blocks'],
            static fn (mixed $block): bool => is_array($block)
        ));
    }

    /**
     * @param array<string, mixed> $block
     * @return list<array<string, mixed>>
     */
    private function lines(array $block): array
    {
        if (!isset($block['lines']) || !is_array($block['lines'])) {
            return [];
        }

        return array_values(array_filter(
            $block['lines'],
            static fn (mixed $line): bool => is_array($line)
        ));
    }

    /**
     * @param array<string, mixed> $line
     * @return list<float>
     */
    private function lineBbox(array $line): array
    {
        if (!isset($line['bbox']) || !is_array($line['bbox'])) {
            throw new InvalidArgumentException('Debug line is missing bbox data.');
        }

        return $this->bbox($line['bbox']);
    }

    /**
     * @param array<string, mixed> $block
     * @return list<float>
     */
    private function blockBbox(array $block): array
    {
        if (isset($block['bbox']) && is_array($block['bbox'])) {
            return $this->bbox($block['bbox']);
        }

        $boxes = [];
        foreach ($this->lines($block) as $line) {
            $boxes[] = $this->lineBbox($line);
        }
        if ($boxes === []) {
            throw new InvalidArgumentException('Debug block is missing bbox data.');
        }

        $merged = array_shift($boxes);
        foreach ($boxes as $box) {
            $merged = $this->geometry->mergeBoxes($merged, $box);
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $line
     */
    private function lineText(array $line): string
    {
        if (array_key_exists('prelim_text', $line)) {
            return (string) $line['prelim_text'];
        }
        if (array_key_exists('text', $line)) {
            return (string) $line['text'];
        }
        if (isset($line['spans']) && is_array($line['spans'])) {
            $text = '';
            foreach ($line['spans'] as $span) {
                if (is_array($span)) {
                    $text .= (string) ($span['text'] ?? '');
                }
            }

            return $text;
        }

        return '';
    }

    /**
     * @param array<string, mixed> $block
     */
    private function blockType(array $block): string
    {
        return (string) ($block['block_type'] ?? $block['blockType'] ?? 'Text');
    }

    /**
     * @param array<string, mixed> $page
     * @return list<list<float>>
     */
    private function detectedLineBboxes(array $page): array
    {
        if (!isset($page['text_lines']['bboxes']) || !is_array($page['text_lines']['bboxes'])) {
            return [];
        }

        $boxes = [];
        foreach ($page['text_lines']['bboxes'] as $item) {
            if (is_array($item) && isset($item['bbox']) && is_array($item['bbox'])) {
                $boxes[] = $this->bbox($item['bbox']);
            } elseif (is_array($item)) {
                $boxes[] = $this->bbox($item);
            }
        }

        return $boxes;
    }

    /**
     * @param array<string, mixed> $page
     * @param list<float> $textLineImageBbox
     * @return array{0: list<list<float>>, 1: list<string>}
     */
    private function layoutBoxesAndLabels(array $page, array $textLineImageBbox): array
    {
        if (!isset($page['layout']) || !is_array($page['layout'])) {
            throw new InvalidArgumentException('Debug page is missing layout data.');
        }
        if (!isset($page['layout']['image_bbox']) || !is_array($page['layout']['image_bbox'])) {
            throw new InvalidArgumentException('Debug page layout data is missing image_bbox.');
        }

        $layoutImageBbox = $this->bbox($page['layout']['image_bbox']);
        $boxes = [];
        $labels = [];
        foreach (($page['layout']['bboxes'] ?? []) as $item) {
            if (!is_array($item) || !isset($item['bbox']) || !is_array($item['bbox'])) {
                continue;
            }

            $boxes[] = $this->geometry->rescaleBbox($layoutImageBbox, $textLineImageBbox, $this->bbox($item['bbox']));
            $labels[] = (string) ($item['label'] ?? '');
        }

        return [$boxes, $labels];
    }

    /**
     * @param array<mixed> $bbox
     * @return list<float>
     */
    private function bbox(array $bbox): array
    {
        $values = array_values($bbox);
        if (count($values) !== 4) {
            throw new InvalidArgumentException('Debug bbox values must have 4 elements.');
        }

        foreach ($values as $value) {
            if (!is_float($value) && !is_int($value)) {
                throw new InvalidArgumentException('Debug bbox values must be numeric.');
            }
        }

        return array_map(static fn (float|int $value): float => (float) $value, $values);
    }

    private function stripFinalExtension(string $filename): string
    {
        $lastDot = strrpos($filename, '.');
        if ($lastDot === false) {
            return $filename;
        }

        return substr($filename, 0, $lastDot);
    }
}
