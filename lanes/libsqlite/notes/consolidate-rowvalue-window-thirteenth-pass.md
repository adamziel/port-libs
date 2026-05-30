# Row-Value Window Consolidation Thirteenth Pass

## Scope

- Consolidated the unused public `executeNext699()` through `executeNext734()` row-value/window wrapper chain in `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`.
- Replaced the chain with `readyPublicationSeedThroughCurrentBase()`, a stable private helper that advances the same continuation steps through the existing canonical step applicator.
- Kept the public unsuffixed `executeReadyPublicationContinuation()` entrypoint as the direct caller used by the final-handoff Application smoke/test.

## Verification

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowReadyPublicationFinalHandoffTest.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-ready-publication-final-handoff.php --self-test`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. This is production method-wrapper consolidation only; it preserves the existing row-value UPDATE/DELETE RETURNING window continuation behavior and direct Application smoke path.
