# real-upstream-corpus-json1-jsonb-dynamic-20260531T043204Z-0

Lane: `libsqlite`

Base accepted HEAD: `7db59d242cf2590641e3217c1b87d71727256c92`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json502.test`

Ported behavior cluster:

- `json502-3.1` and `json502-3.2`: escaped JSON object labels compare equal across source labels and `->` / `->>` RHS labels.
- `json502-3.4`: `json_patch()` / `jsonb_patch()` match equivalent escaped labels.
- `json502-4.1`: quoted path lookup for escaped control-character labels.
- `json502-5.1`, `json502-5.2`, and `json502-5.3`: quoted and unquoted escaped-quote paths work for extraction and JSON mutation.
- Additional escaped-label variants in the same behavior family cover `\x`, `\u`, quote, slash, whitespace escapes, surrogate-pair labels, text JSON inputs, JSONB inputs, `SQLiteSelectExpression`, and parser-level `SQLiteSelectSql`.

Focused movement:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamJson502EscapedLabelDynamicTest.php`.
- The focused test defines `1014` distinct TestRunner cases and passed with `17208` assertions.
- This is non-overlapping with the accepted JSON501 control-character corpus and existing JSON102 operator JSONB coverage because it targets `json502.test` escaped object label/path equivalence plus JSON patch/mutation behavior.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson502EscapedLabelDynamicTest.php`
  - Result: `1 test files, 17208 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP JSON5 parsing, JSON path normalization, JSONB encode/decode, JSON patch/mutation, and SELECT expression/SQL dispatch.

Root harness:

- Not run - isolated micro-slice.
