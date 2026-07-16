# DOCX/OpenXML DrawingML Textbox Shape Metadata

## Behavior

Mapped one bounded DOCX/OpenXML support case for DrawingML WordprocessingShape text boxes.

- `DocxReader` now preserves inline/anchor DrawingML geometry on text boxes by reusing the existing drawing geometry extractor.
- DrawingML text-box wrappers now expose `wps:cNvSpPr` text-box state, `wps:spPr/a:xfrm` rotation, flip, offset, and extents, plus `a:prstGeom` preset geometry.
- `wps:bodyPr` metadata now survives handoff for anchor, wrap, vertical text, column/inset values, boolean layout flags, and no/norm/shape autofit state.
- Markdown and WordPress output keep the text-box content in paragraph order while carrying reviewer-safe shape and body metadata as classes and `data-docx-*` attributes.

## Evidence

- Rework note check: no `port-pandoc-*.needs-lane-rework.md` note existed for this slice.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 3259 assertions, 0 failures`.
- Red-first: after adding the DrawingML textbox shape/bodyPr expectations before implementation, `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` failed with `1 test files, 3238 assertions, 1 failures` because only the base `docx-textbox` and `docx-drawing-textbox` classes were present.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 3288 assertions, 0 failures`.
- WordPress example smoke: `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test` passed.
- PHP lint passed for changed PHP files.

Focused delta: one mapped DOCX/OpenXML support behavior gains `+29` assertions inside an existing `DocxReaderTest.php` PASS case. Lane `phpPass` is unchanged because no new TestRunner case name was added. `docxOpenXmlCoreCases` and `mappedDocxOpenXmlCoreCases` move `33 -> 34`; `docxOpenXmlCoreAssertions` moves `385 -> 414`.

## Non-Overlap

This does not repeat accepted DOCX/OpenXML slices for DrawingML picture crop/transform, chart/diagram placeholders, DrawingML shape text, VML text boxes, caption SEQ metadata, custom XML bindings, repeating-section content controls, glossary-local relationships, embedded object/package placeholders, subdocuments, section geometry, or header/footer relationship inventories. This slice only exposes non-picture DrawingML textbox shape and bodyPr review metadata.

## Dependency Closure

No new support component is needed. The slice reuses native `DocxReader`, the existing DrawingML geometry metadata path, `MarkdownWriter`, `WordPressBlockWriter`, `ZipPackage` in-memory fixtures, and the DOCX body handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, online service, live provider test, or live-service provider test was executed.

## Follow-Up

Good next DOCX/OpenXML gaps include section-scoped style inheritance, numbering interactions in imported body parts, and any remaining non-picture DrawingML metadata not covered by textbox shape/bodyPr extraction.
