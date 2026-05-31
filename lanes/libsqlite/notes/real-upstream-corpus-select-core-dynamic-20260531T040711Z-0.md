# real-upstream-corpus-select-core-dynamic-20260531T040711Z-0

Added `SQLiteRealUpstreamESelect2JoinCollationDynamicTest.php` as a focused
real-upstream SELECT core corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select2.test`
- `e_select-2.1.19` through `e_select-2.1.27`: joins where a NOCASE-collated
  operand changes ON/USING/LEFT JOIN match behavior relative to binary
  comparison.

Focused behavior:

- 1000 dynamic generic row sets.
- Each row set checks inner JOIN with explicit `COLLATE nocase` on the left
  comparison operand, inner JOIN with explicit `COLLATE nocase` on the right
  comparison operand, binary inner JOIN non-match, NOCASE LEFT JOIN
  null-extension, and binary LEFT JOIN null-extension.
- The upstream schema-level `t3(b COLLATE nocase)` default is represented as
  explicit `COLLATE nocase` in the SELECT text because the row-array executor
  does not carry `CREATE TABLE` column collation metadata.

Non-overlap:

- This does not repeat existing e_select2 cartesian/ON/USING/LEFT JOIN dataset
  coverage, e_select2 subquery affinity coverage, selectD parenthesized JOIN
  coverage, expression ORDER BY, grouped SELECT text, JSON table SELECT source
  work, or metadata-only runner rows.
- Mapped denominator remains unchanged because `e_select2.test` is already in
  the hydrated upstream runner-map evidence.

Dependency closure:

- No new support component is needed. The batch reuses native
  `SQLiteSelectSql`, `SQLiteSelectPredicate`, JOIN execution, explicit
  `COLLATE nocase` comparison, ORDER BY, and LEFT JOIN null-extension support.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamESelect2JoinCollationDynamicTest.php`
  reported no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelect2JoinCollationDynamicTest.php`
  passed with `1 test files, 22006 assertions, 0 failures`.
- Focused PASS-line growth: `1001` distinct TestRunner PASS cases.
- `git diff --check -- lanes/libsqlite` passed.
- `SQLiteNoWordPressSpecificApiTest.php` is not present in this worktree.
