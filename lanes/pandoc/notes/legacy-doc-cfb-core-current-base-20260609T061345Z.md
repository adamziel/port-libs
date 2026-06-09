# Legacy DOC CFB PAPX Paragraph Layout Metadata

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260609T061345Z`
Accepted base: `ad25c5c67f0859a34d555620436625e00d668451`

## Source Truth

Microsoft MS-DOC defines paragraph property exceptions in `PlcBtePapx` /
`PapxFkp` records. This slice maps direct PAPX grpprl SPRMs into metadata-only
review state:

- `sprmPJc`
- `sprmPFKeep`
- `sprmPFKeepFollow`
- `sprmPFPageBreakBefore`
- `sprmPDxaLeft`
- `sprmPDxaLeft1`
- `sprmPDxaRight`
- `sprmPDyaBefore`
- `sprmPDyaAfter`
- `sprmPDyaLine`

Source reference:
https://learn.microsoft.com/en-ie/openspecs/office_file_formats/ms-doc/484822ee-a9d9-4af4-8423-29fda67a6a58

## Implementation

`LegacyDocReader` now parses selected direct paragraph layout properties from
PAPX grpprls attached to paragraph formatting runs. The extracted
`paragraphProperties` remain review metadata with explicit
`metadata-only-native-review` policy. Markdown and WordPress output keep only
visible paragraph text, so layout metadata does not render into imported
content.

## Evidence

Baseline before this patch:
`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
passed with `1 test files, 2312 assertions, 0 failures`.

Red-first before source changes:
`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
failed with `1 test files, 2318 assertions, 1 failures` because
`paragraphPropertyFormattingRunCount` was absent.

Final focused test:
`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
passed with `1 test files, 2342 assertions, 0 failures`.

Example smoke:
`php lanes/pandoc/examples/wordpress-legacy-doc-paragraph-formatting-handoff.php --self-test`
passed with `legacy doc paragraph-formatting handoff self-test ok`.

Manifest/status delta: `phpPass` `2428 -> 2429`, mapped denominator
`2817 -> 2818`, `legacyDocCfbCoreCases` `7 -> 8`,
`mappedLegacyDocCfbCoreCases` `7 -> 8`, and `legacyDocCfbCoreAssertions`
`64 -> 94`.

## Non-Overlap

This does not repeat the accepted CHPX direct text-property metadata slice,
revision marks, field-code mapping, list table parsing, FIB Unicode extraction,
encryption preflight, picture placeholders, DOCX/OpenXML, ODF/OpenDocument,
PDF, EPUB, YAML, CSL, BibTeX, math, table-geometry, archive, or XML/HTML5 DOM
support.

## Dependency Closure

No new support component is needed. The slice reuses the native
`CompoundFileBinary` parser, `LegacyDocReader` FIB/PlcBte/PapxFkp parsing,
the Pandoc-like AST, `MarkdownWriter`, and `WordPressBlockWriter`.

## Exclusions

No Pandoc, Cabal, Haskell runners, Word, LibreOffice, zip/unzip, external
template engines, external converters, TeX/PDF engines, browser renderers,
online services, live provider tests, or live-service provider tests were run.
Root harness was not run for this isolated micro-slice.

## Next Task

The next non-overlapping legacy DOC/CFB slice should resolve stylesheet-linked
paragraph formatting, list/numbering handoff, or safe visible paragraph layout
application to AST blocks.
