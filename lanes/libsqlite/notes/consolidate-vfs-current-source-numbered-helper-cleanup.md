# VFS Current-source Numbered Helper Cleanup

Consolidated two remaining numbered private production helper names in
`SQLiteVfsCurrentSourceNextPlan` into stable descriptive helpers:

- `run182185()` is now `runTempDirectoryReadonly()`.
- `run186189()` is now `runReservedLockFileControl()`.

The externally asserted slice labels, dependency strings, event statuses, and
receipt keys are unchanged. The duplicate `snapshot-reuse-publication` dispatch
arm was also removed without changing the canonical target helper.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext182185Test.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext186189Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext*Test.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this reuses the
existing canonical VFS current-source dispatcher and preserves current behavior.
