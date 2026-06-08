# pandoc-legacy-doc-cfb-core-current-base-20260608T113042Z

## Scope

Implemented one bounded legacy DOC/CFB support-library cluster: metadata-only
field table extraction for the MS-DOC Textbox Document and Header Textbox
Document.

`LegacyDocReader` already extracted `ccpTxbx` and `ccpHdrTxbx` subdocument
text from the CLX piece table. This slice adds the matching FibRgFcLcb97
`PlcfldTxbx` and `PlcfldHdrTxbx` field table descriptors so textbox story
fields are parsed into `fieldCharacters`, `fields`, and `fieldStories` without
rendering supplemental story text into WordPress blocks.

## Source Truth

- Microsoft MS-DOC FibRgFcLcb97 specifies `fcPlcfFldTxbx` /
  `lcbPlcfFldTxbx` as a Plcfld for field characters in the Textbox Document,
  with CPs relative to `FibRgLw97.ccpTxbx`:
  https://learn.microsoft.com/fr-fr/openspecs/office_file_formats/ms-doc/0c9df81f-98d0-454e-ad84-b612cd05b1a4
- The same FibRgFcLcb97 table specifies `fcPlcffldHdrTxbx` /
  `lcbPlcffldHdrTxbx` as a Plcfld for field characters in the Header Textbox
  Document, with CPs relative to `FibRgLw97.ccpHdrTxbx`.
- This ports only the bounded table-stream format contract. It does not run
  Word, LibreOffice, Pandoc, Cabal, Haskell runners, zip/unzip, external
  office tools, online services, live provider tests, or live-service provider
  tests.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed before this slice.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 1282 assertions, 0 failures`.
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

- `phpPass`: unchanged at `1629`; this grew an existing focused PASS case
  rather than adding a new named PASS line.
- `benchmarkDenominator.mapped`: `2048 -> 2049`.
- `legacyDocCfbCoreCases`: `7 -> 8`.
- `mappedLegacyDocCfbCoreCases`: `7 -> 8`.
- `legacyDocCfbCoreAssertions`: `64 -> 84`.
- Focused `LegacyDocReaderTest.php` assertions moved from the prior in-tree
  legacy DOC evidence at `1262` to `1282` (`+20`).

## Dependency Closure

No new support component is needed. This reuses native PHP
`CompoundFileBinary`, `LegacyDocReader`, CLX/FibRgLw97 subdocument extraction,
the existing Plcfld parser, existing field metadata handoff, and the existing
WordPress legacy DOC handoff example.

## Non-Overlap

This avoids accepted legacy DOC/CFB clusters for CFB header/FAT/DIFAT/MiniFAT
preflight, directory provenance, FIB flags, CLX piece-table extraction,
FibRgLw97 subdocument range extraction, DOP/document metadata,
ObjectPool/OLE metadata, macro project policy, picture placeholders,
PlcfldEdn, field-end flag metadata, hyperlink/cross-reference/form/data/
SET/prompt/symbol/generated/numbering/include/action/nested field handoffs,
notes/comments/bookmarks, sections, styles, and lists. The only new behavior
is parsing textbox and header-textbox story Plcfld tables as metadata-only
field provenance.

## Follow-Up

Keep follow-up work bounded to non-overlapping native MS-DOC table surfaces
such as FFData form-option decoding, hyperlink object payload metadata,
route-slip metadata, or another safe table-stream review handoff. Full
upstream Pandoc runner parity remains separate because external
Pandoc/Haskell/office runners were not authorized or needed for this bounded
support-library case.
