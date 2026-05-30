Consolidation slice: consolidate-final-numbered-production-suffix-cleanup-dynamic-20260529T234731Z-0

Scope:
- Renamed the private `v201_*` helper cluster in `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` to stable descriptive null-pattern rebind helper names.
- Preserved the public entrypoint, direct tests/examples, status keys, dependency strings, action/status labels, and proof metadata.

Verification:
- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext201Test.php`: 1 test file, 81 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext*Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNext*Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimRhsCurrentSourceNext*Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimNulCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimResumeTokenCurrentSourceNextTest.php`: 65 test files, 4967 assertions, 0 failures.
- `php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next201.php --self-test`: passed.
- `git diff --check -- lanes/libsqlite`: passed.

Dependency closure:
- No new support component is needed; this is a production helper-name consolidation only.
