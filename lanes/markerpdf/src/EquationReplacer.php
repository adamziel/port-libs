<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class EquationReplacer
{
    private const DEFAULT_TEXIFY_MODEL_MAX = 384;
    private const DEFAULT_INTERSECTION_THRESHOLD = 0.7;

    private LayoutOrderer $layout;
    private MarkerSettings $settings;
    private DocumentStructureBoundary $boundaries;

    public function __construct(
        ?LayoutOrderer $layout = null,
        ?MarkerSettings $settings = null,
        ?DocumentStructureBoundary $boundaries = null
    )
    {
        $this->layout = $layout ?? new LayoutOrderer();
        $this->settings = $settings ?? new MarkerSettings();
        $this->boundaries = $boundaries ?? new DocumentStructureBoundary($this->layout);
    }

    /**
     * @param array<string, mixed> $page
     * @return array{page: array<string, mixed>, equations: list<array{block_index: int, line_index: int, token_count: int, block_text: string, bbox: list<float>}>}
     */
    public function findEquationBlocks(
        array $page,
        int $modelMaxTokens = self::DEFAULT_TEXIFY_MODEL_MAX,
        float $intersectionThreshold = self::DEFAULT_INTERSECTION_THRESHOLD
    ): array {
        $blocks = array_values(array_filter(
            $page['blocks'] ?? [],
            static fn (mixed $block): bool => is_array($block)
        ));
        $page['blocks'] = $blocks;

        $equationRegions = $this->equationRegions($page, $intersectionThreshold);
        $linesToRemove = [];
        $equationLines = [];
        $insertPoints = [];

        foreach ($equationRegions as $regionIndex => $region) {
            foreach ($blocks as $blockIndex => $block) {
                foreach (($block['lines'] ?? []) as $lineIndex => $line) {
                    $lineBbox = $this->lineBbox($line);
                    if ($lineBbox === null) {
                        continue;
                    }

                    if ($this->layout->intersectionPct($lineBbox, $region) > $intersectionThreshold) {
                        $linesToRemove[$regionIndex][] = [$blockIndex, $lineIndex];
                        $equationLines[$regionIndex][] = $line;

                        if (!isset($insertPoints[$regionIndex])) {
                            $insertPoints[$regionIndex] = [$blockIndex, $lineIndex];
                        }
                    }
                }
            }
        }

        foreach ($equationRegions as $regionIndex => $region) {
            if (!isset($insertPoints[$regionIndex])) {
                $insertPoints[$regionIndex] = [$this->findInsertBlock($blocks, $region), 0];
            }
        }

        $blockLinesToRemove = [];
        $equations = [];
        foreach ($equationRegions as $regionIndex => $region) {
            $lines = $equationLines[$regionIndex] ?? [];
            $blockText = implode(' ', array_map(fn (mixed $line): string => $this->lineText($line), $lines));
            $tokenCount = $this->totalTexifyTokens($blockText);
            $insertPoint = $insertPoints[$regionIndex];
            $insertBlockIndex = (int) $insertPoint[0];
            $insertLineIndex = (int) $insertPoint[1];

            foreach ($linesToRemove[$regionIndex] ?? [] as $lineToRemove) {
                if ($lineToRemove[0] === $insertBlockIndex && $lineToRemove[1] < $insertPoint[1]) {
                    $insertLineIndex--;
                }
            }

            if ($tokenCount >= $modelMaxTokens) {
                continue;
            }

            foreach ($linesToRemove[$regionIndex] ?? [] as $lineToRemove) {
                $blockLinesToRemove[$lineToRemove[0]][$lineToRemove[1]] = true;
            }

            $equations[] = [
                'block_index' => $insertBlockIndex,
                'line_index' => $insertLineIndex,
                'token_count' => $tokenCount,
                'block_text' => $blockText,
                'bbox' => $region,
            ];
        }

        foreach ($blockLinesToRemove as $blockIndex => $badLines) {
            if (!isset($page['blocks'][$blockIndex]['lines']) || !is_array($page['blocks'][$blockIndex]['lines'])) {
                continue;
            }

            $page['blocks'][$blockIndex]['lines'] = array_values(array_filter(
                $page['blocks'][$blockIndex]['lines'],
                static fn (mixed $_line, int $lineIndex): bool => !isset($badLines[$lineIndex]),
                ARRAY_FILTER_USE_BOTH
            ));
        }

        return [
            'page' => $page,
            'equations' => $equations,
        ];
    }

    /**
     * @param array<string, mixed> $page
     * @param list<array{block_index: int, line_index: int, token_count: int, block_text: string, bbox: list<float>}> $equations
     * @param list<string> $predictions
     * @return array{page: array<string, mixed>, successful_ocr: int, unsuccessful_ocr: int, converted_spans: list<array<string, mixed>>}
     */
    public function insertLatexBlocks(
        array $page,
        array $equations,
        array $predictions,
        int $modelMaxTokens = self::DEFAULT_TEXIFY_MODEL_MAX
    ): array {
        $page['blocks'] = array_values(array_filter(
            $page['blocks'] ?? [],
            static fn (mixed $block): bool => is_array($block)
        ));

        $successful = 0;
        $unsuccessful = 0;
        $convertedSpans = [];
        $pnum = (int) ($page['pnum'] ?? 0);

        for ($index = 0; $index < count($equations); $index++) {
            $equation = $equations[$index];
            $prediction = str_replace("\n", ' ', $predictions[$index] ?? '');
            $blockText = str_replace("\n", ' ', $equation['block_text']);
            $accepted = $this->totalTexifyTokens($prediction) < $modelMaxTokens
                && $this->length(trim($prediction)) > 0
                && $this->length($prediction) > $this->length($blockText) * 0.7;

            $spanText = $accepted ? $prediction : $blockText;
            $newBlock = $this->formulaBlock($equation['bbox'], $spanText, $pnum);

            if ($accepted) {
                $successful++;
                $convertedSpans[] = $newBlock['lines'][0]['spans'][0];
            } else {
                $unsuccessful++;
            }

            $insertBlockIndex = max(0, min($equation['block_index'], count($page['blocks'])));
            $lineCount = isset($page['blocks'][$insertBlockIndex]['lines']) && is_array($page['blocks'][$insertBlockIndex]['lines'])
                ? count($page['blocks'][$insertBlockIndex]['lines'])
                : 0;
            $insertLineIndex = $equation['line_index'];

            if ($insertLineIndex === 0 || !isset($page['blocks'][$insertBlockIndex])) {
                array_splice($page['blocks'], $insertBlockIndex, 0, [$newBlock]);
                $this->incrementInsertPoints($equations, $insertBlockIndex, 1);
                continue;
            }

            if ($insertLineIndex >= $lineCount) {
                array_splice($page['blocks'], $insertBlockIndex + 1, 0, [$newBlock]);
                $this->incrementInsertPoints($equations, $insertBlockIndex + 1, 1);
                continue;
            }

            $split = $this->splitBlockLines($page['blocks'][$insertBlockIndex], $insertLineIndex);
            array_splice($page['blocks'], $insertBlockIndex, 1, [$split[0], $newBlock, $split[1]]);
            $this->incrementInsertPoints($equations, $insertBlockIndex, 2);
        }

        return [
            'page' => $page,
            'successful_ocr' => $successful,
            'unsuccessful_ocr' => $unsuccessful,
            'converted_spans' => $convertedSpans,
        ];
    }

    /**
     * @param list<array<string, mixed>> $pages
     * @param list<string> $predictions
     * @return array{pages: list<array<string, mixed>>, metadata: array{successful_ocr: int, unsuccessful_ocr: int, equations: int}, converted_spans: list<array<string, mixed>>}
     */
    public function replaceEquations(
        array $pages,
        array $predictions,
        int $modelMaxTokens = self::DEFAULT_TEXIFY_MODEL_MAX,
        float $intersectionThreshold = self::DEFAULT_INTERSECTION_THRESHOLD
    ): array {
        $pageEquationBlocks = [];
        $foundPages = [];
        $equationCount = 0;

        foreach ($pages as $pageIndex => $page) {
            $found = $this->findEquationBlocks($page, $modelMaxTokens, $intersectionThreshold);
            $foundPages[$pageIndex] = $found['page'];
            $pageEquationBlocks[$pageIndex] = $found['equations'];
            $equationCount += count($found['equations']);
        }

        $predictionOffset = 0;
        $successful = 0;
        $unsuccessful = 0;
        $convertedSpans = [];

        foreach ($foundPages as $pageIndex => $page) {
            $equations = $pageEquationBlocks[$pageIndex];
            $pagePredictions = array_slice($predictions, $predictionOffset, count($equations));
            $predictionOffset += count($equations);

            $inserted = $this->insertLatexBlocks($page, $equations, $pagePredictions, $modelMaxTokens);
            $foundPages[$pageIndex] = $inserted['page'];
            $successful += $inserted['successful_ocr'];
            $unsuccessful += $inserted['unsuccessful_ocr'];
            array_push($convertedSpans, ...$inserted['converted_spans']);
        }

        return [
            'pages' => array_values($foundPages),
            'metadata' => [
                'successful_ocr' => $successful,
                'unsuccessful_ocr' => $unsuccessful,
                'equations' => $equationCount,
            ],
            'converted_spans' => $convertedSpans,
        ];
    }

    public function totalTexifyTokens(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        preg_match_all('/\S+/u', $text, $matches);
        return count($matches[0]);
    }

    /**
     * Native boundary for marker.equations.inference::get_batch_size.
     */
    public function texifyBatchSize(float $batchMultiplier = 1.0): int
    {
        $configured = $this->settings->get('TEXIFY_BATCH_SIZE');
        if ($configured !== null) {
            return (int) ((int) $configured * $batchMultiplier);
        }

        $device = $this->settings->torchDeviceModel();
        $base = ($device === 'cuda' || $device === 'mps') ? 6 : 2;

        return (int) ($base * $batchMultiplier);
    }

    /**
     * Native supplied-output boundary for marker.equations.inference::get_latex_batched.
     *
     * Upstream batches rendered equation images, sets max_tokens from the
     * largest detected equation token count in each batch, then blanks outputs
     * whose tokenizer count reaches the generated max-length sentinel. This
     * method preserves that control flow for supplied model outputs without
     * loading Texify.
     *
     * @param list<mixed> $images
     * @param list<int|float|string> $tokenCounts
     * @param list<mixed> $modelOutputs
     * @return array{
     *     predictions: list<string>,
     *     batches: list<array{start: int, end: int, image_count: int, token_counts: list<int>, max_tokens: int}>,
     *     dropped_output_indexes: list<int>,
     *     batch_size: int
     * }
     */
    public function getLatexBatchedFromSuppliedOutputs(
        array $images,
        array $tokenCounts,
        array $modelOutputs,
        float $batchMultiplier = 1.0
    ): array {
        $images = array_values($images);
        $tokenCounts = $this->normalizeTokenCounts($tokenCounts);
        $modelOutputs = array_values($modelOutputs);

        if (count($images) !== count($tokenCounts)) {
            throw new InvalidArgumentException('Texify images and token counts must have matching counts.');
        }

        $batchSize = $this->texifyBatchSize($batchMultiplier);
        if ($batchSize <= 0) {
            throw new InvalidArgumentException('Texify batch size must be positive.');
        }

        if ($images === []) {
            return [
                'predictions' => [],
                'batches' => [],
                'dropped_output_indexes' => [],
                'batch_size' => $batchSize,
            ];
        }

        $modelMax = (int) $this->settings->get('TEXIFY_MODEL_MAX');
        $tokenBuffer = (int) $this->settings->get('TEXIFY_TOKEN_BUFFER');
        $predictions = array_fill(0, count($images), '');
        $batches = [];
        $droppedIndexes = [];

        for ($start = 0; $start < count($images); $start += $batchSize) {
            $end = min($start + $batchSize, count($images));
            $batchTokenCounts = array_slice($tokenCounts, $start, $end - $start);
            $maxTokens = min(max($batchTokenCounts), $modelMax) + $tokenBuffer;

            $batches[] = [
                'start' => $start,
                'end' => $end,
                'image_count' => $end - $start,
                'token_counts' => $batchTokenCounts,
                'max_tokens' => $maxTokens,
            ];

            for ($index = $start; $index < $end; $index++) {
                $output = array_key_exists($index, $modelOutputs) ? (string) $modelOutputs[$index] : '';
                if ($this->totalTexifyTokens($output) >= $maxTokens - 1) {
                    $output = '';
                    $droppedIndexes[] = $index;
                }
                $predictions[$index] = $output;
            }
        }

        return [
            'predictions' => $predictions,
            'batches' => $batches,
            'dropped_output_indexes' => $droppedIndexes,
            'batch_size' => $batchSize,
        ];
    }

    /**
     * @param list<int|float|string> $tokenCounts
     * @return list<int>
     */
    private function normalizeTokenCounts(array $tokenCounts): array
    {
        if (!array_is_list($tokenCounts)) {
            throw new InvalidArgumentException('Texify token counts must be a list.');
        }

        $normalized = [];
        foreach ($tokenCounts as $value) {
            if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
                throw new InvalidArgumentException('Texify token counts must be numeric.');
            }
            $normalized[] = (int) $value;
        }

        return $normalized;
    }

    /**
     * @param list<array{block_index: int, line_index: int, token_count: int, block_text: string, bbox: list<float>}> $equations
     */
    private function incrementInsertPoints(array &$equations, int $insertBlockIndex, int $insertCount): void
    {
        foreach ($equations as $index => $equation) {
            if ($equation['block_index'] >= $insertBlockIndex) {
                $equations[$index]['block_index'] += $insertCount;
            }
        }
    }

    /**
     * @param array<string, mixed> $page
     * @return list<list<float>>
     */
    private function equationRegions(array $page, float $intersectionThreshold): array
    {
        return $this->boundaries->rejectContainedRegions(
            $this->boundaries->layoutRegions($page, ['Formula']),
            $this->boundaries->layoutRegions($page, ['Table']),
            $intersectionThreshold
        );
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param list<float> $bbox
     */
    private function findInsertBlock(array $blocks, array $bbox): int
    {
        $nearestMatch = null;
        $matchDistance = null;

        foreach ($blocks as $index => $block) {
            $blockBbox = $this->blockBbox($block);
            if ($blockBbox === null) {
                continue;
            }

            $distance = sqrt(($blockBbox[1] - $bbox[1]) ** 2 + ($blockBbox[0] - $bbox[0]) ** 2);
            if ($nearestMatch === null || $matchDistance === null || $distance < $matchDistance) {
                $nearestMatch = $index;
                $matchDistance = $distance;
            }
        }

        return $nearestMatch ?? 0;
    }

    /**
     * @param list<float> $bbox
     * @return array<string, mixed>
     */
    private function formulaBlock(array $bbox, string $text, int $pnum): array
    {
        return [
            'type' => 'Formula',
            'block_type' => 'Formula',
            'bbox' => $bbox,
            'pnum' => $pnum,
            'lines' => [
                [
                    'bbox' => $bbox,
                    'spans' => [
                        [
                            'text' => $text,
                            'bbox' => $bbox,
                            'span_id' => $pnum . '_0_fixeq',
                            'font' => 'Latex',
                            'font_weight' => 0,
                            'font_size' => 0,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $block
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function splitBlockLines(array $block, int $splitLineIndex): array
    {
        $lines = array_values(array_filter(
            $block['lines'] ?? [],
            static fn (mixed $line): bool => is_array($line)
        ));

        $before = $block;
        $before['lines'] = array_slice($lines, 0, $splitLineIndex);
        $before['bbox'] = $this->bboxFromLines($before['lines']) ?? ($this->blockBbox($block) ?? [0.0, 0.0, 0.0, 0.0]);

        $after = $block;
        $after['lines'] = array_slice($lines, $splitLineIndex);
        $after['bbox'] = $this->bboxFromLines($after['lines']) ?? ($this->blockBbox($block) ?? [0.0, 0.0, 0.0, 0.0]);

        return [$before, $after];
    }

    /**
     * @param list<array<string, mixed>> $lines
     * @return list<float>|null
     */
    private function bboxFromLines(array $lines): ?array
    {
        $boxes = [];
        foreach ($lines as $line) {
            $bbox = $this->lineBbox($line);
            if ($bbox !== null) {
                $boxes[] = $bbox;
            }
        }

        if ($boxes === []) {
            return null;
        }

        return [
            min(array_column($boxes, 0)),
            min(array_column($boxes, 1)),
            max(array_column($boxes, 2)),
            max(array_column($boxes, 3)),
        ];
    }

    /**
     * @param array<string, mixed> $block
     * @return list<float>|null
     */
    private function blockBbox(array $block): ?array
    {
        $bbox = $this->bbox($block['bbox'] ?? null);
        if ($bbox !== null) {
            return $bbox;
        }

        $lineBoxes = [];
        foreach (($block['lines'] ?? []) as $line) {
            $lineBbox = $this->lineBbox($line);
            if ($lineBbox !== null) {
                $lineBoxes[] = $lineBbox;
            }
        }

        if ($lineBoxes === []) {
            return null;
        }

        return [
            min(array_column($lineBoxes, 0)),
            min(array_column($lineBoxes, 1)),
            max(array_column($lineBoxes, 2)),
            max(array_column($lineBoxes, 3)),
        ];
    }

    /**
     * @param mixed $line
     * @return list<float>|null
     */
    private function lineBbox(mixed $line): ?array
    {
        if (!is_array($line)) {
            return null;
        }

        return $this->bbox($line['bbox'] ?? null);
    }

    private function lineText(mixed $line): string
    {
        if (is_string($line)) {
            return $line;
        }
        if (!is_array($line)) {
            return '';
        }
        if (isset($line['prelim_text'])) {
            return (string) $line['prelim_text'];
        }
        if (isset($line['text'])) {
            return (string) $line['text'];
        }
        if (isset($line['spans']) && is_array($line['spans'])) {
            return implode('', array_map(static fn (mixed $span): string => is_array($span) ? (string) ($span['text'] ?? '') : '', $line['spans']));
        }

        return '';
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
            if (!is_float($item) && !is_int($item)) {
                return null;
            }
        }

        return array_map(static fn (float|int $item): float => (float) $item, $values);
    }

    private function length(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        preg_match_all('/./us', $text, $matches);
        return count($matches[0]);
    }
}
