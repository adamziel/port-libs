# Source-Neutral JSONB CHECK Current-Source Dynamic

Slice: `source-neutral-src-jsonb-check-current-source-dynamic-20260601T222749Z-0`
Base accepted HEAD: `6c407b945def566045574bae6568d2cc8a5d78a8`

## Change

- Expanded `SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php` so the
  JSONB CHECK source-neutral guard dynamically includes every current
  `SQLiteJsonb*.php` production file plus direct JSONB CHECK tests/examples.
- Tightened the forbidden token list for the owned source and fixture scan to
  include the historical WordPress-shaped API tokens from the supervisor rule.
- The current focused production-source scan found no remaining `wp_*`,
  `option_*`, `autoload`, `blog_id`, or WordPress-shaped JSONB CHECK source
  strings, so this slice does not rename production behavior or move counters.

## Evidence

- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php`
  -> no syntax errors.
- Focused production-source scan over the owned JSONB current-source files
  with `rg` for WordPress-shaped source terms -> no matches.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php`
  -> `1 test files, 28 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext64Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext67Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext68Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext69Test.php lanes/libsqlite/tests/SQLiteJsonbGeneratedCheckIndexCurrentNext54Test.php lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  -> `7 test files, 426 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` -> passed.

## Dependency Closure

No new support component is needed. This source-neutral slice only broadens the
existing guard over current JSONB production source and direct fixtures.

Root harness: not run - isolated micro-slice.
