# real-upstream-corpus-json1-jsonb-dynamic-20260530T224519Z-0

Base accepted HEAD: `dc9a740fd34e07dba61e9143b3604d183ad170bf`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`

Ported behavior cluster:

- `json101-5.3` and `json101-5.3b`: `json_tree()` `fullkey` is always
  `path || formatted(key)` for text JSON and JSONB inputs.
- `json101-5.4`: root-path `json_tree()` fullkey/path/key composition.
- `json101-5.5` and `json101-5.6`: `json_each()` and `json_tree()` preserve
  the input JSON value in the table-valued `json` column.
- `json101-5.7` and `json101-5.8`: scalar table-valued rows keep `value` and
  `atom` equal while container rows keep `atom` NULL.

Focused test added:

- `lanes/libsqlite/tests/SQLiteRealUpstreamJson101TreeInvariantDynamicMegaTest.php`

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101TreeInvariantDynamicMegaTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 625559 assertions, 0 failures
```

PASS-line and assertion delta:

- Adds 1,001 distinct TestRunner PASS cases.
- Adds 625,559 focused behavior assertions.

Non-overlap:

- This is not metadata admission and does not add generated fake upstream
  script ids.
- It does not repeat JSON table cursor/source/hidden/visible planner work,
  JSON105 reverse-path mutation, JSON107 legacy BLOB text compatibility,
  JSON109 array insert, JSON501/JSON502 JSON5 escaped-path behavior, JSONB
  removal, JSON aggregate/window behavior, or JSON malformed planner edges.
- It widens the real upstream `json101.test` 5.3 through 5.8 table-valued
  function invariants with 1,000 distinct document shapes and checks text JSON
  plus JSONB input behavior in each PASS case.

Dependency closure:

- No new support component is needed. The batch reuses existing native PHP
  `SQLiteJsonEach`, `SQLiteJsonTree`, `SQLiteJsonExtract`,
  `SQLiteJsonCanonical`, and `SQLiteJsonB` helpers.
