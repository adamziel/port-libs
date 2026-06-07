# pandoc-docx-openxml-core-current-base-20260607T142437Z

Base accepted HEAD: `36d073995c6764bcbe303db1103cdc8f0b195b22`

## Behavior

Mapped one bounded DOCX/OpenXML support case for WordprocessingML table-cell
shading metadata from `w:tcPr/w:shd`.

- `DocxReader` now preserves table-cell shading pattern, RGB fill, source
  color, theme fill, theme fill tint/shade, theme color, and theme tint/shade
  as reviewer-visible `data-docx-cell-shading-*` attributes.
- Concrete six-digit RGB fills are handed to WordPress table output as safe
  `background-color:#RRGGBB` table-cell styling.
- Theme-only shading remains metadata-only so the importer can review the
  source theme state without inventing a resolved RGB color.
- `w:shd w:val="nil"` remains inert and does not create reviewer metadata or
  WordPress styling.
- Table geometry review packets receive the same source classes, data
  attributes, and safe style metadata for importer audits.

## Evidence

Baseline before this slice:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 2068 assertions, 0 failures
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 2094 assertions, 0 failures

php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test
docx body handoff self-test ok
```

Delta:

- `+1` PHP PASS case.
- `+26` focused DOCX assertions.
- `benchmarkDenominator.mapped`: `1932 -> 1933`.
- `mappedDocxOpenXmlCoreCases`: `33 -> 34`.
- `docxOpenXmlCoreAssertions`: `385 -> 411`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted DOCX/OpenXML support for table spans,
table-caption/description metadata, table-cell vertical alignment, paragraph
borders, run shading/highlight/color, tracked changes, content controls,
media, embedded objects, altChunk, section/header/footer metadata, settings,
theme fonts, glossary parts, OMML, bookmarks, comments, or fields. It owns only
`w:tcPr/w:shd` table-cell shading metadata and WordPress-safe table-cell style
handoff.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `DocxReader`
WordprocessingML parsing, `AstNode` table-cell attributes, `TableGeometry`
source-attribute review packets, `MarkdownWriter` pipe-table fallback,
`WordPressBlockWriter` safe table-cell attributes/styles, in-memory
`ZipPackage` fixtures, the focused PHP test harness, and the existing DOCX
body WordPress example.

No Pandoc, Word, LibreOffice, zip/unzip, Cabal/Haskell runner, external office
tool, online service, live provider test, or live-service provider test was
executed.

## Follow-Up

Next DOCX/OpenXML work should stay bounded to non-overlapping WordprocessingML
table/body metadata such as cell width/type hints, table row repeat/header
metadata, or style inheritance merge edges needed by WordPress review output.
