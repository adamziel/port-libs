# SQLite Pager Savepoint Current/Next69

## Behavior

This slice covers the pager/savepoint edge where `ROLLBACK TO current` keeps the
named savepoint open and the next nested savepoint/page write must be recorded
under that retained current frame. The helper clears discarded nested WAL frames
and statement journals, opens the next savepoint, and optionally records the
next page/WAL frame using the rollback prefix frame index.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSavepointCurrentNext69Test.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `68 PASS` lines
  - `1 test files, 68 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-savepoint-current-next69.php --self-test`
  - `application-pager-savepoint-current-next69 self-test passed`
- `php -l lanes/libsqlite/src/SQLiteSavepointStack.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteSavepointStack.php`
- `php -l lanes/libsqlite/tests/SQLitePagerSavepointCurrentNext69Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePagerSavepointCurrentNext69Test.php`
- `php -l lanes/libsqlite/examples/application-pager-savepoint-current-next69.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-pager-savepoint-current-next69.php`
- `git diff --check -- lanes/libsqlite`
  - clean

## Dashboard Delta

- `phpPass`: `25516 -> 25584` (`+68` focused PASS lines verified locally).
- `benchmarkDenominator.mapped`: unchanged. This is runtime pager/savepoint
  behavior evidence and does not claim a new upstream inventory denominator row.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This avoids accepted batch67 pager journal lifecycle/sync behavior, accepted WAL
byte truncation, savepoint page-image rollback, VFS savepoint rollback
application, rollback-journal commit/application, and WAL reader/checkpoint
visibility clusters. The new behavior is specifically the next nested savepoint
opened after `ROLLBACK TO current` preserves the current frame.

## Dependency Closure

No new support component is needed. The slice reuses the existing native
`SQLiteSavepointStack` pager/savepoint state model and existing focused
TestRunner/Application smoke infrastructure.
