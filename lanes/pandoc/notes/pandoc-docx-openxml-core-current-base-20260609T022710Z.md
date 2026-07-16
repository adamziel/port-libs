# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260609T022710Z`
Base: `ad0b29726a9f952ccc81c677e4a1cb6fc0f76215`
Date: 2026-06-09 UTC

## Behavior

DOCX WordprocessingML body import now preserves inline directional wrappers:

- `w:dir w:val="rtl|ltr"` becomes a visible AST span with `docx-direction`, `docx-dir`, direction-specific class, `data-docx-direction-kind="embedding"`, `data-docx-direction`, and HTML `dir`.
- `w:bdo w:val="rtl|ltr"` becomes a visible AST span with `docx-direction`, `docx-bidi-override`, `docx-bdo`, direction-specific class, `data-docx-direction-kind="override"`, `data-docx-bidi-override="true"`, `data-docx-direction`, and HTML `dir`.
- Unsupported direction values preserve visible child text without leaking invalid metadata.

This fills a DOCX/OpenXML body gap without invoking Pandoc, Word, LibreOffice, zip/unzip, external template engines, TeX/PDF engines, Haskell test binaries, or online services.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `2148` -> `2149`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2573` -> `2575` for `w:dir` and `w:bdo`.
- Blocker: none for this bounded DOCX wrapper behavior.
- Next gate: keep broader DOCX document-part parsing and remaining unmapped WordprocessingML body wrappers separate.

## Evidence

- `php -l lanes/pandoc/src/DocxReader.php`
  - `No syntax errors detected in lanes/pandoc/src/DocxReader.php`
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/DocxReaderTest.php`
- `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
  - `No syntax errors detected in lanes/pandoc/examples/wordpress-docx-body-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 3590 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - `docx body handoff self-test ok`

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP DOCX/OPC package parsing, inline container traversal, AST span attributes, Markdown writing, and WordPress block writing. The next separate DOCX gates remain broader document-part expansion and any still-unmapped WordprocessingML body wrappers.
