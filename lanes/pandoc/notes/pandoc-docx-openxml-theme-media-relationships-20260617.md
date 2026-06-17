# DOCX OpenXML Theme Media Relationships

Date: 2026-06-17
Bead: plib-7tx3l
Slice: pandoc-docx-openxml-theme-media-relationships
Base: a5bd166c23

## Scope

- Adds metadata-only DOCX/OpenXML package ingestion provenance for image relationships declared by the active theme part sidecar, for example `word/theme/_rels/theme1.xml.rels`.
- Reconciles `a:blip` `r:embed` and `r:link` references in the theme XML with theme relationship declarations.
- Reports referenced and unreferenced theme image relationships, existing/missing/internal/external/unsafe targets, content-type diagnostics, target query/fragment suffixes, byte length, CRC32, SHA-256, and relationship inventory summaries.
- Tags existing internal theme image targets with the `theme-media` package inventory role.
- Keeps theme media bytes metadata-only and does not expose them through document media.

## Accounting

- `phpPass`: 17066 -> 17067
- `phpFail`: 0
- Upstream mapped cases: 16652 -> 16653
- Root mapped inventory: 16621 -> 16622
- Benchmark denominator mapped cases: 3790 -> 3791
- Adds `mappedDocxOpenXmlThemeMediaRelationshipCases = 1`
- Adds `docxOpenXmlThemeMediaRelationshipAssertions = 87`

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- Focused: `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 file / 5819 assertions / 0 failures
- Full: `php tools/run-tests.php lanes/pandoc/tests`
  - 258 files / 177587 assertions / 0 failures

No Pandoc, cmark/commonmark runners, Cabal/Haskell runners, Word, LibreOffice, office suites, zip/unzip, TeX/PDF engines, browser renderers, Node tooling, external validators, online services, live provider tests, or live-service provider tests are invoked.
