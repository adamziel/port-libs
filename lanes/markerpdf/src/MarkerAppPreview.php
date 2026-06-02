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
     * @return array{page_count: int, pages: list<array{page_number: int, page_index: int, object_id: int, bbox: list<float>, bbox_source: string}>}
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
     * @return array{page_number: int, page_index: int, page_count: int, object_id: int, bbox_source: string, dpi: float, scale: float, annotation_mode: string, color_mode: string, pypdfium_page_indices: list<int>, page_bbox: list<float>, rendered_image_size: array{width: int, height: int}}
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
            'dpi' => $dpi,
            'scale' => $this->renderer->renderScale($dpi),
            'annotation_mode' => 'pypdfium-default',
            'color_mode' => 'RGB',
            'pypdfium_page_indices' => [$pageNumber - 1],
            'page_bbox' => $page['bbox'],
            'rendered_image_size' => $this->renderer->renderedImageSize($page['bbox'], $dpi),
        ];
    }

    /**
     * @return list<array{page_number: int, page_index: int, object_id: int, bbox: list<float>, bbox_source: string}>
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
                $pages = $this->collectPages($pagesId, $objects);
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
                    'bbox' => $this->pageBox($object['body'], $objects),
                    'bbox_source' => $this->mediaBox($object['body']) === null ? 'default' : 'page',
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
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int> $seen
     * @return list<array{object_id: int, bbox: list<float>, bbox_source: string}>
     */
    private function collectPages(
        int $objectId,
        array $objects,
        ?array $inheritedBbox = null,
        string $inheritedSource = 'default',
        array $seen = []
    ): array {
        if (in_array($objectId, $seen, true) || !isset($objects[$objectId])) {
            return [];
        }

        $seen[] = $objectId;
        $body = $objects[$objectId]['body'];
        $type = $this->objectType($body);
        $ownBbox = $this->mediaBox($body);
        $bbox = $ownBbox ?? $inheritedBbox;
        $bboxSource = $ownBbox === null ? $inheritedSource : ($type === 'Page' ? 'page' : 'pages');

        if ($type === 'Page') {
            return [[
                'object_id' => $objectId,
                'bbox' => $bbox ?? self::DEFAULT_PAGE_BBOX,
                'bbox_source' => $bbox === null ? 'default' : $bboxSource,
            ]];
        }

        if ($type !== 'Pages') {
            return [];
        }

        $pages = [];
        foreach ($this->kidReferences($body, $objects) as $kidId) {
            foreach ($this->collectPages($kidId, $objects, $bbox, $bboxSource, $seen) as $page) {
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
     * @param list<int> $seen
     * @return list<float>
     */
    private function pageBox(string $body, array $objects, array $seen = []): array
    {
        $bbox = $this->mediaBox($body);
        if ($bbox !== null) {
            return $bbox;
        }

        $parentId = $this->reference($body, 'Parent');
        if ($parentId === null || in_array($parentId, $seen, true) || !isset($objects[$parentId])) {
            return self::DEFAULT_PAGE_BBOX;
        }

        $seen[] = $parentId;

        return $this->pageBox($objects[$parentId]['body'], $objects, $seen);
    }

    /**
     * @return list<float>|null
     */
    private function mediaBox(string $body): ?array
    {
        if (!preg_match('/\/MediaBox\s*\[([^\]]+)\]/s', $body, $match)) {
            return null;
        }
        if (!preg_match_all('/[-+]?(?:\d*\.\d+|\d+)(?:[eE][-+]?\d+)?/', $match[1], $numbers)) {
            return null;
        }
        if (count($numbers[0]) < 4) {
            return null;
        }

        return array_map(static fn (string $value): float => (float) $value, array_slice($numbers[0], 0, 4));
    }

    private function assertPdfBytes(string $pdfBytes): void
    {
        if (!str_starts_with(ltrim($pdfBytes), '%PDF-')) {
            throw new InvalidArgumentException('PDF preview requires PDF bytes.');
        }
    }
}
