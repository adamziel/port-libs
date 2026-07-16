# pandoc-legacy-doc-cfb-core-current-base-20260609T120427Z

## Scope

- Lane: `pandoc`
- Accepted base: `e01d0106d0e5222c23a21bcfd0a6b70a04cfac0d`
- Behavior cluster: legacy DOC/CFB CHPX hidden-text suppression.

## Implementation

- `LegacyDocReader` now maps enabled CHPX hidden text properties (`sprmCFVanish`) to `hiddenTextSuppressions` entries with CP ranges, source sprms, character counts, and the `suppressed-hidden-text-native-review` policy.
- Paragraph rendering removes those hidden CP ranges before Markdown or WordPress block serialization while preserving the metadata on the returned packet and document attributes.
- The existing WordPress legacy DOC character-formatting example now includes a hidden reviewer run and verifies that visible formatting survives while hidden text and metadata do not render into blocks.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` => `1 test files, 2527 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` => `1 test files, 2532 assertions, 1 failures` before source support; the new hidden-text test failed on missing `hiddenTextSuppressionCount`.
- Final focused: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` => `1 test files, 2553 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-character-formatting-handoff.php --self-test` => `legacy doc character-formatting handoff self-test ok`.
- Syntax: `php -l lanes/pandoc/src/LegacyDocReader.php`, `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`, and `php -l lanes/pandoc/examples/wordpress-legacy-doc-character-formatting-handoff.php` all reported no syntax errors.
- JSON: `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` decoded successfully with `JSON_THROW_ON_ERROR`.
- Diff hygiene: `git diff --check -- lanes/pandoc` completed with no output.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2740` -> `2741`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2936` -> `2937`.
- `legacyDocCfbCoreCases`: `7` -> `8`.
- `mappedLegacyDocCfbCoreCases`: `7` -> `8`.
- `legacyDocCfbCoreAssertions`: `64` -> `90`.

## Dependency Closure

No new support component is needed. This slice reuses the existing bounded native PHP CFB reader, Word FIB text-range handling, CHPX/FKP parsing, AST nodes, Markdown writer, and WordPress block writer. No Pandoc, Word, LibreOffice, zip/unzip, external converter, online service, live provider test, model, or GPU path is required.

## Non-Overlap

This does not repeat the accepted legacy DOC/CFB Unicode text extraction, encryption preflight, form-field data handoff, inline picture placeholder, revision-mark metadata, or semantic inline formatting slices. It covers only CHPX hidden text suppression and review metadata preservation.

## Follow-Up

Next legacy DOC/CFB work should target a non-overlapping Word binary gap such as complex piece-table ANSI/Unicode mixing, paragraph/list/table property handoff, or embedded OLE object boundaries.
