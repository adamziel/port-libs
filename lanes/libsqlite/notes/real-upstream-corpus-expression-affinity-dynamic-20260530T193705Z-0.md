# real-upstream-corpus-expression-affinity-dynamic-20260530T193705Z-0

Base accepted HEAD: `28f29f1b7137ae1bf099a6bea9838aec79fed0b3`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
  - `e_expr-6.5`: `%` casts operands to integer and returns integer remainder.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test`
  - `types2-4.4`, `types2-4.6`, `types2-4.8` through `types2-4.12`,
    `types2-4.14` through `types2-4.20`, and `types2-4.23` through
    `types2-4.27`.
  - `types2-5.1` through `types2-5.14`.

Patch:

- Fixed `SQLiteSelectExpression` so `%` always returns the integer remainder
  after integer coercion, matching upstream `e_expr-6.5`.
- Extended `SQLiteRealUpstreamCorpusExpressionAffinityDynamicTest.php` with
  source-traced no-index `types2` greater-than and IN-list affinity cases.

Focused evidence:

- Before the fix, `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicTest.php`
  failed `e_expr-6.5`: expected `[2]`, actual `[2.0]`.
- After the fix, the same command passes: `1 test files, 6357 assertions,
  0 failures`.
- PASS-line count for the focused file after the patch: `3853`.
- Newly passing focused cases over the pre-edit red baseline: `+33`
  (`e_expr-6.5` plus 32 new `types2` cases).

Non-overlap:

- Does not repeat accepted indexed `types2-2.*`, `types2-3.*`, or
  `types2-6.*` rowset cases.
- Does not add metadata-only runner rows or fabricated upstream script IDs.
- Uses generic SQLite/application terminology only.

Dependency closure: no new external dependency is needed. The slice reuses the
existing bounded PHP expression, affinity, and corpus helpers.

Root harness: not run - isolated micro-slice.
