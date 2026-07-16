# real-upstream-corpus-json1-jsonb-dynamic-20260530T185456Z-0

Base accepted HEAD: `49b5c4e4a088c53e02910590cc011ce37a3ffc52`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test`

Ported behavior:

- `json104-100` and `json104-110`: RFC-7396 merge-patch object replacement, nested null deletion, array replacement, and member insertion semantics.
- `json104-220`, `json104-221`, `json104-300` through `json104-307`, `json104-312`, and `json104-320`: object-vs-array replacement, missing/member deletion, scalar replacement, null preservation in arrays, and nested object merge behavior.
- Added `SQLiteRealUpstreamJson104MergePatchDynamicCorpusTest.php` with 1,101 distinct focused TestRunner PASS cases and 16,502 assertions. The 1,100 dynamic cases derive from those real upstream scenarios and compare native `json_patch()` / `jsonb_patch()` output against an independent RFC-7396 oracle, then verify deletion paths, added paths, validity, JSONB parity, decoded structure parity, JSON tree visibility, and scalar path extraction.

Non-overlap:

- This does not repeat accepted `json105.test` dynamic path coverage, `json109.test` array insert coverage, `json106` / `json108` invariant coverage, JSON table cursor/source/constraint work, JSON aggregate/window work, or malformed JSONB planner work.
- It focuses only on `json104.test` merge-patch behavior and uses generic JSON documents, not domain-shaped APIs or metadata-only suite rows.

Expected dashboard movement:

- `phpPass`: `355604 -> 356705` from 1,101 newly passing focused TestRunner cases.
- Focused gate: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson104MergePatchDynamicCorpusTest.php` passed with `1 test files, 16502 assertions, 0 failures`.
- Mapped coverage: unchanged at `1472 / 1589`; this ports real upstream behavior but does not claim new denominator rows.

Dependency closure:

- No new support component is needed. The batch reuses native `SQLiteJsonPatch`, `SQLiteJsonB`, `SQLiteJsonCanonical`, `SQLiteJsonExtract`, `SQLiteJsonTree`, and `SQLiteJsonValidity`.
