# real-upstream-corpus-json1-jsonb-dynamic-20260530T193416Z-0

Added `SQLiteRealUpstreamJson501Json502DynamicCorpusTest.php`, a real upstream
JSON5/JSONB dynamic corpus batch sourced from the hydrated SQLite upstream
checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json502.test`

Covered upstream scenarios:

- `json501` JSON5 feature classes 1 through 8: unquoted identifier keys,
  trailing object/array commas, single-quoted strings, comments, hexadecimal
  integers, explicit signed numbers, and leading/trailing decimal numbers.
- `json501` malformed boundary rows `1.10`, `2.3`, `2.4`, `3.3`, and `3.4`.
- `json502` escaped-label and escaped JSON path behavior from rows `3.1`
  through `5.3`, including quoted-key path lookup, `\xNN` label equivalence,
  control-character labels, JSON tree fullkey exposure, `json_set`, and
  `json_patch`.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson501Json502DynamicCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS real upstream json501 JSON5 feature rows canonicalize and validate
PASS real upstream json501 JSON5 feature rows extract object keys arrays strings and numbers
PASS real upstream json501 JSON5 feature rows mutate and patch escaped members
PASS real upstream json502 escaped path labels match extraction and tree rows
PASS real upstream json501 json502 malformed forms keep upstream error boundary
PASS real upstream json501 json502 source coverage cites hydrated upstream files

1 test files, 9679 assertions, 0 failures
```

Countability: this handoff adds `6` focused TestRunner PASS cases and `9,679`
behavior assertions. It is countable as assertion growth, not mapped-denominator
growth.

Non-overlap: this avoids accepted `json101`, `json102`, `json103`, `json104`,
`json105`, `json106`, `json107`, `json108`, `json109`, `jsonb01`, JSON table
cursor/source/hidden/visible constraint, JSON host join, JSON aggregate/window,
and malformed JSONB planner batches. The new focus is the upstream JSON5
feature and escaped-path corpus in `json501.test` and `json502.test`.

Dependency closure: no new support component is needed. The slice reuses the
existing native PHP JSON5 parser, JSON canonicalizer, JSONB codec, mutation,
patch, extraction, validity, error-position, and JSON tree helpers.
