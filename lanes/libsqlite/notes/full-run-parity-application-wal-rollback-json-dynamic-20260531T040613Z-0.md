# full-run-parity-application-wal-rollback-json-dynamic-20260531T040613Z-0

Base accepted HEAD: `86b40e76030ee95766e1bca45c19abb4f5a3c27f`.

## Behavior

Extended the generic application WAL rollback JSON dynamic parity surface with
prefix-preserving retry scenarios. The previous accepted dynamic matrix covered
whole-batch rollback, deferred failed statements, retry after rollback to an
empty WAL header, and rollback that preserves committed WAL prefix frames. This
slice combines the latter two cases: a failed JSON batch rolls back to an
existing WAL prefix, then a corrected retry opens a new savepoint after that
prefix and succeeds without truncating committed frames.

The new deterministic matrix varies page size, tenant id, JSON text versus
JSONB catalog rows, preexisting WAL frame counts, and corrected retry writes.
Each case verifies the failed batch truncates only current JSON frames, the
retry starts from restored database bytes and preserved WAL bytes, successful
retry savepoint rollback previews start after the prefix frame boundary, and
tenant/page isolation survives the retry.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  - `1 test files, 2280 assertions, 0 failures`

Focused assertion movement in this file is `+349` over the prior recorded
`1931` assertions for the same dynamic parity file.

## Non-Overlap

This does not repeat the static current-next38 rollback rows, the initial
rollback-to-frame-zero branch, deferred failure behavior, retry after empty-WAL
rollback, preexisting-WAL rollback without retry, VFS savepoint rollback
application, rollback-journal commit/apply, WAL byte truncation primitives,
pager WAL dynamic corpus, JSON table cursor/source/constraint work, or upstream
JSON constructor/path/error matrices. It owns only the application-level retry
case where committed WAL prefix frames survive both the failed batch rollback
and the corrected retry.

## Dependency Closure

No new support component is needed. This reuses native PHP JSON/JSONB mutation,
savepoint-stack WAL frame tracking, WAL byte parsing, page-image rollback, and
the existing focused TestRunner path. Full release/all-runner parity remains
open outside this slice.
