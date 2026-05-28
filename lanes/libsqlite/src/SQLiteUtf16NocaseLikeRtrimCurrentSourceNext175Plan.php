<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext175Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string}|null $lastYielded
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameTokenFingerprintPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        ?array $lastYielded = null,
        string $currentSource = 'main.wp_options@174',
        string $nextSource = 'main.wp_options@175',
        int $currentSchemaCookie = 174,
        int $nextSchemaCookie = 175,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext171Plan::wordpressOptionNameDuplicateKeyReplayPlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $lastYielded,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentToken = self::tokenFingerprint($base['currentMatchedBytesHex'], $base['currentMatchedEncodings'], $lastYielded);
        $nextToken = self::tokenFingerprint($base['nextMatchedBytesHex'], $base['nextMatchedEncodings'], $lastYielded);
        $tokenFingerprintReasons = [];
        if ($lastYielded !== null && $nextToken === null) {
            $tokenFingerprintReasons[] = 'yielded-token-row-exited';
        }
        if ($lastYielded !== null && $nextToken !== null) {
            if (array_key_exists('bytesHex', $lastYielded) && $lastYielded['bytesHex'] !== $nextToken['bytesHex']) {
                $tokenFingerprintReasons[] = 'yielded-token-bytes-changed';
            }
            if (array_key_exists('encoding', $lastYielded) && $lastYielded['encoding'] !== $nextToken['encoding']) {
                $tokenFingerprintReasons[] = 'yielded-token-encoding-changed';
            }
        }
        if ($currentToken !== null && $nextToken !== null && $currentToken['bytesHex'] !== $nextToken['bytesHex']) {
            $tokenFingerprintReasons[] = 'current-next-token-bytes-changed';
        }
        if ($currentToken !== null && $nextToken !== null && $currentToken['encoding'] !== $nextToken['encoding']) {
            $tokenFingerprintReasons[] = 'current-next-token-encoding-changed';
        }

        $replayInvalidationReasons = array_values(array_unique(array_merge(
            $base['replayInvalidationReasons'],
            $tokenFingerprintReasons,
        )));
        $mustReprepare = $replayInvalidationReasons !== [];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next175',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE',
            'baseStatus' => $base['status'],
            'pattern' => $base['pattern'],
            'escape' => $base['escape'],
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'prefix' => $base['prefix'],
            'range' => $base['range'],
            'lastYielded' => $lastYielded,
            'currentTokenFingerprint' => $currentToken,
            'nextTokenFingerprint' => $nextToken,
            'tokenFingerprintReasons' => array_values(array_unique($tokenFingerprintReasons)),
            'baseReplayInvalidationReasons' => $base['replayInvalidationReasons'],
            'replayInvalidationReasons' => $replayInvalidationReasons,
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentMatchedBytesHex' => $base['currentMatchedBytesHex'],
            'nextMatchedBytesHex' => $base['nextMatchedBytesHex'],
            'currentMatchedEncodings' => $base['currentMatchedEncodings'],
            'nextMatchedEncodings' => $base['nextMatchedEncodings'],
            'duplicateRtrimNocaseKeys' => $base['duplicateRtrimNocaseKeys'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'mustReprepareBeforeReplay' => $mustReprepare,
            'safeToReplayFromToken' => !$mustReprepare,
            'replayPlanRowids' => $mustReprepare ? $base['nextMatchedRowids'] : $base['nextAfterTokenRowids'],
            'replayPlanMode' => $mustReprepare ? 'reprepare-from-range-start' : 'continue-after-key-rowid-byte-token',
            'tokenIncludesRowidTieBreaker' => true,
            'tokenIncludesByteFingerprint' => true,
            'tokenFingerprintVerifiedAgainstNextSource' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-nocase-like-rtrim-replay',
                'sqlite-yield-token-byte-fingerprint',
                'sqlite-current-source-next175',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, NOCASE LIKE/RTRIM replay diagnostics, and adds byte-fingerprint validation for yielded tokens',
            'non_overlap' => 'adds yielded-token byte/encoding fingerprint validation on top of next171 duplicate-key replay; avoids accepted UTF-16 row matching, pattern-byte decoding, RHS RTRIM, malformed insert guards, Unicode GLOB ranges, and storage/planner clusters',
        ];
    }

    /**
     * @param array<int,string> $bytesByRowid
     * @param array<int,string> $encodingByRowid
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string}|null $token
     * @return array{rowid:int,bytesHex:string,encoding:string}|null
     */
    private static function tokenFingerprint(array $bytesByRowid, array $encodingByRowid, ?array $token): ?array
    {
        if ($token === null || !array_key_exists($token['rowid'], $bytesByRowid)) {
            return null;
        }

        return [
            'rowid' => $token['rowid'],
            'bytesHex' => $bytesByRowid[$token['rowid']],
            'encoding' => $encodingByRowid[$token['rowid']],
        ];
    }
}
