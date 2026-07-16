# Legacy DOC/CFB Current-Base FFData Form Field Options

## Scope

This slice adds bounded native MS-DOC `FFData` decoding for legacy Word form
fields. `LegacyDocReader::decodeFormFieldData()` now parses text, checkbox,
and dropdown option payloads into review metadata, including Xstz field names,
default/help/status/macro strings, checkbox size/current/default state, and
dropdown STTB choices/default/current selection.

The WordPress legacy DOC handoff example now includes metadata-only FFData
review samples for text, checkbox, and dropdown controls while keeping hidden
option metadata out of rendered WordPress blocks.

## Source Truth

- Microsoft MS-DOC `FFData` specifies the `version`, `FFDataBits`, text length,
  checkbox size, Xstz strings, default value, and dropdown list layout:
  https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/3dd0761d-34df-4b7a-8d62-d74e23b2d1e0
- This ports only the bounded support-library format contract. It does not run
  Word, LibreOffice, Pandoc, Cabal/Haskell runners, zip/unzip, external office
  tools, online services, live provider tests, or live-service provider tests.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed before this slice.
- Focused test:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 1328 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  passed with `legacy doc handoff self-test ok`.
- Syntax checks:
  `php -l lanes/pandoc/src/LegacyDocReader.php`,
  `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`
  all reported no syntax errors.
- JSON validation:
  `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`
  passed for both lane JSON files.
- Whitespace check:
  `git diff --check -- lanes/pandoc` passed with no output.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1638 -> 1639`.
- `benchmarkDenominator.mapped`: `2058 -> 2059`.
- `legacyDocCfbCoreCases`: `7 -> 8`.
- `mappedLegacyDocCfbCoreCases`: `7 -> 8`.
- `legacyDocCfbCoreAssertions`: `64 -> 110`.
- Focused `LegacyDocReaderTest.php` assertions moved from the prior in-tree
  legacy DOC evidence at `1282` to `1328` (`+46`).

## Dependency Closure

No new support component is needed. This reuses native PHP `LegacyDocReader`,
existing UTF-16LE decoding, bounded Xstz/STTB parsing conventions, and the
existing WordPress legacy DOC handoff example.

True CHPX/Data-stream FFData pointer wiring remains a follow-up because the
current formatting report exposes FKP ranges and does not yet apply CHPX SPRMs
such as Data-stream payload locations.

## Non-Overlap

This avoids accepted legacy DOC/CFB clusters for CFB header/FAT/DIFAT/MiniFAT
preflight, directory provenance, FIB flags, CLX piece-table extraction,
FibRgLw97 subdocument range extraction, DOP/document metadata, ObjectPool/OLE
metadata, macro project policy, picture placeholders, PlcfldEdn, field-end
flag metadata, textbox/header-textbox Plcfld tables, and visible field-result
handoffs for hyperlink/cross-reference/form/data/SET/prompt/symbol/generated/
numbering/include/action/nested fields.

## Follow-Up

Keep follow-up work bounded to non-overlapping native MS-DOC table surfaces
such as true CHPX/Data-stream FFData pointer wiring, hyperlink object payload
metadata, or route-slip metadata. Full upstream Pandoc runner parity remains
separate because external Pandoc/Haskell/office runners were not authorized or
needed for this bounded support-library case.
