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
        foreach ($this->streams($pdfBytes) as $stream) {
            foreach ($this->textRunsFromContentStream($stream) as $run) {
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
        foreach ($this->streams($pdfBytes) as $stream) {
            foreach ($this->textLinesFromContentStream($stream) as $line) {
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
        foreach ($this->streams($pdfBytes) as $stream) {
            $pages[] = implode("\n", $this->textLinesFromContentStream($stream));
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
            if (str_contains($dict, '/FlateDecode')) {
                $inflated = @gzuncompress($stream);
                if ($inflated === false) {
                    $inflated = @gzinflate($stream);
                }
                if ($inflated === false) {
                    continue;
                }
                $stream = $inflated;
            }
            $streams[] = $stream;
        }

        return $streams;
    }

    /**
     * @return list<string>
     */
    private function textRunsFromContentStream(string $stream): array
    {
        $runs = [];
        $operands = [];
        foreach ($this->contentTokens($stream) as $token) {
            if ($this->isTextShowingOperator($token)) {
                $operand = $this->textShowingOperand($token, $operands);
                if ($operand !== null) {
                    $runs[] = $this->decodeTextOperand($operand);
                }
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
     */
    private function textLinesFromContentStream(string $stream): array
    {
        $lines = [];
        $operands = [];
        $currentLine = '';
        $currentFontSize = null;
        $currentTextLeading = null;
        $currentTextX = null;
        $currentTextY = null;
        $currentTextEndX = null;
        $characterSpacing = 0.0;
        $wordSpacing = 0.0;
        $horizontalScale = 100.0;
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
                    $decoded = $this->decodeTextOperand($operand);
                    $this->appendPositionedText($currentLine, $decoded, $pendingPositionWordGap);
                    $currentTextEndX = $this->advanceTextEndXForOperand(
                        $currentTextEndX ?? $currentTextX,
                        $operand,
                        $currentFontSize,
                        $characterSpacing,
                        $wordSpacing,
                        $horizontalScale
                    );
                }
                $operands = [];
                continue;
            }

            if ($token === 'q') {
                $textStateStack[] = [
                    'fontSize' => $currentFontSize,
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
                    $currentTextLeading = $state['textLeading'];
                    $characterSpacing = $state['characterSpacing'];
                    $wordSpacing = $state['wordSpacing'];
                    $horizontalScale = $state['horizontalScale'];
                }
                $operands = [];
                continue;
            }

            if ($token === 'Tf') {
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
                $pendingPositionWordGap = false;
                $operands = [];
                continue;
            }

            if ($token === 'ET') {
                $this->pushLine($lines, $currentLine);
                $currentTextX = null;
                $currentTextY = null;
                $currentTextEndX = null;
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
                $this->decodeTextOperand($operand),
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
                    $this->decodeTextOperand($element['value']),
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

    private function decodeTextOperand(string $operand): string
    {
        $operand = trim($operand);
        if (str_starts_with($operand, '[')) {
            $text = '';
            if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)|<[\da-fA-F\s]+>/', $operand, $parts)) {
                foreach ($parts[0] as $part) {
                    $text .= $this->decodeTextOperand($part);
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
            return $this->decodeHexString($hex);
        }

        return $this->decodeLiteralString(substr($operand, 1, -1));
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
