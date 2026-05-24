<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfTextExtractor
{
    private const POSITIONED_TEXT_WORD_GAP = 12.0;
    private const SIMPLE_TEXT_ADVANCE_RATIO = 0.5;

    /**
     * @return list<string>
     */
    public function extractTextRuns(string $pdfBytes): array
    {
        $runs = [];
        $fontToUnicodeMaps = $this->fontToUnicodeMaps($pdfBytes);
        foreach ($this->streams($pdfBytes) as $stream) {
            foreach ($this->textRunsFromContentStream($stream, $fontToUnicodeMaps) as $run) {
                if ($run !== '') {
                    $runs[] = $run;
                }
            }
        }

        return $runs;
    }

    public function extractPlainText(string $pdfBytes): string
    {
        return implode("\n", $this->extractTextLines($pdfBytes));
    }

    /**
     * Native boundary for marker.pdf.extract_text::naive_get_text.
     *
     * Upstream asks pypdfium for bounded text per page and appends a newline
     * after each page. Here each extractable content stream is treated as the
     * supplied native page boundary used by the lightweight PDF fixtures.
     */
    public function naiveGetText(string $pdfBytes): string
    {
        $text = '';
        foreach ($this->extractPageTexts($pdfBytes) as $pageText) {
            $text .= $pageText . "\n";
        }

        return $text;
    }

    /**
     * Native boundary for marker.pdf.extract_text::get_length_of_text.
     */
    public function getLengthOfText(string $filepath): int
    {
        $bytes = @file_get_contents($filepath);
        if (!is_string($bytes)) {
            throw new \InvalidArgumentException('Unable to read PDF text-length source: ' . $filepath);
        }

        return $this->length(trim($this->naiveGetText($bytes)));
    }

    /**
     * @return list<string>
     */
    public function extractTextLines(string $pdfBytes): array
    {
        $lines = [];
        $fontToUnicodeMaps = $this->fontToUnicodeMaps($pdfBytes);
        foreach ($this->streams($pdfBytes) as $stream) {
            foreach ($this->textLinesFromContentStream($stream, $fontToUnicodeMaps) as $line) {
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function extractPageTexts(string $pdfBytes): array
    {
        $pages = [];
        $fontToUnicodeMaps = $this->fontToUnicodeMaps($pdfBytes);
        foreach ($this->streams($pdfBytes) as $stream) {
            $pages[] = implode("\n", $this->textLinesFromContentStream($stream, $fontToUnicodeMaps));
        }

        return $pages;
    }

    /**
     * @return list<string>
     */
    private function streams(string $pdfBytes): array
    {
        $streams = [];
        if (!preg_match_all('/<<(.*?)>>\s*stream\r?\n?(.*?)\r?\n?endstream/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
            return $streams;
        }

        foreach ($matches as $match) {
            $dict = $match[1];
            $stream = $match[2];
            $decoded = $this->decodeStream($dict, $stream);
            if ($decoded === null) {
                continue;
            }
            $streams[] = $decoded;
        }

        return $streams;
    }

    private function decodeStream(string $dict, string $stream): ?string
    {
        foreach ($this->streamFilters($dict) as $filter) {
            $decoded = match ($filter) {
                'ASCIIHexDecode', 'AHx' => $this->decodeAsciiHexStream($stream),
                'FlateDecode', 'Fl' => $this->decodeFlateStream($stream),
                default => $stream,
            };

            if ($decoded === null) {
                return null;
            }
            $stream = $decoded;
        }

        return $stream;
    }

    /**
     * @return list<string>
     */
    private function streamFilters(string $dict): array
    {
        if (!preg_match('/\/Filter\s*(?:\[(.*?)\]|\/([A-Za-z0-9]+))/s', $dict, $match)) {
            return [];
        }

        if (($match[1] ?? '') !== '') {
            preg_match_all('/\/([A-Za-z0-9]+)/', $match[1], $filters);
            return $filters[1] ?? [];
        }

        return isset($match[2]) ? [$match[2]] : [];
    }

    private function decodeAsciiHexStream(string $stream): ?string
    {
        $body = strstr($stream, '>', true);
        if ($body === false) {
            $body = $stream;
        }

        $hex = preg_replace('/\s+/', '', $body);
        if ($hex === null || preg_match('/^[\da-fA-F]*$/', $hex) !== 1) {
            return null;
        }

        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $decoded = hex2bin($hex);
        return $decoded === false ? null : $decoded;
    }

    private function decodeFlateStream(string $stream): ?string
    {
        $inflated = @gzuncompress($stream);
        if ($inflated === false) {
            $inflated = @gzinflate($stream);
        }
        if ($inflated === false) {
            $inflated = @gzdecode($stream);
        }

        return $inflated === false ? null : $inflated;
    }

    /**
     * @return array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>
     */
    private function fontToUnicodeMaps(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $fontObjectMaps = [];

        foreach ($objects as $objectNumber => $body) {
            if (!str_contains($body, '/Type /Font') && !str_contains($body, '/Type/Font')) {
                continue;
            }
            if (!preg_match('/\/ToUnicode\s+(\d+)\s+\d+\s+R\b/', $body, $match)) {
                continue;
            }

            $cmapObjectNumber = (int) $match[1];
            if (!isset($objects[$cmapObjectNumber])) {
                continue;
            }

            $cmap = $this->toUnicodeMapFromObject($objects[$cmapObjectNumber]);
            if ($cmap !== null && ($cmap['map'] !== [] || $cmap['codeSpaceRanges'] !== [])) {
                $fontObjectMaps[$objectNumber] = $cmap;
            }
        }

        if ($fontObjectMaps === []) {
            return [];
        }

        $resourceMaps = [];
        if (preg_match_all('/\/Font\s*<<(.*?)>>/s', $pdfBytes, $fontMatches)) {
            foreach ($fontMatches[1] as $fontResourceDictionary) {
                if (!preg_match_all('/\/([A-Za-z0-9_.-]+)\s+(\d+)\s+\d+\s+R\b/', $fontResourceDictionary, $resourceMatches, PREG_SET_ORDER)) {
                    continue;
                }

                foreach ($resourceMatches as $resourceMatch) {
                    $fontObjectNumber = (int) $resourceMatch[2];
                    if (isset($fontObjectMaps[$fontObjectNumber])) {
                        $resourceMaps[$resourceMatch[1]] = $fontObjectMaps[$fontObjectNumber];
                    }
                }
            }
        }

        if ($resourceMaps !== []) {
            return $resourceMaps;
        }

        if (count($fontObjectMaps) === 1) {
            $onlyMap = reset($fontObjectMaps);
            return is_array($onlyMap) ? ['' => $onlyMap] : [];
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function pdfObjects(string $pdfBytes): array
    {
        $objects = [];
        if (!preg_match_all('/(\d+)\s+\d+\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
            return $objects;
        }

        foreach ($matches as $match) {
            $objects[(int) $match[1]] = $match[2];
        }

        return $objects;
    }

    /**
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}|null
     */
    private function toUnicodeMapFromObject(string $objectBody): ?array
    {
        if (!preg_match('/<<(.*?)>>\s*stream\r?\n?(.*?)\r?\n?endstream/s', $objectBody, $match)) {
            return null;
        }

        $decoded = $this->decodeStream($match[1], $match[2]);
        if ($decoded === null) {
            return null;
        }

        return $this->parseToUnicodeCMap($decoded);
    }

    /**
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}
     */
    private function parseToUnicodeCMap(string $cmap): array
    {
        $map = [];

        if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $cmap, $charBlocks)) {
            foreach ($charBlocks[1] as $block) {
                if (!preg_match_all('/<([\da-fA-F\s]+)>\s*<([\da-fA-F\s]+)>/s', $block, $entries, PREG_SET_ORDER)) {
                    continue;
                }

                foreach ($entries as $entry) {
                    $source = $this->normalizeHexKey($entry[1]);
                    if ($source !== '') {
                        $map[$source] = $this->decodeCMapUnicodeHex($entry[2]);
                    }
                }
            }
        }

        if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $cmap, $rangeBlocks)) {
            foreach ($rangeBlocks[1] as $block) {
                $this->parseToUnicodeRanges($block, $map);
            }
        }

        return [
            'map' => $map,
            'codeSpaceRanges' => $this->parseCMapCodeSpaceRanges($cmap),
        ];
    }

    /**
     * @param array<string, string> $map
     */
    private function parseToUnicodeRanges(string $block, array &$map): void
    {
        if (preg_match_all('/<([\da-fA-F\s]+)>\s*<([\da-fA-F\s]+)>\s*<([\da-fA-F\s]+)>/s', $block, $ranges, PREG_SET_ORDER)) {
            foreach ($ranges as $range) {
                $start = $this->normalizeHexKey($range[1]);
                $end = $this->normalizeHexKey($range[2]);
                $target = $this->normalizeHexKey($range[3]);
                if ($start === '' || $end === '' || $target === '') {
                    continue;
                }

                $source = hexdec($start);
                $last = hexdec($end);
                $targetCode = hexdec($target);
                $sourceWidth = strlen($start);
                $targetWidth = strlen($target);
                $count = 0;
                while ($source <= $last && $count < 512) {
                    $sourceKey = str_pad(strtolower(dechex($source)), $sourceWidth, '0', STR_PAD_LEFT);
                    $targetHex = str_pad(strtolower(dechex($targetCode + $count)), $targetWidth, '0', STR_PAD_LEFT);
                    $map[$sourceKey] = $this->decodeCMapUnicodeHex($targetHex);
                    $source++;
                    $count++;
                }
            }
        }
    }

    /**
     * @return list<array{start: int, end: int, width: int}>
     */
    private function parseCMapCodeSpaceRanges(string $cmap): array
    {
        $ranges = [];
        if (!preg_match_all('/begincodespacerange(.*?)endcodespacerange/s', $cmap, $blocks)) {
            return [];
        }

        foreach ($blocks[1] as $block) {
            if (!preg_match_all('/<([\da-fA-F\s]+)>\s*<([\da-fA-F\s]+)>/s', $block, $entries, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($entries as $entry) {
                $start = $this->normalizeHexKey($entry[1]);
                $end = $this->normalizeHexKey($entry[2]);
                if ($start === '' || $end === '' || strlen($start) !== strlen($end) || strlen($start) > 8) {
                    continue;
                }

                $startValue = hexdec($start);
                $endValue = hexdec($end);
                if ($startValue > $endValue) {
                    continue;
                }

                $ranges[$start . ':' . $end] = [
                    'start' => $startValue,
                    'end' => $endValue,
                    'width' => strlen($start),
                ];
            }
        }

        $ranges = array_values($ranges);
        usort($ranges, static function (array $left, array $right): int {
            return $right['width'] <=> $left['width'] ?: $left['start'] <=> $right['start'];
        });

        return $ranges;
    }

    private function normalizeHexKey(string $hex): string
    {
        $normalized = preg_replace('/\s+/', '', strtolower($hex));
        if ($normalized === null || $normalized === '' || preg_match('/^[\da-f]+$/', $normalized) !== 1) {
            return '';
        }
        if (strlen($normalized) % 2 === 1) {
            $normalized = '0' . $normalized;
        }

        return $normalized;
    }

    private function decodeCMapUnicodeHex(string $hex): string
    {
        $normalized = $this->normalizeHexKey($hex);
        if ($normalized === '') {
            return '';
        }

        if (strlen($normalized) % 4 === 0) {
            $bytes = hex2bin($normalized);
            if ($bytes !== false) {
                $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', $bytes);
                if ($decoded !== false) {
                    return $decoded;
                }
            }
        }

        return $this->decodeHexString($normalized);
    }

    /**
     * @return list<string>
     * @param array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontToUnicodeMaps
     */
    private function textRunsFromContentStream(string $stream, array $fontToUnicodeMaps): array
    {
        $runs = [];
        $operands = [];
        $currentFontResource = null;
        foreach ($this->contentTokens($stream) as $token) {
            if ($this->isTextShowingOperator($token)) {
                $operand = $this->textShowingOperand($token, $operands);
                if ($operand !== null) {
                    $runs[] = $this->decodeTextOperand($operand, $this->currentToUnicodeMap($fontToUnicodeMaps, $currentFontResource));
                }
                $operands = [];
                continue;
            }

            if ($token === 'Tf') {
                $currentFontResource = $this->fontResourceOperand($operands) ?? $currentFontResource;
                $operands = [];
                continue;
            }

            if ($this->isOperator($token)) {
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        return $runs;
    }

    /**
     * @return list<string>
     * @param array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontToUnicodeMaps
     */
    private function textLinesFromContentStream(string $stream, array $fontToUnicodeMaps): array
    {
        $lines = [];
        $operands = [];
        $currentLine = '';
        $currentFontResource = null;
        $currentFontSize = null;
        $currentTextLeading = null;
        $currentTextX = null;
        $currentTextY = null;
        $currentTextEndX = null;
        $characterSpacing = 0.0;
        $wordSpacing = 0.0;
        $horizontalScale = 100.0;
        $currentTextMatrixHorizontalScale = 1.0;
        $pendingPositionWordGap = false;
        $textStateStack = [];

        foreach ($this->contentTokens($stream) as $token) {
            if ($this->isTextShowingOperator($token)) {
                if ($token === "'" || $token === '"') {
                    $this->pushLine($lines, $currentLine);
                    $currentTextY = $this->advanceTextYByLeading($currentTextY, $currentTextLeading);
                    $currentTextEndX = $currentTextX;
                    $pendingPositionWordGap = false;
                }

                if ($token === '"') {
                    $wordSpacing = $this->quoteWordSpacingOperand($operands) ?? $wordSpacing;
                    $characterSpacing = $this->quoteCharacterSpacingOperand($operands) ?? $characterSpacing;
                }

                $operand = $this->textShowingOperand($token, $operands);
                if ($operand !== null) {
                    $toUnicodeMap = $this->currentToUnicodeMap($fontToUnicodeMaps, $currentFontResource);
                    $decoded = $this->decodeTextOperand($operand, $toUnicodeMap);
                    $this->appendPositionedText($currentLine, $decoded, $pendingPositionWordGap);
                    $currentTextEndX = $this->advanceTextEndXForOperand(
                        $currentTextEndX ?? $currentTextX,
                        $operand,
                        $toUnicodeMap,
                        $currentFontSize,
                        $characterSpacing,
                        $wordSpacing,
                        $horizontalScale * $currentTextMatrixHorizontalScale
                    );
                }
                $operands = [];
                continue;
            }

            if ($token === 'q') {
                $textStateStack[] = [
                    'fontSize' => $currentFontSize,
                    'fontResource' => $currentFontResource,
                    'textLeading' => $currentTextLeading,
                    'characterSpacing' => $characterSpacing,
                    'wordSpacing' => $wordSpacing,
                    'horizontalScale' => $horizontalScale,
                ];
                $operands = [];
                continue;
            }

            if ($token === 'Q') {
                $state = array_pop($textStateStack);
                if (is_array($state)) {
                    $currentFontSize = $state['fontSize'];
                    $currentFontResource = $state['fontResource'];
                    $currentTextLeading = $state['textLeading'];
                    $characterSpacing = $state['characterSpacing'];
                    $wordSpacing = $state['wordSpacing'];
                    $horizontalScale = $state['horizontalScale'];
                }
                $operands = [];
                continue;
            }

            if ($token === 'Tf') {
                $currentFontResource = $this->fontResourceOperand($operands) ?? $currentFontResource;
                $currentFontSize = $this->fontSizeOperand($operands) ?? $currentFontSize;
                $operands = [];
                continue;
            }

            if ($token === 'TL') {
                $currentTextLeading = $this->textLeadingOperand($operands) ?? $currentTextLeading;
                $operands = [];
                continue;
            }

            if ($token === 'Tc') {
                $characterSpacing = $this->textCharacterSpacingOperand($operands) ?? $characterSpacing;
                $operands = [];
                continue;
            }

            if ($token === 'Tw') {
                $wordSpacing = $this->textWordSpacingOperand($operands) ?? $wordSpacing;
                $operands = [];
                continue;
            }

            if ($token === 'Tz') {
                $horizontalScale = $this->textHorizontalScaleOperand($operands) ?? $horizontalScale;
                $operands = [];
                continue;
            }

            if ($token === 'Td' || $token === 'TD') {
                if ($token === 'TD') {
                    $moveY = $this->textMoveOperandY($operands);
                    if ($moveY !== null) {
                        $currentTextLeading = -$moveY;
                    }
                }
                if ($this->textMoveBreaksLine($operands)) {
                    $this->pushLine($lines, $currentLine);
                    $pendingPositionWordGap = false;
                } elseif ($this->textMoveCreatesWordGap($operands)) {
                    $pendingPositionWordGap = $currentLine !== '';
                }
                $currentTextX = $this->textMoveX($operands, $currentTextX);
                $currentTextY = $this->textMoveY($operands, $currentTextY);
                $currentTextEndX = $currentTextX;
                $operands = [];
                continue;
            }

            if ($token === 'Tm') {
                if ($this->textMatrixBreaksLine($operands, $currentTextY)) {
                    $this->pushLine($lines, $currentLine);
                    $pendingPositionWordGap = false;
                } elseif ($this->textMatrixCreatesWordGap($operands, $currentTextEndX)) {
                    $pendingPositionWordGap = $currentLine !== '';
                }
                $currentTextX = $this->textMatrixX($operands);
                $currentTextY = $this->textMatrixY($operands);
                $currentTextEndX = $currentTextX;
                $currentTextMatrixHorizontalScale = $this->textMatrixHorizontalScale($operands) ?? 1.0;
                $operands = [];
                continue;
            }

            if ($token === 'T*') {
                $this->pushLine($lines, $currentLine);
                $currentTextY = $this->advanceTextYByLeading($currentTextY, $currentTextLeading);
                $currentTextEndX = $currentTextX;
                $pendingPositionWordGap = false;
                $operands = [];
                continue;
            }

            if ($token === 'BT') {
                $currentTextX = null;
                $currentTextY = null;
                $currentTextEndX = null;
                $currentTextMatrixHorizontalScale = 1.0;
                $pendingPositionWordGap = false;
                $operands = [];
                continue;
            }

            if ($token === 'ET') {
                $this->pushLine($lines, $currentLine);
                $currentTextX = null;
                $currentTextY = null;
                $currentTextEndX = null;
                $currentTextMatrixHorizontalScale = 1.0;
                $pendingPositionWordGap = false;
                $operands = [];
                continue;
            }

            if ($this->isOperator($token)) {
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        $this->pushLine($lines, $currentLine);

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function contentTokens(string $stream): array
    {
        $tokens = [];
        $length = strlen($stream);
        $index = 0;

        while ($index < $length) {
            $char = $stream[$index];
            if (ctype_space($char)) {
                $index++;
                continue;
            }

            if ($char === '%') {
                while ($index < $length && !in_array($stream[$index], ["\n", "\r"], true)) {
                    $index++;
                }
                continue;
            }

            if ($char === '(') {
                $tokens[] = $this->readLiteralToken($stream, $index);
                continue;
            }

            if ($char === '<' && ($index + 1 >= $length || $stream[$index + 1] !== '<')) {
                $tokens[] = $this->readHexToken($stream, $index);
                continue;
            }

            if ($char === '[') {
                $tokens[] = $this->readArrayToken($stream, $index);
                continue;
            }

            $start = $index;
            while ($index < $length && !$this->isDelimiter($stream[$index])) {
                $index++;
            }
            if ($index === $start) {
                $index++;
                continue;
            }
            $tokens[] = substr($stream, $start, $index - $start);
        }

        return array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
    }

    private function readLiteralToken(string $stream, int &$index): string
    {
        $start = $index;
        $depth = 0;
        $length = strlen($stream);

        while ($index < $length) {
            $char = $stream[$index];
            if ($char === '\\') {
                $index += 2;
                continue;
            }
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $index++;
                    break;
                }
            }
            $index++;
        }

        return substr($stream, $start, $index - $start);
    }

    private function readHexToken(string $stream, int &$index): string
    {
        $start = $index;
        $length = strlen($stream);
        $index++;

        while ($index < $length && $stream[$index] !== '>') {
            $index++;
        }
        if ($index < $length) {
            $index++;
        }

        return substr($stream, $start, $index - $start);
    }

    private function readArrayToken(string $stream, int &$index): string
    {
        $start = $index;
        $length = strlen($stream);
        $index++;

        while ($index < $length) {
            $char = $stream[$index];
            if ($char === '(') {
                $this->readLiteralToken($stream, $index);
                continue;
            }
            if ($char === '<' && ($index + 1 >= $length || $stream[$index + 1] !== '<')) {
                $this->readHexToken($stream, $index);
                continue;
            }
            if ($char === ']') {
                $index++;
                break;
            }
            $index++;
        }

        return substr($stream, $start, $index - $start);
    }

    private function isDelimiter(string $char): bool
    {
        return ctype_space($char) || str_contains('[]()<>{}%', $char);
    }

    /**
     * @param list<string> $operands
     */
    private function textShowingOperand(string $operator, array $operands): ?string
    {
        if ($operator === '"') {
            for ($index = count($operands) - 1; $index >= 0; $index--) {
                if ($this->isTextOperand($operands[$index])) {
                    return $operands[$index];
                }
            }

            return null;
        }

        $operand = end($operands);
        return is_string($operand) && $this->isTextOperand($operand) ? $operand : null;
    }

    private function isTextShowingOperator(string $token): bool
    {
        return in_array($token, ['Tj', 'TJ', "'", '"'], true);
    }

    private function isTextOperand(string $token): bool
    {
        $token = ltrim($token);
        return str_starts_with($token, '(') || str_starts_with($token, '[') || preg_match('/^<[\da-fA-F\s]*>$/', $token) === 1;
    }

    private function isOperator(string $token): bool
    {
        return preg_match('/^[A-Za-z*"\']+$/', $token) === 1;
    }

    /**
     * @param list<string> $operands
     */
    private function fontResourceOperand(array $operands): ?string
    {
        if (count($operands) < 2) {
            return null;
        }

        $operand = $operands[count($operands) - 2];
        if (!str_starts_with($operand, '/')) {
            return null;
        }

        return substr($operand, 1);
    }

    /**
     * @param array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontToUnicodeMaps
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}|null
     */
    private function currentToUnicodeMap(array $fontToUnicodeMaps, ?string $fontResource): ?array
    {
        if ($fontResource !== null && isset($fontToUnicodeMaps[$fontResource])) {
            return $fontToUnicodeMaps[$fontResource];
        }

        return $fontToUnicodeMaps[''] ?? null;
    }

    /**
     * @param list<string> $operands
     */
    private function fontSizeOperand(array $operands): ?float
    {
        if ($operands === []) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function textLeadingOperand(array $operands): ?float
    {
        if ($operands === []) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function textCharacterSpacingOperand(array $operands): ?float
    {
        if ($operands === []) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function textWordSpacingOperand(array $operands): ?float
    {
        if ($operands === []) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function textHorizontalScaleOperand(array $operands): ?float
    {
        if ($operands === []) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function quoteWordSpacingOperand(array $operands): ?float
    {
        if (count($operands) < 3) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 3]);
    }

    /**
     * @param list<string> $operands
     */
    private function quoteCharacterSpacingOperand(array $operands): ?float
    {
        if (count($operands) < 3) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 2]);
    }

    /**
     * @param list<string> $operands
     */
    private function textMoveBreaksLine(array $operands): bool
    {
        $ty = $this->textMoveOperandY($operands);
        if ($ty === null) {
            return true;
        }

        return abs($ty) > 0.000001;
    }

    /**
     * @param list<string> $operands
     */
    private function textMoveCreatesWordGap(array $operands): bool
    {
        $tx = $this->textMoveOperandX($operands);
        if ($tx === null) {
            return false;
        }

        return $tx >= self::POSITIONED_TEXT_WORD_GAP;
    }

    /**
     * @param list<string> $operands
     */
    private function textMoveX(array $operands, ?float $currentTextX): ?float
    {
        $tx = $this->textMoveOperandX($operands);
        if ($tx === null) {
            return null;
        }

        return $currentTextX === null ? $tx : $currentTextX + $tx;
    }

    /**
     * @param list<string> $operands
     */
    private function textMoveY(array $operands, ?float $currentTextY): ?float
    {
        $ty = $this->textMoveOperandY($operands);
        if ($ty === null) {
            return null;
        }

        return $currentTextY === null ? $ty : $currentTextY + $ty;
    }

    /**
     * @param list<string> $operands
     */
    private function textMoveOperandX(array $operands): ?float
    {
        if (count($operands) < 2) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 2]);
    }

    /**
     * @param list<string> $operands
     */
    private function textMoveOperandY(array $operands): ?float
    {
        if (count($operands) < 2) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function textMatrixBreaksLine(array $operands, ?float $currentTextY): bool
    {
        $matrixY = $this->textMatrixY($operands);
        if ($matrixY === null || $currentTextY === null) {
            return true;
        }

        return abs($matrixY - $currentTextY) > 0.000001;
    }

    /**
     * @param list<string> $operands
     */
    private function textMatrixCreatesWordGap(array $operands, ?float $currentTextEndX): bool
    {
        $matrixX = $this->textMatrixX($operands);
        if ($matrixX === null || $currentTextEndX === null) {
            return false;
        }

        return $matrixX - $currentTextEndX >= self::POSITIONED_TEXT_WORD_GAP;
    }

    /**
     * @param list<string> $operands
     */
    private function textMatrixX(array $operands): ?float
    {
        if (count($operands) < 6) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 2]);
    }

    /**
     * @param list<string> $operands
     */
    private function textMatrixY(array $operands): ?float
    {
        if (count($operands) < 6) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function textMatrixHorizontalScale(array $operands): ?float
    {
        if (count($operands) < 6) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 6]);
    }

    private function advanceTextYByLeading(?float $currentTextY, ?float $currentTextLeading): ?float
    {
        if ($currentTextY === null || $currentTextLeading === null) {
            return null;
        }

        return $currentTextY - $currentTextLeading;
    }

    /**
     * @param list<string> $lines
     */
    private function pushLine(array &$lines, string &$currentLine): void
    {
        $line = rtrim($currentLine);
        if ($line !== '') {
            $lines[] = $line;
        }
        $currentLine = '';
    }

    private function appendPositionedText(string &$currentLine, string $decoded, bool &$pendingPositionWordGap): void
    {
        if ($decoded === '') {
            $pendingPositionWordGap = false;
            return;
        }

        if ($pendingPositionWordGap && !$this->endsWithWhitespace($currentLine) && !$this->startsWithWhitespace($decoded)) {
            $currentLine .= ' ';
        }

        $currentLine .= $decoded;
        $pendingPositionWordGap = false;
    }

    private function advanceTextEndX(
        ?float $currentTextEndX,
        string $decoded,
        ?float $fontSize,
        float $characterSpacing,
        float $wordSpacing,
        float $horizontalScale
    ): ?float {
        if ($currentTextEndX === null || $decoded === '') {
            return $currentTextEndX;
        }

        $fontSize ??= 12.0;
        $characters = $this->length($decoded);
        $baseAdvance = $characters * $fontSize * self::SIMPLE_TEXT_ADVANCE_RATIO;
        $spacingAdvance = (max(0, $characters - 1) * $characterSpacing) + (substr_count($decoded, ' ') * $wordSpacing);
        $scale = $horizontalScale / 100.0;

        return $currentTextEndX + (($baseAdvance + $spacingAdvance) * $scale);
    }

    private function advanceTextEndXForOperand(
        ?float $currentTextEndX,
        string $operand,
        ?array $toUnicodeMap,
        ?float $fontSize,
        float $characterSpacing,
        float $wordSpacing,
        float $horizontalScale
    ): ?float {
        if ($currentTextEndX === null) {
            return null;
        }

        $operand = trim($operand);
        if (!str_starts_with($operand, '[')) {
            return $this->advanceTextEndX(
                $currentTextEndX,
                $this->decodeTextOperand($operand, $toUnicodeMap),
                $fontSize,
                $characterSpacing,
                $wordSpacing,
                $horizontalScale
            );
        }

        $endX = $currentTextEndX;
        foreach ($this->textArrayElements($operand) as $element) {
            if ($element['type'] === 'text') {
                $endX = $this->advanceTextEndX(
                    $endX,
                    $this->decodeTextOperand($element['value'], $toUnicodeMap),
                    $fontSize,
                    $characterSpacing,
                    $wordSpacing,
                    $horizontalScale
                );
                continue;
            }

            $endX = $this->adjustTextEndX($endX, (float) $element['value'], $fontSize, $horizontalScale);
        }

        return $endX;
    }

    private function adjustTextEndX(?float $currentTextEndX, float $adjustment, ?float $fontSize, float $horizontalScale): ?float
    {
        if ($currentTextEndX === null) {
            return null;
        }

        $fontSize ??= 12.0;
        $scale = $horizontalScale / 100.0;

        return $currentTextEndX - (($adjustment / 1000.0) * $fontSize * $scale);
    }

    /**
     * @return list<array{type: string, value: string|float}>
     */
    private function textArrayElements(string $operand): array
    {
        $operand = trim($operand);
        $body = substr($operand, 1, -1);
        $elements = [];
        $index = 0;
        $length = strlen($body);

        while ($index < $length) {
            if (ctype_space($body[$index])) {
                $index++;
                continue;
            }

            if ($body[$index] === '(') {
                $elements[] = [
                    'type' => 'text',
                    'value' => $this->readLiteralToken($body, $index),
                ];
                continue;
            }

            if ($body[$index] === '<' && ($index + 1 >= $length || $body[$index + 1] !== '<')) {
                $elements[] = [
                    'type' => 'text',
                    'value' => $this->readHexToken($body, $index),
                ];
                continue;
            }

            $start = $index;
            while ($index < $length && !ctype_space($body[$index]) && !str_contains('[]()<>{}%', $body[$index])) {
                $index++;
            }

            if ($index === $start) {
                $index++;
                continue;
            }

            $token = substr($body, $start, $index - $start);
            $adjustment = $this->numericOperand($token);
            if ($adjustment !== null) {
                $elements[] = [
                    'type' => 'adjustment',
                    'value' => $adjustment,
                ];
            }
        }

        return $elements;
    }

    private function startsWithWhitespace(string $text): bool
    {
        return $text !== '' && ctype_space($text[0]);
    }

    private function endsWithWhitespace(string $text): bool
    {
        return $text !== '' && ctype_space(substr($text, -1));
    }

    private function numericOperand(string $operand): ?float
    {
        if (preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/', $operand) !== 1) {
            return null;
        }

        return (float) $operand;
    }

    /**
     * @param array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}|null $toUnicodeMap
     */
    private function decodeTextOperand(string $operand, ?array $toUnicodeMap = null): string
    {
        $operand = trim($operand);
        if (str_starts_with($operand, '[')) {
            $text = '';
            if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)|<[\da-fA-F\s]+>/', $operand, $parts)) {
                foreach ($parts[0] as $part) {
                    $text .= $this->decodeTextOperand($part, $toUnicodeMap);
                }
            }
            return $text;
        }
        if (str_starts_with($operand, '<')) {
            $hex = preg_replace('/\s+/', '', trim($operand, '<>'));
            if ($hex === null || $hex === '') {
                return '';
            }
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }
            if ($toUnicodeMap !== null) {
                return $this->decodeHexStringWithToUnicodeMap($hex, $toUnicodeMap);
            }
            return $this->decodeHexString($hex);
        }

        $decoded = $this->decodeLiteralString(substr($operand, 1, -1));
        if ($toUnicodeMap !== null) {
            return $this->decodeHexStringWithToUnicodeMap(bin2hex($decoded), $toUnicodeMap);
        }

        return $decoded;
    }

    /**
     * @param array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>} $toUnicodeMap
     */
    private function decodeHexStringWithToUnicodeMap(string $hex, array $toUnicodeMap): string
    {
        $normalized = $this->normalizeHexKey($hex);
        if ($normalized === '') {
            return '';
        }

        $mappings = $toUnicodeMap['map'] ?? [];
        $keyLengths = array_values(array_unique(array_map('strlen', array_keys($mappings))));
        rsort($keyLengths, SORT_NUMERIC);
        if ($keyLengths === []) {
            return $this->decodeHexString($normalized);
        }

        $text = '';
        $offset = 0;
        $length = strlen($normalized);
        while ($offset < $length) {
            $matched = false;
            foreach ($keyLengths as $keyLength) {
                if ($keyLength <= 0 || $offset + $keyLength > $length) {
                    continue;
                }

                $key = substr($normalized, $offset, $keyLength);
                if (array_key_exists($key, $mappings)) {
                    $text .= $mappings[$key];
                    $offset += $keyLength;
                    $matched = true;
                    break;
                }
            }

            if ($matched) {
                continue;
            }

            $fallbackLength = $this->fallbackToUnicodeSourceLength(
                $keyLengths,
                $length - $offset,
                $toUnicodeMap['codeSpaceRanges'] ?? [],
                $normalized,
                $offset
            );
            $text .= $this->decodeUnmappedToUnicodeSource(substr($normalized, $offset, $fallbackLength));
            $offset += $fallbackLength;
        }

        return $text;
    }

    /**
     * @param list<int> $keyLengths
     * @param list<array{start: int, end: int, width: int}> $codeSpaceRanges
     */
    private function fallbackToUnicodeSourceLength(
        array $keyLengths,
        int $remainingHexLength,
        array $codeSpaceRanges,
        string $normalized,
        int $offset
    ): int {
        foreach ($codeSpaceRanges as $range) {
            $width = $range['width'];
            if ($width <= 0 || $width > $remainingHexLength) {
                continue;
            }

            $source = hexdec(substr($normalized, $offset, $width));
            if ($source >= $range['start'] && $source <= $range['end']) {
                return $width;
            }
        }

        $usableLengths = array_values(array_filter(
            $keyLengths,
            static fn (int $keyLength): bool => $keyLength > 0 && $keyLength <= $remainingHexLength
        ));
        sort($usableLengths, SORT_NUMERIC);

        return $usableLengths[0] ?? min(2, max(1, $remainingHexLength));
    }

    private function decodeUnmappedToUnicodeSource(string $hex): string
    {
        if ($hex === '') {
            return '';
        }

        $decoded = $this->decodeCMapUnicodeHex($hex);
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $decoded) ?? $decoded;
    }

    private function decodeHexString(string $hex): string
    {
        $bytes = hex2bin($hex);
        if ($bytes === false) {
            return '';
        }

        $prefix = strtolower(substr($hex, 0, 4));
        if ($prefix === 'feff') {
            $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }
        if ($prefix === 'fffe') {
            $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        return $bytes;
    }

    private function decodeLiteralString(string $value): string
    {
        $value = preg_replace("/\\\\\r\n|\\\\\n|\\\\\r/s", '', $value) ?? $value;

        return preg_replace_callback('/\\\\([nrtbf()\\\\]|[0-7]{1,3})/s', static function (array $match): string {
            return match ($match[1]) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\x0c",
                '(' => '(',
                ')' => ')',
                '\\' => '\\',
                default => chr(octdec($match[1])),
            };
        }, $value) ?? $value;
    }

    private function length(string $text): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($text, 'UTF-8');
        }

        return strlen($text);
    }
}
