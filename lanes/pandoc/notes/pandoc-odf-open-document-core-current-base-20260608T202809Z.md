# Pandoc ODF OpenDocument Core Current Base 20260608T202809Z

Base accepted HEAD: `f5131e36ddeeb4eb873da16b8f77aa4b6e597ea6`

Implemented one bounded ODF/OpenDocument metadata cluster: native `OdfReader`
now preserves `table:content-validations` declarations, validation
conditions/base-cell/allow-empty/display-list metadata, help/error message
text, inert `table:error-macro` metadata, and `table:content-validation-name`
references on table cells. Validated cells receive review attributes for the
resolved validation and WordPress-safe `data-odf-cell-content-validation-*`
attributes; no validation condition, spreadsheet recalculation, or macro is
executed.

Evidence:

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  passed with `1 test files, 2197 assertions, 0 failures`.
- Red-first: the same focused command failed with `1 test files, 2198
  assertions, 1 failures` because `contentValidationCount` was absent.
- Final: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  passed with `1 test files, 2233 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-odf-database-field-handoff.php --self-test`
  passed.

Status delta:

- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2234 -> 2235`.
- `mappedOdfOpenDocumentCoreCases`: `13 -> 14`.
- `odfOpenDocumentCoreAssertions`: `295 -> 331`.
- `lane-status.json` `phpPass`: `1811 -> 1812`.

Dependency closure:

No new support component is needed. This reuses native `OdfReader` content
declaration parsing, the Pandoc-like AST, `TableGeometry` source-attribute
coverage, and `WordPressBlockWriter`. Pandoc, Cabal/Haskell runners, Word,
LibreOffice, zip/unzip, external converters, online services, live provider
tests, validation execution, spreadsheet recalculation, and macro execution
remain out of scope.

Non-overlap:

This does not repeat the accepted ODF database range, subtotal-rules,
data-pilot, named-expression, tracked-table-change, field, style, table span,
formula/typed-value, or media/frame slices. It closes the previously noted
validation-declaration metadata gap.
