# DOCX OpenXML Current-Base Block Revisions

Slice: `pandoc-docx-openxml-core-current-base-20260607T022557Z`
Base accepted HEAD: `dceb129b94af76d8e90cb1d4f15360a8db272ff2`

## Behavior

Native `DocxReader` now handles block-level WordprocessingML tracked-change wrappers:

- accepted `w:ins` block wrappers are parsed through the existing paragraph/table body parser and emitted as `.docx-insertion` div blocks;
- accepted `w:moveTo` block wrappers are emitted as `.docx-move-to` div blocks;
- suppressed `w:del` and `w:moveFrom` block wrappers remain out of Markdown and WordPress output;
- all four wrapper types still appear in the import-report revision audit with id, author, date, accepted state, type, and extracted text.

This extends the existing inline tracked-change support and does not change the existing full-revision policy beyond accepting visible insertion/move-to content and keeping deletion/move-from content report-only.

## Evidence

Baseline before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
- Result: `1 test files, 1881 assertions, 0 failures`

Focused verification after the patch:

- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
- Result: `1 test files, 1944 assertions, 0 failures`

Example smoke:

- `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
- Result: `docx body handoff self-test ok`

Delta:

- `+1` PHP PASS case
- `+63` focused assertions
- mapped DOCX/OpenXML support cases: `32 -> 33`
- mapped denominator: `1852 -> 1853`

## Non-Overlap

This slice avoids the already accepted DOCX clusters for run language/RTL metadata, embedded object/package relationship placeholders, tracked paragraph/run formatting changes, deleted field instruction audit, deleted OMML math audit, move ranges, bookmarks, field-code hyperlinks, section/header/footer metadata, settings/theme/glossary, media reports, altChunk, custom XML, smart tags, and textboxes. The new behavior is specifically block-level `w:ins` / `w:moveTo` / `w:del` / `w:moveFrom` body wrappers.

## Dependency Closure

No new support component is needed. The implementation reuses native PHP `DocxReader` body traversal, `ZipPackage` in-memory fixtures, `AstNode` div/paragraph/table handoff, `MarkdownWriter` fenced div rendering, `WordPressBlockWriter` raw HTML div/table rendering, and the existing DOCX body WordPress example. No Pandoc, Cabal, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, browser renderer, online service, live provider test, or live-service provider test was executed.

## Follow-Up

Full DOCX revision policy remains intentionally bounded. Follow-up work can address non-overlapping gaps such as full accept/reject mode switches, section-level revision metadata, chart/SmartArt relationship details, or complex content-control binding.
