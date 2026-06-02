<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Stringable;

final class OutputWriter
{
    public function getSubfolderPath(string $outputFolder, string $filename): string
    {
        return $this->joinPath($outputFolder, $this->stripFinalExtension($filename));
    }

    public function getMarkdownFilepath(string $outputFolder, string $filename): string
    {
        $markdownFilename = $this->stripFinalExtension($filename) . '.md';

        return $this->joinPath($this->getSubfolderPath($outputFolder, $filename), $markdownFilename);
    }

    public function markdownExists(string $outputFolder, string $filename): bool
    {
        return is_file($this->getMarkdownFilepath($outputFolder, $filename));
    }

    /**
     * Native boundary for marker.output::save_markdown.
     *
     * @param array<string, mixed> $images Values may be raw PNG bytes, Stringable bytes, arrays with a
     *                                     `bytes`, `data`, or `content` string, or objects exposing
     *                                     `save(string $path, string $format)`.
     * @param array<string, mixed> $metadata
     */
    public function saveMarkdown(
        string $outputFolder,
        string $filename,
        string $fullText,
        array $images,
        array $metadata
    ): string {
        return $this->saveMarkdownArtifacts($outputFolder, $filename, $fullText, $images, $metadata)['subfolder'];
    }

    /**
     * Native boundary across marker.output::save_markdown and marker_app.py::markdown_insert_images.
     *
     * Upstream persists Markdown, `_meta.json`, and PNG image artifacts, while the Streamlit
     * app separately turns Markdown image references into runtime data URI preview HTML. This
     * method preserves the existing save contract and returns a review manifest for WordPress
     * import screens without launching Streamlit, pypdfium, PIL, Python models, or external tools.
     *
     * @param array<string, mixed> $images Values may be raw PNG bytes, Stringable bytes, arrays with a
     *                                     `bytes`, `data`, or `content` string, or objects exposing
     *                                     `save(string $path, string $format)`.
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function saveMarkdownArtifactBoundary(
        string $outputFolder,
        string $filename,
        string $fullText,
        array $images,
        array $metadata,
        bool $includeRuntimePreviewHtml = true
    ): array {
        $saved = $this->saveMarkdownArtifacts($outputFolder, $filename, $fullText, $images, $metadata);
        $markdownText = $this->readSavedFile($saved['markdown_path']);

        $imageBytesByFilename = [];
        $imageArtifacts = [];
        foreach ($saved['image_artifacts'] as $artifact) {
            $path = $this->joinPath($saved['subfolder'], $artifact['filename']);
            $bytes = $this->readSavedFile($path);
            $quality = $this->pngArtifactQuality($bytes);
            $imageBytesByFilename[$artifact['filename']] = $bytes;
            $imageArtifacts[] = [
                'source_filename' => $artifact['source_filename'],
                'filename' => $artifact['filename'],
                'path' => $path,
                'format' => 'png',
                'mime_type' => 'image/png',
                'size' => strlen($bytes),
                'sha256' => hash('sha256', $bytes),
                'persisted_to_output_folder' => true,
                'source_filename_rewritten' => $artifact['source_filename'] !== $artifact['filename'],
                'runtime_preview_embeddable' => true,
                'wordpress_media_importable' => $quality['wordpress_media_importable'],
                'png_quality' => $quality,
            ];
        }

        $markdownImageTargets = $this->markdownImageTargets($markdownText);
        $embeddedTargets = array_values(array_intersect($markdownImageTargets, array_keys($imageBytesByFilename)));
        $unembeddedTargets = array_values(array_diff($markdownImageTargets, array_keys($imageBytesByFilename)));

        $previewHtml = null;
        if ($includeRuntimePreviewHtml) {
            $previewHtml = (new MarkdownImageEmbedder())->markdownInsertImages($markdownText, $imageBytesByFilename);
        }
        $imageDataUriCount = is_string($previewHtml) ? substr_count($previewHtml, 'data:image/png;base64,') : 0;

        return [
            'source' => 'marker_output_runtime_preview_artifact_boundary',
            'upstream_boundary' => 'marker.output.save_markdown + marker_app.markdown_insert_images',
            'filename' => $filename,
            'output_folder' => $outputFolder,
            'subfolder' => $saved['subfolder'],
            'markdown_artifact' => [
                ...$this->fileArtifact($saved['markdown_path'], basename($saved['markdown_path']), 'markdown'),
                'visible_text_artifact' => true,
                'runtime_preview_inlined' => false,
            ],
            'metadata_artifact' => [
                ...$this->fileArtifact($saved['metadata_path'], basename($saved['metadata_path']), 'json'),
                'review_only' => true,
                'payload_separated_from_visible_markdown' => true,
            ],
            'image_artifacts' => $imageArtifacts,
            'image_name_map' => $saved['image_name_map'],
            'runtime_preview' => [
                'requested' => $includeRuntimePreviewHtml,
                'runtime_only' => true,
                'persisted_to_output_folder' => false,
                'uses_persisted_image_bytes' => $imageBytesByFilename !== [],
                'markdown_keeps_file_references' => !str_contains($markdownText, 'data:image/png;base64,'),
                'markdown_image_targets' => $markdownImageTargets,
                'embedded_image_targets' => $embeddedTargets,
                'unembedded_markdown_image_targets' => $unembeddedTargets,
                'html' => $previewHtml,
                'html_sha256' => is_string($previewHtml) ? hash('sha256', $previewHtml) : null,
                'html_size' => is_string($previewHtml) ? strlen($previewHtml) : 0,
                'image_data_uri_count' => $imageDataUriCount,
            ],
            'markdown_image_bundle' => $this->markdownImageBundle(
                $imageArtifacts,
                $markdownImageTargets,
                $embeddedTargets,
                $unembeddedTargets,
                $imageDataUriCount,
                $includeRuntimePreviewHtml
            ),
            'markdown_table_image_artifact' => $this->markdownTableImageArtifact(
                $markdownText,
                $imageArtifacts,
                $includeRuntimePreviewHtml
            ),
            'executes_streamlit' => false,
            'executes_pdfium' => false,
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @param array<string, mixed> $images
     * @param array<string, mixed> $metadata
     * @return array{subfolder: string, markdown_path: string, metadata_path: string, image_artifacts: list<array{source_filename: string, filename: string, image: mixed}>, image_name_map: array<string, string>}
     */
    private function saveMarkdownArtifacts(
        string $outputFolder,
        string $filename,
        string $fullText,
        array $images,
        array $metadata
    ): array {
        $subfolderPath = $this->getSubfolderPath($outputFolder, $filename);
        if (!is_dir($subfolderPath) && !mkdir($subfolderPath, 0777, true) && !is_dir($subfolderPath)) {
            throw new RuntimeException('Unable to create markerPDF output folder: ' . $subfolderPath);
        }

        $imageArtifacts = $this->imageArtifacts($images);
        $imageNameMap = [];
        foreach ($imageArtifacts as $artifact) {
            if ($artifact['source_filename'] !== $artifact['filename']) {
                $imageNameMap[$artifact['source_filename']] = $artifact['filename'];
            }
        }

        $markdownPath = $this->getMarkdownFilepath($outputFolder, $filename);
        $this->writeFile($markdownPath, $this->rewriteImageReferences($fullText, $imageNameMap));

        $metadataPath = $this->stripFinalExtension($markdownPath) . '_meta.json';
        $metadata = $this->rewriteMetadataImageReferences($metadata, $imageNameMap);
        try {
            $encodedMetadata = json_encode(
                $metadata,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Unable to encode markerPDF metadata as JSON.', previous: $exception);
        }
        $this->writeFile($metadataPath, $encodedMetadata);

        foreach ($imageArtifacts as $artifact) {
            $imagePath = $this->joinPath($subfolderPath, $artifact['filename']);
            $this->writeImage($imagePath, $artifact['image']);
        }

        return [
            'subfolder' => $subfolderPath,
            'markdown_path' => $markdownPath,
            'metadata_path' => $metadataPath,
            'image_artifacts' => $imageArtifacts,
            'image_name_map' => $imageNameMap,
        ];
    }

    private function stripFinalExtension(string $filename): string
    {
        $lastDot = strrpos($filename, '.');
        if ($lastDot === false) {
            return $filename;
        }

        return substr($filename, 0, $lastDot);
    }

    private function joinPath(string $left, string $right): string
    {
        if ($left === '') {
            return $right;
        }

        return rtrim($left, '/\\') . DIRECTORY_SEPARATOR . ltrim($right, '/\\');
    }

    private function writeFile(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Unable to write markerPDF output file: ' . $path);
        }
    }

