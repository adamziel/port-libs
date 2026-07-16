# Source-Neutral Compound Window Defaults Dynamic

Slice: `source-neutral-src-compound-window-defaults-dynamic-20260601T221343Z-0`
Base accepted HEAD: `61d6474675e62bc503d755e05f4aa9303f52ded5`

## Change

- Neutralized the directly coupled compound/HAVING/window fixture pair for
  `SQLiteCompoundHavingWindowCurrentSourceNextPlan`.
- Replaced the old `wp_options` / `wp_options_stage` table defaults with
  `app_settings` / `app_settings_stage`.
- Renamed fixture keys from `option_id`, `option_name`, and `autoload` to
  `setting_id`, `key_name`, and `load_policy`.
- Replaced WordPress-shaped sample values such as `siteurl`, `home`,
  `active_plugins`, and `plugin_*` with generic application setting names.
- Extended `SQLiteSourceNeutralCompoundWindowDefaultsDynamicTest` so the
  cleaned test/example pair is guarded against those legacy fixture terms.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundHavingWindowCurrentSourceNext128Test.php lanes/libsqlite/tests/SQLiteSourceNeutralCompoundWindowDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `3 test files, 160 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-compound-having-window-current-source-next128.php --self-test`
  - Result: `application-compound-having-window-current-source-next128 self-test passed`.
- `php -l` changed PHP files.
  - Result: no syntax errors in the changed test and example files.
- `git diff --check -- lanes/libsqlite`
  - Result: passed with no output.

## Status Delta

Source-neutral cleanup only. No `phpPass`, mapped coverage, or broad parity
counter movement is claimed.

## Dependency Closure

No new support component is needed. The cleanup reuses the existing native
SELECT SQL compound execution, aggregate HAVING predicate, correlated subquery,
and window projection implementation.

## Non-Overlap

This is a fixture and guard cleanup for the compound/HAVING/window defaults
surface. It does not add upstream PASS rows, runner metadata, compatibility
aliases, dashboard/root edits, or new domain-shaped wrappers.

Root harness: not run - isolated micro-slice.
