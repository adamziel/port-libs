# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T233943Z`

Base accepted HEAD: `b65bbeadd52942a905ef176ab2bea038137f6aca`

Date: 2026-06-05 UTC

## Behavior

`LegacyDocReader` now preserves bounded legacy Word cross-reference field
results for WordPress review:

- `REF` results become visible `legacy-doc-cross-reference` spans with bookmark
  target metadata.
- `PAGEREF` results carry bookmark-page cross-reference metadata and relative
  `\p` switch provenance.
- `NOTEREF` results carry note cross-reference metadata and preserves the
  original target name plus field switches.

Hidden field instructions remain review metadata in
`data-legacy-doc-field-instruction`; only the displayed field result is rendered
as visible Markdown/WordPress text.

The WordPress legacy DOC handoff smoke now includes `REF` and `PAGEREF` fields
in the same native piece-table packet as hyperlinks, bookmarks, note/comment
anchors, sections, styles, lists, OLE metadata, macros, CFB directory
provenance, and form fields.

## Source Truth

- Microsoft MS-DOC `flt` identifies `REF`, `PAGEREF`, and `NOTEREF` as Word
  field types mapped to ECMA-376 field definitions:
  https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/28a8d2c2-6107-409d-8f6a-e345ab6d4179
- Microsoft MS-DOC `Plcfld` documents field character ranges with begin,
  separator, and end control characters:
  https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/751b09bb-72f0-45ef-8e87-666dea68219f

This ports only the bounded native field-result handoff contract. It does not
resolve Word cross-reference targets, update page numbers, open external
documents, execute Word automation, decrypt DOC files, or run Pandoc.

## Evidence

- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 590 assertions, 0 failures`
- Focused test after implementation: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 623 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - Result: `legacy doc handoff self-test ok`
- Root harness: not run - isolated micro-slice.

Status delta:

- `lane-status.json` `phpPass`: `1112 -> 1113`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1564 -> 1565`.
- Focused `LegacyDocReaderTest.php`: added `1` PASS case and `33` assertions
  for legacy DOC cross-reference field provenance.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`LegacyDocReader`, `CompoundFileBinary`, `MarkdownWriter`, `WordPressBlockWriter`,
and the existing WordPress legacy DOC handoff smoke.

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
tables; hyperlink fields; page/date/count field provenance; form fields;
ObjectPool embedded object inventory; macro-project preflight; DOCX, ODT,
EPUB3, ZIP/OPC, XML/HTML5 DOM, table geometry, or PDF-engine handoffs.

Remaining follow-ups include FFData table-property expansion, image extraction
policy, CFB DIFAT overflow-chain fixtures, richer paragraph/table property
application, encrypted DOC password or decryption policy, and full upstream
Pandoc Haskell runner parity.
