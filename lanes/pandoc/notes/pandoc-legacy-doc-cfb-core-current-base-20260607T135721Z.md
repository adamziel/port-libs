# pandoc-legacy-doc-cfb-core-current-base-20260607T135721Z

## Scope

Implemented one bounded legacy DOC/CFB support-library cluster: ASK/FILLIN
prompt-field result provenance. `LegacyDocReader` now preserves displayed
ASK and FILLIN results as inert `legacy-doc-prompt-field` spans while keeping
field instructions hidden from rendered Markdown and WordPress output.

The preserved review metadata includes:

- field type (`ask` / `fillin`);
- normalized field instruction;
- ASK bookmark variable name;
- prompt text;
- default prompt response from `\d`;
- prompt switches such as `\d` and `\o`.

Plcfld metadata now also maps MS-DOC `flt` values `0x26` and `0x27` to
`ask` and `fillin` respectively. Source truth: Microsoft MS-DOC `flt`
enumerates `0x26` as ASK and `0x27` as FILLIN:
https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/28a8d2c2-6107-409d-8f6a-e345ab6d4179

This avoids overlapping accepted CFB allocation/header preflight, FIB flags,
CLX piece-table extraction, DOP/document metadata, ObjectPool metadata,
bookmarks, notes/comments, sections, styles, lists, hyperlinks, form fields,
cross-reference fields, data fields, and symbol fields.

## Evidence

- Red-first focused command:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  failed as expected with `1 test files, 999 assertions, 1 failures` because
  ASK/FILLIN displayed results were plain text instead of prompt-field
  provenance spans.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 1023 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  passed with `legacy doc handoff self-test ok`.

## Mapping Delta

- `phpPass`: `1509 -> 1510`
- `benchmarkDenominator.mapped`: `1928 -> 1929`
- `legacyDocCfbCoreCases`: `7 -> 8`
- `mappedLegacyDocCfbCoreCases`: `7 -> 8`
- `legacyDocCfbCoreAssertions`: `64 -> 89`
- Focused assertions: `+25` in `LegacyDocReaderTest.php`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`LegacyDocReader` field-instruction tokenization, Plcfld metadata parsing,
`AstNode`, `MarkdownWriter`, `WordPressBlockWriter`, focused legacy DOC tests,
and the existing WordPress legacy DOC handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external office tool, online service, live provider test, or
live-service provider test was executed.

## Follow-Up

Keep follow-up work bounded to non-overlapping native MS-DOC field/table
metadata such as TOC/index result provenance, SET field metadata, or additional
STTBF/PLC review handoffs. Full upstream Pandoc runner parity remains out of
this slice because external Pandoc/Haskell/office runners were not authorized
or needed for this bounded support-library case.
