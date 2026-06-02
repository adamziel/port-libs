<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use DOMDocument;
use DOMElement;

final class PdfMetadataExtractor
{
    private const NS_DC = 'http://purl.org/dc/elements/1.1/';
    private const NS_PDF = 'http://ns.adobe.com/pdf/1.3/';
    private const NS_RDF = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    private const NS_XMP = 'http://ns.adobe.com/xap/1.0/';
    private const NS_XML = 'http://www.w3.org/XML/1998/namespace';

    /**
     * Native metadata boundary for PDF Catalog /Metadata XMP streams plus the
     * trailer /Info dictionary used by pdfium-backed document metadata flows.
     *
     * @return array{
     *     source: list<string>,
     *     xmp: array<string, mixed>,
     *     info: array<string, string>,
     *     title?: string,
     *     authors?: list<string>,
     *     description?: string,
     *     keywords?: list<string>,
     *     creator_tool?: string,
     *     producer?: string,
     *     created_at?: string,
     *     modified_at?: string,
     *     metadata_date?: string
     * }
     */
    public function extractDocumentMetadata(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $xmp = $this->extractXmpMetadata($pdfBytes, $objects);
        $info = $this->extractInfoMetadata($pdfBytes, $objects);

        return $this->mergedMetadata($xmp, $info);
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function extractXmpMetadata(string $pdfBytes, array $objects): array
    {
        $catalog = $this->catalogObjectBody($pdfBytes, $objects);
        if ($catalog === null || preg_match('/\/Metadata\s+(\d+)\s+\d+\s+R\b/s', $catalog, $match) !== 1) {
            return [];
        }

        $objectNumber = (int) $match[1];
        if (!isset($objects[$objectNumber])) {
            return [];
        }

        $stream = $this->decodeStreamObject($objects[$objectNumber], $objects);
        if ($stream === null) {
            return [];
        }

        return $this->parseXmpPacket($stream);
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, string>
     */
    private function extractInfoMetadata(string $pdfBytes, array $objects): array
    {
        $trailer = $this->trailerDictionaryBody($pdfBytes);
        if ($trailer === null || preg_match('/\/Info\s+(\d+)\s+\d+\s+R\b/s', $trailer, $match) !== 1) {
            return [];
        }

        $objectNumber = (int) $match[1];
        if (!isset($objects[$objectNumber])) {
            return [];
        }

        $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
        if ($dictionary === null) {
            return [];
        }

        $fields = [];
        foreach (['Title', 'Author', 'Subject', 'Keywords', 'Creator', 'Producer', 'CreationDate', 'ModDate'] as $key) {
            $value = $this->dictionaryStringValue($dictionary, $key);
            if ($value !== null) {
                $fields[$key] = $value;
            }
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $xmp
     * @param array<string, string> $info
     * @return array<string, mixed>
     */
    private function mergedMetadata(array $xmp, array $info): array
    {
        $result = [
            'source' => [],
            'xmp' => $xmp,
            'info' => $info,
        ];

        if ($xmp !== []) {
            $result['source'][] = 'xmp';
        }
        if ($info !== []) {
            $result['source'][] = 'info';
        }

        foreach (['title', 'description', 'creator_tool', 'producer', 'created_at', 'modified_at', 'metadata_date'] as $field) {
            $value = $xmp[$field] ?? $this->infoField($info, $field);
            if (is_string($value) && $value !== '') {
                $result[$field] = $value;
            }
        }

        $authors = $xmp['authors'] ?? $this->authorsFromInfo($info);
        if (is_array($authors) && $authors !== []) {
            $result['authors'] = array_values($authors);
        }

        $keywords = $xmp['keywords'] ?? $this->keywordsFromInfo($info);
        if (is_array($keywords) && $keywords !== []) {
            $result['keywords'] = array_values($keywords);
        }

        return $result;
    }

    /**
     * @param array<string, string> $info
     */
    private function infoField(array $info, string $field): ?string
    {
        return match ($field) {
            'title' => $info['Title'] ?? null,
            'description' => $info['Subject'] ?? null,
            'creator_tool' => $info['Creator'] ?? null,
            'producer' => $info['Producer'] ?? null,
            'created_at' => $info['CreationDate'] ?? null,
            'modified_at' => $info['ModDate'] ?? null,
            default => null,
        };
    }

    /**
     * @param array<string, string> $info
     * @return list<string>
     */
    private function authorsFromInfo(array $info): array
    {
        $author = $info['Author'] ?? '';
        if ($author === '') {
            return [];
        }

        return $this->cleanList(preg_split('/\s*;\s*/', $author) ?: []);
    }

    /**
     * @param array<string, string> $info
     * @return list<string>
     */
    private function keywordsFromInfo(array $info): array
    {
        $keywords = $info['Keywords'] ?? '';
        if ($keywords === '') {
            return [];
        }

        return $this->splitKeywords($keywords);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseXmpPacket(string $xml): array
    {
        $xml = trim($xml);
        if ($xml === '' || !str_contains($xml, '<')) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return [];
        }

        $metadata = [];

        foreach ([
            'title' => [self::NS_DC, 'title', true],
            'description' => [self::NS_DC, 'description', true],
            'creator_tool' => [self::NS_XMP, 'CreatorTool', false],
            'producer' => [self::NS_PDF, 'Producer', false],
            'created_at' => [self::NS_XMP, 'CreateDate', false],
            'modified_at' => [self::NS_XMP, 'ModifyDate', false],
            'metadata_date' => [self::NS_XMP, 'MetadataDate', false],
        ] as $field => $spec) {
            [$namespace, $localName, $preferAlt] = $spec;
            $value = $this->xmpSingleValue($document, $namespace, $localName, $preferAlt);
            if ($value !== null) {
                $metadata[$field] = $value;
            }
        }

        $authors = $this->xmpListValues($document, self::NS_DC, 'creator');
        if ($authors !== []) {
            $metadata['authors'] = $authors;
        }

        $keywords = $this->xmpListValues($document, self::NS_DC, 'subject');
        if ($keywords === []) {
            $keywordsText = $this->xmpSingleValue($document, self::NS_PDF, 'Keywords', false);
            $keywords = $keywordsText === null ? [] : $this->splitKeywords($keywordsText);
        }
        if ($keywords !== []) {
            $metadata['keywords'] = $keywords;
        }

        return $metadata;
    }

    private function xmpSingleValue(DOMDocument $document, string $namespace, string $localName, bool $preferAlt): ?string
    {
        $elements = $document->getElementsByTagNameNS($namespace, $localName);
        if ($elements->length > 0) {
            $element = $elements->item(0);
            if ($element instanceof DOMElement) {
                $value = $preferAlt ? $this->preferredAltText($element) : $this->cleanText($element->textContent);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        foreach ($document->getElementsByTagNameNS(self::NS_RDF, 'Description') as $description) {
            if (!$description instanceof DOMElement || !$description->hasAttributeNS($namespace, $localName)) {
                continue;
            }

            $value = $this->cleanText($description->getAttributeNS($namespace, $localName));
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function xmpListValues(DOMDocument $document, string $namespace, string $localName): array
    {
        $elements = $document->getElementsByTagNameNS($namespace, $localName);
        if ($elements->length > 0) {
            $element = $elements->item(0);
            if ($element instanceof DOMElement) {
                $values = [];
                foreach ($element->getElementsByTagNameNS(self::NS_RDF, 'li') as $item) {
                    $value = $this->cleanText($item->textContent);
                    if ($value !== null) {
                        $values[] = $value;
                    }
                }

                if ($values !== []) {
                    return $this->cleanList($values);
                }

                $value = $this->cleanText($element->textContent);
                return $value === null ? [] : [$value];
            }
        }

        foreach ($document->getElementsByTagNameNS(self::NS_RDF, 'Description') as $description) {
            if (!$description instanceof DOMElement || !$description->hasAttributeNS($namespace, $localName)) {
                continue;
            }

            $value = $this->cleanText($description->getAttributeNS($namespace, $localName));
            return $value === null ? [] : $this->splitKeywords($value);
        }

        return [];
    }

    private function preferredAltText(DOMElement $element): ?string
    {
        $first = null;
        foreach ($element->getElementsByTagNameNS(self::NS_RDF, 'li') as $item) {
            if (!$item instanceof DOMElement) {
                continue;
            }

            $value = $this->cleanText($item->textContent);
            if ($value === null) {
                continue;
            }

            $first ??= $value;
            if ($item->getAttributeNS(self::NS_XML, 'lang') === 'x-default') {
                return $value;
            }
        }

        return $first ?? $this->cleanText($element->textContent);
    }

    /**
     * @return array<int, string>
     */
    private function pdfObjects(string $pdfBytes): array
    {
        if (!preg_match_all('/(\d+)\s+\d+\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $objects = [];
        foreach ($matches as $match) {
            $objects[(int) $match[1]] = $match[2];
        }

        return $objects;
    }

    /**
     * @param array<int, string> $objects
     */
    private function catalogObjectBody(string $pdfBytes, array $objects): ?string
    {
        $trailer = $this->trailerDictionaryBody($pdfBytes);
        if ($trailer !== null && preg_match('/\/Root\s+(\d+)\s+\d+\s+R\b/s', $trailer, $match) === 1) {
            $objectNumber = (int) $match[1];
            if (isset($objects[$objectNumber])) {
                return $objects[$objectNumber];
            }
        }

        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/Catalog\b/', $body) === 1) {
                return $body;
            }
        }

        return null;
    }

    private function trailerDictionaryBody(string $pdfBytes): ?string
    {
        $body = null;
        $offset = 0;
        while (($position = strpos($pdfBytes, 'trailer', $offset)) !== false) {
            $dictionaryOffset = strpos($pdfBytes, '<<', $position);
            if ($dictionaryOffset === false) {
                break;
            }

            $candidate = $this->readPdfDictionaryAt($pdfBytes, $dictionaryOffset);
            if ($candidate !== null) {
                $body = $candidate;
            }
            $offset = $position + 7;
        }

        return $body;
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
    private function decodeStream(string $dict, string $stream, array $objects): ?string
    {
        foreach ($this->streamFilters($dict, $objects) as $filter) {
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
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function streamFilters(string $dict, array $objects): array
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
     * @param array<int, string> $objects
     * @return list<string>
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

    private function dictionaryObjectBody(string $objectBody): ?string
    {
        $offset = strpos($objectBody, '<<');
        return $offset === false ? null : $this->readPdfDictionaryAt($objectBody, $offset);
    }

    private function readPdfDictionaryAt(string $value, int $offset): ?string
    {
        if (substr($value, $offset, 2) !== '<<') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 2;
        for ($index = $offset, $length = strlen($value); $index < $length - 1; $index++) {
            $pair = substr($value, $index, 2);
            if ($pair === '<<') {
                $depth++;
                $index++;
                continue;
            }

            if ($pair !== '>>') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return substr($value, $bodyStart, $index - $bodyStart);
            }
            $index++;
        }

        return null;
    }

    private function dictionaryStringValue(string $dictionary, string $key): ?string
    {
        if (preg_match('/\/' . preg_quote($key, '/') . '\b/s', $dictionary, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $offset = $match[0][1] + strlen($match[0][0]);
        while ($offset < strlen($dictionary) && ctype_space($dictionary[$offset])) {
            $offset++;
        }

        if ($offset >= strlen($dictionary)) {
            return null;
        }

        return match ($dictionary[$offset]) {
            '(' => $this->literalStringValueAt($dictionary, $offset),
            '<' => ($offset + 1 < strlen($dictionary) && $dictionary[$offset + 1] !== '<')
                ? $this->hexStringValueAt($dictionary, $offset)
                : null,
            '/' => $this->nameValueAt($dictionary, $offset),
            default => $this->plainValueAt($dictionary, $offset),
        };
    }

    private function literalStringValueAt(string $value, int $offset): ?string
    {
        $depth = 0;
        $body = '';
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '\\') {
                if ($index + 1 < $length) {
                    $body .= $char . $value[$index + 1];
                    $index++;
                }
                continue;
            }

            if ($char === '(') {
                if ($depth > 0) {
                    $body .= $char;
                }
                $depth++;
                continue;
            }

            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $this->decodePdfStringBytes($this->decodeLiteralEscapes($body));
                }
                $body .= $char;
                continue;
            }

            if ($depth > 0) {
                $body .= $char;
            }
        }

        return null;
    }

    private function hexStringValueAt(string $value, int $offset): ?string
    {
        $end = strpos($value, '>', $offset + 1);
        if ($end === false) {
            return null;
        }

        $hex = preg_replace('/\s+/', '', substr($value, $offset + 1, $end - $offset - 1));
        if ($hex === null || preg_match('/^[\da-fA-F]*$/', $hex) !== 1) {
            return null;
        }
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $bytes = hex2bin($hex);
        return $bytes === false ? null : $this->decodePdfStringBytes($bytes);
    }

    private function nameValueAt(string $value, int $offset): ?string
    {
        if (preg_match('/\/([^\s\[\]()<>{}\/%]+)/A', substr($value, $offset), $match) !== 1) {
            return null;
        }

        return $this->decodePdfName($match[1]);
    }

    private function plainValueAt(string $value, int $offset): ?string
    {
        if (preg_match('/([^\s\[\]()<>{}\/%]+)/A', substr($value, $offset), $match) !== 1) {
            return null;
        }

        return $this->cleanText($match[1]);
    }

    private function decodeLiteralEscapes(string $value): string
    {
        $out = '';
        for ($index = 0, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char !== '\\') {
                $out .= $char;
                continue;
            }

            if ($index + 1 >= $length) {
                continue;
            }

            $next = $value[++$index];
            if ($next === "\r" || $next === "\n") {
                if ($next === "\r" && $index + 1 < $length && $value[$index + 1] === "\n") {
                    $index++;
                }
                continue;
            }

            if ($next >= '0' && $next <= '7') {
                $octal = $next;
                for ($count = 0; $count < 2 && $index + 1 < $length; $count++) {
                    $candidate = $value[$index + 1];
                    if ($candidate < '0' || $candidate > '7') {
                        break;
                    }
                    $octal .= $candidate;
                    $index++;
                }
                $out .= chr(octdec($octal) & 0xff);
                continue;
            }

            $out .= match ($next) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\x0c",
                default => $next,
            };
        }

        return $out;
    }

    private function decodePdfStringBytes(string $bytes): string
    {
        if (str_starts_with($bytes, "\xfe\xff")) {
            $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $this->cleanText($decoded) ?? '';
        }

        if (str_starts_with($bytes, "\xff\xfe")) {
            $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $this->cleanText($decoded) ?? '';
        }

        return $this->cleanText($bytes) ?? '';
    }

    private function decodePdfName(string $name): string
    {
        return preg_replace_callback('/#([\da-fA-F]{2})/', static fn (array $match): string => chr(hexdec($match[1])), $name) ?? $name;
    }

    private function cleanText(string $value): ?string
    {
        $clean = preg_replace('/\s+/u', ' ', trim($value));
        if ($clean === null || $clean === '') {
            return null;
        }

        return $clean;
    }

    /**
     * @param list<string|mixed> $values
     * @return list<string>
     */
    private function cleanList(array $values): array
    {
        $out = [];
        foreach ($values as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $clean = $this->cleanText((string) $value);
            if ($clean !== null && !in_array($clean, $out, true)) {
                $out[] = $clean;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function splitKeywords(string $keywords): array
    {
        return $this->cleanList(preg_split('/\s*[,;]\s*/', $keywords) ?: []);
    }
}
