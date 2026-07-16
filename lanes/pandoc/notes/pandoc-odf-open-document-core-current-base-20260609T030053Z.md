# pandoc-odf-open-document-core-current-base-20260609T030053Z

Base accepted HEAD: `05069a2190fe377801777d2d97b726785a631773`

Implemented one bounded ODF/OpenDocument support-library behavior: `OdfReader`
now preserves `table:calculation-settings` policy metadata in native PHP
content declarations and import reports. The slice records case sensitivity,
precision-as-shown, whole-cell search criteria, automatic labels, regex and
wildcard flags, null-year, iteration, iteration-count, and
iteration-tolerance. It does not evaluate spreadsheet formulas or execute any
office/spreadsheet engine.

Changed lane files:

- `lanes/pandoc/src/OdfReader.php`
- `lanes/pandoc/tests/OdfReaderTest.php`
- `lanes/pandoc/examples/wordpress-odf-database-field-handoff.php`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `lanes/pandoc/lane-status.json`
- `lanes/pandoc/notes/pandoc-odf-open-document-core-current-base-20260609T030053Z.md`

Focused evidence:

- Baseline before this slice: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed with `1 test files, 2806 assertions, 0 failures`.
- Red-first check after adding the new test and before implementation: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` failed with `1 test files, 2807 assertions, 1 failures`; `calculationSettingCount` was `NULL`.
- Final focused ODF check: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed with `1 test files, 2824 assertions, 0 failures`.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-odf-database-field-handoff.php --self-test` printed `odf database field handoff self-test ok`.
- PHP lint passed for `OdfReader.php`, `OdfReaderTest.php`, and the updated ODF database-field example.
- JSON status/manifest validation passed with `json ok`.
- Whitespace check: `git diff --check -- lanes/pandoc` passed with no output.

Status delta:

- `phpPass`: `2196 -> 2197`
- `benchmarkDenominator.mapped`: `2609 -> 2610`
- `mappedOdfOpenDocumentCoreCases`: `13 -> 14`
- `odfOpenDocumentCoreAssertions`: `295 -> 313`
- New focused assertion delta: `+18`

Dependency closure:

No new support component is needed. This slice reuses native `OdfReader`,
`ZipPackage`, `MarkdownWriter`, and `WordPressBlockWriter` paths. Calculation
settings are metadata-only handoff data; upstream Pandoc ODT runner parity
remains a separate hydrated-runner task.

Non-overlap:

This avoids the already accepted ODF subtotal-rules, data-pilot, named
expression, label-range, dropdown field, page-variable/statistic field,
list-marker style, and source metadata field clusters. The next good ODF
follow-up remains table print ranges, table scenarios, or additional data-pilot
source edge metadata.

Root harness: not run - isolated micro-slice.
