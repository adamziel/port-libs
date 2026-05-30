<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimResumeTokenCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyResumeTokenPlan(
        array $currentRows,
        array $nextRows,
        string $currentPatternBytes,
        int $currentPatternEncoding,
        string $nextPatternBytes,
        int $nextPatternEncoding,
        string $currentTokenKeyBytes,
        int $currentTokenKeyEncoding,
        int $currentTokenRowid,
        string $nextTokenKeyBytes,
        int $nextTokenKeyEncoding,
        int $nextTokenRowid,
        ?string $currentEscapeBytes = null,
        int $currentEscapeEncoding = 1,
        ?string $nextEscapeBytes = null,
        int $nextEscapeEncoding = 1,
        string $currentSource = 'main.wp_options@169',
        string $nextSource = 'main.wp_options@170',
        int $currentSchemaCookie = 169,
        int $nextSchemaCookie = 170,
    ): array {
        $currentToken = self::decodeToken($currentTokenKeyBytes, $currentTokenKeyEncoding, $currentTokenRowid, 'current');
        $nextToken = self::decodeToken($nextTokenKeyBytes, $nextTokenKeyEncoding, $nextTokenRowid, 'next');

        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyResumePlan(
            $currentRows,
            $nextRows,
            $currentPatternBytes,
            $currentPatternEncoding,
            $nextPatternBytes,
            $nextPatternEncoding,
            $currentEscapeBytes,
            $currentEscapeEncoding,
            $nextEscapeBytes,
            $nextEscapeEncoding,
            $nextToken['token'],
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $tokenByteReasons = [];
        if ($currentTokenKeyEncoding !== $nextTokenKeyEncoding) {
            $tokenByteReasons[] = 'token-key-encoding';
        }
        if ($currentTokenKeyBytes !== $nextTokenKeyBytes) {
            $tokenByteReasons[] = 'token-key-bytes';
        }
        if ($currentTokenRowid !== $nextTokenRowid) {
            $tokenByteReasons[] = 'token-rowid';
        }

        $tokenSemanticReasons = [];
        if ($currentToken['token']['key'] !== $nextToken['token']['key']) {
            $tokenSemanticReasons[] = 'token-key-text';
        }
        if ($currentTokenRowid !== $nextTokenRowid) {
            $tokenSemanticReasons[] = 'token-rowid';
        }

        $baseResumeReasons = $base['resumeReasons'];
        $resumeReasons = array_values(array_unique(array_merge($baseResumeReasons, $tokenSemanticReasons)));
        $byteOnlyTokenReprepare = $tokenByteReasons !== []
            && $tokenSemanticReasons === []
            && $baseResumeReasons === []
            && $base['safeToResumeFromToken'] === true;

        return [
            'status' => 'utf16-nocase-like-rtrim-resume-token-current-source-nextoneSevenZero',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ?',
            'baseStatus' => $base['status'],
            'caseSensitiveLike' => false,
            'asciiNocaseOnly' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'currentTokenKey' => $currentToken['token']['key'],
            'nextTokenKey' => $nextToken['token']['key'],
            'currentTokenRowid' => $currentTokenRowid,
            'nextTokenRowid' => $nextTokenRowid,
            'currentTokenEncoding' => self::encodingName($currentTokenKeyEncoding),
            'nextTokenEncoding' => self::encodingName($nextTokenKeyEncoding),
            'currentTokenBytesHex' => bin2hex($currentTokenKeyBytes),
            'nextTokenBytesHex' => bin2hex($nextTokenKeyBytes),
            'sameDecodedToken' => $currentToken['token'] === $nextToken['token'],
            'baseResumeReasons' => $baseResumeReasons,
            'tokenByteReasons' => $tokenByteReasons,
            'tokenSemanticReasons' => $tokenSemanticReasons,
            'resumeReasons' => $resumeReasons,
            'byteOnlyTokenReprepare' => $byteOnlyTokenReprepare,
            'mustReprepareBeforeResume' => !$byteOnlyTokenReprepare && $resumeReasons !== [],
            'safeToResumeFromToken' => $byteOnlyTokenReprepare || $resumeReasons === [],
            'resumePlanMode' => (!$byteOnlyTokenReprepare && $resumeReasons !== [])
                ? 'reprepare-from-range-start'
                : 'continue-after-decoded-token-key-rowid',
            'resumePlanRowids' => (!$byteOnlyTokenReprepare && $resumeReasons !== [])
                ? $base['nextMatchedRowids']
                : $base['nextAfterTokenRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'nextAfterTokenRowids' => $base['nextAfterTokenRowids'],
            'nextBeforeOrAtTokenRowids' => $base['nextBeforeOrAtTokenRowids'],
            'enteredAfterTokenRowids' => $base['enteredAfterTokenRowids'],
            'newBeforeTokenRowids' => $base['newBeforeTokenRowids'],
            'baseInvalidationReasons' => $base['baseInvalidationReasons'],
            'semanticInvalidationReasons' => $base['semanticInvalidationReasons'],
            'byteReprepareReasons' => $base['byteReprepareReasons'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'dependencies' => [
                'sqlite-utf16-resume-token-decode',
                'sqlite-nocase-like-rtrim-resume-cursor',
                'sqlite-current-source-nextoneSevenZero',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 token decode, accepted NOCASE/RTRIM LIKE resume planning, and current-source diagnostics',
        ];
    }

    /** @return array{token:array{key:string,rowid:int}} */
    private static function decodeToken(string $bytes, int $encoding, int $rowid, string $label): array
    {
        try {
            $key = SQLiteEncodingCollationSourceCursor::decodeText($bytes, $encoding);
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenZero ' . $label . ' resume token is malformed: ' . $exception->getMessage(), 0, $exception);
        }

        return ['token' => ['key' => self::asciiLower(rtrim($key, ' ')), 'rowid' => $rowid]];
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => 'unknown-' . $encoding,
        };
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
