<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Account for text before PDF layout inference and after AST construction.
 *
 * PDF reading order is inherently ambiguous, so this ledger deliberately
 * reports three independent signals:
 *
 * - token coverage catches removed words and numbers;
 * - significant-character coverage survives spacing and word-boundary repair;
 * - token-adjacency coverage makes large reordering visible without claiming
 *   that source-stream order is always the correct reading order.
 *
 * This is an audit boundary, not another repair heuristic.  Later pipeline
 * stages can attach explicit suppression dispositions to source facts; until
 * then, anything absent from the emitted AST remains unresolved.
 */
final class PdfTextFidelityLedger
{
    private const SAMPLE_LIMIT = 32;

    /**
     * @param list<array{text:string, page?:int, stream?:int}|string> $sourceLineItems
     * @param list<AstNode> $blocks
     * @return array<string, mixed>
     */
    public static function fromSourceLineItems(array $sourceLineItems, array $blocks): array
    {
        $sourceLines = [];
        foreach ($sourceLineItems as $item) {
            $text = is_array($item) ? ($item['text'] ?? '') : $item;
            if (is_string($text) && $text !== '') {
                $sourceLines[] = $text;
            }
        }

        return self::fromText(
            implode("\n", $sourceLines),
            self::textFromNodes($blocks)
        );
    }

    /**
     * Public for focused audit tools and fixtures which already have two text
     * projections and do not need to construct a PDF AST.
     *
     * @return array<string, mixed>
     */
    public static function fromText(string $sourceText, string $emittedText): array
    {
        $sourceText = self::normalizeText($sourceText);
        $emittedText = self::normalizeText($emittedText);
        $sourceTokens = self::tokensFromNormalizedText($sourceText);
        $emittedTokens = self::tokensFromNormalizedText($emittedText);
        $sourceTokenCounts = array_count_values($sourceTokens);
        $emittedTokenCounts = array_count_values($emittedTokens);
        $unresolvedTokens = self::positiveDifference($sourceTokenCounts, $emittedTokenCounts);
        $addedTokens = self::positiveDifference($emittedTokenCounts, $sourceTokenCounts);
        $sourceAdjacencyCounts = self::tokenAdjacencyCounts($sourceTokens);
        $emittedAdjacencyCounts = self::tokenAdjacencyCounts($emittedTokens);
        $unresolvedAdjacencies = self::positiveDifference($sourceAdjacencyCounts, $emittedAdjacencyCounts);
        $sourceTokenCount = count($sourceTokens);
        $emittedTokenCount = count($emittedTokens);
        $unresolvedTokenCount = array_sum($unresolvedTokens);
        $sourceAdjacencyCount = max(0, $sourceTokenCount - 1);
        $unresolvedAdjacencyCount = array_sum($unresolvedAdjacencies);
        $sourceTokenDigest = hash('sha256', implode("\0", $sourceTokens));
        $emittedTokenDigest = hash('sha256', implode("\0", $emittedTokens));
        $unresolvedTokenSample = self::sampleCounts($unresolvedTokens);
        $addedTokenSample = self::sampleCounts($addedTokens);
        unset(
            $sourceTokens,
            $emittedTokens,
            $sourceTokenCounts,
            $emittedTokenCounts,
            $sourceAdjacencyCounts,
            $emittedAdjacencyCounts
        );

        $sourceCharacterInventory = self::significantCharacterInventory($sourceText);
        $emittedCharacterInventory = self::significantCharacterInventory($emittedText);
        $unresolvedCharacters = self::positiveDifference(
            $sourceCharacterInventory['counts'],
            $emittedCharacterInventory['counts']
        );
        $addedCharacters = self::positiveDifference(
            $emittedCharacterInventory['counts'],
            $sourceCharacterInventory['counts']
        );
        $sourceCharacterCount = $sourceCharacterInventory['total'];
        $emittedCharacterCount = $emittedCharacterInventory['total'];
        $unresolvedCharacterCount = array_sum($unresolvedCharacters);
        $sourceAccounted = $unresolvedTokenCount === 0 && $unresolvedCharacterCount === 0;

        return [
            'version' => 1,
            'sourceTokenCount' => $sourceTokenCount,
            'emittedTokenCount' => $emittedTokenCount,
            'accountedSourceTokenCount' => max(0, $sourceTokenCount - $unresolvedTokenCount),
            'unresolvedTokenCount' => $unresolvedTokenCount,
            'addedTokenCount' => array_sum($addedTokens),
            'tokenCoverage' => self::coverage($sourceTokenCount, $unresolvedTokenCount),
            'sourceSignificantCharacterCount' => $sourceCharacterCount,
            'emittedSignificantCharacterCount' => $emittedCharacterCount,
            'accountedSourceSignificantCharacterCount' => max(0, $sourceCharacterCount - $unresolvedCharacterCount),
            'unresolvedSignificantCharacterCount' => $unresolvedCharacterCount,
            'addedSignificantCharacterCount' => array_sum($addedCharacters),
            'significantCharacterCoverage' => self::coverage($sourceCharacterCount, $unresolvedCharacterCount),
            'sourceTokenAdjacencyCount' => $sourceAdjacencyCount,
            'accountedSourceTokenAdjacencyCount' => max(0, $sourceAdjacencyCount - $unresolvedAdjacencyCount),
            'unresolvedTokenAdjacencyCount' => $unresolvedAdjacencyCount,
            'tokenAdjacencyCoverage' => self::coverage($sourceAdjacencyCount, $unresolvedAdjacencyCount),
            'sourceAccounted' => $sourceAccounted,
            'exactProjection' => $sourceAccounted
                && $addedTokens === []
                && $addedCharacters === []
                && $unresolvedAdjacencies === [],
            'unresolvedTokenSample' => $unresolvedTokenSample,
            'addedTokenSample' => $addedTokenSample,
            'unresolvedCharacterSample' => self::sampleCharacterCounts($unresolvedCharacters),
            'sourceTokenDigest' => $sourceTokenDigest,
            'emittedTokenDigest' => $emittedTokenDigest,
        ];
    }

