<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNext182Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string}|null $lastYielded
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameEscapeReplayPlan(
        array $currentRows,
        array $nextRows,
        string $currentPatternBytes,
        int $currentPatternEncoding,
        string $nextPatternBytes,
        int $nextPatternEncoding,
        ?string $currentEscapeBytes,
        int $currentEscapeEncoding,
        ?string $nextEscapeBytes,
        int $nextEscapeEncoding,
        ?array $lastYielded = null,
        string $currentSource = 'main.wp_options@181',
        string $nextSource = 'main.wp_options@182',
        int $currentSchemaCookie = 181,
        int $nextSchemaCookie = 182,
    ): array {
        $pattern = self::decodeOperand($currentPatternBytes, $currentPatternEncoding, 'current-pattern');
        $nextPattern = self::decodeOperand($nextPatternBytes, $nextPatternEncoding, 'next-pattern');
        $currentEscape = self::decodeEscape($currentEscapeBytes, $currentEscapeEncoding, 'current-escape');
        $nextEscape = self::decodeEscape($nextEscapeBytes, $nextEscapeEncoding, 'next-escape');

        $operandReasons = [];
        $operandErrors = array_filter([
            'currentPattern' => $pattern['error'],
            'nextPattern' => $nextPattern['error'],
            'currentEscape' => $currentEscape['error'],
            'nextEscape' => $nextEscape['error'],
        ]);

        if ($pattern['value'] !== $nextPattern['value']) {
            $operandReasons[] = 'pattern-text-changed';
        }
        if ($currentPatternEncoding !== $nextPatternEncoding) {
            $operandReasons[] = 'pattern-encoding-changed';
        }
        if ($currentPatternBytes !== $nextPatternBytes) {
            $operandReasons[] = 'pattern-bytes-changed';
        }
        if ($currentEscape['value'] !== $nextEscape['value']) {
            $operandReasons[] = 'escape-text-changed';
        }
        if ($currentEscapeEncoding !== $nextEscapeEncoding) {
            $operandReasons[] = 'escape-encoding-changed';
        }
        if ($currentEscapeBytes !== $nextEscapeBytes) {
            $operandReasons[] = 'escape-bytes-changed';
        }
        if ($currentEscape['width'] !== null && $currentEscape['width'] !== 1) {
            $operandReasons[] = 'current-escape-not-single-character';
        }
        if ($nextEscape['width'] !== null && $nextEscape['width'] !== 1) {
            $operandReasons[] = 'next-escape-not-single-character';
        }
        if ($operandErrors !== []) {
            $operandReasons[] = 'malformed-pattern-or-escape';
        }

        $base = null;
        if ($pattern['value'] !== null && $nextEscape['value'] !== null && $nextEscape['width'] === 1) {
            $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameTokenFingerprintPlan(
                $currentRows,
                $nextRows,
                $pattern['value'],
                $nextEscape['value'],
                $lastYielded,
                $currentSource,
                $nextSource,
                $currentSchemaCookie,
                $nextSchemaCookie,
            );
        }

        $baseReasons = $base['replayInvalidationReasons'] ?? [];
        $replayReasons = array_values(array_unique(array_merge($baseReasons, $operandReasons)));
        $mustRestart = $replayReasons !== [];

        return [
            'status' => 'utf16-nocase-like-rtrim-escape-current-source-next182',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE',
            'baseStatus' => $base['status'] ?? null,
            'currentPattern' => $pattern['value'],
            'nextPattern' => $nextPattern['value'],
            'currentPatternEncoding' => self::encodingName($currentPatternEncoding),
            'nextPatternEncoding' => self::encodingName($nextPatternEncoding),
            'currentPatternBytesHex' => bin2hex($currentPatternBytes),
            'nextPatternBytesHex' => bin2hex($nextPatternBytes),
            'currentEscape' => $currentEscape['value'],
            'nextEscape' => $nextEscape['value'],
            'currentEscapeWidth' => $currentEscape['width'],
            'nextEscapeWidth' => $nextEscape['width'],
            'currentEscapeEncoding' => self::encodingName($currentEscapeEncoding),
            'nextEscapeEncoding' => self::encodingName($nextEscapeEncoding),
            'currentEscapeBytesHex' => $currentEscapeBytes === null ? null : bin2hex($currentEscapeBytes),
            'nextEscapeBytesHex' => $nextEscapeBytes === null ? null : bin2hex($nextEscapeBytes),
            'operandErrors' => $operandErrors,
            'operandInvalidationReasons' => array_values(array_unique($operandReasons)),
            'baseReplayInvalidationReasons' => $baseReasons,
            'replayInvalidationReasons' => $replayReasons,
            'lastYielded' => $lastYielded,
            'currentMatchedRowids' => $base['currentMatchedRowids'] ?? [],
            'nextMatchedRowids' => $base['nextMatchedRowids'] ?? [],
            'replayPlanRowids' => $mustRestart ? ($base['nextMatchedRowids'] ?? []) : ($base['replayPlanRowids'] ?? []),
            'replayPlanMode' => $mustRestart ? 'reprepare-from-decoded-escape-start' : ($base['replayPlanMode'] ?? 'continue-after-key-rowid-byte-token'),
            'mustReprepareBeforeReplay' => $mustRestart,
            'safeToReplayFromToken' => !$mustRestart,
            'tokenIncludesRowidTieBreaker' => true,
            'tokenIncludesByteFingerprint' => true,
            'escapeOperandVerifiedBeforeReplay' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-escape-single-character',
                'sqlite-nocase-like-rtrim-replay',
                'sqlite-current-source-next182',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 operand decode, LIKE ESCAPE validation, and NOCASE/RTRIM byte-token replay diagnostics',
            'non_overlap' => 'adds UTF-16 LIKE ESCAPE operand validation and escape-byte current/next replay invalidation; avoids accepted UTF-16 malformed insert guards, Unicode GLOB ranges, RHS RTRIM planning, token byte fingerprint-only replay, and storage/planner clusters',
        ];
    }

    /** @return array{value:?string,error:?string} */
    private static function decodeOperand(string $bytes, int $encoding, string $label): array
    {
        try {
            return ['value' => SQLiteEncodingCollationSourceCursor::decodeText($bytes, $encoding), 'error' => null];
        } catch (\InvalidArgumentException $exception) {
            return ['value' => null, 'error' => $label . ': ' . $exception->getMessage()];
        }
    }

    /** @return array{value:?string,width:?int,error:?string} */
    private static function decodeEscape(?string $bytes, int $encoding, string $label): array
    {
        if ($bytes === null) {
            return ['value' => null, 'width' => null, 'error' => null];
        }

        try {
            $value = SQLiteEncodingCollationSourceCursor::decodeText($bytes, $encoding);
        } catch (\InvalidArgumentException $exception) {
            return ['value' => null, 'width' => null, 'error' => $label . ': ' . $exception->getMessage()];
        }

        preg_match_all('/./us', $value, $matches);

        return ['value' => $value, 'width' => count($matches[0]), 'error' => null];
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }
}
