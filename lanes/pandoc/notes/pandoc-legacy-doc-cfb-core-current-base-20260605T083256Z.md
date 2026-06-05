# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T083256Z`
Base accepted HEAD: `99b8eff658aae1555f9b088e04999c64b4363135`
Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

- Added bounded FibBase provenance decoding for legacy Word `WordDocument`
  streams: install language id/tag, `nFibBack`, `pnNext`, selected document
  state flags, table-stream selection, and quick-save count.
- Exposed this as `metadata.fibBase` and the document `meta.fibBase` review
  packet so WordPress import queues can audit legacy `.doc` state without
  invoking Word, LibreOffice, Pandoc, or OLE handlers.
- Added a preflight guard that rejects unencrypted FIBs with nonzero `lKey`
  before text extraction. MS-DOC reserves nonzero `lKey` for encrypted or
  obfuscated documents, and encrypted documents remain out of native scope.
- Updated the WordPress legacy DOC handoff smoke fixture to carry FibBase
  language/version/state metadata alongside existing text, property-set,
  ObjectPool, macro, bookmark, note, field-code, section, and directory
  provenance checks.

## Source Truth

- Microsoft `[MS-DOC]` `FibBase` defines the fixed FIB header, `lid`,
  `pnNext`, document-state bits including `fDot`, `fGlsy`, `fComplex`,
  `fWhichTblStm`, `fReadOnlyRecommended`, `fWriteReservation`, `fExtChar`,
  `fLoadOverride`, `fFarEast`, `fObfuscated`, plus `nFibBack` and `lKey`:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/26fb6c06-4e5c-4778-ab4e-edbf26a545bb`

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, OLE handler,
macro engine, zip/unzip, external template engine, TeX/PDF engine, browser
renderer, online sanitizer, or online conversion service was used.

## Verification

Baseline before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 327 assertions, 0 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 352 assertions, 0 failures
```

Focused delta: `34 -> 35` PASS cases and `327 -> 352` assertions (`+25`).

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
legacy DOC reader, Pandoc-like AST, Markdown writer, and WordPress block
writer. Full upstream Pandoc runner parity remains gated on hydrating the
pinned Pandoc checkout with Cabal project/package files.

## Non-Overlap

This does not repeat recent legacy DOC/CFB work for CFB header preflight,
directory-sector checks, directory timestamps, CLSID/state-bit provenance,
encrypted FIB rejection, fExtChar Unicode text decoding, CLX piece-table
decoding, PCD flag validation, field-code hyperlink output, standard bookmark
tables, footnote/endnote reference PLCs, PlcfSed section descriptors, OLE
property metadata, ObjectPool stream-role grouping, ObjInfo format preflight,
Ole10Native metadata parsing, CompObj parsing, macro preflight, mini-stream
handling, or ZIP/OPC package work. It owns only bounded FibBase provenance and
the unencrypted nonzero-`lKey` guard.

## Follow-Up

Keep STSH style tables, list tables, revision-mark property inspection,
picture extraction, embedded-object extraction/export policy, VBA `dir`
decompression/signature trust, encrypted DOC password/decryption policy,
FibRgFcLcb range expansion, and full upstream Pandoc runner parity as separate
bounded slices.
