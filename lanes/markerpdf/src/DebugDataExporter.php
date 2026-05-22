<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class DebugDataExporter
{
    private MarkerSettings $settings;

    public function __construct(?MarkerSettings $settings = null)
    {
        $this->settings = $settings ?? new MarkerSettings();
    }

    /**
     * Native boundary for marker.debug.data::dump_bbox_debug_data.
     *
     * @param list<array<string, mixed>> $pages
     */
    public function dumpBboxDebugData(string $filename, array $pages): ?string
    {
        if (!$this->settings->get('DEBUG')) {
            return null;
        }

        $debugFolder = (string) $this->settings->get('DEBUG_DATA_FOLDER');
        if ($debugFolder === '') {
            $debugFolder = '.';
        }
        if (!is_dir($debugFolder) && !mkdir($debugFolder, 0777, true) && !is_dir($debugFolder)) {
            throw new RuntimeException('Unable to create markerPDF debug data folder: ' . $debugFolder);
        }

        $path = rtrim($debugFolder, '/\\') . DIRECTORY_SEPARATOR . $this->stripFinalExtension(basename($filename)) . '_bbox.json';
        try {
            $json = json_encode($this->bboxDebugData($pages), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Unable to encode markerPDF bbox debug data as JSON.', previous: $exception);
        }

        if (file_put_contents($path, $json) === false) {
            throw new RuntimeException('Unable to write markerPDF bbox debug data: ' . $path);
        }

        return $path;
    }

    /**
     * @param list<array<string, mixed>> $pages
     * @return list<array<string, mixed>>
     */
    public function bboxDebugData(array $pages): array
    {
        $debugData = [];
        foreach (array_values($pages) as $page) {
            if (!is_array($page)) {
                throw new InvalidArgumentException('Debug pages must be arrays.');
            }
            if (!isset($page['layout']) || !is_array($page['layout'])) {
                throw new InvalidArgumentException('Debug page is missing layout data.');
            }
            if (!isset($page['text_lines']) || !is_array($page['text_lines'])) {
                throw new InvalidArgumentException('Debug page is missing text line data.');
            }

            $pageData = $this->withoutKeys($page, ['images', 'layout', 'text_lines']);
            $pageData['layout'] = $this->withoutKeys($page['layout'], ['segmentation_map']);
            $pageData['text_lines'] = $this->withoutKeys($page['text_lines'], ['heatmap', 'affinity_map']);
            $debugData[] = $pageData;
        }

        return $debugData;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    private function withoutKeys(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            unset($data[$key]);
        }

        return $data;
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
