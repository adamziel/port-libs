<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfClassicXrefRebuilder
{
    /**
     * @param list<array<string, mixed>> $definitions
     */
    public static function startxrefOffsetWithClassicRebuild(string $pdfBytes, array $definitions): ?int
    {
        $entry = self::latestStartxrefEntry($pdfBytes, $definitions);
        $boundary = $entry['token_offset'] ?? self::latestTopLevelEofOffset($pdfBytes, $definitions);
        if ($entry === null) {
            return self::latestClassicXrefTableOffset($pdfBytes, $definitions, $boundary);
        }

        $declaredOffset = $entry['offset'];
        if (self::xrefStreamObjectExistsAt($definitions, $declaredOffset)) {
            return $declaredOffset;
        }

        $latestClassicOffset = self::latestClassicXrefTableOffset($pdfBytes, $definitions, $boundary);
        if ($latestClassicOffset === null) {
            return $declaredOffset >= 0 && $declaredOffset < strlen($pdfBytes) ? $declaredOffset : null;
        }

        if ($declaredOffset < 0 || $declaredOffset >= strlen($pdfBytes)) {
            return $latestClassicOffset;
        }

        $declaredClassicEntries = self::classicTableEntriesAt($pdfBytes, $declaredOffset);
        if ($declaredClassicEntries === null) {
            return $latestClassicOffset;
        }

        return $latestClassicOffset > $declaredOffset ? $latestClassicOffset : $declaredOffset;
    }

    /**
     * @return array<int, array{type: int, generation: int, offset: int}>|null
     */
    public static function classicTableEntriesAt(string $pdfBytes, int $offset): ?array
    {
        $offset = self::skipPdfWhitespace($pdfBytes, $offset);
        if (!self::pdfKeywordAt($pdfBytes, $offset, 'xref')) {
            return null;
        }

        $afterKeywordOffset = $offset + strlen('xref');
        if ($afterKeywordOffset >= strlen($pdfBytes)) {
            return null;
        }

        $afterKeyword = $pdfBytes[$afterKeywordOffset];
        if ($afterKeyword !== '%' && !self::isPdfWhitespace($afterKeyword)) {
            return null;
        }

        $trailerOffset = self::xrefTableTrailerKeywordOffset($pdfBytes, $afterKeywordOffset);
        if ($trailerOffset === null) {
            return null;
        }

        $dictionaryOffset = self::skipPdfWhitespace($pdfBytes, $trailerOffset + strlen('trailer'));
        if (substr($pdfBytes, $dictionaryOffset, 2) !== '<<') {
            return null;
        }

        $dictionaryEnd = self::skipPdfCompositeTokenAt($pdfBytes, $dictionaryOffset);
        if ($dictionaryEnd === null) {
            return null;
        }

        return self::xrefTableRows(substr($pdfBytes, $afterKeywordOffset, $trailerOffset - $afterKeywordOffset));
    }

    /**
     * @param list<array<string, mixed>> $definitions
     * @return array{offset: int, token_offset: int}|null
     */
    private static function latestStartxrefEntry(string $pdfBytes, array $definitions): ?array
    {
        if (preg_match_all('/\bstartxref\b/s', $pdfBytes, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return null;
        }

        for ($index = count($matches[0]) - 1; $index >= 0; $index--) {
            $tokenOffset = $matches[0][$index][1] ?? null;
            if (
                !is_int($tokenOffset)
                || !self::pdfKeywordAt($pdfBytes, $tokenOffset, 'startxref')
                || self::tokenStartsInPdfCommentLine($pdfBytes, $tokenOffset)
                || self::offsetOwnedByDirectObjectBody($tokenOffset, $definitions)
                || self::tokenStartsInsidePdfCompositeToken($pdfBytes, $tokenOffset, $definitions)
            ) {
                continue;
            }

            $declaredOffset = self::startxrefDeclaredOffsetFromOperand(substr($pdfBytes, $tokenOffset + strlen('startxref'), 64));
            if ($declaredOffset === null) {
                continue;
            }

            return [
                'offset' => max(0, $declaredOffset),
                'token_offset' => $tokenOffset,
            ];
        }

        return null;
    }

    private static function startxrefDeclaredOffsetFromOperand(string $operandBytes): ?int
    {
        $offset = 0;
        $length = strlen($operandBytes);
        while ($offset < $length && self::isPdfWhitespace($operandBytes[$offset])) {
            $offset++;
        }

        if (preg_match('/\G[+-]?\d+/s', $operandBytes, $match, 0, $offset) !== 1) {
            return 0;
        }

        $after = $offset + strlen($match[0]);
        while ($after < $length) {
            $char = $operandBytes[$after];
            if ($char === "\n" || $char === "\r" || $char === '%') {
                return (int) $match[0];
            }
            if (!self::isPdfWhitespace($char)) {
                return null;
            }

            $after++;
        }

        return (int) $match[0];
    }

    /**
     * @param list<array<string, mixed>> $definitions
     */
    private static function latestClassicXrefTableOffset(string $pdfBytes, array $definitions, ?int $boundary): ?int
    {
        if (preg_match_all('/\bxref\b/s', $pdfBytes, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return null;
        }

        $offsets = [];
        foreach ($matches[0] as $match) {
            $offset = $match[1] ?? null;
            if (is_int($offset)) {
                $offsets[] = $offset;
            }
        }
        rsort($offsets, SORT_NUMERIC);

        foreach ($offsets as $offset) {
            if (
                ($boundary !== null && $offset >= $boundary)
                || !self::pdfKeywordAt($pdfBytes, $offset, 'xref')
                || self::tokenStartsInPdfCommentLine($pdfBytes, $offset)
                || self::offsetOwnedByDirectObjectBody($offset, $definitions)
                || self::tokenStartsInsidePdfCompositeToken($pdfBytes, $offset, $definitions)
            ) {
                continue;
            }

            if (self::classicTableEntriesAt($pdfBytes, $offset) !== null) {
                return $offset;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{type: int, generation: int, offset: int}>|null
     */
    private static function xrefTableRows(string $sectionBody): ?array
    {
        $normalized = str_replace(["\0", "\f"], ' ', $sectionBody);
        $lines = preg_split('/\r\n|\r|\n/', trim($normalized));
        if ($lines === false) {
            return null;
        }

        $entries = [];
        $foundSection = false;
        for ($lineIndex = 0, $lineCount = count($lines); $lineIndex < $lineCount; $lineIndex++) {
            $line = trim($lines[$lineIndex]);
            if ($line === '' || str_starts_with($line, '%')) {
                continue;
            }

            if (preg_match('/^(\+?\d+)\s+(\+?\d+)(?:\s*(?:%.*)?)$/', $line, $header) !== 1) {
                if (!$foundSection) {
                    return null;
                }

                continue;
            }

            $foundSection = true;
            $startObject = (int) $header[1];
            $count = max(0, (int) $header[2]);
            for ($entryIndex = 0; $entryIndex < $count;) {
                if (++$lineIndex >= $lineCount) {
                    return $entries === [] ? null : $entries;
                }

                $row = trim($lines[$lineIndex]);
                if ($row === '' || str_starts_with($row, '%')) {
                    continue;
                }

                if (preg_match('/^(\d{10})\s+(\d{5})\s+([nf])(?:\s*(?:%.*)?)$/', $row, $rowMatch) !== 1) {
                    return $entries === [] ? null : $entries;
                }

                $entries[$startObject + $entryIndex] = [
                    'type' => $rowMatch[3] === 'n' ? 1 : 0,
                    'generation' => (int) $rowMatch[2],
                    'offset' => (int) $rowMatch[1],
                ];
                $entryIndex++;
            }
        }

        return $foundSection ? $entries : null;
    }

    private static function xrefTableTrailerKeywordOffset(string $pdfBytes, int $offset): ?int
    {
        while (($candidate = strpos($pdfBytes, 'trailer', $offset)) !== false) {
            if (
                self::pdfKeywordAt($pdfBytes, $candidate, 'trailer')
                && !self::tokenStartsInPdfCommentLine($pdfBytes, $candidate)
            ) {
                return $candidate;
            }

            $offset = $candidate + 1;
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $definitions
     */
    private static function latestTopLevelEofOffset(string $pdfBytes, array $definitions): ?int
    {
        if (preg_match_all('/%%EOF\b/s', $pdfBytes, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return null;
        }

        for ($index = count($matches[0]) - 1; $index >= 0; $index--) {
            $offset = $matches[0][$index][1] ?? null;
            if (
                is_int($offset)
                && !self::tokenStartsInPdfCommentLine($pdfBytes, $offset)
                && !self::offsetOwnedByDirectObjectBody($offset, $definitions)
                && !self::tokenStartsInsidePdfCompositeToken($pdfBytes, $offset, $definitions)
            ) {
                return $offset;
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $definitions
     */
    private static function offsetOwnedByDirectObjectBody(int $offset, array $definitions): bool
    {
        foreach ($definitions as $definition) {
            $bodyStart = $definition['body_start'] ?? $definition['bodyStart'] ?? null;
            $bodyEnd = $definition['body_end'] ?? $definition['bodyEnd'] ?? null;
            if (is_int($bodyStart) && is_int($bodyEnd) && $offset >= $bodyStart && $offset <= $bodyEnd) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $definitions
     */
    private static function tokenStartsInsidePdfCompositeToken(string $pdfBytes, int $tokenOffset, array $definitions): bool
    {
        $length = strlen($pdfBytes);
        $index = 0;
        while ($index < $tokenOffset && $index < $length) {
            foreach ($definitions as $definition) {
                $bodyStart = $definition['body_start'] ?? $definition['bodyStart'] ?? null;
                $bodyEnd = $definition['body_end'] ?? $definition['bodyEnd'] ?? null;
                if (is_int($bodyStart) && is_int($bodyEnd) && $index >= $bodyStart && $index <= $bodyEnd) {
                    $index = $bodyEnd + 1;
                    continue 2;
                }
            }

            $char = $pdfBytes[$index];
            if ($char === '%') {
                self::skipPdfComment($pdfBytes, $index);
                continue;
            }

            if ($char === '(') {
                $end = self::skipPdfLiteralStringAt($pdfBytes, $index);
                if ($end === null) {
                    return true;
                }
                if ($tokenOffset > $index && $tokenOffset < $end) {
                    return true;
                }
                $index = $end + 1;
                continue;
            }

            if ($char === '<' && ($pdfBytes[$index + 1] ?? '') !== '<') {
                $end = self::skipPdfHexStringTokenAt($pdfBytes, $index);
                if ($end !== null) {
                    if ($tokenOffset > $index && $tokenOffset < $end) {
                        return true;
                    }
                    $index = $end;
                    continue;
                }
            }

            $compositeEnd = self::skipPdfCompositeTokenAt($pdfBytes, $index);
            if ($compositeEnd !== null) {
                if ($tokenOffset > $index && $tokenOffset < $compositeEnd) {
                    return true;
                }
                $index = $compositeEnd;
                continue;
            }

            $index++;
        }

        return false;
    }

    private static function skipPdfCompositeTokenAt(string $pdfBytes, int $offset): ?int
    {
        $open = $pdfBytes[$offset] ?? '';
        $close = null;
        $offsetIncrement = 1;
        if ($open === '[') {
            $close = ']';
        } elseif ($open === '<' && ($pdfBytes[$offset + 1] ?? '') === '<') {
            $close = '>>';
            $offsetIncrement = 2;
        } else {
            return null;
        }

        $depth = 1;
        for ($index = $offset + $offsetIncrement, $length = strlen($pdfBytes); $index < $length; $index++) {
            $char = $pdfBytes[$index];
            if ($char === '%') {
                self::skipPdfComment($pdfBytes, $index);
                continue;
            }
            if ($char === '(') {
                $end = self::skipPdfLiteralStringAt($pdfBytes, $index);
                if ($end === null) {
                    return null;
                }
                $index = $end;
                continue;
            }
            if ($char === '<' && ($pdfBytes[$index + 1] ?? '') !== '<') {
                $end = self::skipPdfHexStringTokenAt($pdfBytes, $index);
                if ($end !== null) {
                    $index = $end - 1;
                    continue;
                }
            }
            if ($char === '[' || ($char === '<' && ($pdfBytes[$index + 1] ?? '') === '<')) {
                $nested = self::skipPdfCompositeTokenAt($pdfBytes, $index);
                if ($nested === null) {
                    return null;
                }
                $index = $nested - 1;
                continue;
            }

            if (($close === ']' && $char === ']') || ($close === '>>' && $char === '>' && ($pdfBytes[$index + 1] ?? '') === '>')) {
                $depth--;
                if ($depth === 0) {
                    return $close === '>>' ? $index + 2 : $index + 1;
                }
            }
        }

        return null;
    }

    private static function skipPdfLiteralStringAt(string $pdfBytes, int $offset): ?int
    {
        $depth = 0;
        for ($index = $offset, $length = strlen($pdfBytes); $index < $length; $index++) {
            $char = $pdfBytes[$index];
            if ($char === '\\') {
                $index++;
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $index + 1;
                }
            }
        }

        return null;
    }

    private static function skipPdfHexStringTokenAt(string $pdfBytes, int $offset): ?int
    {
        for ($index = $offset + 1, $length = strlen($pdfBytes); $index < $length; $index++) {
            if ($pdfBytes[$index] === '>') {
                return $index + 1;
            }
        }

        return null;
    }

    private static function tokenStartsInPdfCommentLine(string $pdfBytes, int $tokenOffset): bool
    {
        $before = substr($pdfBytes, 0, $tokenOffset);
        $lastLineFeed = strrpos($before, "\n");
        $lastCarriageReturn = strrpos($before, "\r");
        $lineStart = max($lastLineFeed === false ? -1 : $lastLineFeed, $lastCarriageReturn === false ? -1 : $lastCarriageReturn) + 1;

        return strrpos(substr($pdfBytes, $lineStart, $tokenOffset - $lineStart), '%') !== false;
    }

    private static function skipPdfComment(string $pdfBytes, int &$offset): void
    {
        $length = strlen($pdfBytes);
        while ($offset < $length && $pdfBytes[$offset] !== "\n" && $pdfBytes[$offset] !== "\r") {
            $offset++;
        }
    }

    private static function skipPdfWhitespace(string $pdfBytes, int $offset): int
    {
        $length = strlen($pdfBytes);
        while ($offset < $length && self::isPdfWhitespace($pdfBytes[$offset])) {
            $offset++;
        }

        return $offset;
    }

    private static function isPdfWhitespace(string $char): bool
    {
        return $char === "\0"
            || $char === "\t"
            || $char === "\n"
            || $char === "\f"
            || $char === "\r"
            || $char === ' ';
    }

    private static function pdfKeywordAt(string $pdfBytes, int $offset, string $keyword): bool
    {
        if (substr($pdfBytes, $offset, strlen($keyword)) !== $keyword) {
            return false;
        }

        $before = $offset === 0 ? '' : $pdfBytes[$offset - 1];
        $after = $pdfBytes[$offset + strlen($keyword)] ?? '';

        return ($before === '' || self::isDelimiter($before))
            && ($after === '' || self::isDelimiter($after));
    }

    private static function isDelimiter(string $char): bool
    {
        return $char === "\0"
            || $char === "\t"
            || $char === "\n"
            || $char === "\f"
            || $char === "\r"
            || $char === ' '
            || $char === '('
            || $char === ')'
            || $char === '<'
            || $char === '>'
            || $char === '['
            || $char === ']'
            || $char === '{'
            || $char === '}'
            || $char === '/'
            || $char === '%';
    }

    /**
     * @param list<array<string, mixed>> $definitions
     */
    private static function xrefStreamObjectExistsAt(array $definitions, int $offset): bool
    {
        foreach ($definitions as $definition) {
            if (($definition['offset'] ?? null) !== $offset || !is_string($definition['body'] ?? null)) {
                continue;
            }

            $dictionary = self::dictionaryObjectBody($definition['body']);
            if ($dictionary !== null && preg_match('/\/Type\s*\/XRef\b/s', $dictionary) === 1) {
                return true;
            }
        }

        return false;
    }

    private static function dictionaryObjectBody(string $body): ?string
    {
        $offset = strpos($body, '<<');
        if ($offset === false) {
            return null;
        }

        $end = self::skipPdfCompositeTokenAt($body, $offset);
        return $end === null ? null : substr($body, $offset, $end - $offset);
    }
}
