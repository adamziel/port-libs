# DOCX/OpenXML generated field provenance

Slice: `pandoc-docx-openxml-core-current-base-20260609T062540Z`
Base: `fc8eeee0d58103faabecc24a17572b78d812884d`

## Behavior

- `DocxReader` now recognizes generated Word field instructions for `TOC`,
  `INDEX`, `BIBLIOGRAPHY`, and `CITATION`.
- Displayed field results remain visible content, but are wrapped in
  `docx-generated-field` spans with the normalized field instruction and
  bounded switch metadata for reviewer/import queues.
- The TOC path preserves outline/style levels, hyperlink, outline-level,
  hide-web-layout, and omitted-page-number switch metadata. INDEX preserves
  bookmark, column, separator, and format metadata. Bibliography and citation
  fields preserve locale metadata; citation fields also preserve the source key
  and bounded volume switch.

## Evidence

- Focused new test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxGeneratedFieldMetadataTest.php`
  passed with `1 test files, 55 assertions, 0 failures`.
- Existing DOCX reader regression check:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 4319 assertions, 0 failures`.
- New WordPress smoke:
  `php lanes/pandoc/examples/wordpress-docx-generated-field-handoff.php --self-test`
  passed with `wordpress-docx-generated-field-handoff self-test passed`.
- Syntax checks passed:
  `php -l lanes/pandoc/src/DocxReader.php`,
  `php -l lanes/pandoc/tests/DocxGeneratedFieldMetadataTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-docx-generated-field-handoff.php`.

## Delta

- Added 2 focused PHP PASS cases.
- Added 55 focused assertions.
- `lanes/pandoc/lane-status.json` moved `phpPass` `2443 -> 2445`.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`,
DOM-based `DocxReader` field parsing, `MarkdownWriter`, `WordPressBlockWriter`,
and the focused lane TestRunner. Full upstream Pandoc runner parity remains a
separate upstream-runner dependency task requiring hydrated Pandoc sources and
Haskell test executables.

## Non-Overlap

This does not repeat accepted DOCX field-code hyperlink handling, PAGE/DATE/
REF/PAGEREF/NOTEREF/SEQ/data-field/form-field metadata, tracked deletion
reporting, section/header/footer import, numbering/style/table/drawing/media
handoffs, or OPC relationship preflight. It only adds bounded generated-field
provenance for Word-created TOC/index/bibliography/citation result text.
