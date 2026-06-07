# pandoc-legacy-doc-cfb-core-current-base-20260607T070228Z

Implemented bounded legacy DOC StwUser document-variable extraction on accepted base `d2d9ea88993bebb96d341c8a9132df3b4b90a3ff`.

Source truth:

- MS-DOC StwUser: https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/41981ee7-6f8d-4004-93de-0e897046a000
- FibRgFcLcb97 fcStwUser/lcbStwUser field ordering cross-check: https://lxr.kde.org/source/libraries/binschema/src/mso.xml?v=stable-kf6-qt6

Behavior:

- `LegacyDocReader` now reads FibRgFcLcb97 `fcStwUser` / `lcbStwUser` from the selected table stream.
- StwUser names are parsed from the extended STTB name table and matched by index to Xst values.
- Normal variables are exposed as metadata-only review records plus `metadata.documentVariableValues`.
- `Sign`, `SigAgile`, and `SigV3` variables are reported as signature blobs with byte/character counts, redaction metadata, and no visible value.
- Malformed StwUser tables fail closed before metadata exposure: wrong extended marker, wrong extra-data width, duplicate names, truncated values, trailing bytes, and missing table stream.
- A `fcMin` boundary guard prevents direct-body text in small/simple fixtures from being misread as a high FibRgFcLcb97 table pointer.

Verification:

- Baseline before change: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` -> `1 test files, 903 assertions, 0 failures`.
- Focused after change: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` -> `1 test files, 942 assertions, 0 failures`.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test` -> `legacy doc handoff self-test ok`.
- `php -l` passed for:
  - `lanes/pandoc/src/LegacyDocReader.php`
  - `lanes/pandoc/tests/LegacyDocReaderTest.php`
  - `lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`
- `git diff --check -- lanes/pandoc` passed with no output.
- Root harness: not run - isolated micro-slice.

Counters:

- `phpPass`: `1462 -> 1464` for two new focused legacy DOC PASS cases.
- `benchmarkDenominator.mapped`: `1880 -> 1881` for one StwUser legacy DOC support case.
- Legacy DOC focused assertions: `64 -> 103` for `+39` assertions in this slice.

Dependency closure:

No new support component is needed. This reuses native PHP CFB/table-stream parsing, UTF-16LE Xst decoding, in-memory fixtures, and the existing WordPress legacy DOC handoff example. No Pandoc, Cabal, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, online service, live provider test, or live-service provider test was executed.

Non-overlap:

This intentionally avoids accepted legacy DOC DOP/DopBase, SttbfAssoc associated strings, OLE property sets, CFB directory/DIFAT/MiniFAT preflight, Plcfld field-table handoffs, ObjectPool/OLE, macro inventory, and FibRgLw97 subdocument trimming. Follow-up can stay bounded to saved-by/user metadata, language/compatibility options beyond DopBase, or additional PLC handoffs.
