# real-upstream-corpus-json1-jsonb-dynamic-20260601T144308Z-0

## Source truth

- Hydrated upstream SQLite file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- Ported scenario cluster:
  - `json101-7.1` through `json101-7.7`: JSON whitespace byte validity, including form-feed rejection.
  - `json101-8.1`: `json_array()` control-byte escaping.
  - `json101-8.1b`: `jsonb_array()` control-byte escaping and strict JSONB validity.
  - `json101-8.2`: `json_extract()` round trip of escaped control characters.
  - `json101-8.3` and `json101-8.4`: high-byte JSON string validity, extraction, length, and codepoint behavior.
  - `json101-9.1` through `json101-9.7`: parser-level `json_quote()` scalar results plus BLOB/arity error boundaries.

## Patch

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamJson101ControlQuoteSelectSqlDynamic20260601Test.php`.
- The new file adds 1000 dynamic `SQLiteSelectSql::execute()` cases over generic `app_json_inputs` rows, plus source-citation and dependency-closure tests.
- The SELECT SQL path exercises nested JSON constructor, JSONB constructor, `json_extract`, `json_quote`, `json_valid`, `unicode`, and `length` dispatch through the parser/executor rather than direct helper calls.

## Focused evidence

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson101ControlQuoteSelectSqlDynamic20260601Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamJson101ControlQuoteSelectSqlDynamic20260601Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101ControlQuoteSelectSqlDynamic20260601Test.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `1 test files, 15015 assertions, 0 failures`

## Countability

- Adds 1002 distinct TestRunner PASS cases: 1000 dynamic SELECT SQL rows, one upstream source-citation/error-boundary case, and one dependency-closure case.
- `phpPass` expected movement: `5941178 -> 5942180`.
- Mapped upstream denominator remains `1589 / 1589`; this is PASS-line/focused-assertion growth, not mapped-denominator growth.

## Non-overlap

- This is parser-level SELECT SQL coverage for the upstream `json101.test` control/quote/whitespace cluster.
- It does not repeat the existing direct-helper `SQLiteRealUpstreamJson101ControlQuoteDynamicMegaTest.php` coverage, `json101` quoted-path coverage, hidden-source coverage, subtype handoff coverage, JSON table source/cursor work, or nested edit/cache coverage.

## Dependency closure

- No new support component is needed.
- The batch reuses the existing native PHP `SQLiteSelectSql` executor and JSON function support.

## Root status

- Root harness not run; this was an isolated focused micro-slice.
