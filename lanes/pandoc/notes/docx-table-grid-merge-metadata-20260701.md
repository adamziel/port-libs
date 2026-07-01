# DOCX table grid and merge metadata

Work item: `plib-riy7u`

## Summary

`DocxOpenXmlReader` now preserves bounded DOCX table grid/merge metadata while
reading `word/document.xml`. Table nodes report row, header-row, omitted-grid,
and vertical-merge counts. Row nodes preserve `gridBefore`, `gridAfter`,
computed grid column counts, and `tblHeader` state. Cell nodes preserve
one-based DOCX grid column positions, grid spans, and `vMerge` restart/continue
state.

Leading `tblHeader` rows are emitted as a `table_head` section, matching the
existing table AST model and allowing Markdown/WordPress writers to render real
header cells. The `docx*` source metadata remains AST-only and is not emitted as
raw OpenXML or rendered block attributes.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`

Focused DOCX validation passed with 1 file, 11445 assertions, and 0 failures.

No Pandoc binary, office suite, external validator, unzip/zip command, browser
engine, TeX engine, Jupyter, or Node tooling was invoked.
