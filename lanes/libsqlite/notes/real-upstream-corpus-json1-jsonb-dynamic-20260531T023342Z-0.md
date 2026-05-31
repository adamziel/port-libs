# real-upstream-corpus-json1-jsonb-dynamic-20260531T023342Z-0

- Base accepted HEAD: `0374bb37770e0bf365d4f603a02af1f3e153889e`.
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json107.test`.
- Ported scenarios: `json107-1.1` JSON validity flags for text-looking BLOB input, `json107-1.2` `->` / `->>` operator compatibility, `json107-1.3` through `json107-1.8` JSON function compatibility, and `json107-2.1` `json_tree()` over BLOB JSON text.
- Added PHP coverage: `SQLiteRealUpstreamJson107BlobOperatorDynamicMatrixTest.php` with 256 distinct nested JSON BLOB documents, 1,025 TestRunner PASS cases, and 8,707 assertions.
- Non-overlap: this slice does not add JSON table cursor/source/hidden/visible constraint planner coverage, JSON109 array-insert loops, JSON105 reverse-index mutation loops, JSON108 pretty invariants, JSON102 multi-path extraction, or JSONB remove-path parity already present in current accepted JSON corpus files. It targets legacy BLOB-as-text behavior through SELECT-expression operators and mixed JSON function dispatch.
- Dependency closure: no new support component is needed; the existing native JSON canonicalization, JSON path, JSON mutation/remove, JSON tree, JSON validity, and SELECT expression components are reused.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson107BlobOperatorDynamicMatrixTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 8707 assertions, 0 failures
```
