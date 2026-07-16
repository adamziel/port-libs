# DOCX OpenXML Legacy Form-Field Slice

- Micro-slice: `pandoc-docx-openxml-core-current-base-20260608T170922Z`
- Accepted base: `4b4ed6566d9aa97b39e2a564de2e67000bb01006`
- Scope: native WordprocessingML complex-field `w:ffData` metadata handoff for visible DOCX form-field results.

## Behavior

`DocxReader` now preserves legacy DOCX form-field metadata carried on complex field begin runs:

- `FORMTEXT` results become visible `docx-field-formtext` / `docx-form-field-text` spans with name, enabled, calc-on-exit, macro, help/status text, default text, format, type, and max-length metadata.
- `FORMCHECKBOX` results become visible `docx-field-formcheckbox` / `docx-form-field-checkbox` spans with name, size, default, checked, and size-auto metadata when present.
- `FORMDROPDOWN` results become visible `docx-field-formdropdown` / `docx-form-field-dropdown` spans with name, enabled state, default/result indexes, entry count, and list-entry labels.

The displayed field result remains the rendered content. The metadata is inert `data-docx-form-field-*` reviewer/import state for Markdown and WordPress handoff.

## Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` -> `1 test files, 2704 assertions, 1 failures` because legacy form fields collapsed to plain paragraph text.
- Final: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` -> `1 test files, 2761 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-legacy-form-field-handoff.php --self-test` -> `wordpress-docx-legacy-form-field-handoff self-test passed`.
- Syntax: `php -l lanes/pandoc/src/DocxReader.php`, `php -l lanes/pandoc/tests/DocxReaderTest.php`, and `php -l lanes/pandoc/examples/wordpress-docx-legacy-form-field-handoff.php` all reported no syntax errors.
- JSON validation: `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` decode successfully.
- Whitespace: `git diff --check -- lanes/pandoc` passed.

Status delta: one new named focused DOCX TestRunner PASS case, +57 focused assertions in `DocxReaderTest.php`, `phpPass` updated to `1696`, and mapped native inventory updated to `2116`.

## Dependency Closure

No new support component is needed. This reuses the native DOCX package reader, WordprocessingML DOM parsing, existing complex-field result collection, `AstNode` span metadata, `MarkdownWriter` attribute emission, `WordPressBlockWriter` safe `data-*` pass-through, and in-memory `ZipPackage` fixtures.

No Pandoc, Word, LibreOffice, zip/unzip, Cabal solver/build/test command, Haskell runner, external office tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat accepted DOCX tracked formatting-change metadata, run language/RTL metadata, paragraph border metadata, structured document tag form-control metadata, embedded object/package relationship placeholders, deleted OMML revision audit, field-code hyperlink/cross-reference/sequence provenance, or ODF/OpenDocument form-field slices. It is limited to legacy WordprocessingML `FORMTEXT`, `FORMCHECKBOX`, and `FORMDROPDOWN` complex fields with `w:ffData`.

## Follow-Up

Next DOCX/OpenXML work should choose a non-overlapping field/body/package gap such as additional field-code metadata, numbering/style edge cases, content-control data binding, or media relationship provenance, still without external office tools or Pandoc execution.
