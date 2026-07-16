# Legacy DOC/CFB Plcfld Slice

Micro-slice: `pandoc-legacy-doc-cfb-core-current-base-20260606T045650Z`
Base: `b31c2c96194cda376adb4409356e49f96c468cf4`

## Source Truth

- Microsoft MS-DOC `FibRgFcLcb97` defines `fcPlcfFldMom` and `lcbPlcfFldMom` as the Table Stream offset and byte size of the main-document `Plcfld`.
- Microsoft MS-DOC `Plcfld` defines a PLC of two-byte `Fld` records. CPs are sorted, duplicate-free, and point to field begin/separator/end characters in the document part.
- Microsoft MS-DOC `Fld` defines `0x13`, `0x14`, and `0x15` as begin, separator, and end records. For begin records, `grffld` is the `flt` field type code; this slice maps common visible handoff fields such as `HYPERLINK`, `REF`, `PAGEREF`, `PAGE`, `FORMTEXT`, `FORMCHECKBOX`, `FORMDROPDOWN`, and `SYMBOL`.

## Patch

- `LegacyDocReader` now reads the main-document `Plcfld` table from the FIB-advertised Table Stream range.
- The reader validates PLC length, sorted unique CPs, marker-character agreement with extracted main text, duplicate separators, separator/end outside fields, and unterminated fields.
- Field-character metadata is exposed as `fieldCharacters`; balanced field ranges are exposed as `fields` on the result, document attrs, and metadata.
- The WordPress legacy DOC handoff example now embeds a `Plcfld` table for the existing field-code packet and self-tests field type/count metadata while preserving the existing rendered field spans.

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` passed with `1 test files, 673 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` failed with `1 test files, 675 assertions, 2 failures` because Plcfld metadata was absent and malformed Plcfld tables were ignored.
- After patch: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` passed with `1 test files, 706 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test` passed with `legacy doc handoff self-test ok`.
- Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is required. This reused the native PHP `LegacyDocReader`, `CompoundFileBinary`, FIB/table-stream slicing helpers, and existing WordPress block writer. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external converter, online service, or live provider test was executed.

## Non-Overlap

This slice owns only main-document MS-DOC `Plcfld` field-character and field-range metadata. It avoids accepted legacy DOC/CFB work for CFB validation, directory timestamp/CLSID/state-bit provenance, encrypted FIB and `lKey` preflight, `fExtChar` direct Unicode text, FibRgLw97 subdocument trimming, CLX PCD flag validation, OLE property sets, ObjectPool reports, macros, inline field rendering, SttbfAssoc metadata, styles, sections, notes, bookmarks, lists, and formatting runs. Follow-up should keep FFData option decoding, header/footer/textbox field tables, image extraction policy, and nested-field rendering parity as separate bounded slices.
