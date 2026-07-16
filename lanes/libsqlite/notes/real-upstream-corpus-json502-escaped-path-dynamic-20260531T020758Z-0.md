# real-upstream-corpus-json502-escaped-path-dynamic-20260531T020758Z-0

Slice: `real-upstream-corpus-json1-jsonb-dynamic-20260531T020758Z-0`

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json502.test`

Ported behavior cluster:

- `json502-3.1` and `json502-3.2`: escaped JSON labels compare equal after `\xNN` decoding on source labels and path labels.
- `json502-3.3`: quoted JSON path labels with trailing backslashes and embedded escaped quotes remain addressable.
- `json502-3.4`: `json_patch()` preserves escaped-label comparison semantics.
- `json502-4.1`: `json_tree()` preserves addressability of escaped control-code labels.
- `json502-5.1` through `json502-5.3`: quote-bearing path labels work in `json_extract()` and `json_set()`.

Coverage added:

- New focused test file: `SQLiteRealUpstreamJson502EscapedPathDynamicCorpusTest.php`.
- 250 generated real-corpus variants, each with four distinct TestRunner cases: text/JSONB extraction, mutation/patch, `json_tree()` fullkey round trip, and malformed-boundary validation.
- Verified focused output: `1 test files, 10503 assertions, 0 failures`.
- PASS-line movement: `+1001` focused TestRunner PASS cases.
- Mapped denominator movement: none; `json502.test` is already part of mapped upstream JSON inventory.

Non-overlap:

- Avoids accepted JSON table cursor/source/hidden/visible constraint work, JSON101 constructor corpus, JSON102 operator/mutation corpus, JSON104 merge-patch matrix, JSON105 reverse-index path corpus, JSON501/502 broad JSON5 stress coverage, and JSONB malformed/operator slices.
- This file focuses only on upstream `json502.test` escaped label/path comparison and control-key `json_tree()` addressability, with JSONB parity where the port exposes JSONB inputs.

Dependency closure:

- No new support component needed. The slice reuses the existing native PHP JSON5 parser, JSONB encoder/decoder, JSON path inspection, mutation, patch, validity, error-position, and `json_tree()` support.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson502EscapedPathDynamicCorpusTest.php`
  - `1 test files, 10503 assertions, 0 failures`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson502EscapedPathDynamicCorpusTest.php`
  - no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - no-domain guard passed
- `git diff --check -- lanes/libsqlite`
  - passed

Root harness: not run - isolated micro-slice.
