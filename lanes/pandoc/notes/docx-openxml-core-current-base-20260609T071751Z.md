# DOCX/OpenXML XE index-entry field metadata

Slice: `pandoc-docx-openxml-core-current-base-20260609T071751Z`
Base: `606e24ec818a38feb2a796c2f2b7d182ce531afd`

## Behavior

- `DocxReader` now recognizes Word `XE` field instructions and emits empty
  Pandoc-style `indexref` spans instead of dropping the hidden marker.
- Complex fields with no displayed result and empty `w:fldSimple` elements
  still stay invisible, but recognized `XE` markers retain entry metadata.
- Preserved metadata includes the normalized DOCX field instruction, index
  entry text, cross-reference text from `\t`, yomi text from `\y`, bold and
  italic page-number flags from `\b` and `\i`, plus bounded DOCX extras for
  `\f` entry type and `\r` bookmark.

## Source Truth

- Upstream Pandoc commit `0640c4c9859aa5a3ede082c190fcd5883c24ac83` maps
  `XE` field instructions to `IndexrefField`.
- Upstream `Text.Pandoc.Readers.Docx` renders `IndexrefField` as an empty Span
  with class `indexref` and entry/crossref/yomi/bold/italic attributes.
- The local upstream cache path named by the manifest was not present in this
  isolated worktree; source was read from the pinned upstream GitHub raw files
  only. No upstream runner or converter was executed.

## Evidence

- Red-first focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxGeneratedFieldMetadataTest.php`
  failed with `1 test files, 57 assertions, 1 failures` before the parser fix
  because hidden `XE` fields were dropped into surrounding text.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxGeneratedFieldMetadataTest.php`
  passed with `1 test files, 90 assertions, 0 failures`.
- Existing DOCX reader regression check:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 4349 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-docx-generated-field-handoff.php --self-test`
  passed with `wordpress-docx-generated-field-handoff self-test passed`.

## Delta

- Added 1 focused PHP PASS case.
- Added 35 focused assertions to `DocxGeneratedFieldMetadataTest.php`.
- `lanes/pandoc/lane-status.json` moved `phpPass` `2482 -> 2483`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` moved mapped coverage
  `2861 -> 2862`, `mappedDocxOpenXmlCoreCases` `33 -> 34`, and
  `docxOpenXmlCoreAssertions` `385 -> 420`.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`,
DOM-based `DocxReader` field parsing, the existing field-instruction tokenizer,
`MarkdownWriter`, `WordPressBlockWriter`, and the focused lane TestRunner. Full
upstream Pandoc runner parity remains a separate upstream-runner dependency
task requiring hydrated Pandoc sources and Haskell test executables.

## Non-Overlap

This does not repeat accepted DOCX field-code hyperlink handling,
PAGE/DATE/REF/PAGEREF/NOTEREF/SEQ/data-field/form-field metadata, generated
TOC/INDEX/BIBLIOGRAPHY/CITATION displayed-result metadata, tracked deletion
reporting, section/header/footer import, numbering/style/table/drawing/media
handoffs, or OPC relationship preflight. It only adds bounded hidden `XE`
index-entry field provenance.
