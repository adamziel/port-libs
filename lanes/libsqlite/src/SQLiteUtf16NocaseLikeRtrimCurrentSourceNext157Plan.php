<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext157Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNamePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        string $currentSource = 'main.wp_options@156',
        string $nextSource = 'main.wp_options@157',
        int $currentSchemaCookie = 156,
        int $nextSchemaCookie = 157,
    ): array {
        self::assertUtf16Rows($currentRows);
        self::assertUtf16Rows($nextRows);

        $plan = SQLiteNocaseLikeRtrimCurrentSourceNext146Plan::wordpressOptionNamePlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentByteOrders = self::byteOrders($plan['currentDecoded']);
        $nextByteOrders = self::byteOrders($plan['nextDecoded']);
        $changedByteOrders = self::changedByteOrders($currentByteOrders, $nextByteOrders);

        $reasons = $plan['invalidationReasons'];
        if ($changedByteOrders !== [] && !in_array('utf16-byte-order', $reasons, true)) {
            $reasons[] = 'utf16-byte-order';
        }

        $plan['expression'] = 'rtrim(option_name) COLLATE NOCASE /* UTF-16 source */';
        $plan['sourceTextEncoding'] = 'UTF-16';
        $plan['acceptedTextEncodings'] = ['UTF-16LE', 'UTF-16BE'];
        $plan['utf16ByteOrderSensitive'] = true;
        $plan['currentByteOrders'] = $currentByteOrders;
        $plan['nextByteOrders'] = $nextByteOrders;
        $plan['changedByteOrderRowids'] = $changedByteOrders;
        $plan['cursorInvalidated'] = $reasons !== [];
        $plan['cursorReusable'] = $reasons === [] && $plan['rangeUsable'];
        $plan['invalidationReasons'] = $reasons;
        $plan['dependencies'] = [
            'sqlite-utf16-source-decode',
            'sqlite-like-nocase-prefix-range',
            'sqlite-rtrim-expression-index',
            'sqlite-current-source-next157',
        ];
        $plan['dependency_closure'] = 'no new support component needed; next157 composes native UTF-16 decode validation, ASCII NOCASE LIKE prefix planning, RTRIM index-key normalization, and current-source cursor invalidation';
        $plan['non_overlap'] = 'avoids accepted generic NOCASE/RTRIM LIKE next146 and UTF-16 RTRIM/NOCASE/GLOB slices by requiring UTF-16-only source rows and asserting byte-order invalidation for NOCASE LIKE over RTRIM keys';

        return $plan;
    }

    /** @param list<array<string,mixed>> $rows */
    private static function assertUtf16Rows(array $rows): void
    {
        foreach ($rows as $row) {
            if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next157 rows require integer text_encoding');
            }
            if (!in_array($row['text_encoding'], [2, 3], true)) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next157 rows must use UTF-16LE or UTF-16BE text_encoding');
            }
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,string>
     */
    private static function byteOrders(array $rows): array
    {
        $orders = [];
        foreach ($rows as $row) {
            $orders[$row['rowid']] = $row['encoding'] === 'UTF-16LE' ? 'little' : 'big';
        }

        return $orders;
    }

    /**
     * @param array<int,string> $current
     * @param array<int,string> $next
     * @return list<int>
     */
    private static function changedByteOrders(array $current, array $next): array
    {
        $changed = [];
        foreach ($next as $rowid => $order) {
            if (!isset($current[$rowid]) || $current[$rowid] !== $order) {
                $changed[] = $rowid;
            }
        }
        foreach ($current as $rowid => $_order) {
            if (!isset($next[$rowid])) {
                $changed[] = $rowid;
            }
        }
        $changed = array_values(array_unique($changed));
        sort($changed);

        return $changed;
    }
}
