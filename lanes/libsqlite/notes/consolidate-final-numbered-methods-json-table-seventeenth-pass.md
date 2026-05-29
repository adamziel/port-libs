2026-05-29 JSON-table seventeenth consolidation pass

- Consolidated the generated-path rowid current-source admission wrapper into
  the stable
  `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceAdmissionPlan()`.
- Renamed the direct private admission helpers by removing the worker number
  suffix and updated the immediate downstream order wrapper to consume the
  stable admission replan key and dependency marker.
- Migrated the direct focused test and WordPress smoke to unsuffixed filenames
  and scenario text while preserving the same admission assertions.
- Verification: `php -l` for changed PHP files, focused
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCurrentSourceAdmissionTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext164Test.php`
  passed with `2 test files, 119 assertions, 0 failures`, and
  `php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-current-source-admission.php --self-test`
  passed.
- Dependency closure: no new support component needed; this reuses the existing
  native JSON table generated-path, rowid seek, and current-source planner
  profiles.
