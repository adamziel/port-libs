# real-upstream-corpus-pragma-schema-dynamic-20260530T193742Z-0

Base accepted HEAD: `28f29f1b7137ae1bf099a6bea9838aec79fed0b3`.

Implemented a non-overlapping real upstream PRAGMA pager-state batch from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  - `pragma-1.1` through `pragma-1.15`: `cache_size`,
    `default_cache_size`, `synchronous`, reopen reset, persistent default
    cache, keyword and numeric synchronous normalization.
  - `pragma-2.*` and `pragma-4.*`: schema-qualified pager PRAGMA behavior for
    attached schemas.

Added `SQLitePragmaPagerState`, a generic PHP state executor for these pager
PRAGMAs. It is separate from the already-saturated schema-query catalog corpus
and does not add domain-specific APIs.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaPagerStateDynamicTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1682 assertions, 0 failures
```

Expected focused growth: `1682` distinct `TestRunner` PASS cases from one new
real upstream behavior test file, moving lane-local `phpPass` from `430515` to
`432197`. Mapped coverage is unchanged because this is PASS-line growth over an
already mapped PRAGMA source family.

Dependency closure: no new support component is needed. The slice adds the
small native PHP support component required for selected stateful PRAGMA pager
behavior and reuses the existing lane test harness.

Non-overlap: this does not repeat the existing dynamic schema-query,
schema-invalidation, data-version, schema2/schema3, pragma4, or pragma5
introspection batches. It targets stateful pager PRAGMA behavior rather than
table/index/foreign-key catalog metadata.
