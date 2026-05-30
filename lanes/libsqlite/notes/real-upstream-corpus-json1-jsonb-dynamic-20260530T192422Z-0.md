# real-upstream-corpus-json1-jsonb-dynamic-20260530T192422Z-0

Base accepted HEAD: `de394d1a2a5407b1856e89f4b996c5ea3450f50d`.

## Upstream Sources

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json502.test`

## Behavior Added

Added `SQLiteRealUpstreamJson501502DynamicBulkTest.php`, a focused real upstream JSON5 and escaped-path corpus batch.

Ported scenario families:

- `json501-1.*` JSON5 identifier object keys, including dollar, underscore, alphanumeric, and Unicode identifier names.
- `json501-2.*` and `json501-3.*` object and array trailing-comma canonicalization.
- `json501-4.*` through `json501-6.*` single-quoted strings, line continuations, character escapes, hex escapes, and raw JSON5 control-character strings.
- `json501-7.*` through `json501-10.*` hexadecimal numbers, decimal-point forms, exponent forms, infinity, NaN, and explicit-plus numbers.
- `json501-11.*` and `json501-12.*` comment and extended-whitespace handling.
- `json501-13.*` single-quoted strings containing double quotes.
- `json501-14.1` through `json501-14.31` control-character JSON5 string canonicalization and JSONB parity.
- `json502-2.1` malformed JSON5 error-position reporting.
- `json502-3.*`, `json502-4.1`, and `json502-5.*` escaped object-label/path matching, JSON patch label comparison, JSON tree escaped labels, and quoted-key JSON mutation.

The tests exercise native JSON canonicalization, JSON5 validity flags, error positions, path extraction, JSONB encode/decode parity, JSON tree row production, mutation helpers, patch helpers, and `SQLiteSelectExpression` dispatch for `json()`, `json_extract()`, `->`, and `->>`.

## Focused Evidence

- New focused file: `lanes/libsqlite/tests/SQLiteRealUpstreamJson501502DynamicBulkTest.php`
- Focused PASS cases: `199`
- Focused behavior assertions: `10,475`
- Expected `phpPass` movement: `+199` if admitted as a new selected test file.
- Mapped denominator movement: none claimed; this is behavior coverage over already-mapped upstream JSON scripts.

Command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson501502DynamicBulkTest.php
```

Result:

```text
1 test files, 10475 assertions, 0 failures
```

## Non-Overlap

This batch avoids accepted JSON table cursor/source/hidden/visible constraint work, JSON aggregate/window work, `json105` reverse-index mutation coverage, `json106`/`json108` invariant bulk coverage, `json103` aggregate coverage, `json104` merge-patch coverage, `json109` array-insert coverage, and `jsonb01` remove coverage. It specifically widens real upstream JSON5 lexical behavior from `json501.test` and escaped-label/path behavior from `json502.test`.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP JSON helpers and the lane-local PHP TestRunner.

## Root Harness

Not run - isolated micro-slice.
