# Trigger RETURNING Consolidation Forty-Eighth Pass

Consolidated the recursive view-trigger `RETURNING` current-source
receipt/resume wrapper into the canonical
`SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceReceiptResumeFence()`
entry point. The direct focused test and WordPress smoke were renamed to stable
descriptive filenames:

- `SQLiteTriggerRecursiveViewReturningSourceResumeTest.php`
- `wordpress-trigger-recursive-view-returning-source-resume.php`

The production helper names, direct options, result keys, dependency tags, test
labels, and smoke references for this touched receipt/resume surface now use
the descriptive `source_resume` suffix instead of the removed worker-numbered
method/file suffix.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningSourceResumeTest.php`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-source-resume.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningSourceResumeTest.php`
  -> `1 test files, 84 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-source-resume.php`
  -> `wordpress-trigger-recursive-view-returning-current-source-source_resume self-test passed`
- `git diff --check -- lanes/libsqlite`
- Exact user-named numeric suffix scan over `src`, `tests`, `examples`, and
  `notes`: no matches
- Touched trigger source-resume numbered method/file reference scan: no matches

Dependency closure: no new support component is needed. This is a
consolidation-only cleanup over the existing native recursive view-trigger
`RETURNING`, current-source fingerprint, and source-resume receipt model.

Non-overlap: avoids accepted next191 fingerprint fencing, next188 ordinal
watermarks, next184 checkpoint acknowledgements, row-value `RETURNING`,
deferred FK trigger work, schema reparse, WAL/VFS, B-tree, JSON, PRAGMA, and
encoding clusters.
