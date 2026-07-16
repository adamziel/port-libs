# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T073016Z`
Base accepted HEAD: `2f0c67a4423e347ffc4b41c379e28870d045a8ab`
Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

- Added bounded MS-OLEDS `Ole10Native` metadata parsing for legacy Word
  ObjectPool native-data streams.
- `LegacyDocReader` now records safe embedded-object review metadata:
  display label, source path, temporary path, declared native-data byte count,
  and aggregate native labels/source paths/temporary paths on the ObjectPool
  object report.
- Malformed native-data size records stay as diagnostics on the native stream
  and parent object instead of exposing payload bytes or aborting unrelated
  text extraction.
- Embedded native bytes remain opaque with `canExposeBytes=false`; WordPress
  block output still excludes native/presentation payload bytes.
- The WordPress legacy DOC handoff smoke now uses a structured native-data
  stream and verifies the safe metadata while guarding against payload output.

## Source Truth

- Microsoft MS-DOC ObjectPool Storage documents ObjectPool child storages for
  embedded OLE objects:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/f7983581-d107-4a1f-b5f7-f3650e777c04`
- Microsoft MS-OLEDS Embedded Objects documents OLE2 `\001Ole10Native` native
  data streams and keeps them distinct from presentation streams:
  `https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-oleds/2677fcf2-ad48-4386-ba8f-b1b7baf4c02f`

No Pandoc, Word, LibreOffice, OLE handler, macro engine, zip/unzip, external
template engine, TeX/PDF engine, Haskell runner, Cabal build, or online
conversion service was used.

## Verification

Baseline before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 299 assertions, 0 failures
```

Red check after adding Ole10Native expectations:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 295 assertions, 2 failures
Expected failure: nativeDataBytes and oleNative metadata were absent.
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 312 assertions, 0 failures
```

Full pandoc lane verification:

```text
php tools/run-tests.php lanes/pandoc/tests
20 test files, 8666 assertions, 0 failures
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
legacy DOC reader, ObjectPool report path, Pandoc-like AST, Markdown writer,
and WordPress block writer. Upstream Pandoc runner parity remains gated on
hydrating the pinned Pandoc checkout with Cabal project/package files.

## Non-Overlap

This does not repeat recent legacy DOC/CFB work for CFB header preflight,
directory-sector checks, encrypted FIB rejection, fExtChar text decoding, CLX
piece-table decoding, PCD flag validation, field-code hyperlink output,
standard bookmark tables, footnote/endnote reference PLCs, PlcfSed section
descriptor parsing, OLE property metadata, CFB directory timestamps, CFB
CLSID/state-bit provenance, ObjectPool stream-role grouping, ObjInfo format
preflight, macro preflight, or mini-stream handling. It owns only bounded
`Ole10Native` metadata parsing and diagnostics for ObjectPool native-data
streams.

## Follow-Up

Keep CompObj display-name parsing, embedded object extraction/export policy,
picture and image extraction, style/list tables, revision-mark property
inspection, encrypted DOC password/decryption policy, full MS-OVBA `dir`
decompression/signature trust, and full upstream Pandoc runner parity as
separate bounded slices.
