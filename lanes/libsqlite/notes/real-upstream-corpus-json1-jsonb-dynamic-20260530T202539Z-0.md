# real-upstream-corpus-json1-jsonb-dynamic-20260530T202539Z-0

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json106.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json108.test`

## Ported Behavior

Added `lanes/libsqlite/tests/SQLiteRealUpstreamJson106Json108InvariantLargeCorpusTest.php`, a deterministic large-corpus port of upstream JSON invariant behavior:

- `json106-1`: strict JSON and JSONB validity over 260 generated documents.
- `json106-2`: `json_tree()` scalar `atom` values match direct path extraction for text and JSONB inputs.
- `json106-5` and `json106-6`: `json_remove()` removes scalar object leaves and `json_insert()` restores those leaves.
- `json106-7`: `json_patch()` and `jsonb_patch()` expose patch scalar leaves with canonical parity.
- `json106-8` and `json108-1`: `json_pretty()` output canonicalizes back to the original JSON for default, empty, tab, and custom comment indents, including JSONB inputs.

## Evidence

Focused verification:

```text
php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson106Json108InvariantLargeCorpusTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamJson106Json108InvariantLargeCorpusTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson106Json108InvariantLargeCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS real upstream json106 1 validity over deterministic large corpus
PASS real upstream json106 2 tree scalar atoms match path extraction over large corpus
PASS real upstream json106 5 and 6 remove then insert restores scalar leaves over large corpus
PASS real upstream json106 7 json patch scalar leaves are visible over large corpus
PASS real upstream json106 8 and json108 1 pretty canonical round trip over large corpus
PASS real upstream json106 json108 large corpus source citations

1 test files, 24704 assertions, 0 failures
```

## Non-Overlap

This is not metadata-only admission and does not add fabricated upstream script ids. It widens real upstream `json106.test` and `json108.test` invariant coverage with 260 deterministic documents and 24,704 behavior assertions. It avoids accepted JSON table cursor/source/hidden/visible constraint work, `json105` dynamic path mutation coverage, `json109` array insert coverage, `json102` scalar/path rows, `json103` aggregate coverage, and prior smaller `json106/json108` invariant files.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP JSON helpers: `SQLiteJsonValidity`, `SQLiteJsonTree`, `SQLiteJsonExtract`, `SQLiteJsonRemove`, `SQLiteJsonMutation`, `SQLiteJsonPatch`, `SQLiteJsonPretty`, `SQLiteJsonCanonical`, and `SQLiteJsonB`.
