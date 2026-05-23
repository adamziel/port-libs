<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfTextExtractor
{
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

        foreach ($this->contentTokens($stream) as $token) {
            if ($this->isTextShowingOperator($token)) {
                if ($token === "'" || $token === '"') {
                    $this->pushLine($lines, $currentLine);
                }

                $operand = $this->textShowingOperand($token, $operands);
                if ($operand !== null) {
                    $currentLine .= $this->decodeTextOperand($operand);
                }
                $operands = [];
                continue;
            }

            if (in_array($token, ['Td', 'TD', 'Tm', 'T*'], true)) {
                $this->pushLine($lines, $currentLine);
                $operands = [];
                continue;
            }

            if ($token === 'ET') {
                $this->pushLine($lines, $currentLine);
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
