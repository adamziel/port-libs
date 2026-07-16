# real-upstream-corpus-json1-jsonb-dynamic-20260531T045916Z-0

Base accepted HEAD: `d470482ec8f04bd52049cae518f9a06a2103fe0c`.

Added focused real-upstream JSON1/JSONB corpus coverage from hydrated upstream
`/home/claude/port-libs/.upstream-cache/libsqlite/test/json109.test`.

Owned upstream sections:

- `json109-1.1` repeated `$[0]` inserts.
- `json109-1.2` `$[0]` followed by `$[#]`.
- `json109-1.3` through `json109-1.5` positive current-array insertion slots.
- `json109-1.6` through `json109-1.9` reverse `#-N` current-array insertion
  slots, including the too-far no-op.

Behavior covered:

- `json_array_insert` canonical text result parity.
- `jsonb_array_insert` strict JSONB result parity.
- `json_array_insert` over JSONB input.
- SQL scalar insertion, text insertion, JSON subtype insertion, and JSONB
  insertion over the same upstream index semantics.
- Result validity, array length, decoded result parity, and SQLite
  `json_extract` scalar-vs-compound return boundaries.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson109ArrayInsertValueDynamicCorpusTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson109ArrayInsertValueDynamicCorpusTest.php`
  passed with `1 test files, 29162 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson109*.php`
  passed with `4 test files, 44498 assertions, 0 failures`.
- An attempted adjacent run including
  `lanes/libsqlite/tests/SQLiteJsonbMutationPathCurrentNext16Test.php` exposed
  three pre-existing empty-object expectation failures unrelated to this slice;
  that file is excluded from the handoff evidence.

Expected dashboard movement:

- Adds `3241` distinct TestRunner PASS cases in one focused test file.
- Adds `29162` focused behavior assertions.
- No mapped-denominator movement; `UPSTREAM_TEST_MANIFEST.json` is already
  complete at `1589 / 1589`.

Non-overlap note:

- This does not repeat the existing json109 error-matrix, atomic-error, or
  generic bulk-array tests. The new file focuses on upstream `json109-1.1`
  through `json109-1.9` current-array insertion semantics across value
  representations and validates JSONB input/output plus extraction boundaries.

Dependency closure:

- No new support component is needed. The existing native PHP JSONB, JSON
  subtype, canonicalization, path, array-insert, extract, inspection, and
  validity components are reused.
