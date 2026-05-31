# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T044633Z-0

Status: focused PHP behavior growth for generic row-value UPDATE assignment
with ordered LIMIT tuple sources.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now accepts row-value assignments whose RHS
  is a parenthesized constant `SELECT` expression list, for example
  `SET (state, key_value) = (SELECT 'next', key_value || ':next')`.
- The added coverage combines that assignment form with row-value
  `IN (SELECT tenant_id, key_name ... ORDER BY ... LIMIT/OFFSET)` predicates,
  comma-form LIMIT windows, RETURNING source-order checks, and generic
  `app_settings` rows.
- Malformed row-value assignment SELECT lists with wrong arity or a `FROM`
  clause remain rejected by the bounded parser.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test`
  row-value UPDATE assignment SELECT scenarios around `rowvalue-16`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT selection behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET window semantics.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from `8201` to
  `8459` assertions.
- The added behavior contributes `50` focused TestRunner PASS cases over the
  current accepted row-value/update-delete-limit dynamic parity file.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 8459 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING parser, row-value tuple source evaluator, assignment
  callback path, and LIMIT/OFFSET selection plan.

Non-overlap:

- This extends row-value UPDATE assignment parsing with `SELECT` expression
  lists. It does not repeat prior LIMIT scalar expression matrices, row-value
  nullable tuple predicates, generic UPDATE/DELETE ORDER BY LIMIT selection,
  grouped SELECT, JSON table, WAL/VFS, B-tree, PRAGMA, trigger/FK,
  source-neutral cleanup, or metadata-only suite evidence surfaces.
