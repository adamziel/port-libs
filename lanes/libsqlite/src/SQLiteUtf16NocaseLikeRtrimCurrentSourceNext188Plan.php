<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext188Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string}|null $lastYielded
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameReusedRowidResumePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        ?array $lastYielded = null,
        string $currentSource = 'main.wp_options@187',
        string $nextSource = 'main.wp_options@188',
        int $currentSchemaCookie = 187,
        int $nextSchemaCookie = 188,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext185Plan::wordpressOptionNameDeletedTokenResumePlan(
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
        $rowidProbe = $token === null ? null : self::probeNextRowid($nextRows, $token['rowid'], $pattern, $escape, $base['range']);
        $unsafe = $base['resumeUnsafeReasons'];
        if ($rowidProbe !== null && !$rowidProbe['sameToken']) {
            $unsafe[] = 'yield-token-rowid-reused';
            if (!$rowidProbe['insideRange']) {
                $unsafe[] = 'yield-token-rowid-reused-outside-range';
            }
            if (!$rowidProbe['matchesResidual']) {
                $unsafe[] = 'yield-token-rowid-reused-outside-like-residual';
            }
            if ($rowidProbe['decodeError'] !== null) {
                $unsafe[] = 'yield-token-rowid-reused-malformed';
            }
        }
        $unsafe = array_values(array_unique($unsafe));
        $safe = $unsafe === [];

        $base['status'] = 'utf16-nocase-like-rtrim-current-source-next188';
        $base['baseStatus'] = 'utf16-nocase-like-rtrim-current-source-next185';
        $base['nextRowidProbe'] = $rowidProbe;
        $base['rowidReuseDetected'] = $rowidProbe !== null && !$rowidProbe['sameToken'];
        $base['rowidReuseSafeForDeletedTokenResume'] = $safe;
        $base['resumeUnsafeReasons'] = $unsafe;
        $base['deletedTokenResumeSafe'] = $safe;
        $base['mustReprepareBeforeDeletedTokenResume'] = !$safe;
        $base['safeToResumeAfterDeletedToken'] = $safe;
        $base['replayPlanMode'] = $safe ? $base['replayPlanMode'] : 'reprepare-from-range-start-after-rowid-reuse';
        $base['replayPlanRowids'] = $safe ? $base['replayPlanRowids'] : $base['nextMatchedRowids'];
        $base['rowidReuseInvalidatesBeforeKeyBoundary'] = true;
        $base['rowidReuseCheckedBeforeDeletedTokenResume'] = true;
        $base['dependencies'] = [
            'sqlite-utf16-decode',
            'sqlite-rtrim-expression',
            'sqlite-nocase-like-deleted-token-resume',
            'sqlite-rowid-reuse-current-source-fence',
            'sqlite-current-source-next188',
        ];
        $base['dependency_closure'] = 'no new support component needed; reuses native UTF-16 decode, RTRIM expression keys, ASCII NOCASE LIKE matching, deleted-token replay diagnostics, and rowid source-fence checks';
        $base['non_overlap'] = 'adds next-source rowid reuse fencing before deleted-token resume for UTF-16 RTRIM/NOCASE LIKE cursors; avoids accepted next185 deleted-token replay, next184 escaped token residual validation, next181 peer replay, Unicode GLOB ranges, UTF-16 malformed insert guards, and storage/planner clusters';

        return $base;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{rowid:int,text:?string,rtrimText:?string,key:?string,encoding:?string,bytesHex:string,insideRange:bool,matchesResidual:bool,sameToken:bool,decodeError:?string}|null
     */
    private static function probeNextRowid(array $rows, int $rowid, string $pattern, ?string $escape, ?array $range): ?array
    {
        foreach ($rows as $row) {
            self::assertRow($row);
            if ($row['option_id'] !== $rowid) {
                continue;
            }

            $bytesHex = bin2hex($row['option_name_bytes']);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $key = self::asciiLower($rtrim);
                $insideRange = $range === null || (strcmp($key, $range['lowerInclusive']) >= 0 && ($range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0));
                $matchesResidual = SQLiteDatabase::likeMatches($rtrim, $pattern, $escape, false);

                return [
                    'rowid' => $rowid,
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'key' => $key,
                    'encoding' => self::encodingName($row['text_encoding']),
                    'bytesHex' => $bytesHex,
                    'insideRange' => $insideRange,
                    'matchesResidual' => $matchesResidual,
                    'sameToken' => $insideRange && $matchesResidual,
                    'decodeError' => null,
                ];
            } catch (\InvalidArgumentException $exception) {
                return [
                    'rowid' => $rowid,
                    'text' => null,
                    'rtrimText' => null,
                    'key' => null,
                    'encoding' => self::encodingName($row['text_encoding']),
                    'bytesHex' => $bytesHex,
                    'insideRange' => false,
                    'matchesResidual' => false,
                    'sameToken' => false,
                    'decodeError' => $exception->getMessage(),
                ];
            }
        }

        return null;
    }

    /** @param array<string,mixed> $row */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next188 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next188 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next188 rows require integer text_encoding');
        }
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => 'unknown',
        };
    }
}
