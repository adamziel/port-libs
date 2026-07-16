# real-upstream-corpus-json1-jsonb-dynamic-20260530T215204Z-0

Added `SQLiteRealUpstreamJson104PatchDynamicCorpusTest.php` as an additive real upstream JSON1/JSONB behavior batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test`

Ported upstream sections:

- `json104-100` through `json104-110`: RFC-7396 merge patch object examples, including JSON5 target and patch key forms.
- `json104-200` through `json104-222`: object patch replacement of array targets and null-member deletion/preservation behavior.
- `json104-300` through `json104-320`: RFC-7396 example matrix, scalar/array/object replacement, duplicate patch-key final-value behavior, and SQL NULL argument behavior from `json104-300a` / `json104-310a`.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson104PatchDynamicCorpusTest.php`
- Result: `1 test files, 6325 assertions, 0 failures`
- Selected PASS cases: 1224 focused TestRunner PASS lines.

Non-overlap:

- This batch is limited to upstream `json104.test` JSON merge-patch behavior.
- It does not repeat accepted JSON table cursor/source wiring, hidden/visible constraint pushdown, JSON aggregate/window behavior, JSON101/JSON102 constructor/extract coverage, JSON105 reverse/append path behavior, JSON107 legacy blob-text behavior, JSON109 array-insert behavior, or JSONB remove coverage from `jsonb01.test`.
- Mapped denominator remains unchanged because the upstream JSON corpus inventory is already fully mapped; this is focused PHP PASS/assertion growth over an existing mapped upstream file.

Dependency closure:

- No new external support component is needed. This reuses the existing native PHP `SQLiteJsonPatch`, `SQLiteJsonB`, and JSON canonicalization helpers.
