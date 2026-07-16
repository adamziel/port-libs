# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260605T121707Z`
Base accepted HEAD: `db0a228eb07b0f12263314fd229427abcb5374d6`

## Behavior Added

- `DocxReader` now derives automatic `w:footnoteReference` and `w:endnoteReference` numbering from section-level `w:footnotePr` / `w:endnotePr` policy.
- Resolved and missing custom-mark note references keep `customMarkFollows: true`, skip counter advancement, and expose `referenceLabel: null` for importer audit.
- Automatic footnote/endnote AST note nodes and import-report items now expose `referenceNumber`, `referenceLabel`, `referenceFormat`, `referenceStart`, and `referenceRestart`.
- The WordPress DOCX body handoff example now self-tests automatic section-numbered note labels alongside custom note markers and missing-note diagnostics.

## Source Truth

- The bounded OpenXML contract for this slice is the accepted local WordprocessingML fixture shape: `w:sectPr/w:footnotePr`, `w:sectPr/w:endnotePr`, `w:numFmt`, `w:numStart`, `w:numRestart`, and automatic/custom `w:footnoteReference` / `w:endnoteReference` body references.
- The previous DOCX note-policy slice parsed section policy and custom marker metadata but left automatic body-reference labels unmaterialized; this slice owns only that direct follow-up.
- No hydrated local Pandoc upstream checkout was available in `/home/claude/port-libs/.upstream-cache`, so no upstream Haskell runner or external office conversion was executed.

## Verification

- Baseline focused DOCX test: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` -> `1 test files, 1141 assertions, 0 failures`.
- Red-first check: the new DOCX note label expectations failed before implementation with `Expected: 3` / `Actual: NULL` for `referenceNumber`.
- Focused DOCX test after implementation: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` -> `1 test files, 1176 assertions, 0 failures`.
- Broader lane-scoped sanity test: `php tools/run-tests.php lanes/pandoc/tests` -> `21 test files, 11368 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test` -> `docx body handoff self-test ok`.
- Syntax checks:
  - `php -l lanes/pandoc/src/DocxReader.php` -> no syntax errors.
  - `php -l lanes/pandoc/tests/DocxReaderTest.php` -> no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php` -> no syntax errors.
- JSON validation: `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'` -> `json ok`.
- Whitespace check: `git diff --check -- lanes/pandoc` -> passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: unchanged at `890`; this strengthens an existing focused DOCX test rather than adding a new PASS case.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1347` -> `1348`.
- `docxOpenXmlCoreCases`: `31` -> `32`.
- `mappedDocxOpenXmlCoreCases`: `31` -> `32`.
- `docxOpenXmlCoreAssertions`: `313` -> `348`.
- Added `mappedDocxNoteReferenceLabelCases: 1` and `docxNoteReferenceLabelAssertions: 35`.

## Non-Overlap

This patch does not repeat accepted DOCX/OpenXML work for ZIP/OPC package relationships, styles, numbering lists, media bytes, drawings, VML, charts, diagrams, embedded OLE/package placeholders, comments, comment ranges, bookmarks, field-code hyperlinks, tracked revisions, content controls, smart tags, custom XML, OMML formulas, altChunk import, document settings, section page geometry, header/footer relationships, missing-note placeholders, custom note markers, or section note-policy parsing. It owns only automatic footnote/endnote body-reference numbering metadata derived from the already parsed section policy.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP ZIP/OPC package reader, XML DOM helpers, `DocxReader`, `MarkdownWriter`, and `WordPressBlockWriter`.

## Follow-Up

Keep footnote/endnote separator and continuation-separator materialization, note style inheritance, per-section restart counters across multiple sections, glossary document parts, commentsExt metadata, and full upstream Pandoc Haskell runner parity as separate bounded slices.