    /**
     * @param list<AstNode> $nodes
     */
    private static function textFromNodes(array $nodes): string
    {
        $parts = [];
        foreach ($nodes as $node) {
            if (!$node instanceof AstNode) {
                continue;
            }
            $text = self::textFromNode($node);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode("\n", $parts);
    }

    private static function textFromNode(AstNode $node): string
    {
        if ($node->type === 'text') {
            return (string) $node->attr('text', '');
        }

        $parts = [];
        foreach ($node->children() as $child) {
            $text = self::textFromNode($child);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode(' ', $parts);
    }

    /** @return list<string> */
    private static function tokensFromNormalizedText(string $text): array
    {
        $matched = preg_match_all(
            "/[\p{L}\p{M}\p{N}]+(?:['\x{2019}][\p{L}\p{M}\p{N}]+)*/u",
            $text,
            $matches
        );

        return $matched === false ? [] : array_values($matches[0]);
    }

    /**
     * Count characters without materializing one PHP string zval per glyph.
     * That distinction matters while a dense PDF AST and diagnostics are
     * already resident under a conventional 128 MB WordPress memory limit.
     *
     * @return array{counts:array<string,int>,total:int}
     */
    private static function significantCharacterInventory(string $text): array
    {
        $counts = [];
        $total = 0;
        $offset = 0;
        $length = strlen($text);
        while ($offset < $length) {
            $matched = preg_match('/[^\s\p{Cc}\p{Cf}]/u', $text, $match, PREG_OFFSET_CAPTURE, $offset);
            if ($matched !== 1) {
                break;
            }
            $character = (string) $match[0][0];
            $byteOffset = (int) $match[0][1];
            $counts[$character] = ($counts[$character] ?? 0) + 1;
            $total++;
            $offset = $byteOffset + strlen($character);
        }

        return compact('counts', 'total');
    }

    private static function normalizeText(string $text): string
    {
        $text = str_replace(["\u{00AD}", "\u{2060}"], '', $text);
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_KC);
            if (is_string($normalized)) {
                $text = $normalized;
            }
        }

        return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    }

    /**
     * @param list<string> $tokens
     * @return array<string,int>
     */
    private static function tokenAdjacencyCounts(array $tokens): array
    {
        $adjacencies = [];
        $count = count($tokens);
        for ($index = 1; $index < $count; $index++) {
            $adjacency = $tokens[$index - 1] . "\0" . $tokens[$index];
            $adjacencies[$adjacency] = ($adjacencies[$adjacency] ?? 0) + 1;
        }

        return $adjacencies;
    }

    /**
     * @param array<string, int> $minuend
     * @param array<string, int> $subtrahend
     * @return array<string, int>
     */
    private static function positiveDifference(array $minuend, array $subtrahend): array
    {
        $difference = [];
        foreach ($minuend as $value => $count) {
            $remaining = $count - ($subtrahend[$value] ?? 0);
            if ($remaining > 0) {
                $difference[$value] = $remaining;
            }
        }

        return $difference;
    }

    private static function coverage(int $sourceCount, int $unresolvedCount): float
    {
        if ($sourceCount === 0) {
            return 1.0;
        }

        return max(0.0, min(1.0, ($sourceCount - $unresolvedCount) / $sourceCount));
    }

    /**
     * @param array<string, int> $counts
     * @return list<array{text:string,count:int}>
     */
    private static function sampleCounts(array $counts): array
    {
        $records = [];
        foreach ($counts as $text => $count) {
            $records[] = ['text' => (string) $text, 'count' => $count];
        }
        usort($records, static fn (array $left, array $right): int =>
            ($right['count'] <=> $left['count']) ?: strcmp($left['text'], $right['text'])
        );

        return array_slice($records, 0, self::SAMPLE_LIMIT);
    }

    /**
     * @param array<string, int> $counts
     * @return list<array{character:string,codePoint:string,count:int}>
     */
    private static function sampleCharacterCounts(array $counts): array
    {
        $records = [];
        foreach (self::sampleCounts($counts) as $record) {
            $records[] = [
                'character' => $record['text'],
                'codePoint' => self::unicodeCodePoint($record['text']),
                'count' => $record['count'],
            ];
        }

        return $records;
    }

    private static function unicodeCodePoint(string $character): string
    {
        if (function_exists('mb_ord')) {
            return sprintf('U+%04X', mb_ord($character, 'UTF-8'));
        }

        $bytes = array_values(unpack('C*', $character) ?: []);
        $first = $bytes[0] ?? 0;
        $codePoint = match (true) {
            $first < 0x80 => $first,
            $first < 0xE0 => (($first & 0x1F) << 6) | (($bytes[1] ?? 0) & 0x3F),
            $first < 0xF0 => (($first & 0x0F) << 12) | ((($bytes[1] ?? 0) & 0x3F) << 6) | (($bytes[2] ?? 0) & 0x3F),
            default => (($first & 0x07) << 18) | ((($bytes[1] ?? 0) & 0x3F) << 12)
                | ((($bytes[2] ?? 0) & 0x3F) << 6) | (($bytes[3] ?? 0) & 0x3F),
        };

        return sprintf('U+%04X', $codePoint);
    }
}
