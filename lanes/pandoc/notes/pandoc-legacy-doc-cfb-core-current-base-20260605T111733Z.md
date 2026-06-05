# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T111733Z`
Base accepted HEAD: `e781728480a3b1609812682d98c89c5c0ad42a2a`
Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

- Added bounded FibRgLw97 parsing for legacy Word `.doc` CFB imports:
  `cbMac`, `ccpText`, `ccpFtn`, `ccpHdd`, `ccpAtn`, `ccpEdn`, `ccpTxbx`,
  and `ccpHdrTxbx`.
- `LegacyDocReader` now exposes FibRgLw97 review metadata, supplemental
  subdocument character totals, `pieceTableExpectedLastCp`, and ordered
  subdocument CP ranges for main, footnote, header, comment, endnote,
  textbox, and header-textbox content.
- CLX/PlcPcd extraction now validates sorted/non-duplicate CPs and fails if
  the final CP does not match declared FibRgLw97 subdocument counts.
- Main-body extraction is trimmed to `ccpText`, so supplemental
  footnote/header/comment/endnote pieces remain review metadata instead of
  rendering as WordPress main blocks.
- Malformed negative subdocument counts, nonzero FibRgLw97 `reserved3`,
  out-of-bounds `cbMac`, and mismatched piece-table final CPs are rejected
  before piece-table text is exposed.
- The WordPress legacy DOC handoff example now carries supplemental
  subdocument bytes and asserts that they do not render while their CP ranges
  remain inspectable.

## Source Truth

Microsoft [MS-DOC] FibRgLw97 defines `cbMac` as the meaningful byte count for
the WordDocument stream, requires `reserved3` to be zero, and defines
nonnegative CP counts for main, footnote, header, comment, endnote, textbox,
and header-textbox subdocuments:
https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/37713d3c-a0c8-40f5-821f-bc9622c7de48

Microsoft [MS-DOC] PlcPcd states that PlcPcd CPs must not be duplicated and
that when supplemental FibRgLw97 subdocument counts are nonzero, the last CP
must equal those counts plus `ccpText + 1`:
https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/1caae71f-35c4-49d7-adf0-af5fc766331c

This ports the bounded format contract and WordPress review handoff only. It
does not shell out to Word, LibreOffice, Pandoc, zip/unzip, external office
tools, online validators, or online conversion services.

## Verification

Baseline focused check before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 448 assertions, 0 failures
```

Focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 469 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php | rg -c '^PASS '
44
```

Focused delta over the previous LegacyDocReader run: `448 -> 469` assertions
(`+21`) and `42 -> 44` focused PASS cases (`+2`).

Example smoke:

```text
php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test
legacy doc handoff self-test ok
```

Syntax and JSON checks:

```text
php -l lanes/pandoc/src/LegacyDocReader.php
No syntax errors detected in lanes/pandoc/src/LegacyDocReader.php

php -l lanes/pandoc/tests/LegacyDocReaderTest.php
No syntax errors detected in lanes/pandoc/tests/LegacyDocReaderTest.php

php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-legacy-doc-handoff.php

php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'
lanes/pandoc/lane-status.json ok
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json ok

git diff --check -- lanes/pandoc
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`LegacyDocReader`, `CompoundFileBinary`, `WordPressBlockWriter`, focused PHP
test harness, and WordPress legacy DOC handoff example. Full upstream Pandoc
runner parity remains gated on hydrating/building the pinned Pandoc checkout
at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, but FibRgLw97/PlcPcd
subdocument boundary handling is not blocked by that runner.

## Non-Overlap

This does not repeat accepted CFB header parsing, MiniFAT stream extraction,
directory timestamps, CLSID/state-bit provenance, SummaryInformation or
DocumentSummaryInformation property parsing, encrypted FIB rejection,
fExtChar direct Unicode decoding, ObjectPool or macro inventory, bookmarks,
footnote/endnote/comment reference PLCs, section descriptors, stylesheet
metadata, formatting table ranges, field-code hyperlinks, DOCX/ODT/EPUB
package parsing, ZIP/OPC package behavior, archive streams, table geometry,
doctemplates, math/TeX, PDF engine handoff, charset handling, YAML metadata,
or Markdown/HTML/XML reader/writer behavior. It owns only FibRgLw97
subdocument CP counts, PlcPcd final-CP validation for those counts, and
main-document trimming for legacy DOC piece-table text.

## Follow-Up

Keep deeper subdocument body extraction into footnote/endnote/comment AST
annotations, textbox subdocuments, FastSave piece-table edge cases, full
style/section application, and embedded object byte export policy as separate
bounded legacy DOC/CFB slices unless a concrete import fixture requires them.
