# ODF/OpenDocument Table-Cell Detective Metadata Slice

Micro-slice: `pandoc-odf-open-document-core-current-base-20260609T044848Z`
Base accepted HEAD: `ed0eac2bb60c741dd69063ef3bea95aa86948d6f`

## Behavior

This slice adds native PHP ODF/OpenDocument handling for `table:detective`
children under `table:table-cell`.

- Preserves `table:highlighted-range` children as `odfCellDetective.highlightedRanges`.
- Preserves `table:operation` children as `odfCellDetective.operations`.
- Keeps formula tracing metadata inert; formulas and ranges are not evaluated.
- Adds WordPress-safe cell attributes such as
  `data-odf-cell-detective-highlight-count`,
  `data-odf-cell-detective-ranges`,
  `data-odf-cell-detective-directions`, and
  `data-odf-cell-detective-operation-names`.
- Reports import counters for detective cells, highlighted ranges, and
  operations.

## Evidence

Red-first check before implementation:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result: failed on the new ODF detective test because the cell only had
`odf-table-cell-value` and `odf-table-cell-formula`, not
`odf-table-cell-detective`.

Final focused verification:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result: `1 test files, 3016 assertions, 0 failures`.

`php lanes/pandoc/examples/wordpress-odf-database-field-handoff.php --self-test`

Result: `odf database field handoff self-test ok`.

Syntax and lane checks:

- `php -l lanes/pandoc/src/OdfReader.php`: no syntax errors.
- `php -l lanes/pandoc/tests/OdfReaderTest.php`: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-odf-database-field-handoff.php`: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`: `lane-status json ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "manifest json ok\n";'`: `manifest json ok`.
- `git diff --check -- lanes/pandoc`: passed with no output.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP DOM parsing in
`OdfReader`, existing table-cell metadata attributes, `ZipPackage` fixtures,
and `WordPressBlockWriter` attribute emission. No Pandoc, Haskell runner, Word,
LibreOffice, zip/unzip, external converter, online service, live provider test,
or live-service provider test was executed.

## Non-Overlap

This does not duplicate the accepted duplicate named range/expression,
data-pilot, database-range subtotal, calculation setting, dropdown field, table
annotation, draw layer, chart/object, manifest, or list-style ODF slices. A
good next ODF follow-up is additional data-pilot source metadata, database-range
edge provenance, quoted sheet-name range tokenization, or style-driven
table/list semantics.
