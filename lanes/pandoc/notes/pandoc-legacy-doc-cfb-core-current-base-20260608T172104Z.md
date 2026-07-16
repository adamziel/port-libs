# pandoc-legacy-doc-cfb-core-current-base-20260608T172104Z

## Scope

- Lane: `pandoc`
- Accepted base: `19e469ac5fba851474b6c82ad19f3b8c0f411282`
- Behavior cluster: bounded legacy DOC automatic-numbering field handoff.

This slice maps MS-DOC `flt` field type codes `0x34`, `0x35`, and `0x36` to `AUTONUMOUT`, `AUTONUMLGL`, and `AUTONUM` provenance when reading Plcfld field records. Displayed field results are preserved as inert `legacy-doc-numbering-field` spans in the Pandoc-like AST, Markdown attributes, and WordPress block HTML. Hidden field instructions stay in metadata and are not rendered as visible text.

Source truth is the MS-DOC `flt` enumeration and Plcfld field-boundary contract in Microsoft Learn:

- https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/28a8d2c2-6107-409d-8f6a-e345ab6d4179
- https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/751b09bb-72f0-45ef-8e87-666dea68219f

## Evidence

Red-first focused check before the field-type mapping change:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 1379 assertions, 1 failures
```

Final focused checks after the patch:

```text
php -l lanes/pandoc/src/LegacyDocReader.php
No syntax errors detected in lanes/pandoc/src/LegacyDocReader.php

php -l lanes/pandoc/tests/LegacyDocReaderTest.php
No syntax errors detected in lanes/pandoc/tests/LegacyDocReaderTest.php

php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-legacy-doc-handoff.php

php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 1412 assertions, 0 failures

php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test
legacy doc handoff self-test ok
```

## Status Delta

- `lane-status.json` `phpPass`: `1695 -> 1696`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2115 -> 2116`
- `legacyDocCfbCoreCases`: `7 -> 8`
- `mappedLegacyDocCfbCoreCases`: `7 -> 8`
- `legacyDocCfbCoreAssertions`: `64 -> 100`
- Focused `LegacyDocReaderTest.php`: `1379` assertions red-first to `1412` assertions final, adding `36` runtime assertions in the new automatic-numbering field case.

## Dependency Closure

No new support component is needed. This reuses `CompoundFileBinary`, the existing `LegacyDocReader` Plcfld parser and field-tokenizer path, `MarkdownWriter`, `WordPressBlockWriter`, and the bounded legacy DOC fixture builders. It does not run Pandoc, Word, LibreOffice, zip/unzip, Cabal/Haskell runners, external office tools, online services, live provider tests, or live-service provider tests.

## Non-Overlap And Follow-Up

This intentionally avoids accepted CFB/FIB/DIFAT/MiniFAT/CLX/DOP/ObjectPool, macro/action, picture, bookmark, notes/comments, section, style, list-table, PlcfldEdn, textbox Plcfld, field-end flag, hyperlink, form, data, `SET`, prompt, symbol, generated, `SEQ`, `LISTNUM`, include-field, and nested-field behavior. It only adds the `AUTONUMOUT`, `AUTONUMLGL`, and `AUTONUM` type-code and displayed-result handoff.

Useful follow-up slices would cover DOCPROPERTY/INFO metadata fields, OLE link metadata, another Plcf table family, or list-table cross-references for automatic numbering without recalculating Word numbering.
