# SQLite Encoding LIKE/GLOB Affinity Range Current Source Next104

## Behavior

- Added `SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan`.
- Covers SQLite `LIKE` and `GLOB` option-value scans where scalar values are coerced with text affinity, prefix ranges are exposed for current-source cursors, residual matching is preserved, and current/next invalidation records source, schema-cookie, storage, text, encoded-byte, range-class, and matched-rowset changes.
- Includes numeric, boolean, SQL NULL, escaped wildcard, Unicode, and malformed UTF-8 guard coverage for copied `wp_options` values.

## Application Smoke

- Added `examples/application-like-glob-affinity-range-current-source-next.php`.
- The smoke models copied Application option import diagnostics for plugin option-value LIKE/GLOB range scans and numeric text-affinity changes without requiring `ext/sqlite`.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNext104Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 57 assertions, 0 failures
```

Expected dashboard movement after clean integration:

- `phpPass`: `40110 -> 40167` (`+57` focused PASS lines).
- Mapped coverage: one new focused encoding LIKE/GLOB affinity-range row can move `597 / 1589 -> 598 / 1589` if the integrator accepts the manifest/status mapping.

## Non-overlap

This does not repeat accepted Unicode GLOB ranges, UTF-16 malformed guards, LIKE current/next cursor ranges, dynamic affinity LIKE behavior, UTF-16 collation/affinity source switching, or LIKE/GLOB current-source collation planning. The new surface is option-value text-affinity coercion plus LIKE/GLOB prefix-range invalidation across current/next sources.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP LIKE/GLOB matchers, affinity storage classification, UTF-16 text encoding helper, and current-source invalidation pattern.
