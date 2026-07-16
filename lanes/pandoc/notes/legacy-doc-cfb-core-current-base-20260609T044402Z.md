# Legacy DOC/CFB QUOTE And SHAPE Field Handoff - 2026-06-09

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260609T044402Z`
Base: `10cec8176e93477cb20def666e3e2a65f821e87c`

## Source Truth

- MS-DOC Plcfld `flt` field type codes map `0x23` to `QUOTE` and `0x5f` to `SHAPE`. The SHAPE field is treated as a QUOTE-shaped literal field for this bounded handoff. Source: Microsoft MS-DOC field type table, https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/28a8d2c2-6107-409d-8f6a-e345ab6d4179
- This slice preserves field provenance and displayed results only. It does not execute legacy Word field instructions.

## Implementation

- `LegacyDocReader` now names Plcfld type codes `0x23` and `0x5f` as `quote` and `shape`.
- QUOTE and SHAPE fields render the displayed result inside `legacy-doc-literal-field` spans with metadata-only attributes for the instruction, format switch, literal arguments, result length, and SHAPE-to-QUOTE alias.
- Hidden field instructions remain excluded from rendered Markdown, HTML, and WordPress text.
- The WordPress legacy DOC handoff example now includes QUOTE and SHAPE fields in the synthetic CFB fixture and self-test.

## Verification

- Current-base focused baseline recorded for `LegacyDocReaderTest.php`: `1 test files, 2062 assertions, 0 failures`.
- `php -l lanes/pandoc/src/LegacyDocReader.php` - no syntax errors.
- `php -l lanes/pandoc/tests/LegacyDocReaderTest.php` - no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php` - no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` - `1 test files, 2102 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test` - `legacy doc handoff self-test ok`.
- Root harness: not run - isolated micro-slice.

Focused delta: +1 PHP PASS line, +40 focused assertions, +1 mapped native legacy DOC/CFB support case.

## Dependency Closure

No new support component is needed. This reuses the native PHP LegacyDocReader CFB/Plcfld parser, existing Pandoc-like AST spans, WordPressBlockWriter, and the lane-local WordPress handoff example. Full upstream Pandoc runner parity remains a separate upstream-runner dependency task requiring a hydrated Pandoc checkout and Haskell test executables.

## Non-Overlap

This slice does not repeat CP_WINUNICODE dictionary validation, source-location/include aliases, transaction-signature preflight, encryption preflight, picture/OLE/macro metadata, CFB allocation repair, or previous ODT/YAML metadata work. It owns only bounded QUOTE/SHAPE Plcfld type mapping and literal-result field handoff.

## Follow-Up

Next legacy DOC/CFB work should target a non-overlapping native MS-DOC support gap such as remaining field-code metadata, table/shape property structures, or master-document review metadata. Keep field execution out of scope unless a later slice explicitly narrows and authorizes it.
