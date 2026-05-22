<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class MarkdownPostProcessor
{
    /**
     * @param list<string> $lines
     */
    public function mergeLines(array $lines, string $blockType = 'Text'): string
    {
        $text = '';
        foreach ($lines as $line) {
            if ($text === '') {
                $text = $line;
                continue;
            }

            $text = $this->lineSeparator($text, $line, $blockType);
        }

        return $text;
    }

    public function surroundBlock(string $text, string $blockType, ?int $headingLevel = null): string
    {
        if ($blockType === 'Section-header') {
            if (!str_starts_with($text, '#')) {
                $prefix = $headingLevel !== null ? str_repeat('#', $headingLevel) : '##';
                $text = "\n" . $prefix . ' ' . $this->titleCase(trim($text)) . "\n";
            }
        } elseif ($blockType === 'Title') {
            if (!str_starts_with($text, '#')) {
                $text = '# ' . $this->titleCase(trim($text)) . "\n";
            }
        } elseif ($blockType === 'Table') {
            $text = "\n" . $text . "\n";
        } elseif ($blockType === 'List-item') {
            $text = $this->escapeMarkdown(rtrim($text)) . "\n";
        } elseif ($blockType === 'Code') {
            $text = "\n```\n" . $text . "\n```\n";
        } elseif ($blockType === 'Text') {
            $text = $this->escapeMarkdown($text);
        } elseif ($blockType === 'Formula') {
            $trimmed = trim($text);
            if (str_starts_with($trimmed, '$$') && str_ends_with($trimmed, '$$')) {
                $text = "\n" . $trimmed . "\n";
            }
        } elseif ($blockType === 'Caption') {
            $text = "\n" . $this->escapeMarkdown($text) . "\n";
        }

        return $text;
    }

    public function escapeMarkdown(string $text): string
    {
        return preg_replace('/[#]/', '\\\\$0', $text) ?? $text;
    }

    private function lineSeparator(string $line1, string $line2, string $blockType, bool $isContinuation = false): string
    {
        $hyphens = '\-\x{2014}\x{00AC}';
        if (
            preg_match('~.*[\p{Lo}\p{Ll}\d][' . $hyphens . ']\s?$~us', $line1) === 1
            && preg_match('~^\s?[\p{Lo}\p{Ll}\d]~u', $line2) === 1
        ) {
            $line1 = preg_replace('~[' . $hyphens . ']\s?$~u', '', $line1) ?? $line1;
            return rtrim($line1) . ltrim($line2);
        }

        $sentenceContinuations = ',;(' . '\x{2014}' . '"' . "'" . '*';
        $lineEndPattern = '~.*[\p{Lo}\p{Ll}\d][' . $sentenceContinuations . ']?\s?$~us';
        $lineStartPattern = '~^\s?[\p{L}\d]~u';
        $sentenceEndPattern = '~.*[\x{3002}\x{0E46}\.\?!]\s?$~us';

        $textBlocks = ['Text', 'List-item', 'Footnote', 'Caption', 'Figure'];
        if (in_array($blockType, ['Title', 'Section-header'], true)) {
            return rtrim($line1) . ' ' . ltrim($line2);
        }
        if ($blockType === 'Formula') {
            return $line1 . "\n" . $line2;
        }
        if (
            preg_match($lineEndPattern, $line1) === 1
            && preg_match($lineStartPattern, $line2) === 1
            && in_array($blockType, $textBlocks, true)
        ) {
            return rtrim($line1) . ' ' . ltrim($line2);
        }
        if ($isContinuation) {
            return rtrim($line1) . ' ' . ltrim($line2);
        }
        if ($blockType !== 'Table' && in_array($blockType, $textBlocks, true) && preg_match($sentenceEndPattern, $line1) === 1) {
            return $line1 . "\n\n" . $line2;
        }
        if ($blockType === 'Table') {
            return $line1 . "\n\n" . $line2;
        }

        return $line1 . "\n" . $line2;
    }

    private function titleCase(string $text): string
    {
        return ucwords(strtolower($text));
    }
}
