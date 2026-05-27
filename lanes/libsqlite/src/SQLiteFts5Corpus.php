<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteFts5Corpus
{
    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $columns
     * @return list<array<string, mixed>>
     */
    public static function search(
        array $rows,
        array $columns,
        string $query,
        array $options = [],
    ): array {
        $tokens = self::queryTokens($query);
        $columnWeights = self::columnWeights($columns, $options['columnWeights'] ?? []);
        $prefix = (bool) ($options['prefix'] ?? false);
        $phrase = (bool) ($options['phrase'] ?? false);
        $start = (string) ($options['start'] ?? '<b>');
        $end = (string) ($options['end'] ?? '</b>');
        $ellipsis = (string) ($options['ellipsis'] ?? '...');
        $snippetColumn = (string) ($options['snippetColumn'] ?? ($columns[0] ?? ''));
        $snippetTokens = (int) ($options['snippetTokens'] ?? 12);

        if ($columns === []) {
            throw new \InvalidArgumentException('FTS5 corpus search requires at least one indexed column');
        }
        if ($tokens === []) {
            throw new \InvalidArgumentException('FTS5 corpus search requires at least one query token');
        }
        if (!in_array($snippetColumn, $columns, true)) {
            throw new \InvalidArgumentException('FTS5 snippet column must be indexed');
        }

        $documentFrequencies = self::documentFrequencies($rows, $columns, $tokens, $prefix);
        $averageLength = max(1.0, self::averageDocumentLength($rows, $columns));
        $matches = [];

        foreach ($rows as $index => $row) {
            $columnTexts = self::columnTexts($row, $columns);
            if (!self::matches($columnTexts, $tokens, $prefix, $phrase)) {
                continue;
            }

            $rank = self::bm25($columnTexts, $tokens, $documentFrequencies, count($rows), $averageLength, $columnWeights, $prefix);
            $result = $row;
            $result['fts5_rank'] = $rank;
            $result['fts5_snippet'] = self::snippet((string) ($row[$snippetColumn] ?? ''), $tokens, $prefix, $start, $end, $ellipsis, $snippetTokens);
            $result['fts5_match_count'] = self::totalMatchCount($columnTexts, $tokens, $prefix);
            $result['fts5_source_index'] = $index;
            $matches[] = $result;
        }

        usort($matches, static function (array $left, array $right): int {
            $rankOrder = $left['fts5_rank'] <=> $right['fts5_rank'];
            if ($rankOrder !== 0) {
                return $rankOrder;
            }

            return $left['fts5_source_index'] <=> $right['fts5_source_index'];
        });

        return $matches;
    }

    /**
     * @return list<string>
     */
    public static function queryTokens(string $query): array
    {
        preg_match_all('/"([^"]+)"|([\\p{L}\\p{N}_]+)\\*?/u', $query, $matches, PREG_SET_ORDER);
        $tokens = [];
        foreach ($matches as $match) {
            $text = $match[1] !== '' ? $match[1] : $match[2];
            foreach (self::tokenize($text) as $token) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    /**
     * @return list<string>
     */
    public static function tokenize(string $text): array
    {
        $normalized = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        preg_match_all('/[\\p{L}\\p{N}_]+/u', $normalized, $matches);

        return $matches[0];
    }

    /**
     * @param list<string> $tokens
     */
    public static function snippet(
        string $text,
        array $tokens,
        bool $prefix = false,
        string $start = '<b>',
        string $end = '</b>',
        string $ellipsis = '...',
        int $tokenLimit = 12,
    ): string {
        if ($tokenLimit < 1) {
            throw new \InvalidArgumentException('FTS5 snippet token limit must be positive');
        }

        $parts = self::tokenParts($text);
        $tokenPositions = [];
        foreach ($parts as $position => $part) {
            if ($part['token'] !== null) {
                $tokenPositions[] = $position;
            }
        }

        $firstMatchToken = null;
        foreach ($tokenPositions as $tokenIndex => $partPosition) {
            if (self::tokenMatchesAny((string) $parts[$partPosition]['token'], $tokens, $prefix)) {
                $firstMatchToken = $tokenIndex;
                break;
            }
        }

        if ($firstMatchToken === null) {
            return implode('', array_column(array_slice($parts, 0, $tokenLimit), 'text'));
        }

        $startToken = max(0, $firstMatchToken - intdiv($tokenLimit, 3));
        $endToken = min(count($tokenPositions) - 1, $startToken + $tokenLimit - 1);
        $startPart = $tokenPositions[$startToken];
        $endPart = $tokenPositions[$endToken];
        $slice = array_slice($parts, $startPart, $endPart - $startPart + 1);

        $rendered = '';
        foreach ($slice as $part) {
            $piece = (string) $part['text'];
            if ($part['token'] !== null && self::tokenMatchesAny((string) $part['token'], $tokens, $prefix)) {
                $piece = $start . $piece . $end;
            }
            $rendered .= $piece;
        }

        return ($startToken > 0 ? $ellipsis : '') . $rendered . ($endToken < count($tokenPositions) - 1 ? $ellipsis : '');
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $columns
     * @return array<string, string>
     */
    private static function columnTexts(array $row, array $columns): array
    {
        $texts = [];
        foreach ($columns as $column) {
            $value = $row[$column] ?? '';
            $texts[$column] = is_scalar($value) || $value === null ? (string) $value : '';
        }

        return $texts;
    }

    /**
     * @param array<string, string> $columnTexts
     * @param list<string> $tokens
     */
    private static function matches(array $columnTexts, array $tokens, bool $prefix, bool $phrase): bool
    {
        $documentTokens = [];
        foreach ($columnTexts as $text) {
            $documentTokens = array_merge($documentTokens, self::tokenize($text));
        }

        if ($phrase) {
            return self::containsPhrase($documentTokens, $tokens, $prefix);
        }

        foreach ($tokens as $token) {
            if (!self::tokenListContains($documentTokens, $token, $prefix)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $documentTokens
     * @param list<string> $queryTokens
     */
    private static function containsPhrase(array $documentTokens, array $queryTokens, bool $prefix): bool
    {
        $length = count($queryTokens);
        if ($length === 0 || count($documentTokens) < $length) {
            return false;
        }

        for ($offset = 0; $offset <= count($documentTokens) - $length; $offset++) {
            for ($index = 0; $index < $length; $index++) {
                if (!self::tokenMatches($documentTokens[$offset + $index], $queryTokens[$index], $prefix)) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * @param list<string> $tokens
     */
    private static function tokenListContains(array $tokens, string $queryToken, bool $prefix): bool
    {
        foreach ($tokens as $token) {
            if (self::tokenMatches($token, $queryToken, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $queryTokens
     */
    private static function tokenMatchesAny(string $token, array $queryTokens, bool $prefix): bool
    {
        foreach ($queryTokens as $queryToken) {
            if (self::tokenMatches($token, $queryToken, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function tokenMatches(string $token, string $queryToken, bool $prefix): bool
    {
        return $prefix ? str_starts_with($token, $queryToken) : $token === $queryToken;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $columns
     * @param list<string> $tokens
     * @return array<string, int>
     */
    private static function documentFrequencies(array $rows, array $columns, array $tokens, bool $prefix): array
    {
        $frequencies = array_fill_keys($tokens, 0);
        foreach ($rows as $row) {
            $documentTokens = [];
            foreach (self::columnTexts($row, $columns) as $text) {
                $documentTokens = array_merge($documentTokens, self::tokenize($text));
            }
            foreach (array_keys($frequencies) as $token) {
                if (self::tokenListContains($documentTokens, (string) $token, $prefix)) {
                    $frequencies[$token]++;
                }
            }
        }

        return $frequencies;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $columns
     */
    private static function averageDocumentLength(array $rows, array $columns): float
    {
        if ($rows === []) {
            return 1.0;
        }

        $total = 0;
        foreach ($rows as $row) {
            foreach (self::columnTexts($row, $columns) as $text) {
                $total += count(self::tokenize($text));
            }
        }

        return max(1.0, $total / count($rows));
    }

    /**
     * @param array<string, string> $columnTexts
     * @param list<string> $tokens
     * @param array<string, int> $documentFrequencies
     * @param array<string, float> $columnWeights
     */
    private static function bm25(
        array $columnTexts,
        array $tokens,
        array $documentFrequencies,
        int $documentCount,
        float $averageLength,
        array $columnWeights,
        bool $prefix,
    ): float {
        $score = 0.0;
        $length = 0;
        foreach ($columnTexts as $text) {
            $length += count(self::tokenize($text));
        }
        $length = max(1, $length);

        foreach ($tokens as $token) {
            $termFrequency = 0.0;
            foreach ($columnTexts as $column => $text) {
                $matches = self::matchCount(self::tokenize($text), $token, $prefix);
                $termFrequency += $matches * ($columnWeights[$column] ?? 1.0);
            }
            if ($termFrequency <= 0.0) {
                continue;
            }

            $df = max(0, $documentFrequencies[$token] ?? 0);
            $idf = log((($documentCount - $df + 0.5) / ($df + 0.5)) + 1.0);
            $denominator = $termFrequency + 1.2 * (1 - 0.75 + 0.75 * ($length / $averageLength));
            $score -= $idf * (($termFrequency * 2.2) / $denominator);
        }

        return round($score, 6);
    }

    /**
     * @param array<string, string> $columnTexts
     * @param list<string> $tokens
     */
    private static function totalMatchCount(array $columnTexts, array $tokens, bool $prefix): int
    {
        $count = 0;
        foreach ($tokens as $token) {
            foreach ($columnTexts as $text) {
                $count += self::matchCount(self::tokenize($text), $token, $prefix);
            }
        }

        return $count;
    }

    /**
     * @param list<string> $tokens
     */
    private static function matchCount(array $tokens, string $queryToken, bool $prefix): int
    {
        $count = 0;
        foreach ($tokens as $token) {
            if (self::tokenMatches($token, $queryToken, $prefix)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param list<string> $columns
     * @param mixed $weights
     * @return array<string, float>
     */
    private static function columnWeights(array $columns, mixed $weights): array
    {
        $normalized = [];
        foreach ($columns as $column) {
            $weight = is_array($weights) && array_key_exists($column, $weights) ? $weights[$column] : 1.0;
            if (!is_int($weight) && !is_float($weight)) {
                throw new \InvalidArgumentException('FTS5 column weights must be numeric');
            }
            $normalized[$column] = (float) $weight;
        }

        return $normalized;
    }

    /**
     * @return list<array{text:string, token:?string}>
     */
    private static function tokenParts(string $text): array
    {
        preg_match_all('/[\\p{L}\\p{N}_]+|[^\\p{L}\\p{N}_]+/u', $text, $matches);
        $parts = [];
        foreach ($matches[0] as $part) {
            $tokens = self::tokenize($part);
            $parts[] = [
                'text' => $part,
                'token' => $tokens[0] ?? null,
            ];
        }

        return $parts;
    }
}
