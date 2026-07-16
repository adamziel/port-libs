# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T104515Z`
Base accepted HEAD: `78358c7fc7811874b12aaa4e531144997b377904`
Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

- Added bounded legacy Word comment-reference parsing in `LegacyDocReader`.
- The reader now follows `FibRgFcLcb97.fcPlcfandRef` / `lcbPlcfandRef` and
  `fcPlcfandTxt` / `lcbPlcfandTxt` in the selected table stream.
- `PlcfandRef` entries are parsed as `ATRDPre10` records with author initials,
  author string-table index, and bookmark tag provenance.
- `PlcfandTxt` comment-text CP ranges are exposed as metadata only; this slice
  does not extract the annotation subdocument body.
- Main-text special comment-reference character `0x05` is replaced with a
  WordPress-safe review span and superscript marker, so raw control characters
  do not leak into rendered blocks.
- Malformed comment tables fail closed before rendering: missing parallel
  text-range PLCs, bad annotation markers, duplicate/unsorted CPs, and nonzero
  reserved descriptor fields are rejected.
- The WordPress legacy DOC handoff smoke now includes comment-reference
  metadata alongside the existing text, property, field, bookmark, note,
  section, formatting, ObjectPool, macro, and CFB provenance checks.

## Source Truth

- Microsoft MS-DOC `FibRgFcLcb97` defines the FIB table-stream offset/length
  pairs, including `fcPlcfandRef` / `lcbPlcfandRef` and `fcPlcfandTxt` /
  `lcbPlcfandTxt`:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/0c9df81f-98d0-454e-ad84-b612cd05b1a4`
- Microsoft MS-DOC `PlcfandRef` defines comment-reference CPs, the required
  main-document `0x05` marker, and 30-byte `ATRDPre10` records:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/9d82ca25-ff21-488c-bee2-dd654935c65a`
- Microsoft MS-DOC `PlcfandTxt` defines comment-text CP ranges with no data
  payload:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/MS-DOC/ac1c69d1-b6b9-4d2c-8cc6-4300e0ee5921`
- Microsoft MS-DOC `ATRDPre10` defines the author initials, author index, and
  bookmark tag carried by the pre-Word-2010 comment descriptor:
  `https://learn.microsoft.com/de-ch/openspecs/office_file_formats/ms-doc/f2327847-8ba3-4b9c-b9a3-b0bdfac1206c`

No Pandoc, Word, LibreOffice, OLE handler, macro engine, zip/unzip, external
office tooling, external template engine, TeX/PDF engine, Haskell runner,
Cabal build, or online conversion service was used.

## Verification

Focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 448 assertions, 0 failures
```

Focused delta over the previous legacy DOC/CFB run: `40 -> 42` PASS cases and
`414 -> 448` assertions (`+34`).

Example smoke:

```text
php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test
legacy doc handoff self-test ok
```

Syntax, JSON, and whitespace checks:

```text
php -l lanes/pandoc/src/LegacyDocReader.php
No syntax errors detected in lanes/pandoc/src/LegacyDocReader.php

php -l lanes/pandoc/tests/LegacyDocReaderTest.php
No syntax errors detected in lanes/pandoc/tests/LegacyDocReaderTest.php

php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-legacy-doc-handoff.php

php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'
pandoc json ok

git diff --check -- lanes/pandoc
no output
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native CFB reader,
legacy DOC FIB/table-stream parsing helpers, Pandoc-like AST, Markdown writer,
and WordPress block writer. Full upstream Pandoc runner parity remains gated on
hydrating the pinned Pandoc checkout with Cabal project/package files.

## Non-Overlap

This does not repeat recent legacy DOC/CFB work for CFB header/root preflight,
directory timestamps, CLSID/state-bit provenance, encrypted FIB and nonzero
`lKey` guards, fExtChar Unicode text ranges, CLX piece-table text, PCD flag
validation, field-code hyperlinks, standard bookmarks, footnote/endnote PLCs,
PlcfSed section descriptors, STSH stylesheets, formatting BTE/FKP provenance,
OLE property metadata, ObjectPool ObjInfo/Ole10Native/CompObj metadata,
macro preflight, or mini-stream handling. It owns only bounded Word comment
reference PLC metadata and rendered review anchors.

## Follow-Up

Keep comment-body extraction from the annotation subdocument, annotation owner
string-table expansion, `ATRDPost10` timestamp/thread metadata, revision-mark
SPRM inspection, list tables, full PAPX/CHPX SPRM style application, picture
extraction, embedded-object export policy, VBA trust policy, encrypted DOC
password/decryption policy, and full upstream Pandoc runner parity as separate
bounded slices.
