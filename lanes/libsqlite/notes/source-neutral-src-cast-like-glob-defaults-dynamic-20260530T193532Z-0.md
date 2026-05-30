Source-neutral cleanup for `source-neutral-src-cast-like-glob-defaults-dynamic-20260530T193532Z-0`.

- Neutralized the INSERT DEFAULT VALUES focused fixture/example from legacy application-specific setting/default names to generic `app_setting_defaults`, `setting_id`, `key_name`, `key_value`, and `load_policy` names.
- Extended `SQLiteEncodingSourceNeutralDefaultsTest` to guard the assigned default/dynamic production files in addition to the existing cast/LIKE/GLOB source files.
- Production behavior is unchanged; this is a source/test surface cleanup for generic SQLite application settings terminology.
- Dependency closure: no new support component needed.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteInsertDefaultValuesGeneratedDefaultTest.php lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php` => 2 test files, 52 assertions, 0 failures.
- `php lanes/libsqlite/examples/application-insert-default-values-generated-default.php --self-test` => self-test completed and emitted the generic inserted row payload.
- `php -l` passed for changed PHP files.
- `git diff --check -- lanes/libsqlite` passed.
