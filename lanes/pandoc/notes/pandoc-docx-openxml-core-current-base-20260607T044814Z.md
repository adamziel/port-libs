# pandoc-docx-openxml-core-current-base-20260607T044814Z

## Behavior

Mapped one bounded DOCX/OpenXML support case on accepted base
`1608f08ebac7656df8e591e9e9564302b71fb161`: paragraph border metadata from
`w:pPr/w:pBdr` is now preserved by `DocxReader` as reviewer-visible span
classes and `data-docx-border-*` attributes before Markdown and WordPress
handoff.

The slice covers `top`, `left`, `bottom`, `right`, `between`, and `bar` edges,
including `val`, `sz`, `space`, `color`, `themeColor`, `themeTint`,
`themeShade`, `frame`, and `shadow` metadata. Disabled `nil`/`none`/falsey
borders are intentionally ignored so absent visual borders do not create
reviewer metadata.

## Evidence

- Baseline focused test before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 1944 assertions, 0 failures`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 1972 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  passed with `docx body handoff self-test ok`.
- Syntax checks passed for `DocxReader.php`, `DocxReaderTest.php`, and
  `wordpress-docx-body-handoff.php`.
- JSON metadata validation passed for `lane-status.json` and
  `UPSTREAM_TEST_MANIFEST.json`.

## Non-Overlap

This does not repeat recent DOCX/OpenXML slices for tracked formatting changes,
deleted OMML, deleted field instructions, block revisions, run language/RTL,
paragraph bidi/text direction, paragraph spacing/indent/tabs, section geometry,
document settings, glossary parts, or embedded object/package relationships.
It is limited to paragraph border metadata preservation.

## Dependency Closure

No new support component is needed. The implementation reuses native PHP
`DocxReader` WordprocessingML parsing, in-memory `ZipPackage` fixtures,
`AstNode` spans, `MarkdownWriter`, `WordPressBlockWriter`, and the focused lane
PHP harness.

No Pandoc, Word, LibreOffice, zip/unzip, Cabal, Haskell runner, external office
tool, online service, live provider test, or live-service provider test was
executed.

## Follow-Up

Next DOCX/OpenXML work should stay bounded to non-overlapping body/style/package
gaps such as table cell vertical alignment, paragraph frame/drop-cap metadata,
style inheritance merge details, or richer numbering-level review metadata.
