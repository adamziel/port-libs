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
     * after each page. Here page /Contents streams are the native page
     * boundary, with a stream-only fallback for lightweight fixtures.
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
        $objects = $this->pdfObjects($pdfBytes);
        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        if ($pageObjectNumbers !== []) {
            return $this->pageContentStreams($objects, $pageObjectNumbers);
        }

        return $this->allDecodedStreams($pdfBytes, $objects);
    }

    /**
     * @return list<string>
     * @param array<int, string> $objects
     * @param list<int> $pageObjectNumbers
     */
    private function pageContentStreams(array $objects, array $pageObjectNumbers): array
    {
        $pages = [];
        foreach ($pageObjectNumbers as $pageObjectNumber) {
            if (!isset($objects[$pageObjectNumber])) {
                continue;
            }

            $streams = [];
            foreach ($this->pageContentObjectNumbers($objects[$pageObjectNumber]) as $contentObjectNumber) {
                if (!isset($objects[$contentObjectNumber])) {
                    continue;
                }

                $decoded = $this->decodeStreamObject($objects[$contentObjectNumber], $objects);
                if ($decoded !== null) {
                    $streams[] = $decoded;
                }
            }

            if ($streams !== []) {
                $pages[] = implode("\n", $streams);
            }
        }

        return $pages;
    }

    /**
     * @return list<string>
     * @param array<int, string> $objects
     */
    private function allDecodedStreams(string $pdfBytes, array $objects): array
    {
        $streams = [];
        if (!preg_match_all('/<<(.*?)>>\s*stream\r?\n?(.*?)\r?\n?endstream/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
            return $streams;
        }

        foreach ($matches as $match) {
            $dict = $match[1];
            $stream = $match[2];
            $decoded = $this->decodeStream($dict, $stream, $objects);
            if ($decoded === null) {
                continue;
            }
            $streams[] = $decoded;
        }

        return $streams;
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     */
    private function orderedPageObjectNumbers(array $objects): array
    {
        foreach ($objects as $objectNumber => $body) {
            if (!$this->isCatalogObject($body) || !preg_match('/\/Pages\s+(\d+)\s+\d+\s+R\b/s', $body, $match)) {
                continue;
            }

            $pages = $this->pageObjectNumbersFromTree((int) $match[1], $objects);
            if ($pages !== []) {
                return $pages;
            }
        }

        $pages = [];
        foreach ($objects as $objectNumber => $body) {
            if ($this->isPageObject($body)) {
                $pages[] = $objectNumber;
            }
        }

        return $pages;
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function pageObjectNumbersFromTree(int $objectNumber, array $objects, array $seen = []): array
    {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return [];
        }

        $seen[$objectNumber] = true;
        $body = $objects[$objectNumber];
        if ($this->isPageObject($body)) {
            return [$objectNumber];
        }

        if (!preg_match('/\/Kids\s*\[(.*?)\]/s', $body, $match)) {
            return [];
        }

        $pages = [];
        foreach ($this->objectReferences($match[1]) as $childObjectNumber) {
            foreach ($this->pageObjectNumbersFromTree($childObjectNumber, $objects, $seen) as $pageObjectNumber) {
                $pages[] = $pageObjectNumber;
            }
        }

        return $pages;
    }

    private function isCatalogObject(string $body): bool
    {
        return preg_match('/\/Type\s*\/Catalog\b/', $body) === 1;
    }

    private function isPageObject(string $body): bool
    {
        return preg_match('/\/Type\s*\/Page\b/', $body) === 1;
    }

    /**
     * @return list<int>
     */
    private function pageContentObjectNumbers(string $pageBody): array
    {
        if (preg_match('/\/Contents\s*\[(.*?)\]/s', $pageBody, $match)) {
            return $this->objectReferences($match[1]);
        }

        if (preg_match('/\/Contents\s+(\d+)\s+\d+\s+R\b/s', $pageBody, $match)) {
            return [(int) $match[1]];
        }

        return [];
    }

    /**
     * @return list<int>
     */
    private function objectReferences(string $value): array
    {
        if (!preg_match_all('/(\d+)\s+\d+\s+R\b/', $value, $matches)) {
            return [];
        }

        return array_map('intval', $matches[1]);
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeStreamObject(string $objectBody, array $objects): ?string
    {
        if (!preg_match('/<<(.*?)>>\s*stream\r?\n?(.*?)\r?\n?endstream/s', $objectBody, $match)) {
            return null;
        }

        return $this->decodeStream($match[1], $match[2], $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeStream(string $dict, string $stream, array $objects = []): ?string
    {
        $filters = $this->streamFilters($dict, $objects);
        $decodeParms = $this->streamDecodeParms($dict, $objects);
        foreach ($filters as $index => $filter) {
            $filterDecodeParms = $decodeParms[$index] ?? null;
            if (!$this->canApplyDecodeParms($filter, $filterDecodeParms)) {
                return null;
            }

            $decoded = match ($filter) {
                'ASCIIHexDecode', 'AHx' => $this->decodeAsciiHexStream($stream),
                'ASCII85Decode', 'A85' => $this->decodeAscii85Stream($stream),
                'RunLengthDecode', 'RL' => $this->decodeRunLengthStream($stream),
                'FlateDecode', 'Fl' => $this->decodeFlateStream($stream, $filterDecodeParms),
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
     * @param array<int, string> $objects
     */
    private function streamFilters(string $dict, array $objects = []): array
    {
        if (!preg_match('/\/Filter\s*(?:\[(.*?)\]|\/([^\s\[\]()<>{}\/%]+)|(\d+)\s+\d+\s+R\b)/s', $dict, $match)) {
            return [];
        }

        if (($match[1] ?? '') !== '') {
            return $this->filterNamesFromValue($match[1], $objects);
        }

        if (($match[2] ?? '') !== '') {
            return [$this->decodePdfName($match[2])];
        }

        $objectNumber = isset($match[3]) ? (int) $match[3] : 0;
        return $objectNumber > 0 && isset($objects[$objectNumber])
            ? $this->filterNamesFromValue($objects[$objectNumber], $objects)
            : [];
    }

    /**
     * @return list<string>
     * @param array<int, string> $objects
     */
    private function filterNamesFromValue(string $value, array $objects): array
    {
        preg_match_all('/\/([^\s\[\]()<>{}\/%]+)|(\d+)\s+\d+\s+R\b/', $value, $matches, PREG_SET_ORDER);
        $filters = [];
        foreach ($matches as $match) {
            if (($match[1] ?? '') !== '') {
                $filters[] = $this->decodePdfName($match[1]);
                continue;
            }

            $objectNumber = isset($match[2]) ? (int) $match[2] : 0;
            if ($objectNumber > 0 && isset($objects[$objectNumber])) {
                foreach ($this->filterNamesFromValue($objects[$objectNumber], $objects) as $filter) {
                    $filters[] = $filter;
                }
            }
        }

        return $filters;
    }

    /**
     * @return list<string|null>
     * @param array<int, string> $objects
     */
    private function streamDecodeParms(string $dict, array $objects): array
    {
        if (!preg_match('/\/DecodeParms\s*(\[(.*?)\]|<<(.*?)>>|null|(\d+)\s+\d+\s+R\b)/s', $dict, $match)) {
            return [];
        }

        if (($match[2] ?? '') !== '') {
            preg_match_all('/<<(.*?)>>|null|(\d+)\s+\d+\s+R\b/s', $match[2], $items, PREG_SET_ORDER);
            return array_map(fn (array $item): ?string => $this->decodeParmsItem($item[0], $objects), $items);
        }

        return [$this->decodeParmsItem($match[1], $objects)];
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeParmsItem(string $value, array $objects): ?string
    {
        $value = trim($value);
        if ($value === '' || $value === 'null') {
            return null;
        }
        if (preg_match('/^(\d+)\s+\d+\s+R$/', $value, $match)) {
            $objectNumber = (int) $match[1];
            return isset($objects[$objectNumber]) ? $this->decodeParmsItem($objects[$objectNumber], $objects) : null;
        }
        if (preg_match('/^<<(.*?)>>$/s', $value, $match)) {
            return $match[1];
        }

        return $value;
    }

    private function canApplyDecodeParms(string $filter, ?string $decodeParms): bool
    {
        if ($decodeParms === null || trim($decodeParms) === '') {
            return true;
        }

        if (
            preg_match('/\/Predictor\s+(\d+)/', $decodeParms, $match) === 1
            && (int) $match[1] !== 1
            && !in_array($filter, ['FlateDecode', 'Fl'], true)
        ) {
            return false;
        }

        return true;
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

    private function decodeAscii85Stream(string $stream): ?string
    {
        $body = trim($stream);
        if (str_starts_with($body, '<~')) {
            $body = substr($body, 2);
        }

        $terminator = strpos($body, '~>');
        if ($terminator !== false) {
            $body = substr($body, 0, $terminator);
        }

        $out = '';
        $group = [];
        $length = strlen($body);
        for ($index = 0; $index < $length; $index++) {
            $char = $body[$index];
            if (ctype_space($char)) {
                continue;
            }

            if ($char === 'z') {
                if ($group !== []) {
                    return null;
                }
                $out .= "\0\0\0\0";
                continue;
            }

            $ord = ord($char);
            if ($ord < 33 || $ord > 117) {
                return null;
            }

            $group[] = $ord - 33;
            if (count($group) === 5) {
                $out .= $this->decodeAscii85Group($group, 4);
                $group = [];
            }
        }

        if ($group !== []) {
            $groupLength = count($group);
            if ($groupLength === 1) {
                return null;
            }
            while (count($group) < 5) {
                $group[] = 84;
            }
            $out .= $this->decodeAscii85Group($group, $groupLength - 1);
        }

        return $out;
    }

    /**
     * @param list<int> $group
     */
    private function decodeAscii85Group(array $group, int $bytesToReturn): string
    {
        $value = 0;
        foreach ($group as $digit) {
            $value = ($value * 85) + $digit;
        }

        $bytes = '';
        for ($shift = 24; $shift >= 0; $shift -= 8) {
            $bytes .= chr(($value >> $shift) & 0xff);
        }

        return substr($bytes, 0, $bytesToReturn);
    }

    private function decodeFlateStream(string $stream, ?string $decodeParms = null): ?string
    {
        $inflated = @gzuncompress($stream);
        if ($inflated === false) {
            $inflated = @gzinflate($stream);
        }
        if ($inflated === false) {
            $inflated = @gzdecode($stream);
        }

        if ($inflated === false) {
            return null;
        }

        return $this->applyFlatePredictor($inflated, $decodeParms);
    }

    private function applyFlatePredictor(string $bytes, ?string $decodeParms): ?string
    {
        $predictor = $this->decodeParmsInt($decodeParms, 'Predictor') ?? 1;
        if ($predictor === 1) {
            return $bytes;
        }

        $colors = max(1, $this->decodeParmsInt($decodeParms, 'Colors') ?? 1);
        $bitsPerComponent = max(1, $this->decodeParmsInt($decodeParms, 'BitsPerComponent') ?? 8);
        $columns = max(1, $this->decodeParmsInt($decodeParms, 'Columns') ?? 1);
        $rowLength = intdiv(($colors * $columns * $bitsPerComponent) + 7, 8);
        $bytesPerPixel = max(1, intdiv(($colors * $bitsPerComponent) + 7, 8));

        if ($predictor === 2) {
            return $this->applyTiffPredictor($bytes, $rowLength, $bytesPerPixel);
        }

        if ($predictor < 10 || $predictor > 15) {
            return null;
        }

        return $this->applyPngPredictor($bytes, $rowLength, $bytesPerPixel);
    }

    private function decodeParmsInt(?string $decodeParms, string $name): ?int
    {
        if ($decodeParms === null || preg_match('/\/' . preg_quote($name, '/') . '\s+(-?\d+)/', $decodeParms, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    private function applyTiffPredictor(string $bytes, int $rowLength, int $bytesPerPixel): ?string
    {
        if ($rowLength < 1 || strlen($bytes) % $rowLength !== 0) {
            return null;
        }

        $out = '';
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $rowLength) {
            $row = substr($bytes, $offset, $rowLength);
            for ($index = $bytesPerPixel; $index < $rowLength; $index++) {
                $row[$index] = chr((ord($row[$index]) + ord($row[$index - $bytesPerPixel])) & 0xff);
            }
            $out .= $row;
        }

        return $out;
    }

    private function applyPngPredictor(string $bytes, int $rowLength, int $bytesPerPixel): ?string
    {
        $stride = $rowLength + 1;
        if ($rowLength < 1 || strlen($bytes) % $stride !== 0) {
            return null;
        }

        $out = '';
        $previous = str_repeat("\0", $rowLength);
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $stride) {
            $filter = ord($bytes[$offset]);
            $row = substr($bytes, $offset + 1, $rowLength);
            if ($filter > 4) {
                return null;
            }

            for ($index = 0; $index < $rowLength; $index++) {
                $left = $index >= $bytesPerPixel ? ord($row[$index - $bytesPerPixel]) : 0;
                $up = ord($previous[$index]);
                $upperLeft = $index >= $bytesPerPixel ? ord($previous[$index - $bytesPerPixel]) : 0;
                $encoded = ord($row[$index]);
                $row[$index] = chr(($encoded + $this->pngPredictorValue($filter, $left, $up, $upperLeft)) & 0xff);
            }

            $out .= $row;
            $previous = $row;
        }

        return $out;
    }

    private function pngPredictorValue(int $filter, int $left, int $up, int $upperLeft): int
    {
        return match ($filter) {
            0 => 0,
            1 => $left,
            2 => $up,
            3 => intdiv($left + $up, 2),
            4 => $this->paethPredictor($left, $up, $upperLeft),
        };
    }

    private function paethPredictor(int $left, int $up, int $upperLeft): int
    {
        $estimate = $left + $up - $upperLeft;
        $leftDistance = abs($estimate - $left);
        $upDistance = abs($estimate - $up);
        $upperLeftDistance = abs($estimate - $upperLeft);

        if ($leftDistance <= $upDistance && $leftDistance <= $upperLeftDistance) {
            return $left;
        }
        if ($upDistance <= $upperLeftDistance) {
            return $up;
        }

        return $upperLeft;
    }

    private function decodeRunLengthStream(string $stream): ?string
    {
        $out = '';
        $length = strlen($stream);
        for ($offset = 0; $offset < $length; $offset++) {
            $control = ord($stream[$offset]);
            if ($control === 128) {
                return $out;
            }

            if ($control <= 127) {
                $copyLength = $control + 1;
                if ($offset + $copyLength >= $length) {
                    return null;
                }
                $out .= substr($stream, $offset + 1, $copyLength);
                $offset += $copyLength;
                continue;
            }

            if ($offset + 1 >= $length) {
                return null;
            }
            $out .= str_repeat($stream[$offset + 1], 257 - $control);
            $offset++;
        }

        return null;
    }

    /**
     * @return array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>
     */
    private function fontToUnicodeMaps(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $namedCMapBodies = $this->namedCMapBodies($objects);
        $fontObjectMaps = [];

        foreach ($objects as $objectNumber => $body) {
            if (!str_contains($body, '/Type /Font') && !str_contains($body, '/Type/Font')) {
                continue;
            }

            $cmap = null;
            if (preg_match('/\/ToUnicode\s+(\d+)\s+\d+\s+R\b/', $body, $match)) {
                $cmapObjectNumber = (int) $match[1];
                if (isset($objects[$cmapObjectNumber])) {
                    $cmap = $this->toUnicodeMapFromObject($objects[$cmapObjectNumber], $objects, $namedCMapBodies);
                }
            } elseif (preg_match('/\/Differences\s*\[(.*?)\]/s', $body, $match)) {
                $cmap = $this->encodingDifferencesMap($match[1]);
            } elseif (preg_match('/\/Encoding\s+\/([^\s\[\]()<>{}\/%]+)/', $body, $match)) {
                $cmap = $this->namedEncodingMap($this->decodePdfName($match[1]));
            }

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
                if (!preg_match_all('/\/([^\s\[\]()<>{}\/%]+)\s+(\d+)\s+\d+\s+R\b/', $fontResourceDictionary, $resourceMatches, PREG_SET_ORDER)) {
                    continue;
                }

                foreach ($resourceMatches as $resourceMatch) {
                    $fontObjectNumber = (int) $resourceMatch[2];
                    if (isset($fontObjectMaps[$fontObjectNumber])) {
                        $resourceMaps[$this->decodePdfName($resourceMatch[1])] = $fontObjectMaps[$fontObjectNumber];
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
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}
     */
    private function encodingDifferencesMap(string $differences): array
    {
        preg_match_all('/\/[^\s\[\]()<>{}\/%]+|[+-]?\d+/', $differences, $matches);
        $map = [];
        $code = null;

        foreach ($matches[0] ?? [] as $token) {
            if (preg_match('/^[+-]?\d+$/', $token) === 1) {
                $code = max(0, min(255, (int) $token));
                continue;
            }

            if ($code === null || !str_starts_with($token, '/')) {
                continue;
            }

            $glyph = $this->glyphNameToUnicode($this->decodePdfName(substr($token, 1)));
            if ($glyph !== '') {
                $map[str_pad(strtolower(dechex($code)), 2, '0', STR_PAD_LEFT)] = $glyph;
            }
            $code++;
        }

        return [
            'map' => $map,
            'codeSpaceRanges' => [
                ['start' => 0, 'end' => 255, 'width' => 2],
            ],
        ];
    }

    /**
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}|null
     */
    private function namedEncodingMap(string $encodingName): ?array
    {
        if ($encodingName !== 'WinAnsiEncoding') {
            return null;
        }

        return [
            'map' => [
                '80' => $this->unicodeCodepoint(0x20ac),
                '82' => $this->unicodeCodepoint(0x201a),
                '83' => $this->unicodeCodepoint(0x0192),
                '84' => $this->unicodeCodepoint(0x201e),
                '85' => $this->unicodeCodepoint(0x2026),
                '86' => $this->unicodeCodepoint(0x2020),
                '87' => $this->unicodeCodepoint(0x2021),
                '88' => $this->unicodeCodepoint(0x02c6),
                '89' => $this->unicodeCodepoint(0x2030),
                '8a' => $this->unicodeCodepoint(0x0160),
                '8b' => $this->unicodeCodepoint(0x2039),
                '8c' => $this->unicodeCodepoint(0x0152),
                '8e' => $this->unicodeCodepoint(0x017d),
                '91' => $this->unicodeCodepoint(0x2018),
                '92' => $this->unicodeCodepoint(0x2019),
                '93' => $this->unicodeCodepoint(0x201c),
                '94' => $this->unicodeCodepoint(0x201d),
                '95' => $this->unicodeCodepoint(0x2022),
                '96' => $this->unicodeCodepoint(0x2013),
                '97' => $this->unicodeCodepoint(0x2014),
                '98' => $this->unicodeCodepoint(0x02dc),
                '99' => $this->unicodeCodepoint(0x2122),
                '9a' => $this->unicodeCodepoint(0x0161),
                '9b' => $this->unicodeCodepoint(0x203a),
                '9c' => $this->unicodeCodepoint(0x0153),
                '9e' => $this->unicodeCodepoint(0x017e),
                '9f' => $this->unicodeCodepoint(0x0178),
            ],
            'codeSpaceRanges' => [
                ['start' => 0, 'end' => 255, 'width' => 2],
            ],
        ];
    }

    private function unicodeCodepoint(int $codepoint): string
    {
        $decoded = iconv('UTF-32BE', 'UTF-8//IGNORE', pack('N', $codepoint));
        return $decoded === false ? '' : $decoded;
    }

    private function glyphNameToUnicode(string $glyphName): string
    {
        $baseName = explode('.', $glyphName, 2)[0];
        if ($baseName === '') {
            return '';
        }

        if (preg_match('/^uni([\da-fA-F]{4})(?:[\da-fA-F]{4})*$/', $baseName) === 1) {
            $hex = substr($baseName, 3);
            return $this->decodeCMapUnicodeHex($hex);
        }

        if (preg_match('/^u([\da-fA-F]{4,6})$/', $baseName, $match) === 1) {
            $codepoint = hexdec($match[1]);
            if ($codepoint <= 0x10ffff) {
                $decoded = iconv('UTF-32BE', 'UTF-8//IGNORE', pack('N', $codepoint));
                return $decoded === false ? '' : $decoded;
            }
        }

        $names = [
            'space' => ' ',
            'hyphen' => '-',
            'minus' => '-',
            'period' => '.',
            'comma' => ',',
            'colon' => ':',
            'semicolon' => ';',
            'parenleft' => '(',
            'parenright' => ')',
            'slash' => '/',
            'A' => 'A',
            'B' => 'B',
            'C' => 'C',
            'D' => 'D',
            'E' => 'E',
            'F' => 'F',
            'G' => 'G',
            'H' => 'H',
            'I' => 'I',
            'J' => 'J',
            'K' => 'K',
            'L' => 'L',
            'M' => 'M',
            'N' => 'N',
            'O' => 'O',
            'P' => 'P',
            'Q' => 'Q',
            'R' => 'R',
            'S' => 'S',
            'T' => 'T',
            'U' => 'U',
            'V' => 'V',
            'W' => 'W',
            'X' => 'X',
            'Y' => 'Y',
            'Z' => 'Z',
            'a' => 'a',
            'b' => 'b',
            'c' => 'c',
            'd' => 'd',
            'e' => 'e',
            'f' => 'f',
            'g' => 'g',
            'h' => 'h',
            'i' => 'i',
            'j' => 'j',
            'k' => 'k',
            'l' => 'l',
            'm' => 'm',
            'n' => 'n',
            'o' => 'o',
            'p' => 'p',
            'q' => 'q',
            'r' => 'r',
            's' => 's',
            't' => 't',
            'u' => 'u',
            'v' => 'v',
            'w' => 'w',
            'x' => 'x',
            'y' => 'y',
            'z' => 'z',
        ];

        return $names[$baseName] ?? '';
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
     * @return array<string, string>
     * @param array<int, string> $objects
     */
    private function namedCMapBodies(array $objects): array
    {
        $named = [];
        foreach ($objects as $body) {
            $cmap = $this->decodedCMapBody($body, $objects);
            if ($cmap === null || !preg_match('/\/CMapName\s+\/([^\s\[\]()<>{}\/%]+)\s+def\b/s', $cmap, $match)) {
                continue;
            }

            $named[$this->decodePdfName($match[1])] = $cmap;
        }

        return $named;
    }

    /**
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}|null
     * @param array<int, string> $objects
     * @param array<string, string> $namedCMapBodies
     */
    private function toUnicodeMapFromObject(string $objectBody, array $objects, array $namedCMapBodies): ?array
    {
        $decoded = $this->decodedCMapBody($objectBody, $objects);
        if ($decoded === null) {
            return null;
        }

        return $this->parseToUnicodeCMap($decoded, $namedCMapBodies);
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodedCMapBody(string $objectBody, array $objects): ?string
    {
        if (!preg_match('/<<(.*?)>>\s*stream\r?\n?(.*?)\r?\n?endstream/s', $objectBody, $match)) {
            return null;
        }

        return $this->decodeStream($match[1], $match[2], $objects);
    }

    /**
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}
     * @param array<string, string> $namedCMapBodies
     * @param list<string> $seenCMaps
     */
    private function parseToUnicodeCMap(string $cmap, array $namedCMapBodies = [], array $seenCMaps = []): array
    {
        $map = [];
        $codeSpaceRanges = [];

        if (preg_match_all('/\/([^\s\[\]()<>{}\/%]+)\s+usecmap\b/s', $cmap, $useCMapMatches)) {
            foreach ($useCMapMatches[1] as $rawName) {
                $name = $this->decodePdfName($rawName);
                if (in_array($name, $seenCMaps, true) || !isset($namedCMapBodies[$name])) {
                    continue;
                }

                $base = $this->parseToUnicodeCMap($namedCMapBodies[$name], $namedCMapBodies, [...$seenCMaps, $name]);
                $map = $base['map'] + $map;
                foreach ($base['codeSpaceRanges'] as $range) {
                    $codeSpaceRanges[$range['start'] . ':' . $range['end'] . ':' . $range['width']] = $range;
                }
            }
        }

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

        foreach ($this->parseCMapCodeSpaceRanges($cmap) as $range) {
            $codeSpaceRanges[$range['start'] . ':' . $range['end'] . ':' . $range['width']] = $range;
        }
        $codeSpaceRanges = array_values($codeSpaceRanges);
        usort($codeSpaceRanges, static function (array $left, array $right): int {
            return $right['width'] <=> $left['width'] ?: $left['start'] <=> $right['start'];
        });

        return [
            'map' => $map,
            'codeSpaceRanges' => $codeSpaceRanges,
        ];
    }

    /**
     * @param array<string, string> $map
     */
    private function parseToUnicodeRanges(string $block, array &$map): void
    {
        if (preg_match_all('/<([\da-fA-F\s]+)>\s*<([\da-fA-F\s]+)>\s*\[(.*?)\]/s', $block, $arrayRanges, PREG_SET_ORDER)) {
            foreach ($arrayRanges as $range) {
                $start = $this->normalizeHexKey($range[1]);
                $end = $this->normalizeHexKey($range[2]);
                if ($start === '' || $end === '') {
                    continue;
                }

                preg_match_all('/<([\da-fA-F\s]+)>/s', $range[3], $targets);
                if (($targets[1] ?? []) === []) {
                    continue;
                }

                $source = hexdec($start);
                $last = hexdec($end);
                $sourceWidth = strlen($start);
                foreach ($targets[1] as $target) {
                    if ($source > $last) {
                        break;
                    }

                    $sourceKey = str_pad(strtolower(dechex($source)), $sourceWidth, '0', STR_PAD_LEFT);
                    $map[$sourceKey] = $this->decodeCMapUnicodeHex($target);
                    $source++;
                }
            }
        }

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

        return $this->decodePdfName(substr($operand, 1));
    }

    private function decodePdfName(string $name): string
    {
        return preg_replace_callback('/#([\da-fA-F]{2})/', static function (array $match): string {
            return chr(hexdec($match[1]));
        }, $name) ?? $name;
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
            foreach ($this->textArrayElements($operand) as $element) {
                if ($element['type'] === 'text') {
                    $text .= $this->decodeTextOperand((string) $element['value'], $toUnicodeMap);
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

        return $this->decodePdfStringBytes($decoded);
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
            $sourceLength = $this->toUnicodeSourceLength(
                $keyLengths,
                $length - $offset,
                $toUnicodeMap['codeSpaceRanges'] ?? [],
                $normalized,
                $offset
            );
            $key = substr($normalized, $offset, $sourceLength);
            $text .= array_key_exists($key, $mappings)
                ? $mappings[$key]
                : $this->decodeUnmappedToUnicodeSource($key);
            $offset += $sourceLength;
        }

        return $text;
    }

    /**
     * @param list<int> $keyLengths
     * @param list<array{start: int, end: int, width: int}> $codeSpaceRanges
     */
    private function toUnicodeSourceLength(
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
        rsort($usableLengths, SORT_NUMERIC);

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

        return $this->decodePdfStringBytes($bytes);
    }

    private function decodePdfStringBytes(string $bytes): string
    {
        $prefix = strtolower(bin2hex(substr($bytes, 0, 2)));
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

        return preg_replace_callback('/\\\\([0-7]{1,3}|.)/s', static function (array $match): string {
            return match ($match[1]) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\x0c",
                '(' => '(',
                ')' => ')',
                '\\' => '\\',
                default => preg_match('/^[0-7]+$/', $match[1]) === 1 ? chr(octdec($match[1]) & 0xff) : $match[1],
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
