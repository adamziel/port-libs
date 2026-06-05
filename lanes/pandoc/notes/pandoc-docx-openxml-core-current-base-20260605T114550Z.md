# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260605T114550Z`
Base accepted HEAD: `21ca4e606962df02c069c2fe826037f969abd856`

## Behavior Added

- `DocxReader` now preserves `w:customMarkFollows` from `w:footnoteReference` and `w:endnoteReference` on both resolved note AST nodes and unresolved missing-note placeholders.
- `DocxReader` now maps section-level `w:footnotePr` and `w:endnotePr` policy from `w:sectPr` into `sectionProperties`, including number format, number start, number restart, and note position.
- The DOCX notes import report now exposes `customMarkFollows` per note item, and section import-report entries mirror the parsed note policy metadata.
- The WordPress DOCX body handoff example now self-tests custom note markers and section note numbering policy for resolved and unresolved footnote/endnote references.

## Source Truth

- The bounded OpenXML contract for this slice is the WordprocessingML shape exercised in local fixtures: `w:sectPr/w:footnotePr`, `w:sectPr/w:endnotePr`, child `w:numFmt`, `w:numStart`, `w:numRestart`, `w:pos`, and `w:customMarkFollows` on `w:footnoteReference` / `w:endnoteReference`.
- The prior accepted DOCX missing-note slice explicitly left footnote/endnote custom marks and note numbering style/restart metadata as follow-up work; this slice owns only that follow-up and the directly coupled import-report/example coverage.
- The local hydrated Pandoc upstream checkout was not present in this isolated worktree, so no upstream Haskell runner or external office conversion was executed.

## Verification

- Focused DOCX test: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` -> `1 test files, 1141 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test` -> `docx body handoff self-test ok`.
- Syntax checks:
  - `php -l lanes/pandoc/src/DocxReader.php` -> no syntax errors.
  - `php -l lanes/pandoc/tests/DocxReaderTest.php` -> no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php` -> no syntax errors.
- JSON validation: `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'` -> `json ok`.
- Whitespace check: `git diff --check -- lanes/pandoc` -> passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `876` -> `877`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1334` -> `1335`.
- `docxOpenXmlCoreCases`: `31` -> `32`.
- `mappedDocxOpenXmlCoreCases`: `31` -> `32`.
- `docxOpenXmlCoreAssertions`: `313` -> `358`.
- Added `mappedDocxNoteReferencePolicyCases: 1` and `docxNoteReferencePolicyAssertions: 45`.

## Non-Overlap

This patch does not repeat accepted DOCX/OpenXML work for package relationships, styles, numbering, tables, media bytes, drawings, VML, charts, diagrams, OLE/package placeholders, comments, comment ranges, bookmarks, field-code hyperlinks, tracked revisions, content controls, smart tags, custom XML, OMML formulas, altChunk import, document settings, section geometry, headers/footers, or missing note placeholders. It owns only custom note-reference marker metadata and section footnote/endnote policy metadata.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP ZIP/OPC package reader, XML DOM helpers, `DocxReader`, `MarkdownWriter`, and `WordPressBlockWriter`.

## Follow-Up

Keep footnote/endnote separator and continuation-separator materialization, note style inheritance, rendered numbering restart behavior, glossary document parts, commentsExt metadata, and full upstream Pandoc Haskell runner parity as separate bounded slices.
