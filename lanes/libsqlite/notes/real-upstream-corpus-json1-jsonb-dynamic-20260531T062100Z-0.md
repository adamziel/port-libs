# real-upstream-corpus-json1-jsonb-dynamic-20260531T062100Z-0

Added a focused real-upstream JSON1/JSONB dynamic corpus test file sourced from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`.

Owned upstream sections:

- `json101-3.5`: duplicate `json_set()` path behavior where the later edit wins and `json_tree()` exposes the final value.
- `json101-4.1..4.3`: top-level JSON values with leading/trailing SQLite JSON whitespace.
- `json101-4.9`: control-character text insertion through `json_insert()` / `jsonb_insert()`.
- `json101-4.10` / `json101-4.10b`: root `json_extract(...,'$')` parity for text and JSONB.

Focused test evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101RootMutationDynamic20260531Test.php`
- Expected focused growth: 1,102 distinct TestRunner PASS cases and 8,963 behavior assertions.

Non-overlap:

- Does not repeat the existing 20260531 dynamic expansion file, which covers
  `json107` BLOB compatibility, `json109` array insertion, and `json105`
  reverse paths.
- Does not change mapped denominator records; mapped coverage is already
  complete at 1589 / 1589.

Dependency closure: no new support component is needed; this reuses the
existing native PHP JSON canonicalization, JSONB, mutation, inspection,
`json_tree()`, and SELECT expression dispatch components.
