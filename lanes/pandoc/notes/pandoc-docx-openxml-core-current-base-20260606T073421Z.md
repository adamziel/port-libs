# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260606T073421Z`
Base accepted HEAD: `b03dbfb6f34d3383aa6d1c0bb24447ed232247bd`

## Behavior Added

- `DocxReader` now preserves DOCX/OpenXML `w:rPr/w:color` foreground color metadata as a bounded reviewer span handoff instead of coalescing colored text into adjacent plain runs.
- Direct run color metadata is exported as safe classes and data attributes: `docx-color`, `docx-color-*`, `docx-theme-color`, `docx-theme-color-*`, `data-docx-color`, `data-docx-theme-color`, `data-docx-theme-tint`, and `data-docx-theme-shade`.
- Run metadata family overrides now treat direct `w:color` as its own family, so direct foreground color can override inherited style color without carrying stale color attributes.
- The WordPress DOCX body handoff example now includes a reviewer color label and self-tests the generated HTML span.

## Source Truth

- The bounded format contract is WordprocessingML run properties: `w:rPr/w:color` with `w:val`, `w:themeColor`, `w:themeTint`, and `w:themeShade` attributes.
- This slice builds on existing native DOCX/OpenXML support in the lane for highlight, shading, language, RTL, run style, and reviewer metadata spans.
- No local Pandoc upstream checkout was available in `/home/claude/port-libs/.upstream-cache/pandoc`, so this patch used the OpenXML element contract plus existing lane fixture coverage rather than running upstream Haskell tests.
- No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF engine, browser renderer, external writer, online sanitizer, online service, or live provider test was executed.

## Verification

- Baseline before change: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` -> `1 test files, 1687 assertions, 0 failures`.
- Baseline example before change: `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test` -> `docx body handoff self-test ok`.
- Red-first after adding the colored-run fixture and assertions: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` -> `1 test files, 1659 assertions, 1 failures`; failure showed the colored run was not represented as its own reviewer span.
- Focused after implementation: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` -> `1 test files, 1694 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test` -> `docx body handoff self-test ok`.
- Syntax checks passed for `lanes/pandoc/src/DocxReader.php`, `lanes/pandoc/tests/DocxReaderTest.php`, and `lanes/pandoc/examples/wordpress-docx-body-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: unchanged at `1243`; this extends an existing focused DOCX test case instead of adding a new TestRunner PASS case.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1686` -> `1687`.
- `docxOpenXmlCoreCases`: `32` -> `33`.
- `mappedDocxOpenXmlCoreCases`: `32` -> `33`.
- `docxOpenXmlCoreAssertions`: `357` -> `364`.
- Added `mappedDocxRunColorCases: 1` and `docxRunColorAssertions: 7`.

## Non-Overlap

This patch does not repeat accepted DOCX/OpenXML work for package parsing, relationships, styles, numbering, media, comments, missing notes, note-body markers, OMML, tracked changes, bookmarks, field-code hyperlinks, proof/permission ranges, content controls, glossary parts, smart tags, custom XML, textboxes, markup compatibility, symbols, ruby, breaks, language/RTL, paragraph layout, section geometry, headers/footers, note policies, altChunk, embedded objects, document settings, document variables, highlight, or shading. It owns only bounded direct run foreground color and theme-color metadata handoff.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `DocxReader`, `AstNode`, `MarkdownWriter`, and `WordPressBlockWriter` paths already present in the lane.

## Follow-Up

Keep theme color RGB resolution, CSS color rendering policy, style-theme inheritance beyond metadata preservation, and full upstream Pandoc Haskell runner parity as separate bounded slices.
