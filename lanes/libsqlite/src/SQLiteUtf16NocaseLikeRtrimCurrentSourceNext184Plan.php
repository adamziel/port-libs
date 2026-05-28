<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext184Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string,keyBytes?:string,keyEncoding?:int|string}|null $lastYielded
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameEscapedPeerReplayPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape,
        ?array $lastYielded,
        string $currentSource = 'main.wp_options@183',
        string $nextSource = 'main.wp_options@184',
        int $currentSchemaCookie = 183,
        int $nextSchemaCookie = 184,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext181Plan::wordpressOptionNamePeerReplayPlan(
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

        $token = is_array($base['normalizedLastYielded']) ? $base['normalizedLastYielded'] : null;
        $tokenCheck = self::tokenResidualCheck($token, $pattern, $escape);
        $unsafe = $base['peerReplayUnsafeReasons'];
        if (!$tokenCheck['matchesResidual']) {
            $unsafe[] = 'yield-token-like-residual-mismatch';
        }
        if ($tokenCheck['decodeError'] !== null) {
            $unsafe[] = 'yield-token-decode-error';
        }
        $unsafe = array_values(array_unique($unsafe));
        $safe = $unsafe === [];
        $sameKeyReplay = $safe ? $base['sameKeyReplayRowids'] : [];
        $remainingAfterPeer = $safe ? $base['remainingAfterPeerRowids'] : [];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next184',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE',
            'pattern' => $base['pattern'],
            'escape' => $base['escape'],
            'baseStatus' => $base['status'],
            'baseReplayPlanMode' => $base['replayPlanMode'],
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'prefix' => $base['prefix'],
            'range' => $base['range'],
            'normalizedLastYielded' => $token,
            'tokenDecodedText' => $tokenCheck['decodedText'],
            'tokenRtrimText' => $tokenCheck['rtrimText'],
            'tokenNocaseKey' => $tokenCheck['nocaseKey'],
            'tokenMatchesEscapedLikeResidual' => $tokenCheck['matchesResidual'],
            'tokenDecodeError' => $tokenCheck['decodeError'],
            'tokenEscapePreservesLiteralPercent' => $escape !== null && str_contains($pattern, $escape . '%'),
            'tokenEscapePreservesLiteralUnderscore' => $escape !== null && str_contains($pattern, $escape . '_'),
            'peerKey' => $base['peerKey'],
            'currentPeerRowids' => $base['currentPeerRowids'],
            'nextPeerRowids' => $base['nextPeerRowids'],
            'peerBeforeOrAtTokenRowids' => $base['peerBeforeOrAtTokenRowids'],
            'peerAfterTokenRowids' => $base['peerAfterTokenRowids'],
            'sameKeyReplayRowids' => $sameKeyReplay,
            'remainingAfterPeerRowids' => $remainingAfterPeer,
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'nextMatchedKeys' => $base['nextMatchedKeys'],
            'nextMatchedRtrimText' => $base['nextMatchedRtrimText'],
            'nextMatchedEncodings' => $base['nextMatchedEncodings'],
            'duplicateRtrimNocaseKeys' => $base['duplicateRtrimNocaseKeys'],
            'basePeerReplayUnsafeReasons' => $base['peerReplayUnsafeReasons'],
            'peerReplayUnsafeReasons' => $unsafe,
            'peerContinuationSafe' => $safe,
            'mustReprepareBeforePeerReplay' => !$safe,
            'safeToContinueWithinPeerGroup' => $safe,
            'replayPlanMode' => $safe ? 'continue-after-escaped-like-peer-token' : 'reprepare-from-range-start',
            'replayPlanRowids' => $safe ? array_values(array_merge($sameKeyReplay, $remainingAfterPeer)) : $base['nextMatchedRowids'],
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'escapedLikeResidualAppliesAfterRtrim' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-escape-residual',
                'sqlite-rtrim-expression',
                'sqlite-nocase-like-peer-replay',
                'sqlite-current-source-next184',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, escaped LIKE residual matching, RTRIM expression keys, and next181 peer replay diagnostics',
            'non_overlap' => 'adds escaped LIKE residual validation for yielded UTF-16 RTRIM/NOCASE peer tokens; avoids accepted next181 peer replay, next180 non-ASCII full-scan, next178 canonical token validation, Unicode GLOB ranges, and UTF-16 malformed insert guards',
        ];
    }

    /**
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string,keyBytes?:string,keyEncoding?:int|string}|null $token
     * @return array{decodedText:?string,rtrimText:?string,nocaseKey:?string,matchesResidual:bool,decodeError:?string}
     */
    private static function tokenResidualCheck(?array $token, string $pattern, ?string $escape): array
    {
        if ($token === null) {
            return [
                'decodedText' => null,
                'rtrimText' => null,
                'nocaseKey' => null,
                'matchesResidual' => false,
                'decodeError' => null,
            ];
        }

        try {
            $decoded = self::decodeTokenText($token);
            $rtrim = rtrim($decoded, ' ');

            return [
                'decodedText' => $decoded,
                'rtrimText' => $rtrim,
                'nocaseKey' => self::asciiLower($rtrim),
                'matchesResidual' => SQLiteDatabase::likeMatches($rtrim, $pattern, $escape, false),
                'decodeError' => null,
            ];
        } catch (\InvalidArgumentException $exception) {
            return [
                'decodedText' => null,
                'rtrimText' => null,
                'nocaseKey' => null,
                'matchesResidual' => false,
                'decodeError' => $exception->getMessage(),
            ];
        }
    }

    /** @param array<string,mixed> $token */
    private static function decodeTokenText(array $token): string
    {
        if (isset($token['keyBytes'])) {
            $encoding = self::encodingId($token['keyEncoding'] ?? $token['encoding'] ?? 1);

            return SQLiteEncodingCollationSourceCursor::decodeText($token['keyBytes'], $encoding);
        }
        if (isset($token['bytesHex'])) {
            $bytes = hex2bin((string) $token['bytesHex']);
            if ($bytes === false) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next184 yielded token bytesHex is not valid hex');
            }
            $encoding = self::encodingId($token['encoding'] ?? 1);

            return SQLiteEncodingCollationSourceCursor::decodeText($bytes, $encoding);
        }

        return (string) $token['key'];
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    private static function encodingId(int|string $encoding): int
    {
        return match ($encoding) {
            1, 'UTF-8' => 1,
            2, 'UTF-16LE' => 2,
            3, 'UTF-16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next184 token encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }
}
