# real-upstream-corpus-json1-jsonb-dynamic-20260531T012723Z-0

Base accepted HEAD: `a890092c734c05eb72a795bdc37321c497f93beb`.

Added `SQLiteRealUpstreamJson106InvariantDeterministicDynamicTest.php`, a deterministic PHP port of hydrated upstream `json106.test` loop invariants:

- `json106` loop `ii.1`: strict JSON and JSON5 validity checks for paired `j0` / `j5` documents.
- `json106` loop `ii.2` / `ii.3`: `json_tree()` scalar atom path parity against `json_extract()` and `->>` over text and JSONB inputs.
- `json106` loop `ii.5` / `ii.6`: remove then insert key round trips over text and JSONB.
- `json106` loop `ii.7`: `json_patch()` key preservation across JSON text and JSONB patches.
- `json106` loop `ii.8` / `ii.9`: `json_pretty()` canonical round trips.

Focused evidence:

`php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson106InvariantDeterministicDynamicTest.php`

Result: `1 test files, 19253 assertions, 0 failures`, with `1001` focused PASS lines.

Expected dashboard movement if accepted: `phpPass` `1508939 -> 1509940` (`+1001`), mapped coverage unchanged at `1589 / 1589`.

Non-overlap: this ports `json106.test` deterministic invariant behavior and avoids the already accepted JSON101/102 constructors/extracts, JSON103 aggregates, JSON104 patch matrices, JSON105 reverse-index paths, JSON107 BLOB compatibility, JSON108 pretty-only remainder, JSON109 array insert, JSON501/502 JSON5/escaped path, and JSONB01 remove sweeps.

Dependency closure: no new support component is needed; this reuses existing native JSON1/JSONB, JSON5 parser, JSON table, mutation, patch, pretty, and SELECT expression helpers.
