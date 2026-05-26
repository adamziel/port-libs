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
        return implode("\n", $this->extractTextRuns($pdfBytes));
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
        if (preg_match_all('/(\[[^\]]*\]|(?:\((?:\\\\.|[^\\\\()])*\)|<[\da-fA-F\s]+>))\s*(?:Tj|TJ|\'|")/s', $stream, $matches)) {
            foreach ($matches[1] as $operand) {
                $runs[] = $this->decodeTextOperand($operand);
            }
        }

        return $runs;
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
            return hex2bin($hex) ?: '';
        }

        return $this->decodeLiteralString(substr($operand, 1, -1));
    }

    private function decodeLiteralString(string $value): string
    {
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
}

