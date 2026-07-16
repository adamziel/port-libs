# Pandoc ODF/OpenDocument Core Slice - 2026-06-05

Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T223506Z`

Accepted base: `ddb326e0de676cb18d5010ac541b64ef59fcf1be`

## Behavior

- Added native PHP handling for matched `text:reference-mark-start` / `text:reference-mark-end` ranges in `OdfReader`.
- The marked inline content is now wrapped in a review span with `id`, `odf-reference-mark`, `odf-reference-mark-range`, `data-odf-reference-name`, and `data-odf-reference-range="true"`.
- Nested inline content inside the range, including ODT links, is preserved inside the marked span.
- Standalone point `text:reference-mark` entries still render as empty anchors for compatibility with the existing handoff.

## Evidence

- Static focused assertion-call inventory for `lanes/pandoc/tests/OdfReaderTest.php` increased from `1009` to `1030`.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: `1 test files, 1030 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - Result: `odf open document handoff self-test ok`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP `OdfReader` inline walker plus existing `MarkdownWriter`, `WordPressBlockWriter`, `ZipPackage`, and in-process ODT fixture builders.

No Pandoc, Cabal solver/build/test command, Haskell runner, stack, Word, LibreOffice, zip/unzip, ZipArchive, external converter, browser renderer, online sanitizer, online service, or live provider test was executed.

## Non-Overlap

This is not the accepted ODF `text:tab` normalization slice or paragraph blockquote style slice, and it does not touch DOCX, EPUB, ZIP, OPC, citation, math, or upstream-runner dependency audit surfaces. It stays within ODF/OpenDocument `content.xml` inline reference-mark handling.

## Follow-Up

Keep unresolved reference targets, bibliography source rendering, tracked table/section metadata, and less common ODF field families as separate bounded ODF slices unless a concrete ODT import fixture requires them earlier.
