# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T075836Z`
Base accepted HEAD: `bdca88c1debfc451497e7b148e9ac8253995cb62`
Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

- Added bounded MS-OLEDS `CompObj` stream metadata parsing for legacy Word
  ObjectPool embedded-object storages.
- `LegacyDocReader` now records safe compound-object review metadata:
  ANSI display name, ANSI clipboard format, optional Unicode display name,
  optional Unicode clipboard format, preferred display name, and preferred
  clipboard format.
- Parent ObjectPool records now aggregate safe CompObj display names and
  clipboard-format names while keeping embedded bytes opaque.
- Malformed CompObj streams stay as diagnostics on the compound-object stream
  and parent ObjectPool object instead of exposing payload bytes or aborting
  unrelated text extraction.
- The WordPress legacy DOC handoff smoke now includes a structured `\001CompObj`
  stream and verifies display-name plus clipboard-format metadata alongside the
  existing ObjInfo, Ole10Native, presentation-data, macro, bookmark, note,
  field-code, section, and directory-provenance checks.

## Source Truth

- Microsoft MS-OLEDS `CompObjStream` defines the `\001CompObj` stream and its
  display-name plus clipboard-format payload:
  `https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-oleds/142e0420-2f74-4ed9-829b-0b3d5a684d01`
- Microsoft MS-OLEDS `LengthPrefixedAnsiString` defines the null-terminated
  ANSI display-name envelope:
  `https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-oleds/fc725b5a-bc3e-4ff2-89e9-1dcd1092d497`
- Microsoft MS-OLEDS `ClipboardFormatOrAnsiString` and
  `ClipboardFormatOrUnicodeString` define standard and registered clipboard
  format encodings:
  `https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-oleds/7f2bb395-1339-4669-92a7-7ea6f8e51d18`
  `https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-oleds/540ad461-9bb6-439e-af10-bfe9c702a346`
- Microsoft MS-OLEDS `LengthPrefixedUnicodeString` defines the Unicode
  display-name envelope:
  `https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-oleds/d8e0268a-a7bc-4094-8fd5-711e5a62f076`

No Pandoc, Word, LibreOffice, OLE handler, macro engine, zip/unzip, external
template engine, TeX/PDF engine, Haskell runner, Cabal build, or online
conversion service was used.

## Verification

Baseline before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 312 assertions, 0 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 327 assertions, 0 failures
```

Focused assertion delta over the previous legacy DOC/CFB run: `312 -> 327`
(`+15`) with one new PASS case.

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
preflight, Ole10Native metadata parsing, macro preflight, or mini-stream
handling. It owns only bounded `CompObj` display-name and clipboard-format
metadata parsing for ObjectPool compound-object streams.

## Follow-Up

Keep CFB storage CLSID interpretation, embedded object extraction/export
policy, CompObj edge-case variants beyond display-name and clipboard-format
metadata, picture and image extraction, style/list tables, revision-mark
property inspection, encrypted DOC password/decryption policy, full MS-OVBA
`dir` decompression/signature trust, and full upstream Pandoc runner parity as
separate bounded slices.
