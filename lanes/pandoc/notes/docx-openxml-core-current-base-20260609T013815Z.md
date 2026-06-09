# DOCX/OpenXML Paragraph Run Style Handoff - 2026-06-09

Micro-slice: `pandoc-docx-openxml-core-current-base-20260609T013815Z`

Accepted base: `72bdfd8308ce4b57fa512b92a3a80b6f1309110e`

## Scope

Implemented a bounded DOCX/OpenXML style cascade behavior in native PHP:
paragraph style `<w:rPr>` run properties are now applied to paragraph runs
before character style and direct run properties. This preserves inherited
emphasis, highlight, language, color, and shading metadata in the AST,
Markdown writer output, and WordPress block output while still allowing direct
run properties such as `w:highlight w:val="none"`, `w:i w:val="0"`, and
`w:b w:val="0"` to suppress inherited styling.

## Source Truth

The behavior follows WordprocessingML style precedence for the bounded native
reader path: paragraph style run properties provide the base run formatting,
character style properties override that base, and direct run properties
override both. The existing accepted character-style run-property test remains
unchanged and this slice adds the missing paragraph-style side of the cascade.

## Non-Overlap

This slice does not touch accepted DOCX media import reporting, DrawingML/VML
image metadata, chart/diagram placeholders, textbox content, caption SEQ
fields, comments/notes, tracked changes, SDT/custom XML wrappers, glossary,
OLE/package, subdocument, table geometry, section geometry, OMML math, or
upstream-runner dependency audit behavior.

## Evidence

- `php -l lanes/pandoc/src/DocxReader.php`: no syntax errors.
- `php -l lanes/pandoc/tests/DocxReaderTest.php`: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`: 1 test files, 3518 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`: `docx body handoff self-test ok`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc`: passed.

## Status Delta

- `phpPass`: 2066 -> 2067.
- `benchmarkDenominator.mapped`: 2478 -> 2479.
- `docxOpenXmlCoreCases`: 33 -> 34.
- `mappedDocxOpenXmlCoreCases`: 33 -> 34.
- `docxOpenXmlCoreAssertions`: 385 -> 433.
- New focused DOCX assertion count: 48.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
DOCX/OpenXML ZIP/OPC package reading, style loading, run-property extraction,
Markdown writing, and WordPress block writing. No Pandoc, Cabal/Haskell
runner, Word, LibreOffice, zip/unzip, external converter, browser renderer,
online service, live provider test, or live-service provider test was run.

## Next

Choose a non-overlapping DOCX/OpenXML gap such as latent style defaults,
table/numbering style interactions, chart/diagram metadata, or richer field
code interpretation. Keep upstream-runner dependency closure separate from
DOCX implementation slices.
