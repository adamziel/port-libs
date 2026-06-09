# Pandoc Rich Package Unsupported-Format Registry

- Micro-slice: `pandoc-rich-package-unsupported-format-current-base-20260609T165000Z`
- Source truth: pinned `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83` direct reader/writer format evidence and existing lane-native package readers.
- Scope: rich package direct-format status only. This does not claim new conversion support.

## Behavior

Added `RichPackageUnsupportedFormatRegistry`, a bounded registry for package-style Pandoc formats.

The registry reports:

- 9 rich package format rows.
- 5 upstream package-style input formats, with 3 bounded native inputs (`docx`, `odt`, `epub`) and 2 unsupported inputs (`pptx`, `xlsx`).
- 8 upstream package-style output formats, all still unsupported until native package writers exist.
- Source-alias diagnostics for legacy/container extensions such as `doc`, `ods`, `odp`, and `zip`.

Unsupported rows expose machine-readable gates and diagnostics instead of invoking external converters.

## Evidence

- `php -l lanes/pandoc/src/RichPackageUnsupportedFormatRegistry.php`
- `php -l lanes/pandoc/tests/RichPackageUnsupportedFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/RichPackageUnsupportedFormatRegistryTest.php`
  - Result: `1 test files, 61 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `39 test files, 56413 assertions, 0 failures`.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, office suite, zip/unzip, browser renderer, external converter, online service, live provider test, or live-service provider test was executed.

## Accounting

- `lane-status.json` `phpPass`: `2794 -> 2795`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3029 -> 3030`.
- New manifest counters: `mappedRichPackageUnsupportedFormatCases = 1`, `richPackageUnsupportedFormatAssertions = 61`.
