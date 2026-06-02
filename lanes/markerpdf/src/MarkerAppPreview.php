<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class MarkerAppPreview
{
    private const DEFAULT_PAGE_BBOX = [0.0, 0.0, 612.0, 792.0];

    private PdfImageRenderer $renderer;

    public function __construct(?PdfImageRenderer $renderer = null)
    {
        $this->renderer = $renderer ?? new PdfImageRenderer();
    }

    /**
     * Native boundary for marker_app.py::open_pdf plus page_count metadata.
     *
     * @return array{page_count: int, pages: list<array<string, mixed>>}
     */
    public function openPdfSummary(string $pdfBytes): array
    {
        $pages = $this->pageInventory($pdfBytes);

        return [
            'page_count' => count($pages),
            'pages' => $pages,
        ];
    }

    /**
     * Native boundary for marker_app.py::page_count.
     */
    public function pageCount(string $pdfBytes): int
    {
        return $this->openPdfSummary($pdfBytes)['page_count'];
    }

    /**
     * Plans marker_app.py::get_page_image without pypdfium/PIL rasterization.
     *
     * @return array<string, mixed>
     */
    public function getPageImagePlan(string $pdfBytes, int $pageNumber, float $dpi = 96.0): array
    {
        $summary = $this->openPdfSummary($pdfBytes);
        if ($pageNumber < 1 || $pageNumber > $summary['page_count']) {
            throw new InvalidArgumentException('Preview page number must be within the PDF page count.');
        }

        $page = $summary['pages'][$pageNumber - 1];

        return [
            'page_number' => $pageNumber,
            'page_index' => $pageNumber - 1,
            'page_count' => $summary['page_count'],
            'object_id' => $page['object_id'],
            'bbox_source' => $page['bbox_source'],
            'media_box' => $page['media_box'],
            'media_box_source' => $page['media_box_source'],
            'crop_box' => $page['crop_box'],
            'crop_box_source' => $page['crop_box_source'],
            'bleed_box' => $page['bleed_box'],
            'bleed_box_source' => $page['bleed_box_source'],
            'trim_box' => $page['trim_box'],
            'trim_box_source' => $page['trim_box_source'],
            'art_box' => $page['art_box'],
            'art_box_source' => $page['art_box_source'],
            'rotation' => $page['rotation'],
            'rotation_source' => $page['rotation_source'],
            'user_unit' => $page['user_unit'],
            'user_unit_source' => $page['user_unit_source'],
            'effective_crop_box' => $page['effective_crop_box'],
            'effective_crop_box_source' => $page['effective_crop_box_source'],
            'crop_box_clipped_to_media' => $page['crop_box_clipped_to_media'],
            'crop_box_intersects_media' => $page['crop_box_intersects_media'],
            'preview_zero_area' => $page['preview_zero_area'],
            'rotation_swaps_axes' => $page['rotation_swaps_axes'],
            'user_unit_applied_to_preview' => $page['user_unit_applied_to_preview'],
            'boundary_notes' => $page['boundary_notes'],
            'display_page_size' => $this->displayPageSize($page['bbox'], $page['rotation']),
            'physical_page_size' => $this->displayPageSize($page['bbox'], $page['rotation'], $page['user_unit']),
            'dpi' => $dpi,
            'scale' => $this->renderer->renderScale($dpi),
            'annotation_mode' => 'pypdfium-default',
            'color_mode' => 'RGB',
            'pypdfium_page_indices' => [$pageNumber - 1],
            'page_bbox' => $page['bbox'],
            'rendered_image_size' => $this->renderer->renderedImageSize($page['bbox'], $dpi, $page['rotation'], $page['user_unit']),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pageInventory(string $pdfBytes): array
    {
        $this->assertPdfBytes($pdfBytes);

        $objects = $this->pdfObjects($pdfBytes);
        if ($objects === []) {
            return [];
        }

        $pages = [];
        foreach ($objects as $objectId => $object) {
            if ($this->objectType($object['body']) !== 'Catalog') {
                continue;
            }

            $pagesId = $this->reference($object['body'], 'Pages');
            if ($pagesId !== null && isset($objects[$pagesId])) {
                $pages = $this->uniquePagesByObjectId($this->collectPages($pagesId, $objects));
                break;
            }
        }

        if ($pages === []) {
            foreach ($objects as $objectId => $object) {
                if ($this->objectType($object['body']) !== 'Page') {
                    continue;
                }

                $pages[] = [
                    'object_id' => $objectId,
                    ...$this->pageGeometry($object['body'], $objects),
                ];
            }
        }

        foreach ($pages as $index => $page) {
            $pages[$index]['page_index'] = $index;
            $pages[$index]['page_number'] = $index + 1;
        }

        return array_values($pages);
    }

    /**
     * @param list<array<string, mixed>> $pages
     * @return list<array<string, mixed>>
     */
    private function uniquePagesByObjectId(array $pages): array
    {
        $unique = [];
        $seen = [];
        foreach ($pages as $page) {
            $objectId = $page['object_id'] ?? null;
            if (!is_int($objectId)) {
                continue;
            }

            if (isset($seen[$objectId])) {
                continue;
            }

            $seen[$objectId] = true;
            $unique[] = $page;
        }

        return $unique;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int> $seen
     * @param array<string, mixed> $inherited
     * @return list<array<string, mixed>>
     */
    private function collectPages(
        int $objectId,
        array $objects,
        array $inherited = [],
        array $seen = []
    ): array {
        if (in_array($objectId, $seen, true) || !isset($objects[$objectId])) {
            return [];
        }

        $seen[] = $objectId;
        $body = $objects[$objectId]['body'];
        $type = $this->objectType($body);
        $nextInherited = $this->inheritedPageGeometry($body, $objects, $type, $inherited);

        if ($type === 'Page') {
            return [[
                'object_id' => $objectId,
                ...$this->pageGeometry($body, $objects, $nextInherited),
            ]];
        }

        if ($type !== 'Pages') {
            return [];
        }

        $pages = [];
        foreach ($this->kidReferences($body, $objects) as $kidId) {
            foreach ($this->collectPages($kidId, $objects, $nextInherited, $seen) as $page) {
                $pages[] = $page;
            }
        }

        return $pages;
    }

    /**
     * @return array<int, array{generation: int, body: string}>
     */
    private function pdfObjects(string $pdfBytes): array
    {
        if (!preg_match_all('/(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $objects = [];
        foreach ($matches as $match) {
            $objects[(int) $match[1]] = [
                'generation' => (int) $match[2],
                'body' => $match[3],
            ];
        }
        ksort($objects, SORT_NUMERIC);

        return $objects;
    }

    private function objectType(string $body): ?string
    {
        if (!preg_match('/\/Type\s*\/([A-Za-z0-9_-]+)/', $body, $match)) {
            return null;
        }

        return $match[1];
    }

    private function reference(string $body, string $name): ?int
    {
        if (!preg_match('/\/' . preg_quote($name, '/') . '\s+(\d+)\s+\d+\s+R\b/', $body, $match)) {
            return null;
        }

        return (int) $match[1];
    }

    /**
     * @return list<int>
     * @param array<int, array{generation: int, body: string}> $objects
     */
    private function kidReferences(string $body, array $objects): array
    {
        if (!preg_match('/\/Kids\s*(?:\[(.*?)\]|(\d+)\s+\d+\s+R\b)/s', $body, $match)) {
            return [];
        }

        $kidsBody = $match[1] ?? '';
        if ($kidsBody === '' && isset($match[2])) {
            $objectId = (int) $match[2];
            $objectBody = isset($objects[$objectId]) ? trim($objects[$objectId]['body']) : '';
            $kidsBody = preg_match('/^\[(.*?)\]$/s', $objectBody, $objectMatch) ? $objectMatch[1] : '';
        }

        if (!preg_match_all('/(\d+)\s+\d+\s+R\b/', $kidsBody, $refs)) {
            return [];
        }

        return array_map(static fn (string $value): int => (int) $value, $refs[1]);
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param array<string, mixed> $inherited
     * @return array<string, mixed>
     */
    private function inheritedPageGeometry(string $body, array $objects, ?string $type, array $inherited): array
    {
        $source = $type === 'Page' ? 'page' : 'pages';
        $mediaBox = $this->boxValue($body, 'MediaBox', $objects);
        if ($mediaBox !== null) {
            $inherited['media_box'] = $mediaBox;
            $inherited['media_box_source'] = $source;
        }

        $cropBox = $this->boxValue($body, 'CropBox', $objects);
        if ($cropBox !== null) {
            $inherited['crop_box'] = $cropBox;
            $inherited['crop_box_source'] = $source;
        }

        $rotation = $this->rotationValue($body, $objects);
        if ($rotation !== null) {
            $inherited['rotation'] = $rotation;
            $inherited['rotation_source'] = $source;
        }

        return $inherited;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param array<string, mixed> $inherited
     * @return array<string, mixed>
     */
    private function pageGeometry(string $pageBody, array $objects, array $inherited = []): array
    {
        if ($inherited === []) {
            $inherited = $this->parentInheritedGeometry($pageBody, $objects);
        }

        $source = 'page';
        $mediaBox = $this->boxValue($pageBody, 'MediaBox', $objects);
        $mediaBoxSource = $source;
        if ($mediaBox === null) {
            $mediaBox = $inherited['media_box'] ?? self::DEFAULT_PAGE_BBOX;
            $mediaBoxSource = $inherited['media_box_source'] ?? 'default';
        }

        $cropBox = $this->boxValue($pageBody, 'CropBox', $objects);
        $cropBoxSource = $source;
        if ($cropBox === null) {
            $cropBox = $inherited['crop_box'] ?? $mediaBox;
            $cropBoxSource = $inherited['crop_box_source'] ?? 'media_box';
        }

        $rotation = $this->rotationValue($pageBody, $objects);
        $rotationSource = $source;
        if ($rotation === null) {
            $rotation = $inherited['rotation'] ?? 0;
            $rotationSource = $inherited['rotation_source'] ?? 'default';
        }

        $userUnit = $this->numberValue($pageBody, 'UserUnit', $objects);
        $userUnitSource = 'page';
        if ($userUnit === null || $userUnit <= 0.0) {
            $userUnit = 1.0;
            $userUnitSource = 'default';
        }

        $bbox = $this->intersectBoxes($mediaBox, $cropBox);
        $cropBoxClippedToMedia = !$this->sameBox($cropBox, $bbox);
        $previewZeroArea = !$this->hasPositiveArea($bbox);
        $rotationSwapsAxes = in_array($rotation, [90, 270], true);
        $userUnitAppliedToPreview = abs($userUnit - 1.0) > 0.000001;
        $bboxSource = $cropBoxSource === 'media_box' ? $mediaBoxSource : 'crop_box';
        $bleedBox = $this->boxValue($pageBody, 'BleedBox', $objects);
        $trimBox = $this->boxValue($pageBody, 'TrimBox', $objects);
        $artBox = $this->boxValue($pageBody, 'ArtBox', $objects);

        return [
            'bbox' => $bbox,
            'bbox_source' => $bboxSource,
            'media_box' => $mediaBox,
            'media_box_source' => $mediaBoxSource,
            'crop_box' => $cropBox,
            'crop_box_source' => $cropBoxSource,
            'bleed_box' => $bleedBox ?? $cropBox,
            'bleed_box_source' => $bleedBox === null ? 'crop_box' : 'page',
            'trim_box' => $trimBox ?? $cropBox,
            'trim_box_source' => $trimBox === null ? 'crop_box' : 'page',
            'art_box' => $artBox ?? $cropBox,
            'art_box_source' => $artBox === null ? 'crop_box' : 'page',
            'rotation' => $rotation,
            'rotation_source' => $rotationSource,
            'user_unit' => $userUnit,
            'user_unit_source' => $userUnitSource,
            'effective_crop_box' => $bbox,
            'effective_crop_box_source' => $cropBoxClippedToMedia ? 'crop_box_clipped_to_media_box' : $bboxSource,
            'crop_box_clipped_to_media' => $cropBoxClippedToMedia,
            'crop_box_intersects_media' => $this->hasPositiveIntersection($mediaBox, $cropBox),
            'preview_zero_area' => $previewZeroArea,
            'rotation_swaps_axes' => $rotationSwapsAxes,
            'user_unit_applied_to_preview' => $userUnitAppliedToPreview,
            'boundary_notes' => $this->previewBoundaryNotes($cropBoxClippedToMedia, $previewZeroArea, $rotationSwapsAxes, $userUnitAppliedToPreview),
        ];
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @return array<string, mixed>
     */
    private function parentInheritedGeometry(string $pageBody, array $objects): array
    {
        $ancestors = [];
        $seen = [];
        $parentId = $this->reference($pageBody, 'Parent');
        while ($parentId !== null && !isset($seen[$parentId]) && isset($objects[$parentId])) {
            $seen[$parentId] = true;
            $body = $objects[$parentId]['body'];
            if ($this->objectType($body) !== 'Pages') {
                break;
            }

            $ancestors[] = $body;
            $parentId = $this->reference($body, 'Parent');
        }

        $inherited = [];
        foreach (array_reverse($ancestors) as $body) {
            $inherited = $this->inheritedPageGeometry($body, $objects, $this->objectType($body), $inherited);
        }

        return $inherited;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @return list<float>|null
     */
    private function boxValue(string $body, string $name, array $objects): ?array
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\s*\[([^\]]+)\]/s', $body, $match) === 1) {
            return $this->boxFromNumbers($match[1], $objects);
        }

        $objectId = $this->reference($body, $name);
        if ($objectId === null || !isset($objects[$objectId])) {
            return null;
        }

        $objectBody = trim($objects[$objectId]['body']);
        if (preg_match('/^\[([^\]]+)\]$/s', $objectBody, $match) === 1) {
            return $this->boxFromNumbers($match[1], $objects);
        }

        return null;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @return list<float>|null
     */
    private function boxFromNumbers(string $body, array $objects): ?array
    {
        if (!preg_match_all('/(\d+)\s+(\d+)\s+R\b|[-+]?(?:\d*\.\d+|\d+)(?:[eE][-+]?\d+)?/', $body, $matches, PREG_SET_ORDER)) {
            return null;
        }

        $box = [];
        foreach ($matches as $match) {
            if (($match[1] ?? '') !== '') {
                $value = $this->numericObjectValue((int) $match[1], $objects);
                if ($value === null) {
                    return null;
                }

                $box[] = $value;
            } else {
                $box[] = (float) $match[0];
            }

            if (count($box) === 4) {
                break;
            }
        }

        if (count($box) < 4) {
            return null;
        }

        return [
            min($box[0], $box[2]),
            min($box[1], $box[3]),
            max($box[0], $box[2]),
            max($box[1], $box[3]),
        ];
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param array<int, true> $seen
     */
    private function numericObjectValue(int $objectId, array $objects, array $seen = []): ?float
    {
        if (isset($seen[$objectId]) || !isset($objects[$objectId])) {
            return null;
        }

        $seen[$objectId] = true;
        $body = trim($objects[$objectId]['body']);
        if (preg_match('/^[-+]?(?:\d*\.\d+|\d+)(?:[eE][-+]?\d+)?$/', $body) === 1) {
            return (float) $body;
        }

        if (preg_match('/^(\d+)\s+\d+\s+R$/', $body, $match) === 1) {
            return $this->numericObjectValue((int) $match[1], $objects, $seen);
        }

        return null;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     */
    private function integerValue(string $body, string $name, array $objects): ?int
    {
        $value = $this->numberValue($body, $name, $objects);

        return $value === null ? null : (int) round($value);
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     */
    private function rotationValue(string $body, array $objects): ?int
    {
        $value = $this->numberValue($body, 'Rotate', $objects);
        if ($value === null || abs($value - round($value)) > 0.000001) {
            return null;
        }

        $rotation = (int) round($value);
        if ($rotation % 90 !== 0) {
            return null;
        }

        return $this->normalizedRotation($rotation);
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     */
    private function numberValue(string $body, string $name, array $objects): ?float
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\s+([-+]?(?:\d*\.\d+|\d+)(?:[eE][-+]?\d+)?)(?!\s+\d+\s+R\b)/s', $body, $match) === 1) {
            return (float) $match[1];
        }

        $objectId = $this->reference($body, $name);
        if ($objectId === null || !isset($objects[$objectId])) {
            return null;
        }

        $objectBody = trim($objects[$objectId]['body']);
        if (preg_match('/^[-+]?(?:\d*\.\d+|\d+)(?:[eE][-+]?\d+)?$/', $objectBody) !== 1) {
            return null;
        }

        return (float) $objectBody;
    }

    /**
     * @param list<float> $mediaBox
     * @param list<float> $cropBox
     * @return list<float>
     */
    private function intersectBoxes(array $mediaBox, array $cropBox): array
    {
        $left = max($mediaBox[0], min($mediaBox[2], $cropBox[0]));
        $right = max($mediaBox[0], min($mediaBox[2], $cropBox[2]));
        $bottom = max($mediaBox[1], min($mediaBox[3], $cropBox[1]));
        $top = max($mediaBox[1], min($mediaBox[3], $cropBox[3]));

        return [
            min($left, $right),
            min($bottom, $top),
            max($left, $right),
            max($bottom, $top),
        ];
    }

    /**
     * @param list<float> $left
     * @param list<float> $right
     */
    private function sameBox(array $left, array $right): bool
    {
        for ($index = 0; $index < 4; $index++) {
            if (abs($left[$index] - $right[$index]) > 0.000001) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<float> $box
     */
    private function hasPositiveArea(array $box): bool
    {
        return ($box[2] - $box[0]) > 0.000001 && ($box[3] - $box[1]) > 0.000001;
    }

    /**
     * @param list<float> $left
     * @param list<float> $right
     */
    private function hasPositiveIntersection(array $left, array $right): bool
    {
        return min($left[2], $right[2]) - max($left[0], $right[0]) > 0.000001
            && min($left[3], $right[3]) - max($left[1], $right[1]) > 0.000001;
    }

    /**
     * @return list<string>
     */
    private function previewBoundaryNotes(
        bool $cropBoxClippedToMedia,
        bool $previewZeroArea,
        bool $rotationSwapsAxes,
        bool $userUnitAppliedToPreview
    ): array {
        $notes = [];
        if ($cropBoxClippedToMedia) {
            $notes[] = 'crop_box_clipped_to_media_box';
        }
        if ($previewZeroArea) {
            $notes[] = 'zero_area_preview_box';
        }
        if ($rotationSwapsAxes) {
            $notes[] = 'rotation_swaps_display_axes';
        }
        if ($userUnitAppliedToPreview) {
            $notes[] = 'user_unit_scales_rendered_preview';
        }

        return $notes;
    }

    /**
     * @param list<float> $bbox
     * @return array{width: float, height: float}
     */
    private function displayPageSize(array $bbox, int $rotation, float $userUnit = 1.0): array
    {
        $width = max(0.0, $bbox[2] - $bbox[0]) * $userUnit;
        $height = max(0.0, $bbox[3] - $bbox[1]) * $userUnit;
        if (in_array($this->normalizedRotation($rotation), [90, 270], true)) {
            [$width, $height] = [$height, $width];
        }

        return [
            'width' => $width,
            'height' => $height,
        ];
    }

    private function normalizedRotation(int $rotation): int
    {
        $rotation %= 360;
        if ($rotation < 0) {
            $rotation += 360;
        }

        return in_array($rotation, [0, 90, 180, 270], true) ? $rotation : 0;
    }

    private function assertPdfBytes(string $pdfBytes): void
    {
        if (!str_starts_with(ltrim($pdfBytes), '%PDF-')) {
            throw new InvalidArgumentException('PDF preview requires PDF bytes.');
        }
    }
}
