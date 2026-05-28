<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext261Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressUtf16NameAndValueLikePlan(
        array $currentRows,
        array $nextRows,
        string $namePatternBytes,
        int|string $namePatternEncoding,
        string $valuePattern = 'enabled:%',
        ?string $nameEscape = '!',
        ?string $valueEscape = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.wp_options@260',
        string $nextSource = 'main.wp_options@261',
        int $currentSchemaCookie = 260,
        int $nextSchemaCookie = 261,
    ): array {
        $namePattern = SQLiteEncodingCollationSourceCursor::decodeText($namePatternBytes, self::encodingCode($namePatternEncoding));
        $namePatternPlan = SQLiteDatabase::likePatternPlan($namePattern, $nameEscape);
        $valuePatternPlan = SQLiteDatabase::likePatternPlan($valuePattern, $valueEscape);
        $current = self::scanRows($currentRows, $namePattern, $valuePattern, $nameEscape, $valueEscape, $caseSensitiveLike);
        $next = self::scanRows($nextRows, $namePattern, $valuePattern, $nameEscape, $valueEscape, $caseSensitiveLike);
        $currentMatched = self::rowids($current['matched']);
        $nextMatched = self::rowids($next['matched']);
        $retained = array_values(array_intersect($currentMatched, $nextMatched));
        $exited = array_values(array_diff($currentMatched, $nextMatched));
        $entered = array_values(array_diff($nextMatched, $currentMatched));
        $currentByRowid = self::rowsByRowid($current['decisions']);
        $nextByRowid = self::rowsByRowid($next['decisions']);
        $changedNameText = [];
        $changedValueText = [];
        $changedValueStorage = [];
        $changedCompositeTruth = [];

        foreach (array_intersect(array_keys($currentByRowid), array_keys($nextByRowid)) as $rowid) {
            if ($currentByRowid[$rowid]['nameText'] !== $nextByRowid[$rowid]['nameText']) {
                $changedNameText[] = $rowid;
            }
            if ($currentByRowid[$rowid]['valueText'] !== $nextByRowid[$rowid]['valueText']) {
                $changedValueText[] = $rowid;
            }
            if ($currentByRowid[$rowid]['valueStorageClass'] !== $nextByRowid[$rowid]['valueStorageClass']) {
                $changedValueStorage[] = $rowid;
            }
            if ($currentByRowid[$rowid]['compositeMatch'] !== $nextByRowid[$rowid]['compositeMatch']) {
                $changedCompositeTruth[] = $rowid;
            }
        }

        sort($changedNameText);
        sort($changedValueText);
        sort($changedValueStorage);
        sort($changedCompositeTruth);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($entered !== [] || $exited !== []) {
            $reasons[] = 'matched-rowset';
        }
        if ($changedCompositeTruth !== []) {
            $reasons[] = 'composite-predicate-truth';
        }
        if ($changedNameText !== []) {
            $reasons[] = 'decoded-name-text';
        }
        if ($changedValueText !== []) {
            $reasons[] = 'value-affinity-text';
        }
        if ($changedValueStorage !== []) {
            $reasons[] = 'value-storage-class';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-next261',
            'operator' => 'LIKE',
            'expression' => 'option_name LIKE utf16(?) ESCAPE ? AND option_value LIKE ? /* text affinity current-source fence */',
            'namePattern' => $namePattern,
            'namePatternHex' => bin2hex($namePattern),
            'namePatternBytesHex' => bin2hex($namePatternBytes),
            'namePatternEncoding' => SQLiteEncodingCollationSourceCursor::encodingNameForCode(self::encodingCode($namePatternEncoding)),
            'nameEscape' => $nameEscape,
            'nameEscapeHex' => $nameEscape === null ? null : bin2hex($nameEscape),
            'namePrefix' => $namePatternPlan['prefix'],
            'namePrefixHex' => bin2hex($namePatternPlan['prefix']),
            'nameBinaryRange' => $namePatternPlan['binaryRange'],
            'nameNoCaseRange' => $namePatternPlan['noCaseRange'],
            'valuePattern' => $valuePattern,
            'valuePatternHex' => bin2hex($valuePattern),
            'valueEscape' => $valueEscape,
            'valuePrefix' => $valuePatternPlan['prefix'],
            'valueBinaryRange' => $valuePatternPlan['binaryRange'],
            'collation' => $caseSensitiveLike ? 'BINARY' : 'NOCASE',
            'caseSensitiveLike' => $caseSensitiveLike,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentCandidateRowids' => self::rowids($current['candidates']),
            'nextCandidateRowids' => self::rowids($next['candidates']),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'retainedMatchedRowids' => $retained,
            'exitedMatchedRowids' => $exited,
            'enteredMatchedRowids' => $entered,
            'currentUnknownValueRowids' => $current['unknownValueRowids'],
            'nextUnknownValueRowids' => $next['unknownValueRowids'],
            'changedNameTextRowids' => $changedNameText,
            'changedValueTextRowids' => $changedValueText,
            'changedValueStorageClassRowids' => $changedValueStorage,
            'changedCompositeTruthRowids' => $changedCompositeTruth,
            'currentNameText' => self::fieldByRowid($currentByRowid, 'nameText'),
            'nextNameText' => self::fieldByRowid($nextByRowid, 'nameText'),
            'currentNameTextHex' => self::fieldByRowid($currentByRowid, 'nameHex'),
            'nextNameTextHex' => self::fieldByRowid($nextByRowid, 'nameHex'),
            'currentNameEncoding' => self::fieldByRowid($currentByRowid, 'nameEncoding'),
            'nextNameEncoding' => self::fieldByRowid($nextByRowid, 'nameEncoding'),
            'currentValueText' => self::fieldByRowid($currentByRowid, 'valueText'),
            'nextValueText' => self::fieldByRowid($nextByRowid, 'valueText'),
            'currentValueHex' => self::fieldByRowid($currentByRowid, 'valueHex'),
            'nextValueHex' => self::fieldByRowid($nextByRowid, 'valueHex'),
            'currentValueStorageClasses' => self::fieldByRowid($currentByRowid, 'valueStorageClass'),
            'nextValueStorageClasses' => self::fieldByRowid($nextByRowid, 'valueStorageClass'),
            'currentCompositeMatches' => self::fieldByRowid($currentByRowid, 'compositeMatch'),
            'nextCompositeMatches' => self::fieldByRowid($nextByRowid, 'compositeMatch'),
            'currentNameResidualMatches' => self::fieldByRowid($currentByRowid, 'nameMatch'),
            'currentValueResidualMatches' => self::fieldByRowid($currentByRowid, 'valueMatch'),
            'nextNameResidualMatches' => self::fieldByRowid($nextByRowid, 'nameMatch'),
            'nextValueResidualMatches' => self::fieldByRowid($nextByRowid, 'valueMatch'),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'utf16PatternDecodedBeforeLikeTokenization' => true,
            'nameLikeUsesAsciiNoCaseOnly' => !$caseSensitiveLike,
            'valueLikeAppliesTextAffinityBeforeResidual' => true,
            'blobAndNullValuesRemainUnknownForLike' => true,
            'escapedUnderscoreInUtf16PatternIsLiteral' => true,
            'dependencies' => [
                'sqlite-encoding-source-cursor',
                'sqlite-like-escape-tokenizer',
                'sqlite-text-affinity',
                'sqlite-current-source-next261',
            ],
            'dependency_closure' => 'no new support component needed; next261 reuses native UTF-16 decode, LIKE tokenization, ASCII NOCASE matching, and scalar text-affinity coercion',
            'non_overlap' => 'next261 covers a composite UTF-16 bound option_name LIKE plus option_value text-affinity LIKE current-source fence; it avoids accepted next240 numeric-only LIKE, next258 case_sensitive_like binary transition, Unicode GLOB range next102/next259, UTF-16 malformed guard, and storage/VFS/WAL/B-tree/JSON clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{decisions:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,unknownValueRowids:list<int>}
     */
    private static function scanRows(array $rows, string $namePattern, string $valuePattern, ?string $nameEscape, ?string $valueEscape, bool $caseSensitiveLike): array
    {
        $decisions = [];
        $candidates = [];
        $matched = [];
        $unknown = [];

        foreach ($rows as $index => $row) {
            $rowid = self::rowid($row, $index);
            $name = self::decodeOptionName($row);
            $value = self::coerceLikeText($row['option_value'] ?? null);
            $nameMatch = SQLiteDatabase::likeMatches($name['text'], $namePattern, $nameEscape, $caseSensitiveLike);
            $valueMatch = $value !== null && SQLiteDatabase::likeMatches($value['text'], $valuePattern, $valueEscape, $caseSensitiveLike);
            if ($value === null) {
                $unknown[] = $rowid;
            }

            $decision = [
                'rowid' => $rowid,
                'nameText' => $name['text'],
                'nameHex' => bin2hex($name['text']),
                'nameEncoding' => $name['encoding'],
                'valueText' => $value['text'] ?? null,
                'valueHex' => $value === null ? null : bin2hex($value['text']),
                'valueStorageClass' => $value['storageClass'] ?? 'unknown',
                'nameMatch' => $nameMatch,
                'valueMatch' => $valueMatch,
                'compositeMatch' => $nameMatch && $valueMatch,
                'sortKey' => $caseSensitiveLike ? $name['text'] : self::asciiLower($name['text']),
            ];
            $decisions[] = $decision;
            if ($nameMatch) {
                $candidates[] = $decision;
            }
            if ($decision['compositeMatch']) {
                $matched[] = $decision;
            }
        }

        $sort = static fn (array $left, array $right): int => strcmp($left['sortKey'], $right['sortKey']) ?: $left['rowid'] <=> $right['rowid'];
        usort($decisions, $sort);
        usort($candidates, $sort);
        usort($matched, $sort);
        sort($unknown);

        return ['decisions' => $decisions, 'candidates' => $candidates, 'matched' => $matched, 'unknownValueRowids' => $unknown];
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row, int $index): int
    {
        if (!isset($row['option_id']) || !is_int($row['option_id'])) {
            return $index + 1;
        }

        return $row['option_id'];
    }

    /** @param array<string,mixed> $row @return array{text:string,encoding:string} */
    private static function decodeOptionName(array $row): array
    {
        if (isset($row['option_name_bytes'])) {
            if (!is_string($row['option_name_bytes']) || !isset($row['name_text_encoding'])) {
                throw new \InvalidArgumentException('SQLite next261 option_name_bytes rows require name_text_encoding');
            }
            $encoding = self::encodingCode($row['name_text_encoding']);

            return [
                'text' => SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $encoding),
                'encoding' => SQLiteEncodingCollationSourceCursor::encodingNameForCode($encoding),
            ];
        }
        if (!array_key_exists('option_name', $row) || !is_string($row['option_name'])) {
            throw new \InvalidArgumentException('SQLite next261 rows require option_name text or encoded option_name_bytes');
        }

        return ['text' => $row['option_name'], 'encoding' => 'UTF-8'];
    }

    /** @return null|array{text:string,storageClass:string} */
    private static function coerceLikeText(mixed $value): ?array
    {
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_string($value)) {
            return ['text' => $value, 'storageClass' => 'text'];
        }
        if (is_int($value)) {
            return ['text' => (string) $value, 'storageClass' => 'integer'];
        }
        if (is_float($value)) {
            return ['text' => self::formatReal($value), 'storageClass' => 'real'];
        }
        if (is_bool($value)) {
            return ['text' => $value ? '1' : '0', 'storageClass' => 'integer'];
        }

        throw new \InvalidArgumentException('SQLite next261 LIKE affinity value must be scalar or SQLiteBlobValue');
    }

    private static function formatReal(float $value): string
    {
        if (!is_finite($value)) {
            return (string) $value;
        }

        return rtrim(rtrim(sprintf('%.15G', $value), '0'), '.');
    }

    private static function encodingCode(int|string $encoding): int
    {
        if (is_int($encoding)) {
            if (in_array($encoding, [1, 2, 3], true)) {
                return $encoding;
            }
            throw new \InvalidArgumentException('SQLite next261 text encoding must be UTF-8, UTF-16LE, or UTF-16BE');
        }

        return match (strtoupper(str_replace('_', '-', $encoding))) {
            'UTF-8', 'UTF8' => 1,
            'UTF-16LE', 'UTF16LE' => 2,
            'UTF-16BE', 'UTF16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite next261 text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function asciiLower(string $text): string
    {
        return strtr($text, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => $row['rowid'], $rows));
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param array<int,array<string,mixed>> $rows */
    private static function fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }
        ksort($values);

        return $values;
    }
}
