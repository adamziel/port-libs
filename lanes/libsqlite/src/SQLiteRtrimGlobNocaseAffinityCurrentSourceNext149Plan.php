<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRtrimGlobNocaseAffinityCurrentSourceNext149Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameValuePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        float|int|string $minimumNumeric,
        float|int|string $maximumNumeric,
        string $currentSource = 'main.wp_options@148',
        string $nextSource = 'main.wp_options@149',
        int $currentSchemaCookie = 148,
        int $nextSchemaCookie = 149,
        int $currentCollationVersion = 14,
        int $nextCollationVersion = 15,
    ): array {
        $plan = SQLiteUtf16RtrimGlobAffinityCurrentSourceNextPlan::wordpressOptionNameValuePlan(
            $currentRows,
            $nextRows,
            $pattern,
            $minimumNumeric,
            $maximumNumeric,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
            $currentCollationVersion,
            $nextCollationVersion,
        );

        $classes = self::globClasses($pattern);
        $plan['status'] = 'rtrim-glob-nocase-affinity-current-source-next149-ready';
        $plan['expression'] = 'rtrim(option_name) COLLATE NOCASE GLOB ? AND option_value NUMERIC BETWEEN ? AND ?';
        $plan['nameCollation'] = 'RTRIM+NOCASE index key';
        $plan['residualCollation'] = 'BINARY bytewise GLOB';
        $plan['globCharacterClasses'] = $classes;
        $plan['globCharacterClassCount'] = count($classes);
        $plan['hasNegatedGlobClass'] = in_array(true, array_column($classes, 'negated'), true);
        $plan['rangeLowerComesBeforeFirstGlobClass'] = $plan['range']['lowerInclusive'] ?? null;
        $plan['globResidualKeepsCaseSensitiveClasses'] = true;
        $plan['nocaseRtrimIndexCanOnlyChooseCandidates'] = true;
        $plan['dependency_closure'] = 'no new support component needed; reuses UTF text source decoding, RTRIM+NOCASE comparison keys, bytewise GLOB character-class residuals, and numeric affinity coercion';
        $plan['dependencies'] = [
            'sqlite-rtrim-expression-index',
            'sqlite-nocase-collation-candidate-key',
            'sqlite-glob-character-class-bytewise-residual',
            'sqlite-numeric-affinity',
            'sqlite-encoding-source-cursor',
            'sqlite-current-source-next149',
        ];

        if ($classes !== [] && !in_array('glob-character-class-residual', $plan['invalidationReasons'], true)) {
            $plan['invalidationReasons'][] = 'glob-character-class-residual';
            $plan['cursorInvalidated'] = true;
            $plan['cursorReusable'] = false;
        }

        return $plan;
    }

    /**
     * @return list<array{raw:string,negated:bool,ranges:list<string>,characters:list<string>}>
     */
    private static function globClasses(string $pattern): array
    {
        $characters = self::characters($pattern);
        $classes = [];
        $count = count($characters);
        for ($offset = 0; $offset < $count; $offset++) {
            if ($characters[$offset] !== '[') {
                continue;
            }

            $parsed = self::readClass($characters, $offset);
            if ($parsed === null) {
                continue;
            }
            $classes[] = $parsed['class'];
            $offset = $parsed['nextOffset'] - 1;
        }

        return $classes;
    }

    /**
     * @param list<string> $characters
     * @return null|array{class:array{raw:string,negated:bool,ranges:list<string>,characters:list<string>},nextOffset:int}
     */
    private static function readClass(array $characters, int $offset): ?array
    {
        $count = count($characters);
        if ($offset + 1 >= $count) {
            return null;
        }

        $index = $offset + 1;
        $negated = false;
        if ($characters[$index] === '^') {
            $negated = true;
            $index++;
        }

        $raw = '[' . ($negated ? '^' : '');
        $literals = [];
        $ranges = [];
        $first = true;
        while ($index < $count) {
            $character = $characters[$index];
            if ($character === ']' && !$first) {
                $raw .= ']';
                return [
                    'class' => [
                        'raw' => $raw,
                        'negated' => $negated,
                        'ranges' => $ranges,
                        'characters' => array_values(array_unique($literals)),
                    ],
                    'nextOffset' => $index + 1,
                ];
            }
            if ($index + 2 < $count && $characters[$index + 1] === '-' && $characters[$index + 2] !== ']') {
                $range = $character . '-' . $characters[$index + 2];
                $ranges[] = $range;
                $raw .= $range;
                $index += 3;
                $first = false;
                continue;
            }

            $literals[] = $character;
            $raw .= $character;
            $index++;
            $first = false;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function characters(string $text): array
    {
        if ($text === '') {
            return [];
        }

        preg_match_all('/./us', $text, $matches);
        return $matches[0] ?? str_split($text);
    }
}
