# real-upstream-corpus-json1-jsonb-dynamic-20260530T231947Z-0

Added a real upstream JSON104 merge-patch dynamic corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test`

Ported sections:

- `json104-100` through `json104-103`: RFC-7396 object merge patch with strict JSON and JSON5 target/patch variants.
- `json104-110`: nested author/tags replacement and null deletion.
- `json104-200` through `json104-222`: object patch behavior over array/object targets and nested null deletion.
- `json104-300` through `json104-320`: scalar, array, duplicate-key, and SQL NULL merge-patch boundaries.
- `json104-401` through `json104-405`: quoted object-key extraction after mutation.

Focused coverage:

- Expanded `SQLiteRealUpstreamJson104PatchDynamicCorpusTest.php` while preserving the existing per-case matrix.
- Focused run: `1 test files / 28162 assertions / 0 failures / 1229 PASS lines`.
- New focused growth over the existing file is `+6` PASS lines and more than `+5000` behavior assertions.
- The assertions exercise text `json_patch`, JSON5 input parity, `jsonb_patch` blob parity, SQL NULL propagation, scalar/array replacement, quoted-key extraction, `json_tree` scalar atom extraction, JSONB validity, and pretty/canonical round trips.

Non-overlap:

- This does not repeat accepted JSON101/102 constructor/extract dynamic coverage, JSON105 negative-index mutation, JSON106/108 invariants, JSON107 legacy blob text, JSON109 array insertion, JSON501/JSON502 JSON5/escaped labels, JSON table cursor/source/hidden/visible constraint work, JSON host joins, or JSON aggregate/window batches.
- It owns real upstream `json104.test` merge-patch behavior and uses dynamic generated cases derived from those upstream scenarios.

Dependency closure:

- No new support component is needed. This reuses existing native PHP JSON canonicalization, JSON5 parsing, JSONB, merge-patch, extraction, pretty, tree, and validity helpers.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson104PatchDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson104PatchDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
