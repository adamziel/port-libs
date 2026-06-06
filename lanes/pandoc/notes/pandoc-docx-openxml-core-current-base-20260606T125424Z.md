# DOCX OpenXML Deleted Math Revision Handoff

Micro-slice: `pandoc-docx-openxml-core-current-base-20260606T125424Z`

Base accepted HEAD: `a0ac4f921e09c2e2ea2cae8d976f06dea26b2753`

## Behavior

`DocxReader` now includes OMML `m:t` text when building tracked-change audit text for suppressed `w:del` revisions. Deleted formulas remain excluded from the AST, Markdown output, and WordPress blocks; only the import-report revision item receives the formula text for reviewer audit.

The focused fixture covers a deleted OMML formula `x + y = z` followed by an accepted insertion. The reader reports deletion id `33` with author/date/text metadata and renders only the accepted insertion.

## Source Truth And Non-Overlap

Source truth is the accepted native DOCX/OpenXML support contract in this lane and the existing WordprocessingML/OMML traversal semantics in `DocxReader`. The local upstream Pandoc cache is absent for this worker, so no upstream Haskell runner comparison was attempted.

This slice does not overlap the accepted deleted field-instruction audit, tracked paragraph/run formatting revisions, move ranges, body OMML rendering, comments/endnotes, bookmarks, media, settings, altChunk, ODF, PDF, EPUB, citation, or archive support rows.

## Verification

Red-first:

```sh
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
```

Result: `1 test files, 1818 assertions, 1 failures`; the new deleted math revision case expected `x + y = z` but the actual audit text was empty.

After implementation:

```sh
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test
```

Results: `1 test files, 1822 assertions, 0 failures`; example printed `docx body handoff self-test ok`.

Syntax:

```sh
php -l lanes/pandoc/src/DocxReader.php
php -l lanes/pandoc/tests/DocxReaderTest.php
php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php
```

Results: all reported no syntax errors.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The patch reuses the existing native DOCX/OPC XML stack: `ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `DocxReader`, `AstNode`, `MarkdownWriter`, and `WordPressBlockWriter`.

Remaining out-of-scope work: hydrated upstream Haskell runner comparison, full Word layout and track-changes parity, richer deleted/moved OMML structure provenance, equation numbering, math run styling, external office converter parity, and export-side DOCX writing.
