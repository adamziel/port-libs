# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T052202Z`
Base accepted HEAD: `dd5c31589820160a8d28928cd49e3df83827c8d4`
Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

- Added bounded native legacy Word footnote/endnote reference parsing in
  `LegacyDocReader`:
  - `PlcffndRef` plus `PlcffndTxt` footnote PLCs;
  - `PlcfendRef` plus `PlcfendTxt` endnote PLCs.
- The reader now exposes `footnotes` and `endnotes` arrays on the returned
  result, document attributes, and metadata counts.
- Main-document reference characters are rendered as annotated superscript
  spans for WordPress/Markdown handoff while the raw `0x02` auto-number
  reference character is not exposed as text.
- Malformed or partial note PLCs are rejected before rendering:
  missing text PLCs, invalid lengths, duplicate/unsorted CPs, count mismatch,
  out-of-range reference CPs, and auto-numbered references without the Word
  special reference character fail closed.
- The WordPress legacy DOC handoff smoke now carries footnote/endnote reference
  metadata in the same CFB fixture as CLX text, bookmarks, field-code links,
  metadata, embedded-object preflight, and macro preflight.

## Source Truth

- Microsoft MS-DOC `FibRgFcLcb97` maps `fc/lcbPlcffndRef`,
  `fc/lcbPlcffndTxt`, `fc/lcbPlcfendRef`, and `fc/lcbPlcfendTxt` to the
  selected table stream:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/0c9df81f-98d0-454e-ad84-b612cd05b1a4`
- Microsoft MS-DOC `Footnotes` describes footnote reference locations in the
  main document and footnote text ranges:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/f7e96a05-aad7-4acb-a06d-bfa430ac1fcc`
- Microsoft MS-DOC `PlcffndRef`, `PlcffndTxt`, `PlcfendRef`, and `PlcfendTxt`
  define the reference PLC and note-text CP array shapes:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/3ed81d85-ea74-4790-8579-ab7f8eb651f7`
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/b30472e7-569e-4c9c-a8ca-07fd5432f365`
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/50371008-8334-4d3f-80c2-9bcd6dbe1c93`
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/49650ca7-1bfc-49e5-93ac-01a86bd2fc3e`

No Pandoc, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF
engine, Haskell runner, or online service was used.

## Verification

Baseline before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 195 assertions, 0 failures
```

Red check after adding note expectations:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 195 assertions, 1 failures
Failure: missing returned footnotes/endnotes
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 238 assertions, 0 failures
```

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
legacy DOC FIB/table-stream parsing, AST nodes, Markdown writer, and WordPress
block writer.

## Non-Overlap

This does not repeat recent legacy DOC/CFB work for CFB header preflight,
directory-sector checks, encrypted FIB rejection, fExtChar text decoding, CLX
piece-table decoding, PCD flag validation, field-code hyperlink output,
standard bookmark tables, OLE property metadata, embedded object reporting,
macro preflight, or mini-stream handling. It owns only bounded footnote/endnote
reference PLC handoff.

## Follow-Up

Keep footnote/endnote body extraction from specialized document parts, Plcfld
parsing inside note documents, revision-mark property inspection, style/list
tables, image extraction policy, encrypted DOC password/decryption policy, and
full upstream Pandoc runner parity as separate slices.
