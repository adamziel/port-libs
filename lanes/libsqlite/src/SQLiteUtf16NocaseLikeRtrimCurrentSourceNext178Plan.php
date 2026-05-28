<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext178Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string,keyBytes?:string,keyEncoding?:int|string}|null $lastYielded
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameCanonicalTokenPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        ?array $lastYielded = null,
        string $currentSource = 'main.wp_options@177',
        string $nextSource = 'main.wp_options@178',
        int $currentSchemaCookie = 177,
        int $nextSchemaCookie = 178,
    ): array {
        $token = self::canonicalizeToken($lastYielded);
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext175Plan::wordpressOptionNameTokenFingerprintPlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $token['normalized'],
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $tokenReasons = $token['reasons'];
        $replayInvalidationReasons = array_values(array_unique(array_merge(
            $base['replayInvalidationReasons'],
            $tokenReasons,
        )));
        $mustReprepare = $replayInvalidationReasons !== [];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next178',
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
            'rawLastYielded' => $lastYielded,
            'normalizedLastYielded' => $token['normalized'],
            'tokenRawText' => $token['rawText'],
            'tokenRtrimText' => $token['rtrimText'],
            'tokenCanonicalKey' => $token['canonicalKey'],
            'tokenCanonicalEncoding' => $token['canonicalEncoding'],
            'tokenCanonicalBytesHex' => $token['canonicalBytesHex'],
            'tokenNormalizationReasons' => $tokenReasons,
            'baseReplayInvalidationReasons' => $base['replayInvalidationReasons'],
            'replayInvalidationReasons' => $replayInvalidationReasons,
            'currentTokenFingerprint' => $base['currentTokenFingerprint'],
            'nextTokenFingerprint' => $base['nextTokenFingerprint'],
            'tokenFingerprintReasons' => $base['tokenFingerprintReasons'],
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
            'replayPlanRowids' => $mustReprepare ? $base['nextMatchedRowids'] : $base['replayPlanRowids'],
            'replayPlanMode' => $mustReprepare ? 'reprepare-from-range-start' : $base['replayPlanMode'],
            'tokenIncludesRowidTieBreaker' => true,
            'tokenIncludesByteFingerprint' => true,
            'tokenCanonicalizesRawUtf16Key' => true,
            'tokenRtrimTrimsOnlyAsciiSpace' => true,
            'tokenNocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-rtrim-expression',
                'sqlite-nocase-like-rtrim-token-canonicalization',
                'sqlite-current-source-next178',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, RTRIM key construction, ASCII NOCASE folding, and next175 byte-fingerprint replay diagnostics',
            'non_overlap' => 'adds raw yielded-token canonicalization for UTF-16 RTRIM/NOCASE LIKE replay; avoids accepted next175 byte-fingerprint validation, next174 embedded-NUL residuals, next171 duplicate-key replay, Unicode GLOB ranges, and malformed UTF-16 insert guards',
        ];
    }

    /**
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string,keyBytes?:string,keyEncoding?:int|string}|null $token
     * @return array{normalized:array{key:string,rowid:int,bytesHex?:string,encoding?:string}|null,rawText:?string,rtrimText:?string,canonicalKey:?string,canonicalEncoding:?string,canonicalBytesHex:?string,reasons:list<string>}
     */
    private static function canonicalizeToken(?array $token): array
    {
        if ($token === null) {
            return [
                'normalized' => null,
                'rawText' => null,
                'rtrimText' => null,
                'canonicalKey' => null,
                'canonicalEncoding' => null,
                'canonicalBytesHex' => null,
                'reasons' => ['no-yield-token'],
            ];
        }
        if (!array_key_exists('key', $token) || !is_string($token['key'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next178 token requires string key');
        }
        if (!array_key_exists('rowid', $token) || !is_int($token['rowid'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next178 token requires integer rowid');
        }

        $normalized = [
            'key' => $token['key'],
            'rowid' => $token['rowid'],
        ];
        if (array_key_exists('bytesHex', $token)) {
            if (!is_string($token['bytesHex'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next178 token bytesHex must be a string');
            }
            $normalized['bytesHex'] = $token['bytesHex'];
        }
        if (array_key_exists('encoding', $token)) {
            if (!is_string($token['encoding'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next178 token encoding must be a string');
            }
            $normalized['encoding'] = $token['encoding'];
        }

        $rawText = null;
        $rtrimText = null;
        $canonicalKey = null;
        $canonicalEncoding = null;
        $canonicalBytesHex = null;
        $reasons = [];
        if (array_key_exists('keyBytes', $token) || array_key_exists('keyEncoding', $token)) {
            if (!array_key_exists('keyBytes', $token) || !is_string($token['keyBytes'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next178 token keyBytes must be a string when keyEncoding is present');
            }
            if (!array_key_exists('keyEncoding', $token) || (!is_int($token['keyEncoding']) && !is_string($token['keyEncoding']))) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next178 token keyEncoding must be UTF-8, UTF-16LE, or UTF-16BE');
            }
            $encoding = self::normalizeEncoding($token['keyEncoding']);
            $rawText = SQLiteEncodingCollationSourceCursor::decodeText($token['keyBytes'], $encoding);
            $rtrimText = rtrim($rawText, ' ');
            $canonicalKey = self::asciiLower($rtrimText);
            $canonicalEncoding = self::encodingName($encoding);
            $canonicalBytesHex = bin2hex($token['keyBytes']);

            if ($normalized['key'] !== $canonicalKey) {
                $reasons[] = 'token-key-not-canonical';
                $normalized['key'] = $canonicalKey;
            }
            if (!array_key_exists('bytesHex', $normalized)) {
                $normalized['bytesHex'] = $canonicalBytesHex;
            } elseif ($normalized['bytesHex'] !== $canonicalBytesHex) {
                $reasons[] = 'token-raw-bytes-fingerprint-mismatch';
            }
            if (!array_key_exists('encoding', $normalized)) {
                $normalized['encoding'] = $canonicalEncoding;
            } elseif ($normalized['encoding'] !== $canonicalEncoding) {
                $reasons[] = 'token-raw-encoding-mismatch';
            }
        }

        return [
            'normalized' => $normalized,
            'rawText' => $rawText,
            'rtrimText' => $rtrimText,
            'canonicalKey' => $canonicalKey,
            'canonicalEncoding' => $canonicalEncoding,
            'canonicalBytesHex' => $canonicalBytesHex,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    private static function normalizeEncoding(int|string $encoding): int
    {
        if (is_int($encoding)) {
            if (in_array($encoding, [1, 2, 3], true)) {
                return $encoding;
            }
            throw new \InvalidArgumentException('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE');
        }

        return match (strtoupper(str_replace('_', '-', $encoding))) {
            'UTF-8', 'UTF8' => 1,
            'UTF-16LE', 'UTF16LE' => 2,
            'UTF-16BE', 'UTF16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
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

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
