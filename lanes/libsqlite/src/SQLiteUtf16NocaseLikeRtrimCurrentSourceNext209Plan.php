<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext209Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameAsciiSpaceRtrimPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin%',
        ?string $escape = '!',
        string $currentSource = 'main.wp_options@208',
        string $nextSource = 'main.wp_options@209',
        int $currentSchemaCookie = 208,
        int $nextSchemaCookie = 209,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext200Plan::wordpressOptionNameEscapeRebindPlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentDecoded = self::decodeRows($currentRows);
        $nextDecoded = self::decodeRows($nextRows);
        $currentDiagnostics = self::suffixDiagnostics($currentDecoded);
        $nextDiagnostics = self::suffixDiagnostics($nextDecoded);
        $currentUnicodeCase = self::unicodeCaseDiagnostics($currentDecoded, $pattern, $escape);
        $nextUnicodeCase = self::unicodeCaseDiagnostics($nextDecoded, $pattern, $escape);

        $reasons = $base['invalidationReasons'];
        if ($currentDiagnostics['asciiSpaceTrimmedRowids'] !== $nextDiagnostics['asciiSpaceTrimmedRowids']) {
            $reasons[] = 'ascii-space-rtrim-rowset';
        }
        if ($currentDiagnostics['nonAsciiWhitespacePreservedRowids'] !== $nextDiagnostics['nonAsciiWhitespacePreservedRowids']) {
            $reasons[] = 'non-ascii-whitespace-rtrim-preserved';
        }
        if ($currentUnicodeCase['unicodeCaseVariantRowids'] !== [] || $nextUnicodeCase['unicodeCaseVariantRowids'] !== []) {
            $reasons[] = 'unicode-case-not-folded';
        }
        if ($currentDiagnostics['malformedRowids'] !== [] || $nextDiagnostics['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next209',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* ASCII-space RTRIM only */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $base['currentPrefix'],
            'rangeLowerInclusive' => $base['currentRangeLowerInclusive'],
            'rangeUpperBound' => $base['currentRangeUpperBound'],
            'currentIndexUsable' => $base['currentIndexUsable'],
            'nextIndexUsable' => $base['nextIndexUsable'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'matchedExitedRowids' => $base['matchedExitedRowids'],
            'matchedEnteredRowids' => $base['matchedEnteredRowids'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentAsciiSpaceTrimmedRowids' => $currentDiagnostics['asciiSpaceTrimmedRowids'],
            'nextAsciiSpaceTrimmedRowids' => $nextDiagnostics['asciiSpaceTrimmedRowids'],
            'currentNonAsciiWhitespacePreservedRowids' => $currentDiagnostics['nonAsciiWhitespacePreservedRowids'],
            'nextNonAsciiWhitespacePreservedRowids' => $nextDiagnostics['nonAsciiWhitespacePreservedRowids'],
            'currentTabPreservedRowids' => $currentDiagnostics['tabPreservedRowids'],
            'nextTabPreservedRowids' => $nextDiagnostics['tabPreservedRowids'],
            'currentNbspPreservedRowids' => $currentDiagnostics['nbspPreservedRowids'],
            'nextNbspPreservedRowids' => $nextDiagnostics['nbspPreservedRowids'],
            'currentUnicodeCaseVariantRowids' => $currentUnicodeCase['unicodeCaseVariantRowids'],
            'nextUnicodeCaseVariantRowids' => $nextUnicodeCase['unicodeCaseVariantRowids'],
            'currentUnicodeCaseVariantTexts' => $currentUnicodeCase['unicodeCaseVariantTexts'],
            'nextUnicodeCaseVariantTexts' => $nextUnicodeCase['unicodeCaseVariantTexts'],
            'currentExcludedDecodedRowids' => $base['currentExcludedDecodedRowids'],
            'nextExcludedDecodedRowids' => $base['nextExcludedDecodedRowids'],
            'currentFalsePositiveRowids' => $base['currentFalsePositiveRowids'],
            'nextFalsePositiveRowids' => $base['nextFalsePositiveRowids'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'rtrimTrimsOnlyAsciiSpace' => true,
            'tabSuffixPreservedByRtrim' => true,
            'nbspSuffixPreservedByRtrim' => true,
            'nocaseFoldsAsciiOnly' => true,
            'unicodeCaseVariantsRequireResidualCheck' => true,
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-ascii-space-only',
                'sqlite-nocase-ascii-only',
                'sqlite-current-source-next209',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE range planning, RTRIM expression keys, and residual LIKE matching',
            'non_overlap' => 'next209 covers ASCII-space-only RTRIM and ASCII-only NOCASE diagnostics for UTF-16 LIKE current-source reuse; avoids accepted BOM normalization next206, escape rebind next200, escaped literal/dangling ESCAPE slices, Unicode GLOB ranges, and malformed UTF-16 insert guards',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,array{text:string,rtrimText:string,nocaseKey:string}>
     */
    private static function decodeRows(array $rows): array
    {
        $decoded = [];
        foreach ($rows as $row) {
            if (!isset($row['option_id']) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next209 rows require integer option_id');
            }
            if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next209 rows require option_name_bytes');
            }
            if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next209 rows require integer text_encoding');
            }

            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $decoded[$row['option_id']] = [
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::asciiLower($rtrim),
                ];
            } catch (\InvalidArgumentException) {
                continue;
            }
        }
        ksort($decoded);

        return $decoded;
    }

    /**
     * @param array<int,array{text:string,rtrimText:string,nocaseKey:string}> $decoded
     * @return array{asciiSpaceTrimmedRowids:list<int>,nonAsciiWhitespacePreservedRowids:list<int>,tabPreservedRowids:list<int>,nbspPreservedRowids:list<int>,malformedRowids:list<int>}
     */
    private static function suffixDiagnostics(array $decoded): array
    {
        $asciiSpace = [];
        $nonAsciiWhitespace = [];
        $tabs = [];
        $nbsp = [];
        foreach ($decoded as $rowid => $entry) {
            if ($entry['text'] !== $entry['rtrimText']) {
                $asciiSpace[] = $rowid;
            }
            if (str_ends_with($entry['rtrimText'], "\t")) {
                $tabs[] = $rowid;
                $nonAsciiWhitespace[] = $rowid;
            }
            if (str_ends_with($entry['rtrimText'], "\xc2\xa0")) {
                $nbsp[] = $rowid;
                $nonAsciiWhitespace[] = $rowid;
            }
        }
        $nonAsciiWhitespace = array_values(array_unique($nonAsciiWhitespace));
        sort($asciiSpace);
        sort($nonAsciiWhitespace);
        sort($tabs);
        sort($nbsp);

        return [
            'asciiSpaceTrimmedRowids' => $asciiSpace,
            'nonAsciiWhitespacePreservedRowids' => $nonAsciiWhitespace,
            'tabPreservedRowids' => $tabs,
            'nbspPreservedRowids' => $nbsp,
            'malformedRowids' => [],
        ];
    }

    /**
     * @param array<int,array{text:string,rtrimText:string,nocaseKey:string}> $decoded
     * @return array{unicodeCaseVariantRowids:list<int>,unicodeCaseVariantTexts:array<int,string>}
     */
    private static function unicodeCaseDiagnostics(array $decoded, string $pattern, ?string $escape): array
    {
        $prefix = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false)['prefix'] ?? '';
        $asciiPrefix = self::asciiLower((string) $prefix);
        $rowids = [];
        $texts = [];
        foreach ($decoded as $rowid => $entry) {
            if (str_starts_with(self::unicodeCasePrefixApprox($entry['rtrimText']), $asciiPrefix)
                && !str_starts_with($entry['nocaseKey'], $asciiPrefix)) {
                $rowids[] = $rowid;
                $texts[$rowid] = $entry['rtrimText'];
            }
        }
        sort($rowids);
        ksort($texts);

        return [
            'unicodeCaseVariantRowids' => $rowids,
            'unicodeCaseVariantTexts' => $texts,
        ];
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    private static function unicodeCasePrefixApprox(string $value): string
    {
        $latinCapitalIWithDot = "\xc4\xb0";
        $greekCapitalSigma = "\xce\xa3";
        $greekSmallSigma = "\xcf\x83";

        if (str_starts_with($value, $latinCapitalIWithDot)) {
            return 'i' . self::asciiLower(substr($value, strlen($latinCapitalIWithDot)));
        }
        if (str_starts_with($value, $greekCapitalSigma)) {
            return $greekSmallSigma . self::asciiLower(substr($value, strlen($greekCapitalSigma)));
        }

        return self::asciiLower($value);
    }
}
