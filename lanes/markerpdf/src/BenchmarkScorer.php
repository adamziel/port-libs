<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class BenchmarkScorer
{
    private const CHUNK_MIN_CHARS = 25;

    /**
     * @return list<string>
     */
    public function chunkText(string $text, int $chunkLength = 500): array
    {
        if ($chunkLength < 1) {
            throw new InvalidArgumentException('Chunk length must be positive.');
        }

        $chars = $this->characters($text);
        $chunks = [];
        for ($offset = 0, $count = count($chars); $offset < $count; $offset += $chunkLength) {
            $chunk = implode('', array_slice($chars, $offset, $chunkLength));
            if (trim($chunk) !== '' && $this->length($chunk) > self::CHUNK_MIN_CHARS) {
                $chunks[] = $chunk;
            }
        }

        return $chunks;
    }

    /**
     * @param list<string> $hypothesisChunks
     * @param list<string> $referenceChunks
     * @return list<float>
     */
    public function overlapScore(array $hypothesisChunks, array $referenceChunks): array
    {
        if ($hypothesisChunks === [] || $referenceChunks === []) {
            throw new InvalidArgumentException('Scoring requires at least one hypothesis and reference chunk.');
        }

        $lengthModifier = count($hypothesisChunks) / count($referenceChunks);
        $searchDistance = max(intdiv(count($referenceChunks), 5), 10);
        $chunkScores = [];

        foreach ($hypothesisChunks as $index => $hypothesisChunk) {
            $maxScore = 0.0;
            $indexOffset = (int) ($index * $lengthModifier);
            $start = max(0, $indexOffset - $searchDistance);
            $end = min(count($referenceChunks), $indexOffset + $searchDistance);

            for ($referenceIndex = $start; $referenceIndex < $end; $referenceIndex++) {
                $score = $this->ratio($hypothesisChunk, $referenceChunks[$referenceIndex], 30.0) / 100.0;
                if ($score > $maxScore) {
                    $maxScore = $score;
                }
            }

            $chunkScores[] = $maxScore;
        }

        return $chunkScores;
    }

    public function scoreText(string $hypothesis, string $reference, int $chunkLength = 500): float
    {
        $chunkScores = $this->overlapScore(
            $this->chunkText($hypothesis, $chunkLength),
            $this->chunkText($reference, $chunkLength)
        );

        return array_sum($chunkScores) / count($chunkScores);
    }

    public function ratio(string $left, string $right, float $scoreCutoff = 0.0): float
    {
        if ($left === '' && $right === '') {
            return 100.0;
        }
        if ($left === '' || $right === '') {
            return 0.0;
        }

        $leftChars = $this->characters($left);
        $rightChars = $this->characters($right);
        $score = (200.0 * $this->lcsLength($leftChars, $rightChars)) / (count($leftChars) + count($rightChars));

        return $score >= $scoreCutoff ? $score : 0.0;
    }

    /**
     * @return list<string>
     */
    private function characters(string $text): array
    {
        if ($text === '') {
            return [];
        }
        if (function_exists('mb_str_split')) {
            return mb_str_split($text, 1, 'UTF-8');
        }

        return str_split($text);
    }

    private function length(string $text): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($text, 'UTF-8');
        }

        return strlen($text);
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     */
    private function lcsLength(array $left, array $right): int
    {
        $previous = array_fill(0, count($right) + 1, 0);
        foreach ($left as $leftChar) {
            $current = [0];
            foreach ($right as $index => $rightChar) {
                $current[$index + 1] = $leftChar === $rightChar
                    ? $previous[$index] + 1
                    : max($previous[$index + 1], $current[$index]);
            }
            $previous = $current;
        }

        return $previous[count($right)];
    }
}
