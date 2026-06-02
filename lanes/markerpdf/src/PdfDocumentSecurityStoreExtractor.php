<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfDocumentSecurityStoreExtractor
{
    /**
     * Native review boundary for catalog /DSS validation material. This keeps
     * long-term-validation bytes out of import output and does not validate
     * signatures, certificate chains, OCSP responses, or CRLs.
     *
     * @return array<string, mixed>
     */
    public function extract(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $catalog = $this->catalogObjectBody($pdfBytes, $objects);
        if ($catalog === null) {
            return $this->emptyStore();
        }

        $dssValue = $this->valueAfterName($catalog, 'DSS');
        $dss = $dssValue === null ? null : $this->resolvedDictionaryFromValue($dssValue, $objects);
        if ($dss === null) {
            return $this->emptyStore();
        }

        $certs = $this->streamSummariesFromDictionaryArray($dss['body'], ['Certs'], $objects, 'dss_certs');
        $ocsps = $this->streamSummariesFromDictionaryArray($dss['body'], ['OCSPs'], $objects, 'dss_ocsps');
        $crls = $this->streamSummariesFromDictionaryArray($dss['body'], ['CRLs'], $objects, 'dss_crls');
        $vri = $this->vriRows($dss['body'], $objects);
        $streamKeys = [];
        $this->addStreamKeys($streamKeys, $certs['streams']);
        $this->addStreamKeys($streamKeys, $ocsps['streams']);
        $this->addStreamKeys($streamKeys, $crls['streams']);
        foreach ($vri as $row) {
            $this->addStreamKeys($streamKeys, $row['certificates']);
            $this->addStreamKeys($streamKeys, $row['ocsps']);
            $this->addStreamKeys($streamKeys, $row['crls']);
            if (is_array($row['timestamp_token'] ?? null)) {
                $this->addStreamKeys($streamKeys, [$row['timestamp_token']]);
            }
        }

        return [
            'source' => 'catalog_dss_dictionary',
            'present' => true,
            'object_number' => $dss['object'],
            'type' => $this->pdfNameValueAfterName($dss['body'], 'Type'),
            'cert_count' => count($certs['streams']),
            'ocsp_count' => count($ocsps['streams']),
            'crl_count' => count($crls['streams']),
            'vri_count' => count($vri),
            'vri_keys' => array_map(
                static fn (array $row): string => (string) $row['key'],
                $vri
            ),
            'total_validation_stream_count' => count($streamKeys),
            'global_certificates' => $certs['streams'],
            'global_ocsps' => $ocsps['streams'],
            'global_crls' => $crls['streams'],
            'unresolved_cert_refs' => $certs['unresolved_refs'],
            'unresolved_ocsp_refs' => $ocsps['unresolved_refs'],
            'unresolved_crl_refs' => $crls['unresolved_refs'],
            'vri' => $vri,
            'raw_validation_bytes_exposed' => false,
            'executes_signature_validation' => false,
            'executes_revocation_check' => false,
            'executes_trust_chain_validation' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyStore(): array
    {
        return [
            'source' => null,
            'present' => false,
            'object_number' => null,
            'type' => null,
            'cert_count' => 0,
            'ocsp_count' => 0,
            'crl_count' => 0,
            'vri_count' => 0,
            'vri_keys' => [],
            'total_validation_stream_count' => 0,
            'global_certificates' => [],
            'global_ocsps' => [],
            'global_crls' => [],
            'unresolved_cert_refs' => [],
            'unresolved_ocsp_refs' => [],
            'unresolved_crl_refs' => [],
            'vri' => [],
            'raw_validation_bytes_exposed' => false,
            'executes_signature_validation' => false,
            'executes_revocation_check' => false,
            'executes_trust_chain_validation' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function vriRows(string $dssBody, array $objects): array
    {
        $vriValue = $this->valueAfterName($dssBody, 'VRI');
        $vriDictionary = $vriValue === null ? null : $this->resolvedDictionaryFromValue($vriValue, $objects);
        if ($vriDictionary === null) {
            return [];
        }

        $rows = [];
        foreach ($this->dictionaryNameValueMap($vriDictionary['body']) as $key => $value) {
            $entry = $this->resolvedDictionaryFromValue($value, $objects);
            if ($entry === null) {
                continue;
            }

            $certs = $this->streamSummariesFromDictionaryArray($entry['body'], ['Cert', 'Certs'], $objects, 'vri_certs');
            $ocsps = $this->streamSummariesFromDictionaryArray($entry['body'], ['OCSP', 'OCSPs'], $objects, 'vri_ocsps');
            $crls = $this->streamSummariesFromDictionaryArray($entry['body'], ['CRL', 'CRLs'], $objects, 'vri_crls');
            $timestampValue = $this->valueAfterName($entry['body'], 'TS');
            $timestamp = $timestampValue === null ? null : $this->streamSummaryFromValue($timestampValue, $objects, 'vri_timestamp_token');

            $rows[] = [
                'key' => $key,
                'object_number' => $entry['object'],
                'type' => $this->pdfNameValueAfterName($entry['body'], 'Type'),
                'cert_count' => count($certs['streams']),
                'ocsp_count' => count($ocsps['streams']),
                'crl_count' => count($crls['streams']),
                'certificates' => $certs['streams'],
                'ocsps' => $ocsps['streams'],
                'crls' => $crls['streams'],
                'unresolved_cert_refs' => $certs['unresolved_refs'],
                'unresolved_ocsp_refs' => $ocsps['unresolved_refs'],
                'unresolved_crl_refs' => $crls['unresolved_refs'],
                'timestamp_update' => $this->pdfStringValueAfterName($entry['body'], 'TU', $objects),
                'timestamp_token' => $timestamp,
                'raw_validation_bytes_exposed' => false,
                'executes_signature_validation' => false,
                'executes_revocation_check' => false,
            ];
        }

        return $rows;
    }

    /**
     * @param list<string> $names
     * @param array<int, string> $objects
     * @return array{streams: list<array<string, mixed>>, unresolved_refs: list<int>}
     */
    private function streamSummariesFromDictionaryArray(string $dictionaryBody, array $names, array $objects, string $source): array
    {
        foreach ($names as $name) {
            $value = $this->valueAfterName($dictionaryBody, $name);
            if ($value === null) {
                continue;
            }

            return $this->streamSummariesFromArrayValue($value, $objects, $source);
        }

        return ['streams' => [], 'unresolved_refs' => []];
    }

    /**
     * @param array<int, string> $objects
     * @return array{streams: list<array<string, mixed>>, unresolved_refs: list<int>}
     */
    private function streamSummariesFromArrayValue(string $value, array $objects, string $source): array
    {
        $items = $this->arrayItemsFromValue($value, $objects);
        $streams = [];
        $unresolved = [];
        foreach ($items as $item) {
            $summary = $this->streamSummaryFromValue($item, $objects, $source);
            if ($summary !== null) {
                $streams[] = $summary;
                continue;
            }

            $objectNumber = $this->objectNumberFromReference($item);
            if ($objectNumber !== null && !in_array($objectNumber, $unresolved, true)) {
                $unresolved[] = $objectNumber;
            }
        }

        return ['streams' => $streams, 'unresolved_refs' => $unresolved];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function streamSummaryFromValue(string $value, array $objects, string $source): ?array
    {
        $objectNumber = $this->objectNumberFromReference($value);
        $objectBody = $objectNumber === null
            ? trim($this->resolvePdfValue($value, $objects) ?? $value)
            : ($objects[$objectNumber] ?? null);
        if ($objectBody === null || !str_contains($objectBody, 'stream')) {
            return null;
        }

        $dictionary = $this->dictionaryObjectBody($objectBody);
        $stream = $dictionary === null ? null : $this->decodeStreamObject($objectBody, $objects);
        if ($dictionary === null || $stream === null) {
            return null;
        }

        return [
            'source' => $source,
            'object_number' => $objectNumber,
            'declared_length_bytes' => $this->integerValueAfterName($dictionary, 'Length', $objects),
            'length_bytes' => strlen($stream),
            'sha256' => hash('sha256', $stream),
            'filters' => $this->streamObjectFilters($objectBody, $objects),
            'subtype' => $this->pdfNameValueAfterName($dictionary, 'Subtype'),
            'raw_bytes_exposed' => false,
        ];
    }

    /**
     * @param array<string, true> $keys
     * @param list<array<string, mixed>> $streams
     */
    private function addStreamKeys(array &$keys, array $streams): void
    {
        foreach ($streams as $stream) {
            if (is_int($stream['object_number'] ?? null)) {
                $keys['obj:' . $stream['object_number']] = true;
                continue;
            }

            if (is_string($stream['sha256'] ?? null)) {
                $keys['sha256:' . $stream['sha256']] = true;
            }
        }
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
        $rootObject = $this->rootObjectNumber($pdfBytes, $objects);
        if ($rootObject !== null && isset($objects[$rootObject])) {
            return $this->dictionaryObjectBody($objects[$rootObject]);
        }

        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/Catalog\b/', $body) === 1) {
                return $this->dictionaryObjectBody($body);
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function rootObjectNumber(string $pdfBytes, array $objects): ?int
    {
        $trailer = $this->latestTrailerDictionaryBody($pdfBytes);
        if ($trailer !== null && preg_match('/\/Root\s+(\d+)\s+\d+\s+R\b/s', $trailer, $match) === 1) {
            return (int) $match[1];
        }

        $root = null;
        foreach ($objects as $body) {
            $dictionary = $this->dictionaryObjectBody($body);
            if ($dictionary === null || $this->pdfNameValueAfterName($dictionary, 'Type') !== 'XRef') {
                continue;
            }

            $value = $this->valueAfterName($dictionary, 'Root');
            $objectNumber = is_string($value) ? $this->objectNumberFromReference($value) : null;
            if ($objectNumber !== null) {
                $root = $objectNumber;
            }
        }

        return $root;
    }

    private function latestTrailerDictionaryBody(string $pdfBytes): ?string
    {
        if (preg_match_all('/trailer\s*<</s', $pdfBytes, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $last = end($matches[0]);
        if (!is_array($last)) {
            return null;
        }

        $offset = $last[1] + strlen('trailer');
        $this->skipWhitespace($pdfBytes, $offset);

        return $this->readPdfDictionaryAt($pdfBytes, $offset);
    }

    /**
     * @param array<int, string> $objects
     */
    private function resolvePdfValue(string $value, array $objects): ?string
    {
        $trimmed = trim($value);
        $objectNumber = $this->objectNumberFromReference($trimmed);
        if ($objectNumber === null) {
            return $trimmed;
        }

        return $objects[$objectNumber] ?? null;
    }

    /**
     * @param array<int, string> $objects
     * @return array{body: string, object: int|null}|null
     */
    private function resolvedDictionaryFromValue(string $value, array $objects): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '<<')) {
            $body = $this->readPdfDictionaryAt($value, 0);
            return $body === null ? null : ['body' => $body, 'object' => null];
        }

        $objectNumber = $this->objectNumberFromReference($value);
        if ($objectNumber === null) {
            return null;
        }

        $body = $this->dictionaryObjectBody($objects[$objectNumber] ?? '');
        return $body === null ? null : ['body' => $body, 'object' => $objectNumber];
    }

    private function objectNumberFromReference(string $value): ?int
    {
        return preg_match('/^(\d+)\s+\d+\s+R\b/s', trim($value), $match) === 1 ? (int) $match[1] : null;
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function arrayItemsFromValue(string $value, array $objects): array
    {
        $resolved = trim($this->resolvePdfValue($value, $objects) ?? $value);
        $array = $this->readPdfArrayAt($resolved, 0);
        if ($array === null || strlen($array) < 2) {
            return [];
        }

        $body = substr($array, 1, -1);
        $items = [];
        for ($offset = 0, $length = strlen($body); $offset < $length;) {
            $this->skipWhitespace($body, $offset);
            if ($offset >= $length) {
                break;
            }

            $end = null;
            $item = $this->readPdfValueAt($body, $offset, $end);
            if ($item === null || $end === null) {
                $offset++;
                continue;
            }

            $items[] = $item;
            $offset = $end;
        }

        return $items;
    }

    /**
     * @return array<string, string>
     */
    private function dictionaryNameValueMap(string $dictionaryBody): array
    {
        $entries = [];
        for ($offset = 0, $length = strlen($dictionaryBody); $offset < $length;) {
            $this->skipWhitespace($dictionaryBody, $offset);
            if ($offset >= $length) {
                break;
            }

            if ($dictionaryBody[$offset] !== '/') {
                $offset++;
                continue;
            }

            $nameEnd = $this->skipPdfName($dictionaryBody, $offset);
            $name = $this->decodePdfName(substr($dictionaryBody, $offset + 1, $nameEnd - $offset - 1));
            $offset = $nameEnd;
            $end = null;
            $value = $this->readPdfValueAt($dictionaryBody, $offset, $end);
            if ($value === null || $end === null) {
                continue;
            }

            $entries[$name] = $value;
            $offset = $end;
        }

        return $entries;
    }

    private function valueAfterName(string $body, string $name): ?string
    {
        $entries = $this->dictionaryNameValueMap($body);

        return $entries[$name] ?? null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfStringValueAfterName(string $body, string $name, array $objects): ?string
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        return $this->pdfValueToString($value, $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function integerValueAfterName(string $body, string $name, array $objects): ?int
    {
        $value = $this->valueAfterName($body, $name);
        $resolved = $value === null ? null : trim($this->resolvePdfValue($value, $objects) ?? $value);
        if ($resolved === null || preg_match('/^-?\d+$/', $resolved) !== 1) {
            return null;
        }

        return (int) $resolved;
    }

    private function pdfNameValueAfterName(string $body, string $name): ?string
    {
        $value = $this->valueAfterName($body, $name);
        if (!is_string($value) || preg_match('/^\/([^\s\[\]()<>{}\/%]+)/', trim($value), $match) !== 1) {
            return null;
        }

        return $this->decodePdfName($match[1]);
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfValueToString(string $value, array $objects): ?string
    {
        $resolved = trim($this->resolvePdfValue($value, $objects) ?? $value);
        if ($resolved === '') {
            return null;
        }

        if ($resolved[0] === '(') {
            return $this->literalStringValueAt($resolved, 0);
        }

        if ($resolved[0] === '<' && substr($resolved, 0, 2) !== '<<') {
            return $this->hexStringValueAt($resolved, 0);
        }

        if ($resolved[0] === '/') {
            $end = $this->skipPdfName($resolved, 0);
            return $this->decodePdfName(substr($resolved, 1, $end - 1));
        }

        return $this->cleanText($resolved);
    }

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
    private function streamObjectFilters(string $objectBody, array $objects): array
    {
        if (!preg_match('/<<(.*?)>>\s*stream/s', $objectBody, $match)) {
            return [];
        }

        return $this->streamFilters($match[1], $objects);
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function streamFilters(string $dict, array $objects): array
    {
        $filter = $this->valueAfterName($dict, 'Filter');
        if ($filter === null) {
            return [];
        }

        preg_match_all('/\/([^\s\[\]()<>{}\/%]+)|(\d+)\s+\d+\s+R\b/', $filter, $matches, PREG_SET_ORDER);
        $filters = [];
        foreach ($matches as $match) {
            if (($match[1] ?? '') !== '') {
                $filters[] = $this->decodePdfName($match[1]);
                continue;
            }

            $objectNumber = isset($match[2]) ? (int) $match[2] : 0;
            if ($objectNumber > 0 && isset($objects[$objectNumber])) {
                foreach ($this->streamFilters($objects[$objectNumber], $objects) as $nested) {
                    $filters[] = $nested;
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

    private function readPdfValueAt(string $body, int $offset, ?int &$endOffset = null): ?string
    {
        $this->skipWhitespace($body, $offset);
        if ($offset >= strlen($body)) {
            return null;
        }

        if (preg_match('/\G\d+\s+\d+\s+R\b/s', $body, $ref, 0, $offset) === 1) {
            $endOffset = $offset + strlen($ref[0]);
            return $ref[0];
        }

        if ($body[$offset] === '[') {
            $end = null;
            $array = $this->readPdfArrayAt($body, $offset, $end);
            if ($array === null || $end === null) {
                return null;
            }
            $endOffset = $end;
            return $array;
        }

        if (substr($body, $offset, 2) === '<<') {
            $end = null;
            $dictionary = $this->readPdfDictionaryAt($body, $offset, $end);
            if ($dictionary === null || $end === null) {
                return null;
            }
            $endOffset = $end;
            return substr($body, $offset, $end - $offset);
        }

        if ($body[$offset] === '(') {
            $endOffset = $this->skipLiteralString($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '<') {
            $endOffset = $this->skipHexString($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '/') {
            $endOffset = $this->skipPdfName($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        $end = $offset;
        while ($end < strlen($body) && !ctype_space($body[$end]) && !str_contains('[]()<>{}/%', $body[$end])) {
            $end++;
        }

        if ($end === $offset) {
            return null;
        }

        $endOffset = $end;
        return substr($body, $offset, $end - $offset);
    }

    private function readPdfDictionaryAt(string $value, int $offset, ?int &$endOffset = null): ?string
    {
        if (substr($value, $offset, 2) !== '<<') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 2;
        for ($index = $offset, $length = strlen($value); $index < $length - 1; $index++) {
            if ($value[$index] === '(') {
                $index = $this->skipLiteralString($value, $index) - 1;
                continue;
            }
            if ($value[$index] === '<' && substr($value, $index, 2) !== '<<') {
                $index = $this->skipHexString($value, $index) - 1;
                continue;
            }

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
                $endOffset = $index + 2;
                return substr($value, $bodyStart, $index - $bodyStart);
            }
            $index++;
        }

        return null;
    }

    private function readPdfArrayAt(string $value, int $offset, ?int &$endOffset = null): ?string
    {
        if (($value[$offset] ?? '') !== '[') {
            return null;
        }

        $depth = 0;
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            if ($value[$index] === '(') {
                $index = $this->skipLiteralString($value, $index) - 1;
                continue;
            }
            if ($value[$index] === '<') {
                if (substr($value, $index, 2) === '<<') {
                    $index = ($this->readPdfDictionaryAt($value, $index, $dictionaryEnd) === null || $dictionaryEnd === null)
                        ? $index
                        : $dictionaryEnd - 1;
                    continue;
                }
                $index = $this->skipHexString($value, $index) - 1;
                continue;
            }
            if ($value[$index] === '[') {
                $depth++;
                continue;
            }
            if ($value[$index] !== ']') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                $endOffset = $index + 1;
                return substr($value, $offset, $index - $offset + 1);
            }
        }

        return null;
    }

    private function skipWhitespace(string $body, int &$offset): void
    {
        while ($offset < strlen($body)) {
            if (ctype_space($body[$offset])) {
                $offset++;
                continue;
            }

            if ($body[$offset] === '%') {
                while ($offset < strlen($body) && !in_array($body[$offset], ["\r", "\n"], true)) {
                    $offset++;
                }
                continue;
            }

            break;
        }
    }

    private function skipPdfName(string $body, int $offset): int
    {
        $end = $offset + 1;
        while ($end < strlen($body) && !ctype_space($body[$end]) && !str_contains('[]()<>{}/%', $body[$end])) {
            $end++;
        }

        return $end;
    }

    private function skipLiteralString(string $body, int $offset): int
    {
        $depth = 0;
        for ($index = $offset, $length = strlen($body); $index < $length; $index++) {
            $char = $body[$index];
            if ($char === '\\') {
                $index++;
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char !== ')') {
                continue;
            }
            $depth--;
            if ($depth === 0) {
                return $index + 1;
            }
        }

        return strlen($body);
    }

    private function skipHexString(string $body, int $offset): int
    {
        $end = strpos($body, '>', $offset + 1);

        return $end === false ? strlen($body) : $end + 1;
    }

    private function literalStringValueAt(string $value, int $offset): ?string
    {
        $end = $this->skipLiteralString($value, $offset);
        if ($end <= $offset + 1) {
            return null;
        }

        return $this->decodePdfStringBytes($this->decodeLiteralEscapes(substr($value, $offset + 1, $end - $offset - 2)));
    }

    private function hexStringValueAt(string $value, int $offset): ?string
    {
        $end = $this->skipHexString($value, $offset);
        if ($end <= $offset + 2) {
            return '';
        }

        $hex = preg_replace('/\s+/', '', substr($value, $offset + 1, $end - $offset - 2));
        if ($hex === null || preg_match('/^[\da-fA-F]*$/', $hex) !== 1) {
            return null;
        }
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $bytes = hex2bin($hex);
        return $bytes === false ? null : $this->decodePdfStringBytes($bytes);
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
            $decoded = function_exists('iconv') ? @iconv('UTF-16BE', 'UTF-8//IGNORE', substr($bytes, 2)) : false;
            return is_string($decoded) ? $this->cleanText($decoded) : '';
        }

        if (str_starts_with($bytes, "\xff\xfe")) {
            $decoded = function_exists('iconv') ? @iconv('UTF-16LE', 'UTF-8//IGNORE', substr($bytes, 2)) : false;
            return is_string($decoded) ? $this->cleanText($decoded) : '';
        }

        return $this->cleanText($bytes);
    }

    private function decodePdfName(string $name): string
    {
        return preg_replace_callback('/#([0-9A-Fa-f]{2})/', static fn (array $match): string => chr(hexdec($match[1])), $name) ?? $name;
    }

    private function cleanText(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    }
}