    private function writeImage(string $path, mixed $image): void
    {
        if (is_object($image) && method_exists($image, 'save')) {
            $image->save($path, 'PNG');
            return;
        }

        $this->writeFile($path, $this->imageBytes($image));
    }

    private function readSavedFile(string $path): string
    {
        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            throw new RuntimeException('Unable to read markerPDF output file: ' . $path);
        }

        return $contents;
    }

    /**
     * @return array{filename: string, path: string, format: string, size: int, sha256: string, exists: bool}
     */
    private function fileArtifact(string $path, string $filename, string $format): array
    {
        $contents = $this->readSavedFile($path);

        return [
            'filename' => $filename,
            'path' => $path,
            'format' => $format,
            'size' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'exists' => true,
        ];
    }

    /**
     * @return list<string>
     */
    private function markdownImageTargets(string $markdown): array
    {
        preg_match_all(
            '/!\[[^\]]*\]\((?P<target>[^\)"\s]+)\s*([^\)]*)\)/',
            $markdown,
            $matches,
            PREG_SET_ORDER
        );

        $targets = [];
        foreach ($matches as $match) {
            $targets[] = (string) $match['target'];
        }

        return $targets;
    }

    /**
     * @param list<array<string, mixed>> $imageArtifacts
     * @param list<string> $markdownImageTargets
     * @param list<string> $embeddedTargets
     * @param list<string> $unembeddedTargets
     * @return array<string, mixed>
     */
    private function markdownImageBundle(
        array $imageArtifacts,
        array $markdownImageTargets,
        array $embeddedTargets,
        array $unembeddedTargets,
        int $imageDataUriCount,
        bool $includeRuntimePreviewHtml
    ): array {
        $targetCounts = $this->valueCounts($markdownImageTargets);
        $embeddedCounts = $this->valueCounts($embeddedTargets);
        $missingCounts = $this->valueCounts($unembeddedTargets);

        $artifactRows = [];
        $referencedRows = [];
        $unreferencedFilenames = [];
        $importableFilenames = [];
        $unimportableFilenames = [];
        $referencedUnimportableFilenames = [];
        $previewEmbeddedUnimportableFilenames = [];
        $qualityWarnings = [];
        foreach ($imageArtifacts as $artifact) {
            $filename = (string) $artifact['filename'];
            $markdownReferenceCount = $targetCounts[$filename] ?? 0;
            $runtimePreviewEmbeddedCount = $embeddedCounts[$filename] ?? 0;
            $pngQuality = is_array($artifact['png_quality'] ?? null) ? $artifact['png_quality'] : [];
            $wordpressMediaImportable = (bool) ($artifact['wordpress_media_importable'] ?? false);
            $warnings = array_values(array_filter(
                $pngQuality['quality_warnings'] ?? [],
                static fn (mixed $warning): bool => is_string($warning) && $warning !== ''
            ));
            foreach ($warnings as $warning) {
                $qualityWarnings[] = $warning;
            }
            $row = [
                'source_filename' => (string) $artifact['source_filename'],
                'filename' => $filename,
                'path' => (string) $artifact['path'],
                'mime_type' => (string) $artifact['mime_type'],
                'size' => (int) $artifact['size'],
                'sha256' => (string) $artifact['sha256'],
                'persisted_to_output_folder' => (bool) $artifact['persisted_to_output_folder'],
                'source_filename_rewritten' => (bool) $artifact['source_filename_rewritten'],
                'referenced_in_markdown' => $markdownReferenceCount > 0,
                'markdown_reference_count' => $markdownReferenceCount,
                'runtime_preview_embedded_count' => $runtimePreviewEmbeddedCount,
                'runtime_preview_embeddable' => (bool) $artifact['runtime_preview_embeddable'],
                'wordpress_media_importable' => $wordpressMediaImportable,
                'png_quality' => $pngQuality,
            ];

            $artifactRows[] = $row;
            if ($wordpressMediaImportable) {
                $importableFilenames[] = $filename;
            } else {
                $unimportableFilenames[] = $filename;
                if ($markdownReferenceCount > 0) {
                    $referencedUnimportableFilenames[] = $filename;
                }
                if ($runtimePreviewEmbeddedCount > 0) {
                    $previewEmbeddedUnimportableFilenames[] = $filename;
                }
            }
            if ($markdownReferenceCount > 0) {
                $referencedRows[] = $row;
            } else {
                $unreferencedFilenames[] = $filename;
            }
        }

        return [
            'source' => 'marker_output_artifact_preview_markdown_image_bundle',
            'upstream_boundary' => 'marker.output.save_markdown + marker.images.save.images_to_dict + marker_app.markdown_insert_images',
            'image_artifact_count' => count($imageArtifacts),
            'markdown_reference_count' => count($markdownImageTargets),
            'embedded_reference_count' => count($embeddedTargets),
            'missing_reference_count' => count($unembeddedTargets),
            'unreferenced_artifact_count' => count($unreferencedFilenames),
            'markdown_preview_complete' => $unembeddedTargets === [],
            'preview_html_requested' => $includeRuntimePreviewHtml,
            'preview_data_uri_count' => $imageDataUriCount,
            'preview_data_uri_count_matches_embedded_references' => $includeRuntimePreviewHtml
                ? $imageDataUriCount === count($embeddedTargets)
                : null,
            'target_reference_counts' => $targetCounts,
            'embedded_reference_counts' => $embeddedCounts,
            'missing_reference_counts' => $missingCounts,
            'image_artifacts' => $artifactRows,
            'referenced_image_artifacts' => $referencedRows,
            'unreferenced_image_artifacts' => $unreferencedFilenames,
            'missing_markdown_image_targets' => $unembeddedTargets,
            'image_quality' => [
                'source' => 'marker_output_markdown_image_artifact_quality',
                'upstream_boundary' => 'marker.output.save_markdown image.save(..., "PNG") + marker_app.img_to_html PNG bytes',
                'png_artifact_count' => count($imageArtifacts),
                'wordpress_media_importable_count' => count($importableFilenames),
                'wordpress_media_unimportable_count' => count($unimportableFilenames),
                'all_artifacts_wordpress_media_importable' => $unimportableFilenames === [],
                'all_referenced_artifacts_wordpress_media_importable' => $referencedUnimportableFilenames === [],
                'importable_image_artifacts' => $importableFilenames,
                'unimportable_image_artifacts' => $unimportableFilenames,
                'referenced_unimportable_image_artifacts' => $referencedUnimportableFilenames,
                'preview_embedded_unimportable_image_artifacts' => $previewEmbeddedUnimportableFilenames,
                'quality_warning_counts' => $this->valueCounts($qualityWarnings),
            ],
            'executes_streamlit' => false,
            'executes_pdfium' => false,
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * Native review boundary for tabled Markdown tables that carry Marker image references.
     *
     * Upstream table recognition formats cells as GitHub-style pipe Markdown, then
     * marker.output persists the full Markdown string and marker_app globally embeds
     * any referenced image artifacts in the runtime preview. This manifest keeps
     * table-cell image references reviewable without rewriting table Markdown into
     * WordPress HTML or executing Streamlit/PDFium/model code.
     *
     * @param list<array<string, mixed>> $imageArtifacts
     * @return array<string, mixed>
     */
    private function markdownTableImageArtifact(
        string $markdown,
        array $imageArtifacts,
        bool $includeRuntimePreviewHtml
    ): array {
        $artifactByFilename = [];
        foreach ($imageArtifacts as $artifact) {
            $artifactByFilename[(string) $artifact['filename']] = $artifact;
        }

        $lines = preg_split('/\R/', $markdown) ?: [];
        $tables = [];
        $references = [];
        $tableIndex = 0;

        for ($lineIndex = 0, $lineCount = count($lines); $lineIndex < $lineCount - 1; $lineIndex++) {
            $headerLine = (string) $lines[$lineIndex];
            $separatorLine = (string) $lines[$lineIndex + 1];
            if (!$this->isMarkdownTableHeader($headerLine, $separatorLine)) {
                continue;
            }

            $headerCells = $this->splitMarkdownTableRow($headerLine);
            $rows = [
                [
                    'type' => 'header',
                    'line_index' => $lineIndex,
                    'cells' => $headerCells,
                ],
            ];

            $cursor = $lineIndex + 2;
            while ($cursor < $lineCount && $this->isMarkdownTableRow((string) $lines[$cursor])) {
                $rows[] = [
                    'type' => 'data',
                    'line_index' => $cursor,
                    'cells' => $this->splitMarkdownTableRow((string) $lines[$cursor]),
                ];
                $cursor++;
            }

            $tableReferenceStart = count($references);
            $dataRowIndex = 0;
            foreach ($rows as $rowOffset => $row) {
                $rowType = (string) $row['type'];
                $currentDataRowIndex = $rowType === 'data' ? $dataRowIndex : null;
                if ($rowType === 'data') {
                    $dataRowIndex++;
                }

                foreach ($row['cells'] as $columnIndex => $cellText) {
                    foreach ($this->markdownImageReferencesInText($cellText) as $imageReference) {
                        $target = $imageReference['target'];
                        $artifact = $artifactByFilename[$target] ?? null;
                        $references[] = [
                            'table_index' => $tableIndex,
                            'line_index' => (int) $row['line_index'],
                            'row_index' => $rowOffset,
                            'row_type' => $rowType,
                            'data_row_index' => $currentDataRowIndex,
                            'column_index' => $columnIndex,
                            'column_heading' => $headerCells[$columnIndex] ?? null,
                            'alt' => $imageReference['alt'],
                            'target' => $target,
                            'title' => $imageReference['title'],
                            'embedded_as_persisted_image' => $artifact !== null,
                            'missing_persisted_image' => $artifact === null,
                            'artifact_filename' => $artifact !== null ? (string) $artifact['filename'] : null,
                            'source_filename' => $artifact !== null ? (string) $artifact['source_filename'] : null,
                            'artifact_sha256' => $artifact !== null ? (string) $artifact['sha256'] : null,
                            'runtime_preview_embeddable' => $artifact !== null,
                        ];
                    }
                }
            }

            $tableReferences = array_slice($references, $tableReferenceStart);
            $embeddedCount = count(array_filter(
                $tableReferences,
                static fn (array $reference): bool => ($reference['embedded_as_persisted_image'] ?? false) === true
            ));
            $missingCount = count($tableReferences) - $embeddedCount;

            $tables[] = [
                'table_index' => $tableIndex,
                'start_line_index' => $lineIndex,
                'header' => $headerCells,
                'column_count' => count($headerCells),
                'row_count' => count($rows),
                'data_row_count' => max(0, count($rows) - 1),
                'image_reference_count' => count($tableReferences),
                'embedded_image_reference_count' => $embeddedCount,
                'missing_image_reference_count' => $missingCount,
            ];

            $tableIndex++;
            $lineIndex = $cursor - 1;
        }

        $tableTargets = array_map(static fn (array $reference): string => (string) $reference['target'], $references);
        $embeddedTargets = array_values(array_map(
            static fn (array $reference): string => (string) $reference['target'],
            array_filter($references, static fn (array $reference): bool => ($reference['embedded_as_persisted_image'] ?? false) === true)
        ));
        $missingTargets = array_values(array_map(
            static fn (array $reference): string => (string) $reference['target'],
            array_filter($references, static fn (array $reference): bool => ($reference['missing_persisted_image'] ?? false) === true)
        ));
        $tableTargetCounts = $this->valueCounts($tableTargets);
        $embeddedTargetCounts = $this->valueCounts($embeddedTargets);
        $missingTargetCounts = $this->valueCounts($missingTargets);
        $artifactFilenames = array_map(
            static fn (array $artifact): string => (string) $artifact['filename'],
            $imageArtifacts
        );
        $unreferencedTableArtifacts = array_values(array_filter(
            $artifactFilenames,
            static fn (string $filename): bool => !isset($tableTargetCounts[$filename])
        ));

        return [
            'source' => 'marker_output_markdown_table_image_artifact',
            'upstream_boundary' => 'marker.output.save_markdown + tabled.formats.markdown.markdown_format + marker_app.markdown_insert_images',
            'table_count' => count($tables),
            'table_row_count' => array_sum(array_map(static fn (array $table): int => (int) $table['row_count'], $tables)),
            'table_data_row_count' => array_sum(array_map(static fn (array $table): int => (int) $table['data_row_count'], $tables)),
            'table_image_reference_count' => count($references),
            'embedded_table_image_reference_count' => count($embeddedTargets),
            'missing_table_image_reference_count' => count($missingTargets),
            'table_preview_complete' => $missingTargets === [],
            'preview_html_requested' => $includeRuntimePreviewHtml,
            'expected_runtime_preview_table_data_uri_count' => $includeRuntimePreviewHtml ? count($embeddedTargets) : null,
            'target_reference_counts' => $tableTargetCounts,
            'embedded_reference_counts' => $embeddedTargetCounts,
            'missing_reference_counts' => $missingTargetCounts,
            'unique_table_image_targets' => array_keys($tableTargetCounts),
            'missing_table_image_targets' => array_keys($missingTargetCounts),
            'unreferenced_table_image_artifacts' => $unreferencedTableArtifacts,
            'tables' => $tables,
            'references' => $references,
            'executes_streamlit' => false,
            'executes_pdfium' => false,
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    private function isMarkdownTableHeader(string $headerLine, string $separatorLine): bool
    {
        if (!$this->isMarkdownTableRow($headerLine)) {
            return false;
        }

        $cells = $this->splitMarkdownTableRow($separatorLine);
        if (count($cells) < 2) {
            return false;
        }

        foreach ($cells as $cell) {
            if (preg_match('/^:?-{3,}:?$/', trim($cell)) !== 1) {
                return false;
            }
        }

        return true;
    }

    private function isMarkdownTableRow(string $line): bool
    {
        if (!str_contains($line, '|')) {
            return false;
        }

        return count($this->splitMarkdownTableRow($line)) >= 2;
    }

    /**
     * @return list<string>
     */
    private function splitMarkdownTableRow(string $line): array
    {
        $line = trim($line);
        if (str_starts_with($line, '|')) {
            $line = substr($line, 1);
        }
        if (str_ends_with($line, '|')) {
            $line = substr($line, 0, -1);
        }

        $cells = [];
        $cell = '';
        $escaped = false;
        $length = strlen($line);
        for ($index = 0; $index < $length; $index++) {
            $char = $line[$index];
            if ($char === '|' && !$escaped) {
                $cells[] = trim($cell);
                $cell = '';
                continue;
            }

            $cell .= $char;
            $escaped = $char === '\\' && !$escaped;
            if ($char !== '\\') {
                $escaped = false;
            }
        }
        $cells[] = trim($cell);

        return $cells;
    }

    /**
     * @return list<array{alt: string, target: string, title: string|null}>
     */
    private function markdownImageReferencesInText(string $text): array
    {
        preg_match_all(
            '/!\[(?P<alt>[^\]]+)\]\((?P<target>[^\)"\s]+)\s*(?P<title>[^\)]*)\)/',
            $text,
            $matches,
            PREG_SET_ORDER
        );

        $references = [];
        foreach ($matches as $match) {
            $title = trim((string) ($match['title'] ?? ''));
            $references[] = [
                'alt' => (string) $match['alt'],
                'target' => (string) $match['target'],
                'title' => $title !== '' ? $title : null,
            ];
        }

        return $references;
    }

    /**
     * @param list<string> $values
     * @return array<string, int>
     */
    private function valueCounts(array $values): array
    {
        $counts = [];
        foreach ($values as $value) {
            $value = (string) $value;
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Upstream images are named by marker.images.save::get_image_filename as
     * "{page}_image_{index}.png". Sanitize supplied native map keys back into
     * that same single-file artifact boundary before joining paths.
     *
     * @param array<string, mixed> $images
     * @return list<array{source_filename: string, filename: string, image: mixed}>
     */
    private function imageArtifacts(array $images): array
    {
        $artifacts = [];
        $used = [];
        $index = 0;

        foreach ($images as $sourceFilename => $image) {
            $sourceFilename = (string) $sourceFilename;
            $artifacts[] = [
                'source_filename' => $sourceFilename,
                'filename' => $this->sanitizeImageFilename($sourceFilename, $index, $used),
                'image' => $image,
            ];
            $index++;
        }

        return $artifacts;
    }

    /**
     * @param array<string, bool> $used
     */
    private function sanitizeImageFilename(string $filename, int $index, array &$used): string
    {
        $basename = basename(str_replace('\\', '/', $filename));
        $basename = trim((string) preg_replace('/[\x00-\x1F\x7F]/', '', $basename));
        $stem = (string) preg_replace('/\.[^.]*$/', '', $basename);
        $stem = (string) preg_replace('/[^A-Za-z0-9._-]+/', '_', $stem);
        $stem = (string) preg_replace('/_+/', '_', $stem);
        $stem = trim($stem, '._-');

        if ($stem === '') {
            $stem = 'image_' . $index;
        }

        $candidate = $stem . '.png';
        $suffix = 2;
        while (isset($used[strtolower($candidate)])) {
            $candidate = $stem . '_' . $suffix . '.png';
            $suffix++;
        }
        $used[strtolower($candidate)] = true;

        return $candidate;
    }

    /**
     * @param array<string, string> $imageNameMap
     */
    private function rewriteImageReferences(string $markdown, array $imageNameMap): string
    {
        if ($imageNameMap === []) {
            return $markdown;
        }

        $markdown = (string) preg_replace_callback(
            '/!\[(?P<alt>[^\]]*)\]\((?P<body>[^)\r\n]+)\)/',
            static function (array $match) use ($imageNameMap): string {
                $body = trim((string) $match['body']);
                $target = $body;
                $suffix = '';
                if (
                    !isset($imageNameMap[$target])
                    && preg_match('/^(?P<target>[^\)"\s]+)(?P<suffix>\s+.*)$/', $body, $parts) === 1
                ) {
                    $target = (string) $parts['target'];
                    $suffix = (string) $parts['suffix'];
                }
                if (!isset($imageNameMap[$target])) {
                    return $match[0];
                }

                $alt = (string) $match['alt'];
                if (isset($imageNameMap[$alt])) {
                    $alt = $imageNameMap[$alt];
                }

                return '![' . $alt . '](' . $imageNameMap[$target] . $suffix . ')';
            },
            $markdown
        );

        return (string) preg_replace_callback(
            '/\b(?P<attribute>src|href|alt)=(?P<quote>["\'])(?P<value>.*?)(?P=quote)/i',
            static function (array $match) use ($imageNameMap): string {
                $value = (string) $match['value'];
                if (!isset($imageNameMap[$value])) {
                    return $match[0];
                }

                return $match['attribute'] . '=' . $match['quote'] . $imageNameMap[$value] . $match['quote'];
            },
            $markdown
        );
    }

    /**
     * @param array<string, string> $imageNameMap
     */
    private function rewriteMetadataImageReferences(mixed $value, array $imageNameMap): mixed
    {
        if ($imageNameMap === []) {
            return $value;
        }
        if (is_string($value)) {
            return $imageNameMap[$value] ?? $value;
        }
        if (!is_array($value)) {
            return $value;
        }

        $rewritten = [];
        foreach ($value as $key => $item) {
            $rewrittenKey = is_string($key) && isset($imageNameMap[$key]) ? $imageNameMap[$key] : $key;
            $rewritten[$rewrittenKey] = $this->rewriteMetadataImageReferences($item, $imageNameMap);
        }

        return $rewritten;
    }

    private function imageBytes(mixed $image): string
    {
        if (is_string($image)) {
            return $image;
        }
        if ($image instanceof Stringable) {
            return (string) $image;
        }
        if (is_array($image)) {
            foreach (['bytes', 'data', 'content'] as $key) {
                if (isset($image[$key]) && is_string($image[$key])) {
                    return $image[$key];
                }
            }
        }

        throw new InvalidArgumentException('Image payload must be writable PNG bytes or expose save().');
    }

    /**
     * @return array{
     *     png_signature_valid: bool,
     *     png_header_valid: bool,
     *     png_iend_present: bool,
     *     png_width: int|null,
     *     png_height: int|null,
     *     png_dimensions: array{width: int, height: int}|null,
     *     png_bit_depth: int|null,
     *     png_color_type: int|null,
     *     png_color_type_label: string|null,
     *     png_interlace_method: int|null,
     *     png_chunk_types: list<string>,
     *     png_crc_valid: bool|null,
     *     wordpress_media_importable: bool,
     *     quality_warnings: list<string>
     * }
     */
    private function pngArtifactQuality(string $bytes): array
    {
        $warnings = [];
        $chunkTypes = [];
        $signatureValid = str_starts_with($bytes, "\x89PNG\r\n\x1a\n");
        $ihdrValid = false;
        $iendPresent = false;
        $crcValid = null;
        $width = null;
        $height = null;
        $bitDepth = null;
        $colorType = null;
        $interlaceMethod = null;

        if (!$signatureValid) {
            $warnings[] = 'invalid_png_signature';
        } else {
            $crcValid = true;
            $offset = 8;
            $length = strlen($bytes);
            while ($offset + 8 <= $length) {
                $chunkLength = unpack('Nlength', substr($bytes, $offset, 4))['length'];
                $type = substr($bytes, $offset + 4, 4);
                if (!preg_match('/^[A-Za-z]{4}$/', $type)) {
                    $warnings[] = 'invalid_png_chunk_type';
                    $crcValid = false;
                    break;
                }

                $chunkTypes[] = $type;
                $chunkEnd = $offset + 12 + $chunkLength;
                if ($chunkEnd > $length) {
                    $warnings[] = 'truncated_png_chunk_' . strtolower($type);
                    $crcValid = false;
                    break;
                }

                $data = substr($bytes, $offset + 8, $chunkLength);
                $crc = substr($bytes, $offset + 8 + $chunkLength, 4);
                if (hash('crc32b', $type . $data) !== bin2hex($crc)) {
                    $warnings[] = 'invalid_png_crc_' . strtolower($type);
                    $crcValid = false;
                }

                if ($type === 'IHDR') {
                    if ($chunkLength === 13) {
                        $header = unpack('Nwidth/Nheight/Cbit_depth/Ccolor_type/Ccompression/Cfilter/Cinterlace', $data);
                        $width = (int) $header['width'];
                        $height = (int) $header['height'];
                        $bitDepth = (int) $header['bit_depth'];
                        $colorType = (int) $header['color_type'];
                        $interlaceMethod = (int) $header['interlace'];
                        $ihdrValid = $width > 0 && $height > 0;
                        if (!$ihdrValid) {
                            $warnings[] = 'invalid_png_dimensions';
                        }
                    } else {
                        $warnings[] = 'invalid_png_ihdr_length';
                    }
                } elseif ($type === 'IEND') {
                    $iendPresent = $chunkLength === 0;
                    if (!$iendPresent) {
                        $warnings[] = 'invalid_png_iend_length';
                    }
                    break;
                }

                $offset = $chunkEnd;
            }

            if (!in_array('IHDR', $chunkTypes, true)) {
                $warnings[] = 'missing_png_ihdr';
            }
            if (!$iendPresent) {
                $warnings[] = 'missing_png_iend';
            }
        }

        $importable = $signatureValid && $ihdrValid && $iendPresent && $crcValid === true;

        return [
            'png_signature_valid' => $signatureValid,
            'png_header_valid' => $ihdrValid,
            'png_iend_present' => $iendPresent,
            'png_width' => $width,
            'png_height' => $height,
            'png_dimensions' => $ihdrValid ? ['width' => $width, 'height' => $height] : null,
            'png_bit_depth' => $bitDepth,
            'png_color_type' => $colorType,
            'png_color_type_label' => $this->pngColorTypeLabel($colorType),
            'png_interlace_method' => $interlaceMethod,
            'png_chunk_types' => $chunkTypes,
            'png_crc_valid' => $crcValid,
            'wordpress_media_importable' => $importable,
            'quality_warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function pngColorTypeLabel(?int $colorType): ?string
    {
        return match ($colorType) {
            0 => 'grayscale',
            2 => 'truecolor',
            3 => 'indexed',
            4 => 'grayscale_alpha',
            6 => 'truecolor_alpha',
            default => null,
        };
    }
}
