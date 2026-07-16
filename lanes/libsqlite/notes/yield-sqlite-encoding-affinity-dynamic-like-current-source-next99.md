# SQLite Encoding Affinity Dynamic LIKE Current Source Next99

## Behavior

- Added `SQLiteEncodingAffinityLikeCurrentSourceNextPlan::optionRowValueDynamicPatternPlan()`.
- Covers SQLite `LIKE` execution where the left operand, right pattern operand, and optional `ESCAPE` operand come from row values and are coerced with SQLite text affinity before matching.
- Records current/next invalidation for source name, schema cookie, scan encoding, storage class, value text, pattern text, escape text, encoded bytes, and matched rowset changes.
- Keeps BLOB and SQL NULL operands out of matched rowsets, matching the existing lane assumption for SQLite LIKE over copied Application option values.

## Application Smoke

- Added `examples/application-encoding-affinity-dynamic-like-current-source-next99.php`.
- The smoke models copied `wp_options` import diagnostics where plugin rows carry per-row LIKE patterns and escapes, including numeric/boolean coercion, literal wildcard escapes, and BLOB nonmatches without requiring `ext/sqlite`.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingAffinityDynamicLikeCurrentSourceNext99Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 60 assertions, 0 failures
```

Expected dashboard movement after clean integration:

- `phpPass`: `38278 -> 38338` (`+60` focused PASS lines).
- Mapped coverage: one new focused encoding LIKE row, `568 / 1589 -> 569 / 1589` in status wording; lane manifest local focused mapping moves `450 -> 451`.

## Non-overlap

This does not repeat accepted batch94 static option-value affinity LIKE current/next coverage, accepted LIKE current/next cursor ranges, UTF-16 malformed guards, Unicode GLOB ranges, or collation index LIKE/GLOB planning. The new surface is row-sourced dynamic LIKE pattern and ESCAPE affinity with current/next UTF-16 byte invalidation.

## Dependency Closure

No new support component is needed. This reuses existing native PHP SQLite text-affinity storage classification, LIKE matching, UTF-16 text encoding, and `SQLiteBlobValue` behavior.
