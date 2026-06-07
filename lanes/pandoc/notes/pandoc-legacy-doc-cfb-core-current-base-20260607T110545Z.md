# pandoc-legacy-doc-cfb-core-current-base-20260607T110545Z

## Scope

Implemented one bounded legacy Word/DOC field-result handoff case: displayed
`MERGEFIELD` and `DOCVARIABLE` results now remain visible as inert
`legacy-doc-data-field` spans while the field instructions stay hidden from
rendered Markdown/WordPress text. The preserved review metadata includes the
field type, field name, field format switch, merge prefix/suffix values, and
bounded data-field switches.

This avoids overlapping the accepted CFB/FIB/piece-table/field work already
present in this lane, including CFB directory/FAT/DIFAT/MiniFAT safety, FIB
flags, document metadata, CLX piece tables, supplemental field PLCs,
bookmarks, notes/comments, sections, styles, lists, hyperlinks, form fields,
cross-reference fields, and symbol fields.

## Evidence

- Red-first focused command:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  failed as expected before implementation with `1 test files, 974 assertions,
  1 failures`; the new data-field result was plain text instead of a field
  provenance span.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 998 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  passed with `legacy doc handoff self-test ok`.
- Syntax and handoff hygiene:
  `php -l lanes/pandoc/src/LegacyDocReader.php`,
  `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php` all passed;
  `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  passed; `git diff --check -- lanes/pandoc` passed with no output.

## Dependency Closure

No new support component is needed. This slice reuses the native
`LegacyDocReader` field-instruction tokenizer, the existing CFB fixture path,
`MarkdownWriter` bracketed-span output, `WordPressBlockWriter` data-attribute
output, and the legacy DOC WordPress handoff example. Pandoc, Word,
LibreOffice, zip/unzip, Cabal/Haskell runners, external office tools, online
services, live provider tests, and live-service provider tests were not run.

## Follow-Up

Possible non-overlapping legacy DOC follow-up work: ASK/FILLIN prompt fields,
TOC/index field result provenance, or remaining STTBF/PLC metadata that can be
parsed natively with focused PHP tests.
