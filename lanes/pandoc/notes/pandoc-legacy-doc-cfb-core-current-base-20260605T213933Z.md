# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T213933Z`

Base accepted HEAD: `657d8d9880c9b7e72e8e4cabf7a3db63b8a0a3fd`

Date: 2026-06-05 UTC

## Behavior

`LegacyDocReader` now preserves bounded legacy Word form field-code results for
WordPress review:

- `FORMTEXT` results become visible `legacy-doc-form-field` spans with text
  form-field metadata.
- `FORMCHECKBOX` results become visible form-field spans with checkbox type and
  checked-state metadata derived from the displayed result.
- `FORMDROPDOWN` results become visible form-field spans with dropdown
  form-field metadata.

The hidden field instruction remains review metadata under
`data-legacy-doc-field-instruction`; it is not rendered as visible text. The
existing page/date/count field provenance and hyperlink field behavior are
unchanged.

The WordPress legacy DOC handoff smoke now includes a `FORMTEXT` field in the
same native piece-table packet as hyperlinks, bookmarks, notes, comments,
sections, styles, lists, OLE metadata, macros, and CFB directory provenance.

## Source Truth

- Microsoft MS-DOC `flt` field-code table identifies `FORMTEXT`,
  `FORMCHECKBOX`, and `FORMDROPDOWN` as Word field types:
  https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/28a8d2c2-6107-409d-8f6a-e345ab6d4179
- Microsoft Word FormField object documentation describes text, checkbox, and
  drop-down form fields as distinct form-field object types:
  https://learn.microsoft.com/en-us/office/vba/api/word.formfield

This ports only the bounded native field-result handoff contract. It does not
implement Word form-field editing, FFData table-property expansion, Word or
LibreOffice automation, encrypted DOC decryption, or full upstream Pandoc
runner parity.

## Evidence

- Focused test: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 590 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - Result: `legacy doc handoff self-test ok`
- Root harness: not run - isolated micro-slice.

Status delta:

- `lane-status.json` `phpPass`: `1081 -> 1082`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1533 -> 1534`.
- Focused `LegacyDocReaderTest.php`: added `1` PASS case and `25` assertions
  for legacy DOC form-field provenance.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`LegacyDocReader`, `CompoundFileBinary`, Pandoc-like AST, `MarkdownWriter`, and
`WordPressBlockWriter` support rows.

No Pandoc, Cabal solver/build/test command, Haskell runner, stack, Word,
LibreOffice, zip/unzip, external office tool, browser renderer, online
sanitizer, online service, or live provider test was executed.

## Non-Overlap

This slice does not repeat accepted legacy DOC/CFB coverage for CFB header,
directory, FAT/DIFAT, MiniFAT, red-black tree, timestamp, CLSID, or state-bit
preflight; OLE property metadata; encrypted FIB rejection; fExtChar direct text
range extraction; CLX/Pcd text extraction and PCD flag validation; FibRgLw97
subdocument boundaries; standard bookmark tables; footnote/endnote/comment
PLCs; PlcfSed sections; STSH stylesheets; formatting BTE/FKP tables; list
tables; hyperlink fields; page/date/count field provenance; ObjectPool
embedded object inventory; macro-project preflight; DOCX, ODT, EPUB3,
ZIP/OPC, XML/HTML5 DOM, table geometry, or PDF-engine handoffs.

Remaining follow-ups include FFData table-property expansion, richer
cross-reference fields, image extraction policy, encrypted DOC password or
decryption policy, CFB DIFAT overflow chains, and fuller paragraph/table
property application.
