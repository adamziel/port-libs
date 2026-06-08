# ODF OpenDocument covered table-cell provenance

Slice: `pandoc-odf-open-document-core-current-base-20260608T212513Z`
Base: `d1134e2a181aaf4c0c02f2b0d3b93f388be55ad8`

Implemented one bounded ODF table behavior cluster: `table:covered-table-cell` placeholders are now preserved as source provenance without becoming extra rendered AST `table_cell` nodes. Covered cells that belong to a preceding `table:number-columns-spanned` anchor attach to that anchor with `odfCoveredCells`, source-column metadata, style/value/text provenance, and metadata-bearing `data-odf-covered-cell-*` WordPress review attributes. Leading covered placeholders, such as row-spanned source slots, are retained on the row node with the same review metadata. The parser also keeps the source-column cursor aligned after covered placeholders so the next real table cell can inherit the correct column default style.

Focused evidence:

- Clean-base before: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed with `1 test files, 2353 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed with `1 test files, 2397 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test` passed.
- PHP lint passed for `lanes/pandoc/src/OdfReader.php`, `lanes/pandoc/tests/OdfReaderTest.php`, and `lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`.

Dependency closure: no new support component is needed. This reuses the native ODF reader, existing table geometry review packet, and WordPress block writer attribute handoff. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external converter, online service, live provider test, or live-service provider test was run.

Non-overlap: this does not revisit recent settings.xml, typed metadata, inline meta spans, table cell style properties, tracked table changes, data-pilot/named-expression declarations, table templates, row/column repeats, captions, or field handoff slices.

Next ODF task: choose a non-overlapping table/content edge such as row-spanned covered-cell visual replay, formula reference normalization, or table-cell annotation provenance.
