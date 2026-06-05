<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfXrefFreeObjectMap
{
    /**
     * @return array<int, true>
     */
    public static function freeObjectNumbers(string $pdfBytes): array
    {
        $offset = self::latestStartxrefOffset($pdfBytes);
        if ($offset === null) {
            return [];
        }

        $freeObjects = [];
        foreach (self::xrefEntriesFromOffsetChain($pdfBytes, $offset) as $objectNumber => $entry) {
            if (($entry['state'] ?? null) === 'f') {
                $freeObjects[(int) $objectNumber] = true;
            }
        }

        return $freeObjects;
    }

    private static function latestStartxrefOffset(string $pdfBytes): ?int
    {
        if (preg_match_all('/\bstartxref\b/s', $pdfBytes, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return null;
        }

        for ($index = count($matches[0]) - 1; $index >= 0; $index--) {
            $tokenOffset = $matches[0][$index][1] ?? null;
            if (!is_int($tokenOffset) || self::tokenStartsInCommentLine($pdfBytes, $tokenOffset)) {
                continue;
            }

            $operandBytes = substr($pdfBytes, $tokenOffset + strlen('startxref'), 64);
            if (preg_match('/^\s*([+-]?\d+)/', $operandBytes, $match) === 1) {
                return max(0, (int) $match[1]);
            }
        }

        return null;
    }

    private static function tokenStartsInCommentLine(string $pdfBytes, int $tokenOffset): bool
    {
        $before = substr($pdfBytes, 0, $tokenOffset);
        $lastLineFeed = strrpos($before, "\n");
        $lastCarriageReturn = strrpos($before, "\r");
        $lineStart = max($lastLineFeed === false ? -1 : $lastLineFeed, $lastCarriageReturn === false ? -1 : $lastCarriageReturn) + 1;
        $commentOffset = strpos($pdfBytes, '%', $lineStart);

        return $commentOffset !== false && $commentOffset < $tokenOffset;
    }

    /**
     * @param array<int, true> $seenOffsets
     * @return array<int, array{state: string, generation: int, offset: int}>
     */
    private static function xrefEntriesFromOffsetChain(string $pdfBytes, int $offset, array $seenOffsets = []): array
    {
        $offset = self::skipWhitespace($pdfBytes, $offset);
        if ($offset < 0 || isset($seenOffsets[$offset])) {
            return [];
        }
        $seenOffsets[$offset] = true;

        $section = self::xrefTableSectionAt($pdfBytes, $offset)
            ?? self::xrefStreamSectionAt($pdfBytes, $offset);
        if ($section === null) {
            return [];
        }

        $entries = $section['entries'];
        $previousOffset = self::previousXrefOffsetForSectionBody($pdfBytes, $section['trailer'], $offset);
        if ($previousOffset !== null && $previousOffset >= 0) {
            foreach (self::xrefEntriesFromOffsetChain($pdfBytes, $previousOffset, $seenOffsets) as $objectNumber => $entry) {
                if (!isset($entries[$objectNumber])) {
                    $entries[$objectNumber] = $entry;
                }
            }
        }

        return $entries;
    }

    /**
     * @return array{entries: array<int, array{state: string, generation: int, offset: int}>, trailer: string}|null
     */
    private static function xrefTableSectionAt(string $pdfBytes, int $offset): ?array
    {
        if (!self::keywordAt($pdfBytes, $offset, 'xref')) {
            return null;
        }

        $sectionBodyOffset = $offset + strlen('xref');
        $trailerOffset = self::keywordOffset($pdfBytes, 'trailer', $sectionBodyOffset);
        if ($trailerOffset === null) {
            return null;
        }

        $entries = self::xrefTableRows(substr($pdfBytes, $sectionBodyOffset, $trailerOffset - $sectionBodyOffset));
        if ($entries === null) {
            return null;
        }

        $trailerBodyOffset = self::skipWhitespace($pdfBytes, $trailerOffset + strlen('trailer'));
        $trailer = substr($pdfBytes, $trailerBodyOffset, 512);
        if (substr($pdfBytes, $trailerBodyOffset, 2) === '<<') {
            $dictionary = self::readDictionaryAt($pdfBytes, $trailerBodyOffset);
            if ($dictionary !== null) {
                $trailer = $dictionary;
            }
        }

        return [
            'entries' => $entries,
            'trailer' => $trailer,
        ];
    }

    /**
     * @return array<int, array{state: string, generation: int, offset: int}>|null
     */
    private static function xrefTableRows(string $sectionBody): ?array
    {
        $lines = preg_split('/\r\n|\r|\n/', $sectionBody);
        if (!is_array($lines)) {
            return null;
        }

        $entries = [];
        $index = 0;
        $lineCount = count($lines);
        while ($index < $lineCount) {
            $line = trim($lines[$index]);
            if ($line === '' || str_starts_with($line, '%')) {
                $index++;
                continue;
            }

            if (preg_match('/^(\d+)\s+(\d+)$/', $line, $header) !== 1) {
                $index++;
                continue;
            }

            $startObject = (int) $header[1];
            $count = (int) $header[2];
            $index++;
            $row = 0;
            while ($row < $count && $index < $lineCount) {
                $rowLine = trim($lines[$index]);
                $index++;
                if ($rowLine === '' || str_starts_with($rowLine, '%')) {
                    continue;
                }

                if (preg_match('/^(\d{10})\s+(\d{5})\s+([nf])\b/', $rowLine, $match) !== 1) {
                    return $entries === [] ? null : $entries;
                }

                $entries[$startObject + $row] = [
                    'state' => $match[3],
                    'generation' => (int) $match[2],
                    'offset' => (int) $match[1],
                ];
                $row++;
            }
        }

        return $entries;
    }

    /**
     * @return array{entries: array<int, array{state: string, generation: int, offset: int}>, trailer: string}|null
     */
    private static function xrefStreamSectionAt(string $pdfBytes, int $offset): ?array
    {
        $definition = self::directObjectAtOffset($pdfBytes, $offset);
        if ($definition === null || preg_match('/\/Type\s*\/XRef\b/', $definition['body']) !== 1) {
            return null;
        }

        $dictionaryOffset = strpos($definition['body'], '<<');
        $dictionary = is_int($dictionaryOffset) ? self::readDictionaryAt($definition['body'], $dictionaryOffset) : null;
        if ($dictionary === null) {
            return null;
        }

        $stream = self::streamPayload($definition['body']);
        if ($stream === null) {
            return null;
        }

        $decoded = self::decodedStreamPayload($dictionary, $stream);
        if ($decoded === null) {
            return null;
        }

        $entries = self::xrefStreamRows($dictionary, $decoded, $pdfBytes, $offset);
        if ($entries === null) {
            return null;
        }

        return [
            'entries' => $entries,
            'trailer' => $dictionary,
        ];
    }

    /**
     * @return array{object: int, generation: int, offset: int, body: string}|null
     */
    private static function directObjectAtOffset(string $pdfBytes, int $offset): ?array
    {
        $offset = self::skipWhitespace($pdfBytes, $offset);
        $slice = substr($pdfBytes, $offset);
        if (preg_match('/^(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $slice, $match) !== 1) {
            return null;
        }

        return [
            'object' => (int) $match[1],
            'generation' => (int) $match[2],
            'offset' => $offset,
            'body' => $match[3],
        ];
    }

    private static function streamPayload(string $body): ?string
    {
        $streamOffset = strpos($body, 'stream');
        if ($streamOffset === false) {
            return null;
        }

        $payloadOffset = $streamOffset + strlen('stream');
        if (substr($body, $payloadOffset, 2) === "\r\n") {
            $payloadOffset += 2;
        } elseif (($body[$payloadOffset] ?? '') === "\n" || ($body[$payloadOffset] ?? '') === "\r") {
            $payloadOffset++;
        }

        $endOffset = strpos($body, 'endstream', $payloadOffset);
        if ($endOffset === false) {
            return null;
        }

        $payload = substr($body, $payloadOffset, $endOffset - $payloadOffset);
        return rtrim($payload, "\r\n");
    }

    private static function decodedStreamPayload(string $dictionary, string $stream): ?string
    {
        $filter = self::nameValueAfterName($dictionary, 'Filter');
        if ($filter === null) {
            return $stream;
        }

        if ($filter !== 'FlateDecode' && $filter !== 'Fl') {
            return null;
        }

        $decoded = @gzuncompress($stream);
        return is_string($decoded) ? $decoded : null;
    }

    /**
     * @return array<int, array{state: string, generation: int, offset: int}>|null
     */
    private static function xrefStreamRows(string $dictionary, string $decoded, string $pdfBytes, int $beforeOffset): ?array
    {
        $widths = self::integerArrayValueAfterName($dictionary, 'W', $pdfBytes, $beforeOffset);
        if ($widths === null || count($widths) < 3) {
            return null;
        }

        $widths = array_slice($widths, 0, 3);
        $entryWidth = array_sum($widths);
        if ($entryWidth <= 0) {
            return null;
        }

        $decodedEntryCount = strlen($decoded) % $entryWidth === 0 ? intdiv(strlen($decoded), $entryWidth) : null;
        $ranges = self::xrefIndexRanges($dictionary, $decodedEntryCount, $pdfBytes, $beforeOffset);
        if ($ranges === []) {
            return null;
        }

        $entries = [];
        $offset = 0;
        foreach ($ranges as $range) {
            [$startObject, $count] = $range;
            for ($index = 0; $index < $count; $index++) {
                if ($offset + $entryWidth > strlen($decoded)) {
                    break 2;
                }

                $fieldOffset = $offset;
                $type = $widths[0] === 0 ? 1 : self::xrefFieldValue($decoded, $fieldOffset, $widths[0]);
                $fieldTwo = self::xrefFieldValue($decoded, $fieldOffset, $widths[1]);
                $fieldThree = self::xrefFieldValue($decoded, $fieldOffset, $widths[2]);
                $objectNumber = $startObject + $index;

                $entries[$objectNumber] = [
                    'state' => ($type === 0 || $type > 2) ? 'f' : 'n',
                    'generation' => $fieldThree,
                    'offset' => $fieldTwo,
                ];
                $offset += $entryWidth;
            }
        }

        return $entries;
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private static function xrefIndexRanges(
        string $dictionary,
        ?int $decodedEntryCount,
        string $pdfBytes,
        int $beforeOffset
    ): array
    {
        $indexValues = self::integerArrayValueAfterName($dictionary, 'Index', $pdfBytes, $beforeOffset);
        if ($indexValues !== null && count($indexValues) >= 2) {
            $ranges = [];
            for ($index = 0; $index + 1 < count($indexValues); $index += 2) {
                $start = $indexValues[$index];
                $count = $indexValues[$index + 1];
                if ($start < 0 || $count < 0) {
                    return [];
                }
                $ranges[] = [$start, $count];
            }

            return $ranges;
        }

        $size = self::integerValueAfterName($dictionary, 'Size') ?? $decodedEntryCount;
        return $size === null || $size < 0 ? [] : [[0, $size]];
    }

    private static function xrefFieldValue(string $bytes, int &$offset, int $width): int
    {
        $value = 0;
        for ($index = 0; $index < $width; $index++) {
            $value = ($value << 8) + ord($bytes[$offset + $index] ?? "\0");
        }
        $offset += $width;

        return $value;
    }

    /**
     * @return list<int>|null
     */
    private static function integerArrayValueAfterName(
        string $dictionary,
        string $name,
        ?string $pdfBytes = null,
        ?int $beforeOffset = null
    ): ?array
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\s*\[([^\]]*)\]/s', $dictionary, $match) !== 1) {
            if ($pdfBytes === null || $beforeOffset === null) {
                return null;
            }

            $reference = self::objectReferenceAfterName($dictionary, $name);
            if ($reference === null) {
                return null;
            }

            $body = self::directObjectBodyForReferenceBeforeOffset(
                $pdfBytes,
                $reference['object'],
                $reference['generation'],
                $beforeOffset
            );
            if ($body === null || preg_match('/^\s*\[([^\]]*)\]\s*\z/s', $body, $match) !== 1) {
                return null;
            }
        }

        if (preg_match_all('/[+-]?\d+/', $match[1], $numbers) < 1) {
            return [];
        }

        return array_map('intval', $numbers[0]);
    }

    /**
     * @return array{object: int, generation: int}|null
     */
    private static function objectReferenceAfterName(string $dictionary, string $name): ?array
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\s+(\d+)\s+(\d+)\s+R\b/s', $dictionary, $match) !== 1) {
            return null;
        }

        return [
            'object' => (int) $match[1],
            'generation' => (int) $match[2],
        ];
    }

    private static function directObjectBodyForReferenceBeforeOffset(
        string $pdfBytes,
        int $objectNumber,
        int $generation,
        int $beforeOffset
    ): ?string
    {
        if ($objectNumber <= 0 || $generation < 0 || $beforeOffset <= 0) {
            return null;
        }

        $selected = null;
        $offset = 0;
        while (preg_match('/(\d+)\s+(\d+)\s+obj\b/s', $pdfBytes, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $objectOffset = $match[0][1];
            if ($objectOffset >= $beforeOffset) {
                break;
            }

            $bodyStart = $objectOffset + strlen($match[0][0]);
            $bodyEnd = strpos($pdfBytes, 'endobj', $bodyStart);
            if ($bodyEnd === false) {
                break;
            }

            if ((int) $match[1][0] === $objectNumber && (int) $match[2][0] === $generation) {
                $selected = substr($pdfBytes, $bodyStart, $bodyEnd - $bodyStart);
            }

            $offset = $bodyEnd + strlen('endobj');
        }

        return $selected;
    }

    private static function previousXrefOffsetForSectionBody(string $pdfBytes, string $sectionBody, int $beforeOffset): ?int
    {
        $reference = self::objectReferenceAfterName($sectionBody, 'Prev');
        if ($reference === null) {
            return self::integerValueAfterName($sectionBody, 'Prev');
        }

        $body = self::directObjectBodyForReferenceBeforeOffset(
            $pdfBytes,
            $reference['object'],
            $reference['generation'],
            $beforeOffset
        );
        if ($body === null || preg_match('/^\s*([+-]?\d+)\s*\z/s', $body, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    private static function integerValueAfterName(string $dictionary, string $name): ?int
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\s+([+-]?\d+)\b/', $dictionary, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    private static function nameValueAfterName(string $dictionary, string $name): ?string
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\s*(?:\/([A-Za-z0-9_.#-]+)|\[\s*\/([A-Za-z0-9_.#-]+))/s', $dictionary, $match) !== 1) {
            return null;
        }

        return $match[1] !== '' ? $match[1] : ($match[2] ?? null);
    }

    private static function keywordOffset(string $pdfBytes, string $keyword, int $offset): ?int
    {
        if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/s', $pdfBytes, $match, PREG_OFFSET_CAPTURE, $offset) !== 1) {
            return null;
        }

        return $match[0][1];
    }

    private static function keywordAt(string $pdfBytes, int $offset, string $keyword): bool
    {
        $length = strlen($keyword);
        if (substr($pdfBytes, $offset, $length) !== $keyword) {
            return false;
        }

        $before = $offset > 0 ? $pdfBytes[$offset - 1] : '';
        $after = $pdfBytes[$offset + $length] ?? '';

        return !self::isPdfNameChar($before) && !self::isPdfNameChar($after);
    }

    private static function isPdfNameChar(string $char): bool
    {
        return $char !== '' && preg_match('/[A-Za-z0-9_]/', $char) === 1;
    }

    private static function skipWhitespace(string $bytes, int $offset): int
    {
        $length = strlen($bytes);
        while ($offset < $length) {
            $char = $bytes[$offset];
            if ($char !== "\0" && $char !== "\t" && $char !== "\n" && $char !== "\f" && $char !== "\r" && $char !== ' ') {
                break;
            }
            $offset++;
        }

        return $offset;
    }

    private static function readDictionaryAt(string $bytes, int $offset): ?string
    {
        if (substr($bytes, $offset, 2) !== '<<') {
            return null;
        }

        $start = $offset;
        $length = strlen($bytes);
        $depth = 0;
        while ($offset < $length - 1) {
            $char = $bytes[$offset];
            if ($char === '%') {
                self::skipComment($bytes, $offset);
                continue;
            }

            if ($char === '(') {
                self::skipLiteralString($bytes, $offset);
                continue;
            }

            if ($char === '<' && ($bytes[$offset + 1] ?? '') !== '<') {
                self::skipHexString($bytes, $offset);
                continue;
            }

            if (substr($bytes, $offset, 2) === '<<') {
                $depth++;
                $offset += 2;
                continue;
            }

            if (substr($bytes, $offset, 2) === '>>') {
                $depth--;
                $offset += 2;
                if ($depth === 0) {
                    return substr($bytes, $start, $offset - $start);
                }
                continue;
            }

            $offset++;
        }

        return null;
    }

    private static function skipComment(string $bytes, int &$offset): void
    {
        $length = strlen($bytes);
        while ($offset < $length && $bytes[$offset] !== "\n" && $bytes[$offset] !== "\r") {
            $offset++;
        }
    }

    private static function skipLiteralString(string $bytes, int &$offset): void
    {
        $depth = 1;
        $offset++;
        $length = strlen($bytes);
        while ($offset < $length && $depth > 0) {
            $char = $bytes[$offset];
            if ($char === '\\') {
                $offset += 2;
                continue;
            }
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }
            $offset++;
        }
    }

    private static function skipHexString(string $bytes, int &$offset): void
    {
        $end = strpos($bytes, '>', $offset + 1);
        $offset = $end === false ? strlen($bytes) : $end + 1;
    }
}
