# pandoc-legacy-doc-cfb-core-current-base-20260608T084837Z

## Scope

Implemented one bounded legacy DOC/CFB support-library cluster: action field
handoff for MS-DOC `GOTOBUTTON` and `MACROBUTTON` fields. `LegacyDocReader`
now maps Plcfld `flt` values `0x32` and `0x33`, preserves the displayed field
result as normal document text, and emits inert reviewer metadata spans for
the action destination or macro command.

No action is executed. Macro commands and navigation targets are exposed only
as review metadata attributes with `data-legacy-doc-action-field-execution`
set to `disabled`.

## Source Truth

- Microsoft MS-DOC Plcfld field-character records carry the field begin
  `flt` field type byte and saved field-end flags:
  https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/751b09bb-72f0-45ef-8e87-666dea68219f
- Microsoft MS-DOC field type values define `0x32` as `GOTOBUTTON` and
  `0x33` as `MACROBUTTON`:
  https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/badcfbd2-94f3-4192-9288-096892cefb00
- This slice ports only the bounded format contract needed for visible result
  handoff. It does not evaluate fields, follow navigation targets, execute
  macro buttons, expose VBA bytes, decrypt DOC files, or shell out to Word,
  LibreOffice, Pandoc, zip/unzip, Cabal, Haskell runners, online services,
  live provider tests, or live-service provider tests.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed before this slice.
- Red-first focused command:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  failed as expected with `1 test files, 1230 assertions, 1 failures` because
  Plcfld `flt` `0x33` still mapped to `unknown`.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 1262 assertions, 0 failures`.
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

- `phpPass`: `1585 -> 1586`
- `benchmarkDenominator.mapped`: `2005 -> 2006`
- `legacyDocCfbCoreCases`: `7 -> 8`
- `mappedLegacyDocCfbCoreCases`: `7 -> 8`
- `legacyDocCfbCoreAssertions`: `64 -> 99`
- Focused assertion delta: `+35` in `LegacyDocReaderTest.php`.

## Dependency Closure

No new support component is needed. This reuses native PHP
`CompoundFileBinary`, `LegacyDocReader`, the existing field-instruction
tokenizer, `AstNode`, `MarkdownWriter`, `WordPressBlockWriter`, focused CFB/DOC
fixtures, and the existing WordPress legacy DOC handoff example.

## Non-Overlap

This avoids accepted legacy DOC/CFB clusters for CFB header/FAT/DIFAT/MiniFAT
preflight, directory provenance, FIB flags, CLX piece-table extraction,
FibRgLw97 subdocument ranges, DOP/document metadata, ObjectPool/OLE metadata,
macro project policy, picture placeholders, PlcfldEdn, field-end flag
metadata, hyperlink/cross-reference/form/data/SET/prompt/symbol/generated/
numbering/include/nested field handoffs, notes/comments/bookmarks, sections,
styles, and lists. The only new behavior is inert action-field provenance for
`GOTOBUTTON` and `MACROBUTTON` displayed results.

## Follow-Up

Keep follow-up work bounded to non-overlapping native MS-DOC table surfaces such
as FFData form-option decoding, hyperlink object payload metadata, route-slip
metadata, or another safe table-stream review handoff. Full upstream Pandoc
runner parity remains separate because external Pandoc/Haskell/office runners
were not authorized or needed for this bounded support-library case.
