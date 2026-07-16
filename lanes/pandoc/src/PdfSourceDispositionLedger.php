<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

use InvalidArgumentException;

/**
 * Account for each source text occurrence after PDF semantic inference.
 *
 * PdfTextFidelityLedger intentionally answers aggregate conservation
 * questions. This ledger answers the complementary occurrence question:
 * did every individual source line become output, receive an evidenced
 * suppression/replacement disposition, or remain unresolved?
 *
 * Automatic matching is deliberately conservative. It consumes emitted
 * token and significant-character inventories once, so duplicated source
 * lines cannot all claim the same emitted occurrence. Destructive or visual
 * replacement dispositions must be supplied explicitly with a reason.
 */
final class PdfSourceDispositionLedger
{
    private const SAMPLE_LIMIT = 32;

    /** @var array<string, true> */
    private const ALLOWED_DISPOSITIONS = [
        'emitted' => true,
        'boundary-repair' => true,
        'semantic-structure' => true,
        'actual-text' => true,
        'visual-replacement' => true,
        'artifact' => true,
        'duplicate' => true,
        'running-furniture' => true,
        'original-placeholder' => true,
        'unresolved' => true,
    ];

    /** @var array<string, true> */
    private const EXPLICIT_REASON_REQUIRED = [
        'boundary-repair' => true,
        'semantic-structure' => true,
        'actual-text' => true,
        'visual-replacement' => true,
        'artifact' => true,
        'duplicate' => true,
        'running-furniture' => true,
        'original-placeholder' => true,
    ];

