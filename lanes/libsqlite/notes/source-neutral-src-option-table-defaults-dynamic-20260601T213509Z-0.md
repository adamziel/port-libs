# source-neutral-src-option-table-defaults-dynamic-20260601T213509Z-0

Status: ready for integration.

Source-neutral cleanup:

- Current accepted source already has no production `wp_`, `wp_options`, `option_id`, `option_name`, `option_value`, `autoload`, or `blog_id` matches under `lanes/libsqlite/src`.
- Hardened `SQLiteSourceNeutralOptionTableDefaultsDynamicTest.php` so the option-table default guard now scans the full dynamic PHP source inventory under `lanes/libsqlite/src`, not only the older fixed default-source groups.
- Kept the existing focused group checks for the previously neutralized option table, JSON path, trigger/FK, VFS, and JSON schema import defaults.
- No `phpPass` or mapped-coverage counter movement is claimed; this source-neutral guard patch prevents future production-source regressions.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralOptionTableDefaultsDynamicTest.php`
  - no syntax errors detected.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralOptionTableDefaultsDynamicTest.php`
  - `1 test files, 72 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 8 assertions, 0 failures`.
- `rg -n "wp_|wp_options|option_id|option_name|option_value|autoload|blog_id" lanes/libsqlite/src --glob '*.php'`
  - no production source matches.
- `git diff --check -- lanes/libsqlite`
  - passed.

Dependency closure: no new support component is needed. This reuses the existing source-neutral default planners and the existing no-domain guard surface.

Root harness: not run - isolated micro-slice.
