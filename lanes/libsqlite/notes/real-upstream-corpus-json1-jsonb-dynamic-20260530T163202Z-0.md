# real-upstream-corpus-json1-jsonb-dynamic-20260530T163202Z-0

Base accepted HEAD: `92b65fe2933444167e639234f5a0c525e1097aec`.

## Upstream Sources

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test`

## Behavior Added

Extended `SQLiteRealUpstreamJson1JsonbDynamicTest.php` with real `json105.test` behavior:

- `json105-1.*` reverse-index `json_extract()` cases, including `[#]`, `[#-N]`, padded reverse indexes, huge reverse indexes, nested reverse indexes, and multi-path extraction.
- `json105-2.*` reverse-index and append pseudo-index `json_remove()` cases, including ordered multi-path removal.
- `json105-3.*`, `json105-4.*`, and `json105-5.*` append/reverse-index `json_insert()`, `json_set()`, and `json_replace()` cases.
- `json_array_insert()` / `jsonb_array_insert()` reverse-index insertion before the final array element.

Each case is asserted for text JSON and JSONB where the port exposes both paths. This is non-overlapping with the existing accepted `json101`, `json102`, and `jsonb01` scalar/mutation coverage because it specifically targets upstream `json105.test` append and reverse-index path semantics.

## Focused Evidence

- Before focused file shape: 132 assertions in `SQLiteRealUpstreamJson1JsonbDynamicTest.php`.
- After focused file shape: 198 assertions.
- New focused assertion delta: +66.
- Expected `phpPass` movement: `192937 -> 193003`.
- Mapped denominator movement: none claimed.

Command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicTest.php
```

Result:

```text
1 test files, 198 assertions, 0 failures
```

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP JSON helpers: `SQLiteJsonExtract`, `SQLiteJsonRemove`, `SQLiteJsonMutation`, `SQLiteJsonArrayInsert`, `SQLiteJsonB`, and `SQLiteJsonCanonical`.

## Root Harness

Not run - isolated micro-slice.