    /**
     * @param list<array<string,mixed>|string> $sourceLineItems
     * @param list<AstNode> $blocks
     * @param array<string, array{disposition:string,reason?:string,evidence?:array<string,mixed>,textProjection?:string,allowOrderChange?:bool}|string> $explicitDispositions
     * @return array<string,mixed>
     */
    public static function fromSourceLineItems(
        array $sourceLineItems,
        array $blocks,
        array $explicitDispositions = []
    ): array {
        // Walk the AST independently for inventory and ordered-character
        // hashing instead of retaining a second copy of every emitted text
        // chunk. Large PDFs commonly contain tens of thousands of nodes.
        $emitted = self::inventoryFromChunks(self::textChunksFromNodes($blocks));
        $emittedSignificant = self::significantCharacterSummary(self::textChunksFromNodes($blocks));
        $tokenCounts = $emitted['tokens'];
        $characterCounts = $emitted['characters'];
        $sourceSignificantDigest = hash_init('sha256');
        $sourceSignificantCharacterBytes = 0;
        $counts = [];
        $pageCounts = [];
        $unresolvedSample = [];
        $suppressedSample = [];
        $occurrenceCount = 0;
        $evidencedOrderChangeOccurrenceCount = 0;
        $digest = hash_init('sha256');

        foreach ($sourceLineItems as $index => $item) {
            $record = is_array($item) ? $item : ['text' => (string) $item];
            $text = is_string($record['text'] ?? null) ? trim($record['text']) : '';
            if ($text === '') {
                continue;
            }
            $page = max(1, (int) ($record['page'] ?? 1));
            $id = self::sourceOccurrenceId($record, $index, $text);
            $explicit = self::normalizedExplicitDisposition($explicitDispositions[$id] ?? null, $id);
            if ($explicit === null) {
                $inventory = self::inventoryFromChunks([$text]);
                $matched = self::canConsume($tokenCounts, $inventory['tokens'])
                    && self::canConsume($characterCounts, $inventory['characters']);
                $disposition = $matched ? 'emitted' : 'unresolved';
                $reason = $matched
                    ? 'The emitted AST contains one unclaimed character-equivalent occurrence.'
                    : 'No unclaimed character-equivalent emitted occurrence or explicit disposition was available.';
                $evidence = ['method' => 'conservative-inventory-consumption'];
                if ($matched) {
                    self::consume($tokenCounts, $inventory['tokens']);
                    self::consume($characterCounts, $inventory['characters']);
                }
            } else {
                $disposition = $explicit['disposition'];
                $reason = $explicit['reason'];
                $evidence = $explicit['evidence'];
                $accountingText = $explicit['textProjection'] ?? $text;
                if (isset(self::EXPLICIT_REASON_REQUIRED[$disposition]) && $reason === '') {
                    throw new InvalidArgumentException(
                        'PDF source occurrence ' . $id . ' requires evidence for disposition ' . $disposition . '.'
                    );
                }
                if (in_array($disposition, ['emitted', 'boundary-repair', 'semantic-structure'], true)) {
                    $inventory = self::inventoryFromChunks([$accountingText]);
                    if (self::canConsume($tokenCounts, $inventory['tokens'])
                        && self::canConsume($characterCounts, $inventory['characters'])) {
                        self::consume($tokenCounts, $inventory['tokens']);
                        self::consume($characterCounts, $inventory['characters']);
                    } else {
                        $disposition = 'unresolved';
                        $reason = 'The evidenced text projection could not be reconciled with one unclaimed emitted occurrence.';
                        $evidence['requestedDisposition'] = $explicit['disposition'];
                    }
                }
                if ($disposition !== 'unresolved' && $explicit['allowOrderChange']) {
                    $evidencedOrderChangeOccurrenceCount++;
                }
            }

            $occurrenceCount++;
            if (!isset(self::EXPLICIT_REASON_REQUIRED[$disposition])) {
                $sourceSignificantCharacterBytes += self::updateSignificantCharacterDigest(
                    $sourceSignificantDigest,
                    $disposition === 'unresolved' || $explicit === null
                        ? $text
                        : ($explicit['textProjection'] ?? $text)
                );
            } elseif (in_array($disposition, ['boundary-repair', 'semantic-structure'], true)) {
                $sourceSignificantCharacterBytes += self::updateSignificantCharacterDigest(
                    $sourceSignificantDigest,
                    $explicit['textProjection'] ?? $text
                );
            }
            $counts[$disposition] = ($counts[$disposition] ?? 0) + 1;
            $pageCounts[$page][$disposition] = ($pageCounts[$page][$disposition] ?? 0) + 1;
            hash_update($digest, $id . "\0" . $disposition . "\0" . $reason . "\n");
            $sample = [
                'id' => $id,
                'page' => $page,
                'text' => self::sampleText($text),
                'disposition' => $disposition,
                'reason' => $reason,
            ];
            if ($evidence !== []) {
                $sample['evidence'] = $evidence;
            }
            if ($disposition === 'unresolved' && count($unresolvedSample) < self::SAMPLE_LIMIT) {
                $unresolvedSample[] = $sample;
            } elseif (isset(self::EXPLICIT_REASON_REQUIRED[$disposition])
                && count($suppressedSample) < self::SAMPLE_LIMIT) {
                $suppressedSample[] = $sample;
            }
        }

        ksort($counts);
        ksort($pageCounts, SORT_NUMERIC);
        foreach ($pageCounts as &$pageSummary) {
            ksort($pageSummary);
        }
        unset($pageSummary);
        $unresolvedCount = (int) ($counts['unresolved'] ?? 0);
        $sourceSignificantCharacterDigest = hash_final($sourceSignificantDigest);
        $emittedSignificantCharacterDigest = $emittedSignificant['digest'];
        $exactOrderedSignificantCharactersPreserved = $sourceSignificantCharacterBytes === $emittedSignificant['bytes']
            && hash_equals($sourceSignificantCharacterDigest, $emittedSignificantCharacterDigest);
        $remainingTokenCount = array_sum($tokenCounts);
        $remainingCharacterCount = array_sum($characterCounts);
        $evidencedOrderChangePreserved = !$exactOrderedSignificantCharactersPreserved
            && $unresolvedCount === 0
            && $evidencedOrderChangeOccurrenceCount > 0
            && $remainingTokenCount === 0
            && $remainingCharacterCount === 0
            && $sourceSignificantCharacterBytes === $emittedSignificant['bytes'];
        $orderedSignificantCharactersPreserved = $exactOrderedSignificantCharactersPreserved
            || $evidencedOrderChangePreserved;

        return [
            'version' => 1,
            'sourceOccurrenceCount' => $occurrenceCount,
            'dispositionedOccurrenceCount' => array_sum($counts),
            'resolvedOccurrenceCount' => max(0, $occurrenceCount - $unresolvedCount),
            'unresolvedOccurrenceCount' => $unresolvedCount,
            'allOccurrencesDispositioned' => array_sum($counts) === $occurrenceCount,
            'allOccurrencesResolved' => $unresolvedCount === 0,
            'orderedSignificantCharactersPreserved' => $orderedSignificantCharactersPreserved,
            'orderedSignificantCharacterBasis' => $exactOrderedSignificantCharactersPreserved
                ? 'source-order-exact'
                : ($evidencedOrderChangePreserved ? 'evidenced-layout-reorder' : 'mismatch'),
            'evidencedOrderChangeOccurrenceCount' => $evidencedOrderChangeOccurrenceCount,
            'unclaimedEmittedTokenCount' => $remainingTokenCount,
            'unclaimedEmittedSignificantCharacterCount' => $remainingCharacterCount,
            'sourceSignificantCharacterBytes' => $sourceSignificantCharacterBytes,
            'emittedSignificantCharacterBytes' => $emittedSignificant['bytes'],
            'sourceSignificantCharacterDigest' => $sourceSignificantCharacterDigest,
            'emittedSignificantCharacterDigest' => $emittedSignificantCharacterDigest,
            'dispositionCounts' => $counts,
            'pageDispositionCounts' => $pageCounts,
            'unresolvedOccurrenceSample' => $unresolvedSample,
            'evidencedSuppressionSample' => $suppressedSample,
            'dispositionDigest' => hash_final($digest),
        ];
    }

