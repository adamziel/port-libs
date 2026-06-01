# real-upstream-corpus-json1-jsonb-dynamic-20260601T163134Z-0

## Source truth

- Hydrated upstream SQLite file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- Ported scenario cluster:
  - `json101-1.1.00` through `json101-1.4b`: JSON array / JSONB array constructor scalar quoting, JSON subtype insertion, JSONB insertion, and BLOB rejection.
  - `json101-2.1` through `json101-2.5`: JSON object / JSONB object constructor value handling, label/arity errors, nested constructor values, and BLOB rejection.
  - `json101-3.1` through `json101-3.4b`: edit-value boundaries where plain SQL text remains text while `json()` / `jsonb()` values remain structured JSON.
  - `json101-4.5` through `json101-4.10b`: no-edit mutation identity and root object extraction for text JSON and JSONB inputs.
  - `json101-6.1` through `json101-6.11`: strict trailing-comma validity, JSON5 canonicalization, and double-comma error positions.

## Patch

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamJson101ConstructorEditSelectSqlDynamic20260601T163134ZTest.php`.
- The file adds 1000 dynamic parser-level `SQLiteSelectSql::execute()` cases over generic `app_json_inputs` rows, plus source-citation/error-boundary and dependency-closure cases.
- Each dynamic row exercises constructor and edit dispatch through SELECT SQL with host-row columns, `WHERE`, `ORDER BY`, `LIMIT`, and stored JSONB columns instead of direct helper-only calls.

## Focused evidence

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson101ConstructorEditSelectSqlDynamic20260601T163134ZTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamJson101ConstructorEditSelectSqlDynamic20260601T163134ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101ConstructorEditSelectSqlDynamic20260601T163134ZTest.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `1 test files, 24027 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `1 test files, 7 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output.

## Countability

- Adds 1002 distinct focused TestRunner PASS cases: 1000 dynamic SELECT SQL rows, one upstream citation/error-boundary case, and one dependency-closure case.
- `phpPass` expected movement: `6045175 -> 6046177`.
- Mapped upstream denominator remains `1589 / 1589`; this is PASS-line/focused-assertion growth against already hydrated upstream JSON source truth.

## Non-overlap

- Existing accepted files cover these `json101.test` constructor/edit rows mainly through direct JSON helpers and select-expression probes.
- This slice is limited to parser-level SELECT SQL host-row execution for constructor/edit/no-op/trailing-comma semantics.
- It does not repeat JSON table cursor/source/hidden/visible constraints, JSON101 valid/type and control/quote SELECT SQL slices, quoted-path SELECT SQL, value-subtype SELECT SQL, JSON102 inspection/subtype/tree projection SELECT SQL, JSON103 aggregate/window behavior, JSON104 merge patch, JSON105 reverse path mutation, JSON107 legacy BLOB behavior, JSON108 pretty invariants, JSON109 array insert, JSON501/502 JSON5 escaped-path coverage, or JSONB malformed diagnostics.

## Dependency closure

- No new support component is needed.
- The batch reuses existing native PHP `SQLiteSelectSql`, JSON constructor, JSONB, mutation, inspection, validity, error-position, and SELECT-expression dispatch support.

## Root status

- Root harness not run; this was an isolated focused micro-slice.
