# Legacy DOC/CFB PlcfldEdn Current-Base Slice

## Scope

- Micro-slice: `pandoc-legacy-doc-cfb-core-current-base-20260606T133346Z`
- Accepted base: `5b6122b8531cb9888e3096ea4eb4faa04a0af79a`
- Lane scope: `lanes/pandoc/**`

This slice adds bounded native parsing for the legacy Word endnote field table
(`PlcfldEdn`). `LegacyDocReader` now reads `fcPlcfFldEdn` and
`lcbPlcfFldEdn` from the FibRgFcLcb97 table, parses the table with the existing
Plcfld field-character machinery, and exposes endnote story field characters,
field ranges, and field story inventory as metadata only.

## Source Truth

- Microsoft MS-DOC `FibRgFcLcb97` documents `fcPlcfFldEdn` at offset `0x180`
  and `lcbPlcfFldEdn` at offset `0x184` from the structure, which correspond to
  absolute FIB offsets `0x021a` and `0x021e` in the existing reader layout.
- The same source describes `PlcfldEdn` as field-character data for endnote
  text, matching the existing `PlcfldMom`, `PlcfldHdr`, `PlcfldFtn`, and
  `PlcfldAtn` metadata path.
- Official source used for this slice:
  https://learn.microsoft.com/fr-fr/openspecs/office_file_formats/ms-doc/0c9df81f-98d0-454e-ad84-b612cd05b1a4

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external office tool, online service, or live-service provider test
was executed.

## Behavior

- Adds `PlcfldEdn` descriptor support for endnote story field tables.
- Preserves endnote `NOTEREF` field type/range metadata in `fieldCharacters`,
  `fields`, and `fieldStories`.
- Keeps supplemental endnote field instructions and results out of rendered
  WordPress blocks.
- Adds an `fcMin` guard for supplemental field descriptors so abbreviated test
  FIBs do not treat body bytes as later FibRgFcLcb97 table offsets.
- Extends the WordPress legacy DOC handoff smoke fixture to include endnote
  `PlcfldEdn` metadata.

## Evidence

- `php -l lanes/pandoc/src/LegacyDocReader.php`
  - `No syntax errors detected in lanes/pandoc/src/LegacyDocReader.php`
- `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/LegacyDocReaderTest.php`
- `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`
  - `No syntax errors detected in lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - `1 test files, 854 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - `legacy doc handoff self-test ok`

`git diff --check -- lanes/pandoc` is part of final handoff verification.
Root harness was not run: isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1335 -> 1336`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1749 -> 1750`
- Legacy DOC/CFB mapped cases: `7 -> 8`
- Focused `LegacyDocReaderTest.php` assertion count recorded as `854`

## Dependency Closure

No new support component is needed. This reuses the existing native
`CompoundFileBinary` package reader and `LegacyDocReader` Plcfld machinery.

Remaining follow-up stays bounded and separate: actual legacy DOC picture byte
extraction, OfficeArt/BLIP drawing parsing, encrypted DOC decryption, richer
endnote separator/story variants, hydrated upstream Pandoc runner parity, and
external office converter parity.

## Non-Overlap

This slice does not repeat accepted legacy DOC/CFB work for CFB directory
CLSID/state-bit provenance, MiniFAT cutoff preflight, FibRgLw97 subdocument CP
boundaries, inline picture placeholders, note/comment reference PLCs, or main,
header, footnote, and comment field-table metadata. It adds only the bounded
endnote story `PlcfldEdn` table handoff.
