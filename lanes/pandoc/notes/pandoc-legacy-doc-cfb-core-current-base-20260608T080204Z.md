# pandoc-legacy-doc-cfb-core-current-base-20260608T080204Z

## Scope

Implemented one bounded legacy DOC/CFB support-library cluster: nested field
results in legacy Word text. `LegacyDocReader` now accepts nested field control
sequences only inside displayed field results, renders the nested field result
as normal inline provenance markup, and keeps all field instructions hidden
from Markdown and WordPress output.

The focused WordPress path now covers an outer `HYPERLINK` displayed result
that contains an inner `PAGE` field span. The Plcfld metadata preserves the
inner field nesting level and `grffldEnd` nested/end flag byte for review.

## Source Truth

- Microsoft MS-DOC field character records model nested field begin/separator/end
  ranges with `flt` type codes and the saved field-end `grffldEnd` flag byte.
- This slice ports only the bounded format contract needed for visible result
  handoff. It does not evaluate fields, recalculate Word results, execute OLE
  or macros, decrypt DOC files, or shell out to Word, LibreOffice, Pandoc,
  zip/unzip, Cabal, Haskell runners, online services, live provider tests, or
  live-service provider tests.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed before this slice.
- Red-first focused command:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  failed as expected with `1 test files, 1204 assertions, 1 failures` because
  nested fields were rejected by the previous guard.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 1227 assertions, 0 failures`.
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

- `phpPass`: `1570 -> 1571`
- `benchmarkDenominator.mapped`: `1991 -> 1992`
- `legacyDocCfbCoreCases`: `7 -> 8`
- `mappedLegacyDocCfbCoreCases`: `7 -> 8`
- `legacyDocCfbCoreAssertions`: `64 -> 87`
- Focused assertion delta: `+23` in `LegacyDocReaderTest.php`.

## Dependency Closure

No new support component is needed. This reuses native PHP
`CompoundFileBinary`, `LegacyDocReader`, the existing field-instruction
tokenizer, `AstNode`, `MarkdownWriter`, `WordPressBlockWriter`, focused CFB/DOC
fixtures, and the existing WordPress legacy DOC handoff example.

## Non-Overlap

This avoids accepted legacy DOC/CFB clusters for CFB header/FAT/DIFAT/MiniFAT
preflight, directory provenance, FIB flags, CLX piece-table extraction,
FibRgLw97 subdocument ranges, DOP/document metadata, ObjectPool/OLE metadata,
macro policy, picture placeholders, PlcfldEdn, field-end flag metadata,
hyperlink/cross-reference/form/data/SET/prompt/symbol/generated/numbering/
include field handoffs, notes/comments/bookmarks, sections, styles, and lists.
The only new behavior is nested displayed field-result rendering and metadata.

## Follow-Up

Keep follow-up work bounded to non-overlapping native MS-DOC table surfaces such
as FFData form-option decoding, hyperlink object payload metadata, route-slip
metadata, or another safe table-stream review handoff. Full upstream Pandoc
runner parity remains separate because external Pandoc/Haskell/office runners
were not authorized or needed for this bounded support-library case.