    /**
     * @param array<string,mixed>|string|null $value
     * @return array{disposition:string,reason:string,evidence:array<string,mixed>,textProjection:?string,allowOrderChange:bool}|null
     */
    private static function normalizedExplicitDisposition(array|string|null $value, string $id): ?array
    {
        if ($value === null) {
            return null;
        }
        $record = is_string($value) ? ['disposition' => $value] : $value;
        $disposition = is_string($record['disposition'] ?? null) ? $record['disposition'] : '';
        if (!isset(self::ALLOWED_DISPOSITIONS[$disposition])) {
            throw new InvalidArgumentException('Unknown PDF source disposition for ' . $id . '.');
        }

        return [
            'disposition' => $disposition,
            'reason' => is_string($record['reason'] ?? null) ? trim($record['reason']) : '',
            'evidence' => is_array($record['evidence'] ?? null) ? $record['evidence'] : [],
            'textProjection' => is_string($record['textProjection'] ?? null) ? $record['textProjection'] : null,
            'allowOrderChange' => ($record['allowOrderChange'] ?? false) === true,
        ];
    }

    /** @param array<string,mixed> $record */
    private static function sourceOccurrenceId(array $record, int $index, string $text): string
    {
        if (is_string($record['id'] ?? null) && $record['id'] !== '') {
            return $record['id'];
        }
        $identity = json_encode([
            'page' => max(1, (int) ($record['page'] ?? 1)),
            'stream' => max(1, (int) ($record['stream'] ?? $index + 1)),
            'index' => $index,
            'text' => $text,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return 'line-' . substr(hash('sha256', is_string($identity) ? $identity : $text), 0, 24);
    }

    /**
     * @param iterable<string> $chunks
     * @return array{tokens:array<string,int>,characters:array<string,int>}
     */
    private static function inventoryFromChunks(iterable $chunks): array
    {
        $tokens = [];
        $characters = [];
        foreach ($chunks as $chunk) {
            if (!is_string($chunk) || $chunk === '') {
                continue;
            }
            $normalized = self::normalizeText($chunk);
            $matched = preg_match_all(
                "/[\p{L}\p{M}\p{N}]+(?:['\x{2019}][\p{L}\p{M}\p{N}]+)*/u",
                $normalized,
                $tokenMatches
            );
            if ($matched !== false) {
                foreach ($tokenMatches[0] as $token) {
                    $tokens[$token] = ($tokens[$token] ?? 0) + 1;
                }
            }
            $offset = 0;
            $length = strlen($normalized);
            while ($offset < $length) {
                $found = preg_match('/[^\s\p{Cc}\p{Cf}]/u', $normalized, $match, PREG_OFFSET_CAPTURE, $offset);
                if ($found !== 1) {
                    break;
                }
                $character = (string) $match[0][0];
                $byteOffset = (int) $match[0][1];
                $characters[$character] = ($characters[$character] ?? 0) + 1;
                $offset = $byteOffset + strlen($character);
            }
        }

        return compact('tokens', 'characters');
    }

    private static function normalizeText(string $text): string
    {
        $text = str_replace(["\u{00AD}", "\u{2060}"], '', $text);
        if (class_exists('Normalizer')) {
            // Canonical normalization joins equivalent combining sequences,
            // but deliberately keeps compatibility characters such as ²,
            // ﬀ, and circled digits distinct. An occurrence disposition is
            // allowed to prove boundary/order changes, never substitutions.
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $text = $normalized;
            }
        }

        return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    }

    /** @param iterable<string> $chunks @return array{bytes:int,digest:string} */
    private static function significantCharacterSummary(iterable $chunks): array
    {
        $digest = hash_init('sha256');
        $bytes = 0;
        foreach ($chunks as $chunk) {
            if (!is_string($chunk) || $chunk === '') {
                continue;
            }
            $bytes += self::updateSignificantCharacterDigest($digest, $chunk);
        }

        return ['bytes' => $bytes, 'digest' => hash_final($digest)];
    }

    /** @param \HashContext $digest */
    private static function updateSignificantCharacterDigest(object $digest, string $chunk): int
    {
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($chunk, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $chunk = $normalized;
            }
        }
        $significant = preg_replace('/[\s\p{Cc}\p{Cf}]+/u', '', $chunk) ?? $chunk;
        hash_update($digest, $significant);

        return strlen($significant);
    }

    /** @param array<string,int> $available @param array<string,int> $needed */
    private static function canConsume(array $available, array $needed): bool
    {
        foreach ($needed as $value => $count) {
            if (($available[$value] ?? 0) < $count) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,int> $available @param array<string,int> $needed */
    private static function consume(array &$available, array $needed): void
    {
        foreach ($needed as $value => $count) {
            $remaining = ($available[$value] ?? 0) - $count;
            if ($remaining > 0) {
                $available[$value] = $remaining;
            } else {
                unset($available[$value]);
            }
        }
    }

    /** @param list<AstNode> $nodes @return iterable<string> */
    private static function textChunksFromNodes(array $nodes): iterable
    {
        foreach ($nodes as $node) {
            if ($node instanceof AstNode) {
                yield from self::textChunksFromNode($node);
            }
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

    private static function sampleText(string $text): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, 160, 'UTF-8');
        }

        return substr($text, 0, 160);
    }
}
