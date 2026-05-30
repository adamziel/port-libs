<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonB
{
    private const NULL = 0;
    private const TRUE = 1;
    private const FALSE = 2;
    private const INT = 3;
    private const INT5 = 4;
    private const FLOAT = 5;
    private const FLOAT5 = 6;
    private const TEXT = 7;
    private const TEXTJ = 8;
    private const TEXT5 = 9;
    private const TEXTRAW = 10;
    private const ARRAY = 11;
    private const OBJECT = 12;
    private const MAX_DEPTH = 1000;

    public static function isJsonB(string $bytes): bool
    {
        try {
            self::decode($bytes);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    public static function isSuperficiallyJsonB(string $bytes): bool
    {
        if ($bytes === '') {
            return false;
        }

        try {
            [$type, $payloadOffset, $payloadLength] = self::readHeader($bytes, 0);
        } catch (\InvalidArgumentException) {
            return false;
        }

        $first = ord($bytes[0]);
        if ($payloadOffset + $payloadLength !== strlen($bytes)) {
            return false;
        }
        if ($type <= self::FALSE && $payloadLength !== 0) {
            return false;
        }
        if ($payloadLength > 7) {
            return true;
        }
        if ($first !== 0x7b && $first !== 0x5b && ($first < 0x30 || $first > 0x39)) {
            return true;
        }

        return self::isStrictlyWellFormed($bytes);
    }

    public static function isStrictlyWellFormed(string $bytes): bool
    {
        if ($bytes === '') {
            return false;
        }

        try {
            self::validateElement($bytes, 0, strlen($bytes), 1);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    public static function errorPosition(string $bytes): int
    {
        if ($bytes === '') {
            return 1;
        }

        return self::validateElementForErrorPosition($bytes, 0, strlen($bytes), 1);
    }

    public static function decode(string $bytes): mixed
    {
        if ($bytes === '') {
            throw new \InvalidArgumentException('SQLite JSONB value is empty');
        }

        [$value, $next] = self::parseElement($bytes, 0, 0);
        if ($next !== strlen($bytes)) {
            throw new \InvalidArgumentException('SQLite JSONB value has trailing bytes');
        }

        return $value;
    }

    public static function encode(mixed $value): string
    {
        return self::encodeElement($value, 0);
    }

    public static function type(string $bytes, string $path = '$'): ?string
    {
        $root = self::validatedRootElement($bytes);
        $element = self::locateElement($bytes, $root, self::parsePath($path));
        if ($element === null) {
            return null;
        }

        return self::typeName($element['type']);
    }

    public static function arrayLength(string $bytes, string $path = '$'): ?int
    {
        $root = self::validatedRootElement($bytes);
        $element = self::locateElement($bytes, $root, self::parsePath($path));
        if ($element === null) {
            return null;
        }
        if ($element['type'] !== self::ARRAY) {
            return 0;
        }

        return self::countArrayElements($bytes, $element['payloadOffset'], self::payloadEnd($element));
    }

    public static function remove(string $bytes, string ...$paths): ?string
    {
        $value = self::decodeForEdit($bytes);
        foreach ($paths as $path) {
            $segments = self::parsePath($path);
            if ($segments === []) {
                return null;
            }

            self::removePath($value, $segments);
        }

        return self::encode($value);
    }

    public static function insert(string $bytes, string $path, mixed $value, mixed ...$pathValuePairs): string
    {
        return self::mutate($bytes, 'insert', $path, $value, $pathValuePairs);
    }

    public static function set(string $bytes, string $path, mixed $value, mixed ...$pathValuePairs): string
    {
        return self::mutate($bytes, 'set', $path, $value, $pathValuePairs);
    }

    public static function replace(string $bytes, string $path, mixed $value, mixed ...$pathValuePairs): string
    {
        return self::mutate($bytes, 'replace', $path, $value, $pathValuePairs);
    }

    public static function arrayInsert(string $bytes, string $path, mixed $value, mixed ...$pathValuePairs): string
    {
        return self::mutate($bytes, 'arrayInsert', $path, $value, $pathValuePairs);
    }

    public static function patch(string $targetBytes, string $patchBytes): string
    {
        $target = self::decodeForEdit($targetBytes);
        $patch = self::decodeForEdit($patchBytes);

        return self::encode(self::mergePatch($target, $patch, 0));
    }

    private static function decodeForEdit(string $bytes): mixed
    {
        if ($bytes === '') {
            throw new \InvalidArgumentException('SQLite JSONB value is empty');
        }

        [$value, $next] = self::parseElement($bytes, 0, 0, true);
        if ($next !== strlen($bytes)) {
            throw new \InvalidArgumentException('SQLite JSONB value has trailing bytes');
        }

        return $value;
    }

    /**
     * @param list<mixed> $pathValuePairs
     */
    private static function mutate(string $bytes, string $operation, string $path, mixed $value, array $pathValuePairs): string
    {
        if (count($pathValuePairs) % 2 !== 0) {
            throw new \InvalidArgumentException('SQLite JSONB mutation requires path/value pairs');
        }

        $document = self::decodeForEdit($bytes);
        self::applyMutation($document, $operation, self::parsePath($path), $value);
        for ($offset = 0; $offset < count($pathValuePairs); $offset += 2) {
            $nextPath = $pathValuePairs[$offset];
            if (!is_string($nextPath)) {
                throw new \InvalidArgumentException('SQLite JSONB mutation path must be a string');
            }

            self::applyMutation($document, $operation, self::parsePath($nextPath), $pathValuePairs[$offset + 1]);
        }

        return self::encode($document);
    }

    private static function mergePatch(mixed $target, mixed $patch, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            throw new \InvalidArgumentException('SQLite JSONB patch exceeds the maximum nesting depth');
        }

        if (!self::isJsonObject($patch)) {
            return $patch;
        }

        if (!self::isJsonObject($target)) {
            $target = new \stdClass();
        }

        foreach (self::jsonObjectMembers($patch) as $name => $patchValue) {
            if ($patchValue === null) {
                self::unsetJsonObjectMember($target, (string) $name);
                continue;
            }

            $targetValue = self::jsonObjectMemberExists($target, (string) $name)
                ? self::jsonObjectMember($target, (string) $name)
                : new \stdClass();
            self::setJsonObjectMember($target, (string) $name, self::mergePatch($targetValue, $patchValue, $depth + 1));
        }

        return self::jsonObjectIsEmpty($target) ? new \stdClass() : $target;
    }

    /**
     * @return array{type:int,payloadOffset:int,payloadLength:int}
     */
    private static function validatedRootElement(string $bytes): array
    {
        if ($bytes === '') {
            throw new \InvalidArgumentException('SQLite JSONB value is empty');
        }

        [, $next] = self::parseElement($bytes, 0, 0);
        if ($next !== strlen($bytes)) {
            throw new \InvalidArgumentException('SQLite JSONB value has trailing bytes');
        }

        return self::elementAt($bytes, 0);
    }

    /**
     * @param array{type:int,payloadOffset:int,payloadLength:int} $root
     * @param list<array<string, mixed>> $segments
     * @return null|array{type:int,payloadOffset:int,payloadLength:int}
     */
    private static function locateElement(string $bytes, array $root, array $segments): ?array
    {
        $element = $root;
        foreach ($segments as $segment) {
            if (($segment['type'] ?? null) === 'member') {
                $name = $segment['name'] ?? null;
                if (!is_string($name) || $element['type'] !== self::OBJECT) {
                    return null;
                }

                $element = self::objectMemberElement($bytes, $element, $name);
                if ($element === null) {
                    return null;
                }

                continue;
            }

            if ($element['type'] !== self::ARRAY) {
                return null;
            }

            $count = self::countArrayElements($bytes, $element['payloadOffset'], self::payloadEnd($element));
            $arrayIndex = self::pathArrayIndex($segment, $count);
            if ($arrayIndex === null) {
                return null;
            }

            $element = self::arrayElementAt($bytes, $element, $arrayIndex);
            if ($element === null) {
                return null;
            }
        }

        return $element;
    }

    /**
     * @param array{type:int,payloadOffset:int,payloadLength:int} $object
     * @return null|array{type:int,payloadOffset:int,payloadLength:int}
     */
    private static function objectMemberElement(string $bytes, array $object, string $name): ?array
    {
        $offset = $object['payloadOffset'];
        $end = self::payloadEnd($object);
        while ($offset < $end) {
            [$keyType] = self::readHeader($bytes, $offset);
            if ($keyType < self::TEXT || $keyType > self::TEXTRAW) {
                throw new \InvalidArgumentException('SQLite JSONB object label is not text');
            }

            [$key, $valueOffset] = self::parseElement($bytes, $offset, 0);
            if (!is_string($key)) {
                throw new \InvalidArgumentException('SQLite JSONB object label decoded to a non-string value');
            }
            if ($valueOffset >= $end) {
                throw new \InvalidArgumentException('SQLite JSONB object has an unmatched label');
            }

            $value = self::elementAt($bytes, $valueOffset);
            if ($key === $name) {
                return $value;
            }

            $offset = self::payloadEnd($value);
        }
        if ($offset !== $end) {
            throw new \InvalidArgumentException('SQLite JSONB object payload is malformed');
        }

        return null;
    }

    /**
     * @param array{type:int,payloadOffset:int,payloadLength:int} $array
     * @return null|array{type:int,payloadOffset:int,payloadLength:int}
     */
    private static function arrayElementAt(string $bytes, array $array, int $index): ?array
    {
        $offset = $array['payloadOffset'];
        $end = self::payloadEnd($array);
        $current = 0;
        while ($offset < $end) {
            $element = self::elementAt($bytes, $offset);
            if ($current === $index) {
                return $element;
            }

            $offset = self::payloadEnd($element);
            $current++;
        }
        if ($offset !== $end) {
            throw new \InvalidArgumentException('SQLite JSONB array payload is malformed');
        }

        return null;
    }

    private static function countArrayElements(string $bytes, int $offset, int $end): int
    {
        $count = 0;
        while ($offset < $end) {
            $element = self::elementAt($bytes, $offset);
            $offset = self::payloadEnd($element);
            $count++;
        }
        if ($offset !== $end) {
            throw new \InvalidArgumentException('SQLite JSONB array payload is malformed');
        }

        return $count;
    }

    /**
     * @return array{type:int,payloadOffset:int,payloadLength:int}
     */
    private static function elementAt(string $bytes, int $offset): array
    {
        [$type, $payloadOffset, $payloadLength] = self::readHeader($bytes, $offset);

        return [
            'type' => $type,
            'payloadOffset' => $payloadOffset,
            'payloadLength' => $payloadLength,
        ];
    }

    /**
     * @param array{type:int,payloadOffset:int,payloadLength:int} $element
     */
    private static function payloadEnd(array $element): int
    {
        return $element['payloadOffset'] + $element['payloadLength'];
    }

    private static function typeName(int $type): string
    {
        return match ($type) {
            self::NULL => 'null',
            self::TRUE => 'true',
            self::FALSE => 'false',
            self::INT, self::INT5 => 'integer',
            self::FLOAT, self::FLOAT5 => 'real',
            self::TEXT, self::TEXTJ, self::TEXT5, self::TEXTRAW => 'text',
            self::ARRAY => 'array',
            self::OBJECT => 'object',
            default => throw new \InvalidArgumentException("Unsupported SQLite JSONB element type: {$type}"),
        };
    }

    private static function isJsonObject(mixed $value): bool
    {
        return $value instanceof \stdClass || (is_array($value) && !array_is_list($value));
    }

    /**
     * @return array<string, mixed>
     */
    private static function jsonObjectMembers(mixed $value): array
    {
        if ($value instanceof \stdClass) {
            return get_object_vars($value);
        }
        if (is_array($value) && !array_is_list($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('SQLite JSONB value is not an object');
    }

    private static function jsonObjectMemberExists(mixed $value, string $name): bool
    {
        if ($value instanceof \stdClass) {
            return property_exists($value, $name);
        }

        return is_array($value) && !array_is_list($value) && array_key_exists($name, $value);
    }

    private static function jsonObjectMember(mixed $value, string $name): mixed
    {
        if ($value instanceof \stdClass) {
            return $value->{$name};
        }
        if (is_array($value) && !array_is_list($value)) {
            return $value[$name];
        }

        throw new \InvalidArgumentException('SQLite JSONB value is not an object');
    }

    private static function setJsonObjectMember(mixed &$value, string $name, mixed $member): void
    {
        if ($value instanceof \stdClass) {
            $value->{$name} = $member;

            return;
        }
        if (is_array($value) && !array_is_list($value)) {
            $value[$name] = $member;

            return;
        }

        throw new \InvalidArgumentException('SQLite JSONB value is not an object');
    }

    private static function unsetJsonObjectMember(mixed &$value, string $name): void
    {
        if ($value instanceof \stdClass) {
            unset($value->{$name});

            return;
        }
        if (is_array($value) && !array_is_list($value)) {
            unset($value[$name]);
        }
    }

    private static function jsonObjectIsEmpty(mixed $value): bool
    {
        if ($value instanceof \stdClass) {
            return get_object_vars($value) === [];
        }

        return is_array($value) && $value === [];
    }

    private static function encodeElement(mixed $value, int $depth): string
    {
        if ($depth > self::MAX_DEPTH) {
            throw new \InvalidArgumentException('SQLite JSONB value exceeds the maximum nesting depth');
        }

        if ($value === null) {
            return chr(self::NULL);
        }
        if ($value === true) {
            return chr(self::TRUE);
        }
        if ($value === false) {
            return chr(self::FALSE);
        }
        if (is_int($value)) {
            return self::encodeNode(self::INT, (string) $value);
        }
        if (is_float($value)) {
            if (!is_finite($value)) {
                if (is_nan($value)) {
                    return chr(self::NULL);
                }

                return self::encodeNode(self::FLOAT, $value < 0 ? '-9e999' : '9e999');
            }

            $payload = json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
            if (!is_string($payload) || preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?$/', $payload) !== 1) {
                throw new \InvalidArgumentException('SQLite JSONB encoder could not format a JSON float literal');
            }

            return self::encodeNode(self::FLOAT, $payload);
        }
        if (is_string($value)) {
            $type = self::textPayloadIsJsonSafe($value) ? self::TEXT : self::TEXTRAW;

            return self::encodeNode($type, $value);
        }
        if (is_array($value)) {
            $payload = '';
            if (array_is_list($value)) {
                foreach ($value as $element) {
                    $payload .= self::encodeElement($element, $depth + 1);
                }

                return self::encodeNode(self::ARRAY, $payload);
            }

            foreach ($value as $key => $element) {
                $payload .= self::encodeElement((string) $key, $depth + 1);
                $payload .= self::encodeElement($element, $depth + 1);
            }

            return self::encodeNode(self::OBJECT, $payload);
        }
        if ($value instanceof \stdClass) {
            $payload = '';
            foreach (get_object_vars($value) as $key => $element) {
                $payload .= self::encodeElement((string) $key, $depth + 1);
                $payload .= self::encodeElement($element, $depth + 1);
            }

            return self::encodeNode(self::OBJECT, $payload);
        }

        throw new \InvalidArgumentException('SQLite JSONB encoder supports only null, booleans, numbers, strings, arrays, and objects represented as associative arrays');
    }

    private static function encodeNode(int $type, string $payload): string
    {
        return self::encodeHeader($type, strlen($payload)) . $payload;
    }

    private static function encodeHeader(int $type, int $payloadLength): string
    {
        if ($payloadLength < 0) {
            throw new \InvalidArgumentException('SQLite JSONB payload length cannot be negative');
        }
        if ($payloadLength <= 11) {
            return chr(($payloadLength << 4) | $type);
        }
        if ($payloadLength <= 0xff) {
            return chr(0xc0 | $type) . chr($payloadLength);
        }
        if ($payloadLength <= 0xffff) {
            return chr(0xd0 | $type) . pack('n', $payloadLength);
        }
        if ($payloadLength <= 0xffffffff) {
            return chr(0xe0 | $type) . pack('N', $payloadLength);
        }

        throw new \InvalidArgumentException('SQLite JSONB payload exceeds this native PHP slice');
    }

    private static function textPayloadIsJsonSafe(string $payload): bool
    {
        $length = strlen($payload);
        for ($i = 0; $i < $length; $i++) {
            $byte = ord($payload[$i]);
            if ($byte <= 0x1f || $byte === 0x22 || $byte === 0x5c) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{0:mixed,1:int}
     */
    private static function parseElement(string $bytes, int $offset, int $depth, bool $emptyObjectsAsStdClass = false): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw new \InvalidArgumentException('SQLite JSONB value exceeds the maximum nesting depth');
        }

        [$type, $payloadOffset, $payloadLength] = self::readHeader($bytes, $offset);
        $payload = substr($bytes, $payloadOffset, $payloadLength);
        $next = $payloadOffset + $payloadLength;

        return match ($type) {
            self::NULL => self::zeroPayloadValue($payloadLength, null, $next),
            self::TRUE => self::zeroPayloadValue($payloadLength, true, $next),
            self::FALSE => self::zeroPayloadValue($payloadLength, false, $next),
            self::INT => [self::decodeDecimalInteger($payload), $next],
            self::INT5 => [self::decodeJson5Integer($payload), $next],
            self::FLOAT, self::FLOAT5 => [self::decodeFloat($payload), $next],
            self::TEXT, self::TEXTRAW => [$payload, $next],
            self::TEXTJ => [self::decodeEscapedText($payload, false), $next],
            self::TEXT5 => [self::decodeEscapedText($payload, true), $next],
            self::ARRAY => [self::decodeArrayPayload($bytes, $payloadOffset, $next, $depth + 1, $emptyObjectsAsStdClass), $next],
            self::OBJECT => [self::decodeObjectPayload($bytes, $payloadOffset, $next, $depth + 1, $emptyObjectsAsStdClass), $next],
            default => throw new \InvalidArgumentException("Unsupported SQLite JSONB element type: {$type}"),
        };
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private static function readHeader(string $bytes, int $offset): array
    {
        $length = strlen($bytes);
        if ($offset < 0 || $offset >= $length) {
            throw new \InvalidArgumentException('SQLite JSONB element header is truncated');
        }

        $first = ord($bytes[$offset]);
        $type = $first & 0x0f;
        if ($type > self::OBJECT) {
            throw new \InvalidArgumentException("Unsupported SQLite JSONB element type: {$type}");
        }

        $sizeCode = $first >> 4;
        if ($sizeCode <= 11) {
            $payloadOffset = $offset + 1;
            $payloadLength = $sizeCode;
        } elseif ($sizeCode === 12) {
            self::requireBytes($bytes, $offset + 1, 1);
            $payloadOffset = $offset + 2;
            $payloadLength = ord($bytes[$offset + 1]);
        } elseif ($sizeCode === 13) {
            self::requireBytes($bytes, $offset + 1, 2);
            $payloadOffset = $offset + 3;
            $payloadLength = (ord($bytes[$offset + 1]) << 8) | ord($bytes[$offset + 2]);
        } elseif ($sizeCode === 14) {
            self::requireBytes($bytes, $offset + 1, 4);
            $payloadOffset = $offset + 5;
            $payloadLength = self::readUInt32($bytes, $offset + 1);
        } else {
            self::requireBytes($bytes, $offset + 1, 8);
            if (substr($bytes, $offset + 1, 4) !== "\0\0\0\0") {
                throw new \InvalidArgumentException('SQLite JSONB 64-bit payload length exceeds this native PHP slice');
            }
            $payloadOffset = $offset + 9;
            $payloadLength = self::readUInt32($bytes, $offset + 5);
        }

        self::requireBytes($bytes, $payloadOffset, $payloadLength);

        return [$type, $payloadOffset, $payloadLength];
    }

    /**
     * @return array{0:mixed,1:int}
     */
    private static function zeroPayloadValue(int $payloadLength, mixed $value, int $next): array
    {
        if ($payloadLength !== 0) {
            throw new \InvalidArgumentException('SQLite JSONB null/boolean element has a non-zero payload');
        }

        return [$value, $next];
    }

    /**
     * @return list<mixed>
     */
    private static function decodeArrayPayload(string $bytes, int $offset, int $end, int $depth, bool $emptyObjectsAsStdClass): array
    {
        $array = [];
        while ($offset < $end) {
            [$value, $offset] = self::parseElement($bytes, $offset, $depth, $emptyObjectsAsStdClass);
            $array[] = $value;
        }
        if ($offset !== $end) {
            throw new \InvalidArgumentException('SQLite JSONB array payload is malformed');
        }

        return $array;
    }

    /**
     * @return array<string, mixed>|\stdClass
     */
    private static function decodeObjectPayload(string $bytes, int $offset, int $end, int $depth, bool $emptyObjectsAsStdClass): array|\stdClass
    {
        $object = [];
        while ($offset < $end) {
            [$keyType] = self::readHeader($bytes, $offset);
            if ($keyType < self::TEXT || $keyType > self::TEXTRAW) {
                throw new \InvalidArgumentException('SQLite JSONB object label is not text');
            }

            [$key, $offset] = self::parseElement($bytes, $offset, $depth, $emptyObjectsAsStdClass);
            if (!is_string($key)) {
                throw new \InvalidArgumentException('SQLite JSONB object label decoded to a non-string value');
            }
            if ($offset >= $end) {
                throw new \InvalidArgumentException('SQLite JSONB object has an unmatched label');
            }

            [$value, $offset] = self::parseElement($bytes, $offset, $depth, $emptyObjectsAsStdClass);
            $object[$key] = $value;
        }
        if ($offset !== $end) {
            throw new \InvalidArgumentException('SQLite JSONB object payload is malformed');
        }

        if ($object === []) {
            return new \stdClass();
        }

        return $object;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function parsePath(string $path): array
    {
        if ($path === '' || $path[0] !== '$') {
            throw new \InvalidArgumentException('SQLite JSON path must start with $');
        }

        $segments = [];
        $offset = 1;
        $length = strlen($path);
        while ($offset < $length) {
            $char = $path[$offset];
            if ($char === '.') {
                $offset++;
                if ($offset >= $length) {
                    throw new \InvalidArgumentException('SQLite JSON path object label is missing');
                }

                if ($path[$offset] === '"') {
                    $end = self::quotedPathMemberEnd($path, $offset);
                    $label = SQLiteJson5Parser::decode(substr($path, $offset, $end - $offset + 1));
                    if (!is_string($label)) {
                        throw new \InvalidArgumentException('SQLite JSON path quoted label did not decode to text');
                    }
                    $offset = $end + 1;
                } else {
                    $end = $offset;
                    while ($end < $length && $path[$end] !== '.' && $path[$end] !== '[') {
                        $end++;
                    }
                    if ($end === $offset) {
                        throw new \InvalidArgumentException('SQLite JSON path object label is missing');
                    }

                    $label = SQLiteJsonPath::decodeBareMember(substr($path, $offset, $end - $offset));
                    if ($label === null) {
                        throw new \InvalidArgumentException('SQLite JSON path object label is malformed');
                    }
                    $offset = $end;
                }

                $segments[] = [
                    'type' => 'member',
                    'name' => $label,
                ];
                continue;
            }

            if ($char === '[') {
                $end = strpos($path, ']', $offset + 1);
                if ($end === false) {
                    throw new \InvalidArgumentException('SQLite JSON path array index is unterminated');
                }

                $token = substr($path, $offset + 1, $end - $offset - 1);
                if ($token === '#') {
                    $segments[] = [
                        'type' => 'index',
                        'index' => null,
                        'fromEnd' => false,
                        'append' => true,
                    ];
                } elseif (str_starts_with($token, '#-')) {
                    $digits = substr($token, 2);
                    if ($digits === '' || preg_match('/^[0-9]+$/', $digits) !== 1) {
                        throw new \InvalidArgumentException('SQLite JSON path reverse array index is malformed');
                    }
                    $segments[] = [
                        'type' => 'index',
                        'index' => self::parsePathInteger($digits),
                        'fromEnd' => true,
                        'append' => false,
                    ];
                } elseif (preg_match('/^[0-9]+$/', $token) === 1) {
                    $segments[] = [
                        'type' => 'index',
                        'index' => self::parsePathInteger($token),
                        'fromEnd' => false,
                        'append' => false,
                    ];
                } else {
                    throw new \InvalidArgumentException('SQLite JSON path array index is malformed');
                }

                $offset = $end + 1;
                continue;
            }

            throw new \InvalidArgumentException('SQLite JSON path segment is malformed');
        }

        return $segments;
    }

    private static function quotedPathMemberEnd(string $path, int $offset): int
    {
        $length = strlen($path);
        for ($cursor = $offset + 1; $cursor < $length; $cursor++) {
            if ($path[$cursor] === '\\') {
                $cursor++;
                continue;
            }
            if ($path[$cursor] === '"') {
                return $cursor;
            }
        }

        throw new \InvalidArgumentException('SQLite JSON path quoted object label is unterminated');
    }

    private static function parsePathInteger(string $digits): ?int
    {
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return 0;
        }

        $maximum = (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($maximum) || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
            return null;
        }

        return (int) $digits;
    }

    /**
     * @param list<array<string, mixed>> $segments
     */
    private static function removePath(mixed &$value, array $segments): void
    {
        $target =& $value;
        $last = count($segments) - 1;
        for ($index = 0; $index < $last; $index++) {
            $segment = $segments[$index];
            if (($segment['type'] ?? null) === 'member') {
                $name = $segment['name'];
                if (!is_string($name)) {
                    return;
                }
                if (is_array($target) && !array_is_list($target) && array_key_exists($name, $target)) {
                    $target =& $target[$name];
                    continue;
                }
                if ($target instanceof \stdClass && property_exists($target, $name)) {
                    $target =& $target->{$name};
                    continue;
                }

                return;
            }

            if ($target instanceof \stdClass) {
                return;
            }

            if (!is_array($target) || !array_is_list($target)) {
                return;
            }

            $arrayIndex = self::pathArrayIndex($segment, count($target));
            if ($arrayIndex === null) {
                return;
            }

            $target =& $target[$arrayIndex];
        }

        $segment = $segments[$last];
        if (($segment['type'] ?? null) === 'member') {
            $name = $segment['name'];
            if (!is_string($name)) {
                return;
            }

            if (is_array($target) && !array_is_list($target) && array_key_exists($name, $target)) {
                unset($target[$name]);
                if ($target === []) {
                    $target = new \stdClass();
                }

                return;
            }

            if ($target instanceof \stdClass && property_exists($target, $name)) {
                unset($target->{$name});
            }

            return;
        }

        if (!is_array($target) || !array_is_list($target)) {
            return;
        }

        $arrayIndex = self::pathArrayIndex($segment, count($target));
        if ($arrayIndex !== null) {
            array_splice($target, $arrayIndex, 1);
        }
    }

    /**
     * @param list<array<string, mixed>> $segments
     */
    private static function applyMutation(mixed &$document, string $operation, array $segments, mixed $replacement): void
    {
        self::encode($replacement);
        if ($segments === []) {
            if ($operation !== 'insert' && $operation !== 'arrayInsert') {
                $document = $replacement;
            }

            return;
        }

        self::mutatePath($document, $segments, 0, $operation, $replacement);
    }

    /**
     * @param list<array<string, mixed>> $segments
     */
    private static function mutatePath(mixed &$target, array $segments, int $offset, string $operation, mixed $replacement): void
    {
        $segment = $segments[$offset];
        $last = $offset === count($segments) - 1;

        if (($segment['type'] ?? null) === 'member') {
            $name = $segment['name'];
            if (!is_string($name)) {
                return;
            }

            if (is_array($target) && !array_is_list($target)) {
                $exists = array_key_exists($name, $target);
                if ($last) {
                    if ($operation === 'arrayInsert') {
                        self::throwArrayInsertPathError();
                    }
                    self::mutateObjectMember($target, $name, $exists, $operation, $replacement);

                    return;
                }
                if ($exists) {
                    self::mutatePath($target[$name], $segments, $offset + 1, $operation, $replacement);

                    return;
                }

                self::insertMissingObjectMember($target, $name, array_slice($segments, $offset + 1), $operation, $replacement);

                return;
            }

            if ($target instanceof \stdClass) {
                $exists = property_exists($target, $name);
                if ($last) {
                    if ($operation === 'arrayInsert') {
                        self::throwArrayInsertPathError();
                    }
                    if ($exists) {
                        if ($operation !== 'insert') {
                            $target->{$name} = $replacement;
                        }
                    } elseif ($operation !== 'replace') {
                        $target->{$name} = $replacement;
                    }

                    return;
                }
                if ($exists) {
                    self::mutatePath($target->{$name}, $segments, $offset + 1, $operation, $replacement);

                    return;
                }

                if ($operation === 'replace') {
                    return;
                }
                if ($operation === 'arrayInsert' && !self::segmentsEndWithArrayIndex(array_slice($segments, $offset + 1))) {
                    self::throwArrayInsertPathError();
                }
                [$created, $substructure] = self::createSubstructure(array_slice($segments, $offset + 1), $replacement);
                if ($created) {
                    $target->{$name} = $substructure;
                }
            }

            return;
        }

        if (!is_array($target) || !array_is_list($target)) {
            return;
        }

        $arrayIndex = self::pathMutationArrayIndex($segment, count($target));
        if ($arrayIndex === null) {
            return;
        }

        if ($arrayIndex < count($target)) {
            if ($last) {
                if ($operation === 'arrayInsert') {
                    array_splice($target, $arrayIndex, 0, [$replacement]);

                    return;
                }
                if ($operation !== 'insert') {
                    $target[$arrayIndex] = $replacement;
                }

                return;
            }

            self::mutatePath($target[$arrayIndex], $segments, $offset + 1, $operation, $replacement);

            return;
        }

        if ($operation === 'replace') {
            return;
        }

        if ($last) {
            $target[] = $replacement;

            return;
        }

        if ($operation === 'arrayInsert' && !self::segmentsEndWithArrayIndex(array_slice($segments, $offset + 1))) {
            self::throwArrayInsertPathError();
        }
        [$created, $substructure] = self::createSubstructure(array_slice($segments, $offset + 1), $replacement);
        if ($created) {
            $target[] = $substructure;
        }
    }

    private static function mutateObjectMember(array &$target, string $name, bool $exists, string $operation, mixed $replacement): void
    {
        if ($exists) {
            if ($operation !== 'insert') {
                $target[$name] = $replacement;
            }

            return;
        }

        if ($operation !== 'replace') {
            $target[$name] = $replacement;
        }
    }

    /**
     * @param list<array<string, mixed>> $tail
     */
    private static function insertMissingObjectMember(array &$target, string $name, array $tail, string $operation, mixed $replacement): void
    {
        if ($operation === 'replace') {
            return;
        }
        if ($operation === 'arrayInsert' && !self::segmentsEndWithArrayIndex($tail)) {
            self::throwArrayInsertPathError();
        }

        [$created, $substructure] = self::createSubstructure($tail, $replacement);
        if ($created) {
            $target[$name] = $substructure;
        }
    }

    /**
     * @param list<array<string, mixed>> $segments
     * @return array{0:bool,1:mixed}
     */
    private static function createSubstructure(array $segments, mixed $replacement): array
    {
        if ($segments === []) {
            return [true, $replacement];
        }

        $segment = $segments[0];
        $tail = array_slice($segments, 1);
        if (($segment['type'] ?? null) === 'member') {
            $name = $segment['name'];
            if (!is_string($name)) {
                return [false, null];
            }

            [$created, $value] = self::createSubstructure($tail, $replacement);

            return $created ? [true, [$name => $value]] : [false, null];
        }

        $index = self::substructureArrayIndex($segment);
        if ($index !== 0) {
            return [false, null];
        }

        [$created, $value] = self::createSubstructure($tail, $replacement);

        return $created ? [true, [$value]] : [false, null];
    }

    /**
     * @param list<array<string, mixed>> $segments
     */
    private static function segmentsEndWithArrayIndex(array $segments): bool
    {
        $last = $segments[array_key_last($segments)] ?? null;

        return is_array($last) && ($last['type'] ?? null) === 'index';
    }

    private static function throwArrayInsertPathError(): never
    {
        throw new \InvalidArgumentException('SQLite JSON array_insert path must identify an array element');
    }

    /**
     * @param array<string, mixed> $segment
     */
    private static function pathMutationArrayIndex(array $segment, int $count): ?int
    {
        if (($segment['append'] ?? false) === true) {
            return $count;
        }

        $index = $segment['index'] ?? null;
        if (!is_int($index)) {
            return null;
        }

        if (($segment['fromEnd'] ?? false) === true) {
            $index = $count - $index;
        }

        if ($index < 0 || $index > $count) {
            return null;
        }

        return $index;
    }

    /**
     * @param array<string, mixed> $segment
     */
    private static function substructureArrayIndex(array $segment): ?int
    {
        if (($segment['append'] ?? false) === true) {
            return 0;
        }

        $index = $segment['index'] ?? null;
        if (!is_int($index)) {
            return null;
        }

        if (($segment['fromEnd'] ?? false) === true) {
            return $index === 0 ? 0 : null;
        }

        return $index;
    }

    /**
     * @param array<string, mixed> $segment
     */
    private static function pathArrayIndex(array $segment, int $count): ?int
    {
        if (($segment['append'] ?? false) === true) {
            return null;
        }

        $index = $segment['index'] ?? null;
        if (!is_int($index)) {
            return null;
        }

        if (($segment['fromEnd'] ?? false) === true) {
            $index = $count - $index;
        }

        if ($index < 0 || $index >= $count) {
            return null;
        }

        return $index;
    }

    private static function decodeDecimalInteger(string $payload): int|float
    {
        if (preg_match('/^-?(?:0|[1-9][0-9]*)$/', $payload) !== 1) {
            throw new \InvalidArgumentException('SQLite JSONB integer payload is malformed');
        }

        return self::parseIntegerLiteral($payload, 10);
    }

    private static function decodeJson5Integer(string $payload): int|float
    {
        if (preg_match('/^[+-]?0[xX][0-9A-Fa-f]+$/', $payload) !== 1) {
            throw new \InvalidArgumentException('SQLite JSONB JSON5 integer payload is malformed');
        }

        $negative = str_starts_with($payload, '-');
        if ($payload[0] === '-' || $payload[0] === '+') {
            $payload = substr($payload, 1);
        }

        return self::parseIntegerLiteral(substr($payload, 2), 16, $negative);
    }

    private static function parseIntegerLiteral(string $digits, int $base, bool $negative = false): int|float
    {
        if ($base === 10) {
            $negative = str_starts_with($digits, '-');
            if ($negative) {
                $digits = substr($digits, 1);
            }
        }

        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return 0;
        }

        if ($base === 10) {
            $limit = $negative ? '9223372036854775808' : '9223372036854775807';
            if (strlen($digits) < strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) <= 0)) {
                if ($negative && $digits === '9223372036854775808') {
                    return PHP_INT_MIN;
                }
                $value = (int) $digits;

                return $negative ? -$value : $value;
            }

            return (float) (($negative ? '-' : '') . $digits);
        }

        $value = 0;
        foreach (str_split($digits) as $char) {
            $value = ($value * 16) + hexdec($char);
            if ($value > PHP_INT_MAX) {
                return (float) ($negative ? -$value : $value);
            }
        }

        return $negative ? -$value : $value;
    }

    private static function decodeFloat(string $payload): float
    {
        if ($payload === '' || !is_numeric($payload)) {
            throw new \InvalidArgumentException('SQLite JSONB float payload is malformed');
        }

        return (float) $payload;
    }

    private static function decodeEscapedText(string $payload, bool $json5): string
    {
        $value = '';
        $length = strlen($payload);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $payload[$offset];
            if ($char !== '\\') {
                $value .= $char;
                continue;
            }
            if ($offset + 1 >= $length) {
                throw new \InvalidArgumentException('SQLite JSONB text escape is truncated');
            }

            $offset++;
            $escape = $payload[$offset];
            switch ($escape) {
                case '"':
                case '\\':
                case '/':
                    $value .= $escape;
                    break;
                case "'":
                    if (!$json5) {
                        throw new \InvalidArgumentException('SQLite JSONB JSON text escape is malformed');
                    }
                    $value .= "'";
                    break;
                case 'b':
                    $value .= "\x08";
                    break;
                case 'f':
                    $value .= "\x0c";
                    break;
                case 'n':
                    $value .= "\n";
                    break;
                case 'r':
                    $value .= "\r";
                    break;
                case 't':
                    $value .= "\t";
                    break;
                case 'v':
                    if (!$json5) {
                        throw new \InvalidArgumentException('SQLite JSONB JSON text escape is malformed');
                    }
                    $value .= "\x0b";
                    break;
                case '0':
                    if (!$json5) {
                        throw new \InvalidArgumentException('SQLite JSONB JSON text escape is malformed');
                    }
                    $value .= "\0";
                    break;
                case 'x':
                    if (!$json5 || $offset + 2 >= $length) {
                        throw new \InvalidArgumentException('SQLite JSONB JSON5 hex escape is truncated');
                    }
                    $hex = substr($payload, $offset + 1, 2);
                    if (preg_match('/^[0-9A-Fa-f]{2}$/', $hex) !== 1) {
                        throw new \InvalidArgumentException('SQLite JSONB JSON5 hex escape is malformed');
                    }
                    $value .= chr(hexdec($hex));
                    $offset += 2;
                    break;
                case 'u':
                    if ($offset + 4 >= $length) {
                        throw new \InvalidArgumentException('SQLite JSONB unicode escape is truncated');
                    }
                    $hex = substr($payload, $offset + 1, 4);
                    if (preg_match('/^[0-9A-Fa-f]{4}$/', $hex) !== 1) {
                        throw new \InvalidArgumentException('SQLite JSONB unicode escape is malformed');
                    }
                    $offset += 4;
                    $codepoint = hexdec($hex);
                    if (
                        $codepoint >= 0xd800
                        && $codepoint <= 0xdbff
                        && $offset + 6 < $length
                        && substr($payload, $offset + 1, 2) === '\\u'
                        && preg_match('/^[0-9A-Fa-f]{4}$/', substr($payload, $offset + 3, 4)) === 1
                    ) {
                        $low = hexdec(substr($payload, $offset + 3, 4));
                        if ($low >= 0xdc00 && $low <= 0xdfff) {
                            $offset += 6;
                            $codepoint = 0x10000 + (($codepoint - 0xd800) << 10) + ($low - 0xdc00);
                        }
                    }
                    $value .= self::codepointToUtf8($codepoint);
                    break;
                default:
                    if (!$json5) {
                        throw new \InvalidArgumentException('SQLite JSONB JSON text escape is malformed');
                    }
                    $value .= $escape;
                    break;
            }
        }

        return $value;
    }

    private static function codepointToUtf8(int $codepoint): string
    {
        if ($codepoint < 0 || ($codepoint >= 0xd800 && $codepoint <= 0xdfff) || $codepoint > 0x10ffff) {
            throw new \InvalidArgumentException('SQLite JSONB unicode codepoint is invalid');
        }
        if ($codepoint <= 0x7f) {
            return chr($codepoint);
        }
        if ($codepoint <= 0x7ff) {
            return chr(0xc0 | ($codepoint >> 6)) . chr(0x80 | ($codepoint & 0x3f));
        }
        if ($codepoint <= 0xffff) {
            return chr(0xe0 | ($codepoint >> 12))
                . chr(0x80 | (($codepoint >> 6) & 0x3f))
                . chr(0x80 | ($codepoint & 0x3f));
        }

        return chr(0xf0 | ($codepoint >> 18))
            . chr(0x80 | (($codepoint >> 12) & 0x3f))
            . chr(0x80 | (($codepoint >> 6) & 0x3f))
            . chr(0x80 | ($codepoint & 0x3f));
    }

    private static function requireBytes(string $bytes, int $offset, int $length): void
    {
        if ($offset < 0 || $length < 0 || $offset + $length > strlen($bytes)) {
            throw new \InvalidArgumentException('SQLite JSONB payload is truncated');
        }
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        self::requireBytes($bytes, $offset, 4);

        return unpack('N', substr($bytes, $offset, 4))[1];
    }

    /**
     * @return null|array{type:int,payloadOffset:int,payloadLength:int}
     */
    private static function readHeaderForValidation(string $bytes, int $offset): ?array
    {
        $length = strlen($bytes);
        if ($offset < 0 || $offset >= $length) {
            return null;
        }

        $first = ord($bytes[$offset]);
        $type = $first & 0x0f;
        if ($type > self::OBJECT) {
            return null;
        }

        $sizeCode = $first >> 4;
        if ($sizeCode <= 11) {
            $payloadOffset = $offset + 1;
            $payloadLength = $sizeCode;
        } elseif ($sizeCode === 12) {
            if ($offset + 2 > $length) {
                return null;
            }
            $payloadOffset = $offset + 2;
            $payloadLength = ord($bytes[$offset + 1]);
        } elseif ($sizeCode === 13) {
            if ($offset + 3 > $length) {
                return null;
            }
            $payloadOffset = $offset + 3;
            $payloadLength = (ord($bytes[$offset + 1]) << 8) | ord($bytes[$offset + 2]);
        } elseif ($sizeCode === 14) {
            if ($offset + 5 > $length) {
                return null;
            }
            $payloadOffset = $offset + 5;
            $payloadLength = self::readUInt32($bytes, $offset + 1);
        } else {
            if ($offset + 9 > $length || substr($bytes, $offset + 1, 4) !== "\0\0\0\0") {
                return null;
            }
            $payloadOffset = $offset + 9;
            $payloadLength = self::readUInt32($bytes, $offset + 5);
        }

        if ($payloadOffset + $payloadLength > $length) {
            return null;
        }

        return [
            'type' => $type,
            'payloadOffset' => $payloadOffset,
            'payloadLength' => $payloadLength,
        ];
    }

    private static function validateElementForErrorPosition(string $bytes, int $offset, int $end, int $depth): int
    {
        if ($depth > self::MAX_DEPTH) {
            return $offset + 1;
        }

        $element = self::readHeaderForValidation($bytes, $offset);
        if ($element === null || self::payloadEnd($element) !== $end) {
            return $offset + 1;
        }

        $payloadOffset = $element['payloadOffset'];
        $payloadLength = $element['payloadLength'];
        $payloadEnd = $payloadOffset + $payloadLength;
        $payload = substr($bytes, $payloadOffset, $payloadLength);

        return match ($element['type']) {
            self::NULL, self::TRUE, self::FALSE => $payloadLength === 0 ? 0 : $offset + 1,
            self::INT => self::integerPayloadErrorPosition($payload, $payloadOffset, false, $offset),
            self::INT5 => self::integerPayloadErrorPosition($payload, $payloadOffset, true, $offset),
            self::FLOAT, self::FLOAT5 => self::payloadValidatorErrorPosition(
                static fn () => self::validateFloatPayload($payload, $element['type'] === self::FLOAT5),
                $offset
            ),
            self::TEXT => self::payloadValidatorErrorPosition(static fn () => self::validatePlainTextPayload($payload), $offset),
            self::TEXTJ => self::payloadValidatorErrorPosition(static fn () => self::validateEscapedTextPayload($payload, false), $offset),
            self::TEXT5 => self::payloadValidatorErrorPosition(static fn () => self::validateEscapedTextPayload($payload, true), $offset),
            self::TEXTRAW => 0,
            self::ARRAY => self::arrayPayloadErrorPosition($bytes, $payloadOffset, $payloadEnd, $depth + 1),
            self::OBJECT => self::objectPayloadErrorPosition($bytes, $payloadOffset, $payloadEnd, $depth + 1),
            default => $offset + 1,
        };
    }

    private static function integerPayloadErrorPosition(string $payload, int $payloadOffset, bool $json5, int $elementOffset): int
    {
        if ($json5) {
            if (preg_match('/^-?0[xX][0-9A-Fa-f]+$/', $payload) === 1) {
                return 0;
            }

            return $elementOffset + 1;
        }

        $length = strlen($payload);
        if ($length === 0) {
            return $elementOffset + 1;
        }

        $offset = 0;
        if ($payload[0] === '-') {
            if ($length === 1) {
                return $elementOffset + 1;
            }
            $offset = 1;
        }
        for (; $offset < $length; $offset++) {
            if (!ctype_digit($payload[$offset])) {
                return $payloadOffset + $offset + 1;
            }
        }

        return 0;
    }

    private static function payloadValidatorErrorPosition(\Closure $validator, int $elementOffset): int
    {
        try {
            $validator();

            return 0;
        } catch (\InvalidArgumentException) {
            return $elementOffset + 1;
        }
    }

    private static function arrayPayloadErrorPosition(string $bytes, int $offset, int $end, int $depth): int
    {
        while ($offset < $end) {
            $element = self::readHeaderForValidation($bytes, $offset);
            if ($element === null || self::payloadEnd($element) > $end) {
                return $offset + 1;
            }

            $error = self::validateElementForErrorPosition($bytes, $offset, self::payloadEnd($element), $depth);
            if ($error !== 0) {
                return $error;
            }
            $offset = self::payloadEnd($element);
        }

        return $offset === $end ? 0 : $offset + 1;
    }

    private static function objectPayloadErrorPosition(string $bytes, int $offset, int $end, int $depth): int
    {
        $count = 0;
        while ($offset < $end) {
            $element = self::readHeaderForValidation($bytes, $offset);
            if ($element === null || self::payloadEnd($element) > $end) {
                return $offset + 1;
            }
            if (($count & 1) === 0 && ($element['type'] < self::TEXT || $element['type'] > self::TEXTRAW)) {
                return $offset + 1;
            }

            $error = self::validateElementForErrorPosition($bytes, $offset, self::payloadEnd($element), $depth);
            if ($error !== 0) {
                return $error;
            }
            $count++;
            $offset = self::payloadEnd($element);
        }

        return ($count & 1) === 0 ? 0 : $offset + 1;
    }

    private static function validateElement(string $bytes, int $offset, int $end, int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            throw new \InvalidArgumentException('SQLite JSONB value exceeds the maximum nesting depth');
        }

        [$type, $payloadOffset, $payloadLength] = self::readHeader($bytes, $offset);
        if ($payloadOffset + $payloadLength !== $end) {
            throw new \InvalidArgumentException('SQLite JSONB element payload size is inconsistent');
        }

        $payload = substr($bytes, $payloadOffset, $payloadLength);
        match ($type) {
            self::NULL, self::TRUE, self::FALSE => self::validateZeroPayload($payloadLength),
            self::INT => self::validateIntegerPayload($payload),
            self::INT5 => self::validateJson5IntegerPayload($payload),
            self::FLOAT => self::validateFloatPayload($payload, false),
            self::FLOAT5 => self::validateFloatPayload($payload, true),
            self::TEXT => self::validatePlainTextPayload($payload),
            self::TEXTJ => self::validateEscapedTextPayload($payload, false),
            self::TEXT5 => self::validateEscapedTextPayload($payload, true),
            self::TEXTRAW => null,
            self::ARRAY => self::validateArrayPayload($bytes, $payloadOffset, $end, $depth + 1),
            self::OBJECT => self::validateObjectPayload($bytes, $payloadOffset, $end, $depth + 1),
            default => throw new \InvalidArgumentException("Unsupported SQLite JSONB element type: {$type}"),
        };
    }

    private static function validateZeroPayload(int $payloadLength): void
    {
        if ($payloadLength !== 0) {
            throw new \InvalidArgumentException('SQLite JSONB null/boolean element has a non-zero payload');
        }
    }

    private static function validateIntegerPayload(string $payload): void
    {
        if ($payload === '' || preg_match('/^-?[0-9]+$/', $payload) !== 1) {
            throw new \InvalidArgumentException('SQLite JSONB integer payload is malformed');
        }
    }

    private static function validateJson5IntegerPayload(string $payload): void
    {
        if (preg_match('/^-?0[xX][0-9A-Fa-f]+$/', $payload) !== 1) {
            throw new \InvalidArgumentException('SQLite JSONB JSON5 integer payload is malformed');
        }
    }

    private static function validateFloatPayload(string $payload, bool $json5): void
    {
        $length = strlen($payload);
        if ($length < 2) {
            throw new \InvalidArgumentException('SQLite JSONB float payload is malformed');
        }

        $offset = 0;
        $seen = 0;
        if ($payload[$offset] === '-') {
            $offset++;
            if ($length < 3) {
                throw new \InvalidArgumentException('SQLite JSONB float payload is malformed');
            }
        }

        if ($offset >= $length) {
            throw new \InvalidArgumentException('SQLite JSONB float payload is malformed');
        }
        if ($payload[$offset] === '.') {
            if (!$json5 || $offset + 1 >= $length || !self::isAsciiDigit($payload[$offset + 1])) {
                throw new \InvalidArgumentException('SQLite JSONB float payload is malformed');
            }
            $offset += 2;
            $seen = 1;
        } elseif ($payload[$offset] === '0' && !$json5) {
            if ($offset + 3 > $length) {
                throw new \InvalidArgumentException('SQLite JSONB float payload is malformed');
            }
            $next = $payload[$offset + 1];
            if ($next !== '.' && $next !== 'e' && $next !== 'E') {
                throw new \InvalidArgumentException('SQLite JSONB float payload is malformed');
            }
            $offset++;
        }

        for (; $offset < $length; $offset++) {
            $char = $payload[$offset];
            if (self::isAsciiDigit($char)) {
                continue;
            }
            if ($char === '.') {
                if ($seen > 0 || (!$json5 && ($offset === $length - 1 || !self::isAsciiDigit($payload[$offset + 1])))) {
                    throw new \InvalidArgumentException('SQLite JSONB float payload is malformed');
                }
                $seen = 1;
                continue;
            }
            if ($char === 'e' || $char === 'E') {
                if ($seen === 2 || $offset === $length - 1) {
                    throw new \InvalidArgumentException('SQLite JSONB float payload is malformed');
                }
                if ($payload[$offset + 1] === '+' || $payload[$offset + 1] === '-') {
                    $offset++;
                    if ($offset === $length - 1) {
                        throw new \InvalidArgumentException('SQLite JSONB float payload is malformed');
                    }
                }
                $seen = 2;
                continue;
            }

            throw new \InvalidArgumentException('SQLite JSONB float payload is malformed');
        }

        if ($seen === 0) {
            throw new \InvalidArgumentException('SQLite JSONB float payload is malformed');
        }
    }

    private static function validatePlainTextPayload(string $payload): void
    {
        $length = strlen($payload);
        for ($offset = 0; $offset < $length; $offset++) {
            if (!self::isJsonSafeTextByte(ord($payload[$offset])) && $payload[$offset] !== "'") {
                throw new \InvalidArgumentException('SQLite JSONB text payload is malformed');
            }
        }
    }

    private static function validateEscapedTextPayload(string $payload, bool $json5): void
    {
        $length = strlen($payload);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $payload[$offset];
            $byte = ord($char);
            if (self::isJsonSafeTextByte($byte) || $char === "'") {
                continue;
            }
            if ($char === '"') {
                if (!$json5) {
                    throw new \InvalidArgumentException('SQLite JSONB escaped text payload is malformed');
                }
                continue;
            }
            if ($byte <= 0x1f) {
                if (!$json5) {
                    throw new \InvalidArgumentException('SQLite JSONB escaped text payload is malformed');
                }
                continue;
            }
            if ($char !== '\\' || $offset + 1 >= $length) {
                throw new \InvalidArgumentException('SQLite JSONB escaped text payload is malformed');
            }

            $escape = $payload[$offset + 1];
            if (str_contains('"\\/bfnrt', $escape)) {
                $offset++;
                continue;
            }
            if ($escape === 'u') {
                if ($offset + 5 >= $length || !self::isHexSequence(substr($payload, $offset + 2, 4))) {
                    throw new \InvalidArgumentException('SQLite JSONB unicode escape is malformed');
                }
                $offset += 5;
                continue;
            }
            if (!$json5) {
                throw new \InvalidArgumentException('SQLite JSONB escaped text payload is malformed');
            }
            if ($escape === "\n") {
                $offset++;
                continue;
            }
            if ($escape === "\r") {
                $offset += ($offset + 2 < $length && $payload[$offset + 2] === "\n") ? 2 : 1;
                continue;
            }
            if (substr($payload, $offset + 1, 3) === "\xE2\x80\xA8" || substr($payload, $offset + 1, 3) === "\xE2\x80\xA9") {
                $offset += 3;
                continue;
            }
            if ($escape === '0') {
                if ($offset + 2 < $length && self::isAsciiDigit($payload[$offset + 2])) {
                    throw new \InvalidArgumentException('SQLite JSONB JSON5 nul escape is malformed');
                }
                $offset++;
                continue;
            }
            if ($escape === 'x') {
                if ($offset + 3 >= $length || !self::isHexSequence(substr($payload, $offset + 2, 2))) {
                    throw new \InvalidArgumentException('SQLite JSONB JSON5 hex escape is malformed');
                }
                $offset += 3;
                continue;
            }

            $offset++;
        }
    }

    private static function validateArrayPayload(string $bytes, int $offset, int $end, int $depth): void
    {
        while ($offset < $end) {
            [, $payloadOffset, $payloadLength] = self::readHeader($bytes, $offset);
            $next = $payloadOffset + $payloadLength;
            if ($next > $end) {
                throw new \InvalidArgumentException('SQLite JSONB array payload is malformed');
            }
            self::validateElement($bytes, $offset, $next, $depth);
            $offset = $next;
        }
        if ($offset !== $end) {
            throw new \InvalidArgumentException('SQLite JSONB array payload is malformed');
        }
    }

    private static function validateObjectPayload(string $bytes, int $offset, int $end, int $depth): void
    {
        $count = 0;
        while ($offset < $end) {
            [$type, $payloadOffset, $payloadLength] = self::readHeader($bytes, $offset);
            $next = $payloadOffset + $payloadLength;
            if ($next > $end) {
                throw new \InvalidArgumentException('SQLite JSONB object payload is malformed');
            }
            if ($count % 2 === 0 && ($type < self::TEXT || $type > self::TEXTRAW)) {
                throw new \InvalidArgumentException('SQLite JSONB object label is not text');
            }
            self::validateElement($bytes, $offset, $next, $depth);
            $count++;
            $offset = $next;
        }
        if ($offset !== $end || $count % 2 !== 0) {
            throw new \InvalidArgumentException('SQLite JSONB object payload is malformed');
        }
    }

    private static function isAsciiDigit(string $char): bool
    {
        return $char >= '0' && $char <= '9';
    }

    private static function isHexSequence(string $text): bool
    {
        return $text !== '' && preg_match('/^[0-9A-Fa-f]+$/', $text) === 1;
    }

    private static function isJsonSafeTextByte(int $byte): bool
    {
        return $byte > 0x1f && $byte !== 0x22 && $byte !== 0x5c;
    }
}
