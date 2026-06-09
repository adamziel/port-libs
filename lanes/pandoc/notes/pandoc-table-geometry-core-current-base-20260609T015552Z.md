# Pandoc table geometry invalid source scope handoff

Slice: `pandoc-table-geometry-core-current-base-20260609T015552Z`
Base: `a19062aa0e7b6be3ad1a3778a5e0376791e8169f`

## Behavior

- Added native PHP table-geometry handling for invalid source HTML `scope` values on table cells.
- `TableGeometry::diagnostics()` now emits `table-header-scope-invalid` when a source `scope` is present but is not one of `col`, `row`, `colgroup`, or `rowgroup`.
- Accessibility handoff keeps using computed Pandoc table scope when the source value is invalid.
- `WordPressBlockWriter` now treats invalid source `scope` as absent, renders the computed fallback `scope`, and suppresses the invalid raw source value from WordPress table output.

## Focused Evidence

- Baseline before patch: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - `1 test files, 1736 assertions, 0 failures`
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - `1 test files, 1764 assertions, 0 failures`
  - Adds 1 PHP PASS case and 28 focused assertions.
- Example smoke: `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - `table geometry handoff self-test ok`
- JSON validation: `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- PHP lint:
  - `php -l lanes/pandoc/src/TableGeometry.php`
  - `php -l lanes/pandoc/src/WordPressBlockWriter.php`
  - `php -l lanes/pandoc/tests/TableGeometryTest.php`
  - `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
  - All reported no syntax errors.
- Whitespace check: `git diff --check -- lanes/pandoc`
  - Passed with no output.
- Root harness: not run - isolated micro-slice.

## Mapping Delta

- `lane-status.json` `phpPass`: `2093 -> 2094`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2504 -> 2505`.
- `mappedTableGeometryCoreCases`: `9 -> 10`.
- `tableGeometryCoreAssertions`: `155 -> 183`.

## Non-Overlap

This slice does not repeat accepted source `scope=row`, `scope=rowgroup`, `scope=colgroup`, duplicate header id/reference, axis, abbreviation, declared-column overflow, malformed span, width/layout, frame/rules, spacing, background, or cell background table-geometry work. It only covers invalid source `scope` values and their WordPress accessibility fallback.

## Dependency Closure

No new support component is needed. The behavior reuses the existing native PHP table geometry, source attribute, and WordPress block writer support. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF engine, online service, live provider test, or live-service provider test was executed.

## Next

Potential follow-up: source `headers` recovery and writer-specific accessibility diagnostics for malformed or unresolved header-reference combinations that are distinct from the already accepted duplicate-header-id coverage.
