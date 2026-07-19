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
        $sourceChunks = static function () use ($sourceLineItems): iterable {
            foreach ($sourceLineItems as $item) {
                $text = is_array($item) ? ($item['text'] ?? '') : $item;
                if (is_string($text) && $text !== '') {
                    yield $text;
                }
            }
        };

        return self::fromChunkStreams($sourceChunks(), self::textChunksFromNodes($blocks));
    }

    /**
     * Public for focused audit tools and fixtures which already have two text
     * projections and do not need to construct a PDF AST.
     *
     * @return array<string, mixed>
     */
    public static function fromText(string $sourceText, string $emittedText): array
    {
        return self::fromChunkStreams([$sourceText], [$emittedText]);
    }

    /**
     * The PDF reader already holds positioned facts and the constructed AST.
     * Building two more document-sized strings and two token lists here can
     * exhaust a normal 128 MiB WordPress worker at the very end of an import.
     * Accumulate the same ledger one source line / AST text node at a time.
     *
     * @param iterable<string> $sourceChunks
     * @param iterable<string> $emittedChunks
     * @return array<string, mixed>
     */
    private static function fromChunkStreams(iterable $sourceChunks, iterable $emittedChunks): array
    {
        $source = self::inventoryFromChunks($sourceChunks);
        $emitted = self::reconcileChunksAgainstSourceInventory($emittedChunks, $source);
        $unresolvedTokens = $source['tokenCounts'];
        $addedTokens = $emitted['addedTokenCounts'];
        $unresolvedAdjacencies = $source['adjacencyCounts'];
        $sourceTokenCount = $source['tokenCount'];
        $emittedTokenCount = $emitted['tokenCount'];
        $unresolvedTokenCount = array_sum($unresolvedTokens);
        $sourceAdjacencyCount = max(0, $sourceTokenCount - 1);
        $unresolvedAdjacencyCount = array_sum($unresolvedAdjacencies);
        $unresolvedTokenSample = self::sampleCounts($unresolvedTokens);
        $addedTokenSample = self::sampleCounts($addedTokens);
        $unresolvedCharacters = $source['characterCounts'];
        $addedCharacters = $emitted['addedCharacterCounts'];
        $sourceCharacterCount = $source['characterCount'];
        $emittedCharacterCount = $emitted['characterCount'];
        $unresolvedCharacterCount = array_sum($unresolvedCharacters);
        $sourceAccounted = $unresolvedTokenCount === 0 && $unresolvedCharacterCount === 0;
        $sourceTokenDigest = $source['tokenDigest'];
        $emittedTokenDigest = $emitted['tokenDigest'];
        $addedTokenCount = array_sum($addedTokens);
        $addedCharacterCount = array_sum($addedCharacters);
        $unresolvedCharacterSample = self::sampleCharacterCounts($unresolvedCharacters);
        $exactProjection = $sourceAccounted
            && $addedTokens === []
            && $addedCharacters === []
            && $unresolvedAdjacencies === [];
        unset($source, $emitted);
        // Empty PHP hash tables retain their bucket allocation. None of the
        // complete residual maps is public, so release them after scalarizing
        // the counts/samples instead of carrying their peak capacity through
        // construction of the returned metadata graph.
        unset(
            $unresolvedTokens,
            $addedTokens,
            $unresolvedAdjacencies,
            $unresolvedCharacters,
            $addedCharacters
        );

        return [
            'version' => 1,
            'sourceTokenCount' => $sourceTokenCount,
            'emittedTokenCount' => $emittedTokenCount,
            'accountedSourceTokenCount' => max(0, $sourceTokenCount - $unresolvedTokenCount),
            'unresolvedTokenCount' => $unresolvedTokenCount,
            'addedTokenCount' => $addedTokenCount,
            'tokenCoverage' => self::coverage($sourceTokenCount, $unresolvedTokenCount),
            'sourceSignificantCharacterCount' => $sourceCharacterCount,
            'emittedSignificantCharacterCount' => $emittedCharacterCount,
            'accountedSourceSignificantCharacterCount' => max(0, $sourceCharacterCount - $unresolvedCharacterCount),
            'unresolvedSignificantCharacterCount' => $unresolvedCharacterCount,
            'addedSignificantCharacterCount' => $addedCharacterCount,
            'significantCharacterCoverage' => self::coverage($sourceCharacterCount, $unresolvedCharacterCount),
            'sourceTokenAdjacencyCount' => $sourceAdjacencyCount,
            'accountedSourceTokenAdjacencyCount' => max(0, $sourceAdjacencyCount - $unresolvedAdjacencyCount),
            'unresolvedTokenAdjacencyCount' => $unresolvedAdjacencyCount,
            'tokenAdjacencyCoverage' => self::coverage($sourceAdjacencyCount, $unresolvedAdjacencyCount),
            'sourceAccounted' => $sourceAccounted,
            'exactProjection' => $exactProjection,
            'unresolvedTokenSample' => $unresolvedTokenSample,
            'addedTokenSample' => $addedTokenSample,
            'unresolvedCharacterSample' => $unresolvedCharacterSample,
            'sourceTokenDigest' => $sourceTokenDigest,
            'emittedTokenDigest' => $emittedTokenDigest,
        ];
    }

    /**
     * @param list<AstNode> $nodes
     * @return iterable<string>
     */
    private static function textChunksFromNodes(array $nodes): iterable
    {
        foreach ($nodes as $node) {
            if (!$node instanceof AstNode) {
                continue;
            }
            yield from self::textChunksFromNode($node);
        }
    }

    /** @return iterable<string> */
    private static function textChunksFromNode(AstNode $node): iterable
    {
        if ($node->type === 'text') {
            $text = (string) $node->attr('text', '');
            if ($text !== '') {
                yield $text;
            }

            return;
        }

        foreach ($node->children() as $child) {
            yield from self::textChunksFromNode($child);
        }
    }

    /**
     * @param iterable<string> $chunks
     * @return array{tokenCounts:array<string,int>,adjacencyCounts:array<string,int>,tokenCount:int,tokenDigest:string,characterCounts:array<string,int>,characterCount:int}
     */
    private static function inventoryFromChunks(iterable $chunks): array
    {
        $tokenCounts = [];
        $adjacencyCounts = [];
        $tokenCount = 0;
        $previousToken = null;
        $tokenHash = hash_init('sha256');
        $characterCounts = [];
        $characterCount = 0;
        foreach ($chunks as $chunk) {
            if (!is_string($chunk) || $chunk === '') {
                continue;
            }
            $normalized = self::normalizeText($chunk);
            foreach (self::tokensFromNormalizedText($normalized) as $token) {
                $tokenCounts[$token] = ($tokenCounts[$token] ?? 0) + 1;
                if ($previousToken !== null) {
                    $adjacency = $previousToken . "\0" . $token;
                    $adjacencyCounts[$adjacency] = ($adjacencyCounts[$adjacency] ?? 0) + 1;
                    hash_update($tokenHash, "\0");
                }
                hash_update($tokenHash, $token);
                $previousToken = $token;
                $tokenCount++;
            }
            $characters = self::significantCharacterInventory($normalized);
            $characterCount += $characters['total'];
            foreach ($characters['counts'] as $character => $count) {
                $characterCounts[$character] = ($characterCounts[$character] ?? 0) + $count;
            }
        }

        return [
            'tokenCounts' => $tokenCounts,
            'adjacencyCounts' => $adjacencyCounts,
            'tokenCount' => $tokenCount,
            'tokenDigest' => hash_final($tokenHash),
            'characterCounts' => $characterCounts,
            'characterCount' => $characterCount,
        ];
    }

    /**
     * Consume emitted counts directly from the source inventory. Retaining a
     * second complete token, adjacency, and character inventory until the
     * final differences are calculated doubles the end-of-import peak; dense
     * documents make nearly every adjacency a distinct PHP array key. The
     * residual source maps are exactly the prior positive differences, while
     * only genuinely added token/character counts need separate storage.
     *
     * @param iterable<string> $chunks
     * @param array{tokenCounts:array<string,int>,adjacencyCounts:array<string,int>,tokenCount:int,tokenDigest:string,characterCounts:array<string,int>,characterCount:int} $source
     * @return array{addedTokenCounts:array<string,int>,tokenCount:int,tokenDigest:string,addedCharacterCounts:array<string,int>,characterCount:int}
     */
    private static function reconcileChunksAgainstSourceInventory(iterable $chunks, array &$source): array
    {
        $addedTokenCounts = [];
        $tokenCount = 0;
        $previousToken = null;
        $tokenHash = hash_init('sha256');
        $addedCharacterCounts = [];
        $characterCount = 0;
        foreach ($chunks as $chunk) {
            if (!is_string($chunk) || $chunk === '') {
                continue;
            }
            $normalized = self::normalizeText($chunk);
            foreach (self::tokensFromNormalizedText($normalized) as $token) {
                $availableTokenCount = $source['tokenCounts'][$token] ?? 0;
                if ($availableTokenCount > 1) {
                    $source['tokenCounts'][$token] = $availableTokenCount - 1;
                } elseif ($availableTokenCount === 1) {
                    unset($source['tokenCounts'][$token]);
                } else {
                    $addedTokenCounts[$token] = ($addedTokenCounts[$token] ?? 0) + 1;
                }
                if ($previousToken !== null) {
                    $adjacency = $previousToken . "\0" . $token;
                    $availableAdjacencyCount = $source['adjacencyCounts'][$adjacency] ?? 0;
                    if ($availableAdjacencyCount > 1) {
                        $source['adjacencyCounts'][$adjacency] = $availableAdjacencyCount - 1;
                    } elseif ($availableAdjacencyCount === 1) {
                        unset($source['adjacencyCounts'][$adjacency]);
                    }
                    hash_update($tokenHash, "\0");
                }
                hash_update($tokenHash, $token);
                $previousToken = $token;
                $tokenCount++;
            }
            $characters = self::significantCharacterInventory($normalized);
            $characterCount += $characters['total'];
            foreach ($characters['counts'] as $character => $count) {
                $availableCharacterCount = $source['characterCounts'][$character] ?? 0;
                $consumed = min($availableCharacterCount, $count);
                if ($consumed === $availableCharacterCount) {
                    unset($source['characterCounts'][$character]);
                } else {
                    $source['characterCounts'][$character] = $availableCharacterCount - $consumed;
                }
                $added = $count - $consumed;
                if ($added > 0) {
                    $addedCharacterCounts[$character] = ($addedCharacterCounts[$character] ?? 0) + $added;
                }
            }
        }

        return [
            'addedTokenCounts' => $addedTokenCounts,
            'tokenCount' => $tokenCount,
            'tokenDigest' => hash_final($tokenHash),
            'addedCharacterCounts' => $addedCharacterCounts,
            'characterCount' => $characterCount,
        ];
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
