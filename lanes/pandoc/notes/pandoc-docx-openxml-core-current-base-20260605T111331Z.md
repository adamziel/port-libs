# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260605T111331Z`
Base accepted HEAD: `614193f8d761b9f7ba01ed479006912fb35fcd87`

## Behavior Added

- `DocxReader` now preserves unresolved `w:footnoteReference` and `w:endnoteReference` ids as empty `note` placeholders with `id`, `sourceType`, and `missing` metadata instead of dropping them into surrounding text.
- The DOCX import report now includes a `notes` section with total, footnote, endnote, comment, and missing-note counts plus per-note metadata for source type, id, block count, text, and comment reviewer fields where present.
- The WordPress DOCX body handoff example now includes unresolved footnote/endnote references and self-tests both the import-report inventory and the rendered empty note placeholders.

## Source Truth

- Upstream Pandoc's DOCX reader preserves missing footnote/endnote references as empty notes; the pinned primary source used for this slice is `src/Text/Pandoc/Readers/Docx/Parse.hs` at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`: https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Readers/Docx/Parse.hs
- The local upstream checkout path was not present in this isolated worktree, so this patch used the pinned upstream primary source plus existing fixture-backed native PHP DOCX tests rather than running upstream Haskell tests.
- No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF engine, browser renderer, external writer, online sanitizer, or online service was executed.

## Verification

- Baseline before change: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` -> `1 test files, 1065 assertions, 0 failures`.
- Red-first after adding missing-note expectations: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` -> `1 test files, 1067 assertions, 1 failures`; failure showed unresolved references were dropped from note output and coalesced into paragraph text.
- Focused after implementation: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` -> `1 test files, 1098 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test` -> `docx body handoff self-test ok`.
- Syntax checks passed for `lanes/pandoc/src/DocxReader.php`, `lanes/pandoc/tests/DocxReaderTest.php`, and `lanes/pandoc/examples/wordpress-docx-body-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `856` -> `857`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1314` -> `1315`.
- `docxOpenXmlCoreCases`: `31` -> `32`.
- `mappedDocxOpenXmlCoreCases`: `31` -> `32`.
- `docxOpenXmlCoreAssertions`: `313` -> `346`.
- Added `mappedDocxMissingNoteReferenceCases: 1` and `docxMissingNoteReferenceAssertions: 33`.

## Non-Overlap

This patch does not repeat accepted DOCX/OpenXML work for styles, numbering, relationships, media bytes, comments, comment ranges, bookmarks, field-code hyperlinks, tracked-change revisions, content controls, smart tags, custom XML, OMML formulas, VML, charts, diagrams, embedded object placeholders, section geometry, headers/footers, document settings, language/RTL metadata, or altChunk placeholders. It owns only unresolved footnote/endnote reference fallback and the directly coupled note import-report inventory.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `AstNode`, `DocxReader`, ZIP/OPC package primitives, `MarkdownWriter`, and `WordPressBlockWriter` paths already present in the lane.

## Follow-Up

Keep footnote/endnote custom mark rendering, note numbering style/restart metadata, separator handling, glossary document parts, and full upstream Pandoc Haskell runner parity as separate bounded slices.
