# Pandoc JSON/native table helper payload provenance

Slice: `plib-fni3x` / 2026-06-11 UTC.

Current base: `4c7bc3880`.

This slice preserves Pandoc table helper native payloads on shared AST nodes for both `PandocJsonReader` and `NativeReader`:

- table column alignment helper payloads as `alignmentNatives`
- table column width helper payloads as `columnWidthNatives`
- table body `RowHeadColumns` payload as `rowHeadColumnsNative`
- table cell `Align*`, `RowSpan`, and `ColSpan` payloads as `alignmentNative`, `rowSpanNative`, and `colSpanNative`

Focused verification:

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- Result: 1 test file, 809 assertions, 0 failures.

Full verification:

- `php tools/run-tests.php lanes/pandoc/tests`
- Result: 44 test files, 63900 assertions, 0 failures.

No Pandoc, JSON filter runner, Cabal/Haskell runner, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.
