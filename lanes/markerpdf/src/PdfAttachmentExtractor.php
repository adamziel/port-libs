<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class PdfAttachmentExtractor
{
    /**
     * Native PDF attachment preflight for embedded file streams referenced by
     * document EmbeddedFiles name trees or page FileAttachment annotations.
     *
     * @return list<array<string, mixed>>
     */
    public function extractAttachments(string $pdfBytes): array
    {
        $this->assertPdfBytes($pdfBytes);
        $objects = $this->pdfObjects($pdfBytes);
        if ($objects === []) {
            return [];
        }

        $attachments = [];
        foreach ($this->embeddedFilesNameTreeEntries($objects) as $entry) {
            $attachment = $this->attachmentFromFileSpecValue(
                $entry['fileSpec'],
                $objects,
                'embedded-files-name-tree',
                [
                    'name_key' => $entry['name'],
                ]
            );
            if ($attachment !== null) {
                $attachments[] = $attachment;
            }
        }

        foreach ($this->fileAttachmentAnnotationEntries($objects) as $entry) {
            $attachment = $this->attachmentFromFileSpecValue(
                $entry['fileSpec'],
                $objects,
                'file-attachment-annotation',
                [
                    'page_number' => $entry['pageNumber'],
                    'page_object_id' => $entry['pageObjectId'],
                    'annotation_object_id' => $entry['annotationObjectId'],
                    'annotation_contents' => $entry['contents'],
                    'annotation_rect' => $entry['rect'],
                ]
            );
            if ($attachment !== null) {
                $attachments[] = $attachment;
            }
        }

        return $attachments;
    }

    /**
     * @return array{attachment_count: int, total_bytes: int, filenames: list<string>, attachments: list<array<string, mixed>>, executes_python_or_models: false, executes_external_pdf_tools: false}
     */
    public function attachmentSummary(string $pdfBytes): array
    {
        $attachments = $this->extractAttachments($pdfBytes);
        $summaryAttachments = [];
        $totalBytes = 0;
        foreach ($attachments as $attachment) {
            $totalBytes += (int) $attachment['byte_length'];
            $withoutBytes = $attachment;
            unset($withoutBytes['bytes']);
            $summaryAttachments[] = $withoutBytes;
        }

        return [
            'attachment_count' => count($attachments),
            'total_bytes' => $totalBytes,
            'filenames' => array_values(array_map(static fn (array $attachment): string => (string) $attachment['filename'], $attachments)),
            'attachments' => $summaryAttachments,
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return list<array{name: string, fileSpec: mixed}>
     */
    private function embeddedFilesNameTreeEntries(array $objects): array
    {
        $entries = [];
        foreach ($objects as $object) {
            $dict = $this->dict($object['value']);
            if ($dict === null || $this->nameValue($dict['Type'] ?? null) !== 'Catalog') {
                continue;
            }

            $names = $this->dict($this->resolveValue($dict['Names'] ?? null, $objects));
            if ($names === null || !array_key_exists('EmbeddedFiles', $names)) {
                continue;
            }

            foreach ($this->nameTreeEntries($names['EmbeddedFiles'], $objects) as $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @param list<int> $seen
     * @return list<array{name: string, fileSpec: mixed}>
     */
    private function nameTreeEntries(mixed $value, array $objects, array $seen = []): array
    {
        $objectId = $this->refObjectId($value);
        if ($objectId !== null) {
            if (in_array($objectId, $seen, true) || !isset($objects[$objectId])) {
                return [];
            }
            $seen[] = $objectId;
            $value = $objects[$objectId]['value'];
        }

        $dict = $this->dict($value);
        if ($dict === null) {
            return [];
        }

        $entries = [];
        $names = $this->arrayValue($dict['Names'] ?? null);
        if ($names !== null) {
            for ($index = 0, $count = count($names); $index + 1 < $count; $index += 2) {
                $name = $this->stringValue($names[$index]);
                if ($name === null) {
                    continue;
                }

                $entries[] = [
                    'name' => $name,
                    'fileSpec' => $names[$index + 1],
                ];
            }
        }

        $kids = $this->arrayValue($dict['Kids'] ?? null);
        if ($kids !== null) {
            foreach ($kids as $kid) {
                foreach ($this->nameTreeEntries($kid, $objects, $seen) as $entry) {
                    $entries[] = $entry;
                }
            }
        }

        return $entries;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return list<array{pageNumber: int, pageObjectId: int, annotationObjectId: int|null, contents: string|null, rect: list<float>, fileSpec: mixed}>
     */
    private function fileAttachmentAnnotationEntries(array $objects): array
    {
        $entries = [];
        foreach ($this->pageObjectIds($objects) as $pageIndex => $pageObjectId) {
            if (!isset($objects[$pageObjectId])) {
                continue;
            }

            $page = $this->dict($objects[$pageObjectId]['value']);
            if ($page === null) {
                continue;
            }

            foreach ($this->annotationValues($page['Annots'] ?? null, $objects) as $annotation) {
                $dict = $this->dict($annotation['value']);
                if ($dict === null || $this->nameValue($dict['Subtype'] ?? null) !== 'FileAttachment') {
                    continue;
                }

                $entries[] = [
                    'pageNumber' => $pageIndex + 1,
                    'pageObjectId' => $pageObjectId,
                    'annotationObjectId' => $annotation['objectId'],
                    'contents' => $this->stringValue($dict['Contents'] ?? null),
                    'rect' => $this->numberArray($dict['Rect'] ?? null),
                    'fileSpec' => $dict['FS'] ?? null,
                ];
            }
        }

        return $entries;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return list<int>
     */
    private function pageObjectIds(array $objects): array
    {
        foreach ($objects as $object) {
            $dict = $this->dict($object['value']);
            if ($dict === null || $this->nameValue($dict['Type'] ?? null) !== 'Catalog') {
                continue;
            }

            $pagesId = $this->refObjectId($dict['Pages'] ?? null);
            if ($pagesId !== null) {
                $pages = $this->collectPageObjectIds($pagesId, $objects);
                if ($pages !== []) {
                    return $pages;
                }
            }
        }

        $pages = [];
        foreach ($objects as $objectId => $object) {
            $dict = $this->dict($object['value']);
            if ($dict !== null && $this->nameValue($dict['Type'] ?? null) === 'Page') {
                $pages[] = $objectId;
            }
        }

        return $pages;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @param list<int> $seen
     * @return list<int>
     */
    private function collectPageObjectIds(int $objectId, array $objects, array $seen = []): array
    {
        if (in_array($objectId, $seen, true) || !isset($objects[$objectId])) {
            return [];
        }

        $seen[] = $objectId;
        $dict = $this->dict($objects[$objectId]['value']);
        if ($dict === null) {
            return [];
        }

        $type = $this->nameValue($dict['Type'] ?? null);
        if ($type === 'Page') {
            return [$objectId];
        }
        if ($type !== 'Pages') {
            return [];
        }

        $pages = [];
        $kids = $this->arrayValue($dict['Kids'] ?? null) ?? [];
        foreach ($kids as $kid) {
            $kidId = $this->refObjectId($kid);
            if ($kidId === null) {
                continue;
            }
            foreach ($this->collectPageObjectIds($kidId, $objects, $seen) as $pageId) {
                $pages[] = $pageId;
            }
        }

        return $pages;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return list<array{objectId: int|null, value: mixed}>
     */
    private function annotationValues(mixed $annots, array $objects): array
    {
        $annots = $this->resolveValue($annots, $objects);
        $values = $this->arrayValue($annots);
        if ($values === null) {
            return [];
        }

        $annotations = [];
        foreach ($values as $value) {
            $objectId = $this->refObjectId($value);
            $annotations[] = [
                'objectId' => $objectId,
                'value' => $objectId !== null && isset($objects[$objectId]) ? $objects[$objectId]['value'] : $value,
            ];
        }

        return $annotations;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @param array<string, mixed> $context
     * @return array<string, mixed>|null
     */
    private function attachmentFromFileSpecValue(
        mixed $fileSpecValue,
        array $objects,
        string $source,
        array $context
    ): ?array {
        $fileSpecObjectId = $this->refObjectId($fileSpecValue);
        $fileSpec = $this->dict($this->resolveValue($fileSpecValue, $objects));
        if ($fileSpec === null) {
            return null;
        }

        $streamObjectId = $this->embeddedFileStreamObjectId($fileSpec['EF'] ?? null, $objects);
        if ($streamObjectId === null || !isset($objects[$streamObjectId])) {
            return null;
        }

        $streamObject = $objects[$streamObjectId];
        if ($streamObject['stream'] === null) {
            return null;
        }

        $bytes = $this->decodedStreamBytes($streamObject, $objects);
        if ($bytes === null) {
            return null;
        }

        $streamDict = $this->dict($streamObject['value']) ?? [];
        $params = $this->dict($streamDict['Params'] ?? null) ?? [];
        $nameKey = isset($context['name_key']) && is_string($context['name_key']) ? $context['name_key'] : null;
        $filename = $this->stringValue($fileSpec['UF'] ?? null)
            ?? $this->stringValue($fileSpec['F'] ?? null)
            ?? $nameKey
            ?? 'attachment-' . $streamObjectId;

        return [
            ...$context,
            'source' => $source,
            'file_spec_object_id' => $fileSpecObjectId,
            'stream_object_id' => $streamObjectId,
            'filename' => $filename,
            'description' => $this->stringValue($fileSpec['Desc'] ?? null),
            'content_type' => $this->nameValue($streamDict['Subtype'] ?? null),
            'declared_size' => $this->intValue($params['Size'] ?? null),
            'byte_length' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'checksum_hex' => $this->stringBytesHex($params['CheckSum'] ?? null),
            'created_at' => $this->stringValue($params['CreationDate'] ?? null),
            'modified_at' => $this->stringValue($params['ModDate'] ?? null),
            'bytes' => $bytes,
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     */
    private function embeddedFileStreamObjectId(mixed $efValue, array $objects): ?int
    {
        $ef = $this->dict($this->resolveValue($efValue, $objects));
        if ($ef === null) {
            return null;
        }

        foreach (['UF', 'F', 'DOS', 'Mac', 'Unix'] as $key) {
            $objectId = $this->refObjectId($ef[$key] ?? null);
            if ($objectId !== null) {
                return $objectId;
            }
        }

        foreach ($ef as $value) {
            $objectId = $this->refObjectId($value);
            if ($objectId !== null) {
                return $objectId;
            }
        }

        return null;
    }

    /**
     * @param array{generation: int, body: string, value: mixed, stream: string|null} $streamObject
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     */
    private function decodedStreamBytes(array $streamObject, array $objects): ?string
    {
        if ($streamObject['stream'] === null) {
            return null;
        }

        $bytes = $streamObject['stream'];
        $dict = $this->dict($streamObject['value']) ?? [];
        foreach ($this->filterNames($dict['Filter'] ?? null, $objects) as $filter) {
            $decoded = match ($filter) {
                'FlateDecode', 'Fl' => $this->decodeFlateStream($bytes),
                'ASCIIHexDecode', 'AHx' => $this->decodeAsciiHexStream($bytes),
                'RunLengthDecode', 'RL' => $this->decodeRunLengthStream($bytes),
                default => null,
            };

            if ($decoded === null) {
                return null;
            }
            $bytes = $decoded;
        }

        return $bytes;
    }

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     * @return list<string>
     */
    private function filterNames(mixed $filterValue, array $objects): array
    {
        $filterValue = $this->resolveValue($filterValue, $objects);
        $name = $this->nameValue($filterValue);
        if ($name !== null) {
            return [$name];
        }

        $array = $this->arrayValue($filterValue);
        if ($array === null) {
            return [];
        }

        $filters = [];
        foreach ($array as $value) {
            $filter = $this->nameValue($this->resolveValue($value, $objects));
            if ($filter !== null) {
                $filters[] = $filter;
            }
        }

        return $filters;
    }

    private function decodeFlateStream(string $bytes): ?string
    {
        $decoded = @gzuncompress($bytes);
        if ($decoded === false) {
            $decoded = @gzinflate($bytes);
        }
        if ($decoded === false) {
            $decoded = @gzdecode($bytes);
        }

        return $decoded === false ? null : $decoded;
    }

    private function decodeAsciiHexStream(string $bytes): ?string
    {
        $body = strstr($bytes, '>', true);
        if ($body === false) {
            $body = $bytes;
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

    private function decodeRunLengthStream(string $bytes): ?string
    {
        $out = '';
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $control = ord($bytes[$offset]);
            if ($control === 128) {
                return $out;
            }

            if ($control <= 127) {
                $copyLength = $control + 1;
                if ($offset + $copyLength >= $length) {
                    return null;
                }
                $out .= substr($bytes, $offset + 1, $copyLength);
                $offset += $copyLength;
                continue;
            }

            if ($offset + 1 >= $length) {
                return null;
            }
            $out .= str_repeat($bytes[$offset + 1], 257 - $control);
            $offset++;
        }

        return null;
    }

    /**
     * @return array<int, array{generation: int, body: string, value: mixed, stream: string|null}>
     */
    private function pdfObjects(string $pdfBytes): array
    {
        if (!preg_match_all('/(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $objects = [];
        foreach ($matches as $match) {
            $body = $match[3];
            $index = 0;
            $value = $this->parseValue($body, $index);
            $stream = $this->streamBytesFromBody($body, $index, $value);
            $objects[(int) $match[1]] = [
                'generation' => (int) $match[2],
                'body' => $body,
                'value' => $value,
                'stream' => $stream,
            ];
        }
        ksort($objects, SORT_NUMERIC);

        return $objects;
    }

    private function streamBytesFromBody(string $body, int $index, mixed $value): ?string
    {
        $dict = $this->dict($value);
        if ($dict === null) {
            return null;
        }

        $this->skipWhitespaceAndComments($body, $index);
        if (!str_starts_with(substr($body, $index), 'stream')) {
            return null;
        }

        $index += strlen('stream');
        if (substr($body, $index, 2) === "\r\n") {
            $index += 2;
        } elseif (($body[$index] ?? '') === "\n" || ($body[$index] ?? '') === "\r") {
            $index++;
        }

        $end = strpos($body, 'endstream', $index);
        if ($end === false) {
            return null;
        }

        $stream = substr($body, $index, $end - $index);
        $length = $this->intValue($dict['Length'] ?? null);
        if ($length !== null && $length >= 0 && $length <= strlen($stream)) {
            return substr($stream, 0, $length);
        }

        return preg_replace("/\r\n$|\n$|\r$/", '', $stream) ?? $stream;
    }

    private function parseValue(string $text, int &$index): mixed
    {
        $this->skipWhitespaceAndComments($text, $index);
        $length = strlen($text);
        if ($index >= $length) {
            return null;
        }

        $char = $text[$index];
        if (substr($text, $index, 2) === '<<') {
            return $this->parseDictionary($text, $index);
        }
        if ($char === '[') {
            return $this->parseArray($text, $index);
        }
        if ($char === '(') {
            return $this->stringToken($this->parseLiteralStringBytes($text, $index));
        }
        if ($char === '<') {
            return $this->stringToken($this->parseHexStringBytes($text, $index));
        }
        if ($char === '/') {
            return [
                '__kind' => 'name',
                'value' => $this->parseName($text, $index),
            ];
        }

        return $this->parseBareTokenValue($text, $index);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseDictionary(string $text, int &$index): array
    {
        $index += 2;
        $dict = [];
        while ($index < strlen($text)) {
            $this->skipWhitespaceAndComments($text, $index);
            if (substr($text, $index, 2) === '>>') {
                $index += 2;
                break;
            }
            if (($text[$index] ?? '') !== '/') {
                $index++;
                continue;
            }

            $key = $this->parseName($text, $index);
            $dict[$key] = $this->parseValue($text, $index);
        }

        return $dict;
    }

    /**
     * @return list<mixed>
     */
    private function parseArray(string $text, int &$index): array
    {
        $index++;
        $items = [];
        while ($index < strlen($text)) {
            $this->skipWhitespaceAndComments($text, $index);
            if (($text[$index] ?? '') === ']') {
                $index++;
                break;
            }
            $items[] = $this->parseValue($text, $index);
        }

        return $items;
    }

    private function parseBareTokenValue(string $text, int &$index): mixed
    {
        $token = $this->readBareToken($text, $index);
        if ($token === '') {
            $index++;
            return null;
        }

        if ($token === 'true') {
            return true;
        }
        if ($token === 'false') {
            return false;
        }
        if ($token === 'null') {
            return null;
        }

        if ($this->isNumericToken($token)) {
            $afterFirst = $index;
            $probe = $index;
            $this->skipWhitespaceAndComments($text, $probe);
            $second = $this->readBareToken($text, $probe);
            if ($this->isIntegerToken($token) && $this->isIntegerToken($second)) {
                $this->skipWhitespaceAndComments($text, $probe);
                if (($text[$probe] ?? '') === 'R') {
                    $index = $probe + 1;
                    return [
                        '__kind' => 'ref',
                        'object' => (int) $token,
                        'generation' => (int) $second,
                    ];
                }
            }

            $index = $afterFirst;
            return str_contains($token, '.') ? (float) $token : (int) $token;
        }

        return $token;
    }

    private function readBareToken(string $text, int &$index): string
    {
        $start = $index;
        $length = strlen($text);
        while ($index < $length && !$this->isDelimiter($text[$index])) {
            $index++;
        }

        return substr($text, $start, $index - $start);
    }

    private function parseName(string $text, int &$index): string
    {
        $index++;
        $start = $index;
        $length = strlen($text);
        while ($index < $length && !$this->isDelimiter($text[$index])) {
            $index++;
        }

        return $this->decodePdfName(substr($text, $start, $index - $start));
    }

    private function parseLiteralStringBytes(string $text, int &$index): string
    {
        $index++;
        $depth = 1;
        $out = '';
        $length = strlen($text);

        while ($index < $length && $depth > 0) {
            $char = $text[$index];
            if ($char === '\\') {
                $index++;
                if ($index >= $length) {
                    break;
                }
                $escaped = $text[$index];
                if ($escaped === "\r" || $escaped === "\n") {
                    if ($escaped === "\r" && ($text[$index + 1] ?? '') === "\n") {
                        $index++;
                    }
                    $index++;
                    continue;
                }
                if (preg_match('/[0-7]/', $escaped) === 1) {
                    $octal = $escaped;
                    for ($count = 1; $count < 3 && preg_match('/[0-7]/', $text[$index + 1] ?? '') === 1; $count++) {
                        $index++;
                        $octal .= $text[$index];
                    }
                    $out .= chr(octdec($octal) & 0xff);
                    $index++;
                    continue;
                }

                $out .= match ($escaped) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'b' => "\x08",
                    'f' => "\x0c",
                    '(' => '(',
                    ')' => ')',
                    '\\' => '\\',
                    default => $escaped,
                };
                $index++;
                continue;
            }

            if ($char === '(') {
                $depth++;
                $out .= $char;
                $index++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $index++;
                    break;
                }
                $out .= $char;
                $index++;
                continue;
            }

            $out .= $char;
            $index++;
        }

        return $out;
    }

    private function parseHexStringBytes(string $text, int &$index): string
    {
        $index++;
        $hex = '';
        $length = strlen($text);
        while ($index < $length && $text[$index] !== '>') {
            if (!ctype_space($text[$index])) {
                $hex .= $text[$index];
            }
            $index++;
        }
        if (($text[$index] ?? '') === '>') {
            $index++;
        }

        if ($hex === '' || preg_match('/^[\da-fA-F]+$/', $hex) !== 1) {
            return '';
        }
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $bytes = hex2bin($hex);
        return $bytes === false ? '' : $bytes;
    }

    /**
     * @return array{__kind: string, value: string, bytes: string}
     */
    private function stringToken(string $bytes): array
    {
        return [
            '__kind' => 'string',
            'value' => $this->decodePdfStringBytes($bytes),
            'bytes' => $bytes,
        ];
    }

    private function decodePdfName(string $name): string
    {
        return preg_replace_callback('/#([\da-fA-F]{2})/', static function (array $match): string {
            return chr(hexdec($match[1]));
        }, $name) ?? $name;
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

    /**
     * @param array<int, array{generation: int, body: string, value: mixed, stream: string|null}> $objects
     */
    private function resolveValue(mixed $value, array $objects): mixed
    {
        $objectId = $this->refObjectId($value);
        if ($objectId !== null && isset($objects[$objectId])) {
            return $objects[$objectId]['value'];
        }

        return $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dict(mixed $value): ?array
    {
        if (!is_array($value) || isset($value['__kind'])) {
            return null;
        }

        return $value;
    }

    /**
     * @return list<mixed>|null
     */
    private function arrayValue(mixed $value): ?array
    {
        if (!is_array($value) || isset($value['__kind'])) {
            return null;
        }
        if (!array_is_list($value)) {
            return null;
        }

        return $value;
    }

    private function refObjectId(mixed $value): ?int
    {
        return is_array($value) && ($value['__kind'] ?? null) === 'ref' ? (int) $value['object'] : null;
    }

    private function nameValue(mixed $value): ?string
    {
        return is_array($value) && ($value['__kind'] ?? null) === 'name' ? (string) $value['value'] : null;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_array($value) && ($value['__kind'] ?? null) === 'string' ? (string) $value['value'] : null;
    }

    private function stringBytesHex(mixed $value): ?string
    {
        return is_array($value) && ($value['__kind'] ?? null) === 'string' ? strtolower(bin2hex((string) $value['bytes'])) : null;
    }

    private function intValue(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    /**
     * @return list<float>
     */
    private function numberArray(mixed $value): array
    {
        $array = $this->arrayValue($value);
        if ($array === null) {
            return [];
        }

        $numbers = [];
        foreach ($array as $item) {
            if (is_int($item) || is_float($item)) {
                $numbers[] = (float) $item;
            }
        }

        return $numbers;
    }

    private function skipWhitespaceAndComments(string $text, int &$index): void
    {
        $length = strlen($text);
        while ($index < $length) {
            if (ctype_space($text[$index])) {
                $index++;
                continue;
            }

            if ($text[$index] === '%') {
                while ($index < $length && !in_array($text[$index], ["\r", "\n"], true)) {
                    $index++;
                }
                continue;
            }

            break;
        }
    }

    private function isDelimiter(string $char): bool
    {
        return ctype_space($char) || str_contains('()<>[]{}/%', $char);
    }

    private function isNumericToken(string $token): bool
    {
        return preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/', $token) === 1;
    }

    private function isIntegerToken(string $token): bool
    {
        return preg_match('/^[+-]?\d+$/', $token) === 1;
    }

    private function assertPdfBytes(string $pdfBytes): void
    {
        if (!str_starts_with(ltrim($pdfBytes), '%PDF-')) {
            throw new InvalidArgumentException('PDF attachment extraction requires PDF bytes.');
        }
    }
}
