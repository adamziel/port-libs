# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T065758Z`
Base accepted HEAD: `5be66afb33918558e8f089464110f60faee36e79`
Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

- Added bounded CFB directory CLSID and state-bit decoding in
  `CompoundFileBinary`.
- `LegacyDocReader` now carries non-zero directory `clsid` and `stateBits`
  fields into the `directoryEntries` / `cfbDirectoryEntries` review report.
- Legacy DOC metadata now includes `cfbClassIdDirectoryEntryCount` and
  `cfbStateBitsDirectoryEntryCount` when the CFB directory records those
  fields.
- Zero CLSIDs and zero state bits stay omitted, so ordinary stream entries do
  not gain invented storage provenance.
- The WordPress legacy DOC handoff smoke now proves root and ObjectPool storage
  CLSID/state-bit provenance survives alongside text, metadata, embedded-object,
  macro, bookmark, note, field-code, section, and timestamp handoff data.

## Source Truth

- Microsoft MS-CFB `Compound File Directory Entry` defines the directory-entry
  CLSID field, user-defined state bits, creation time, modified time, stream
  start sector, and stream size:
  `https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/60fe8611-66c3-496b-b70d-a504c94c9ace`
- The native reader records CLSID/state-bit values only as bounded provenance;
  it does not activate COM/OLE handlers, macros, Word automation, or embedded
  object payload execution.

No Pandoc, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF
engine, Haskell runner, Cabal build, or online conversion service was used.

## Verification

Baseline before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 284 assertions, 0 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 299 assertions, 0 failures
```

Focused assertion delta over the previous legacy DOC/CFB run: `284 -> 299`
(`+15`) with one new PASS case.

Example smoke:

```text
php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test
legacy doc handoff self-test ok
```

Syntax, JSON, and whitespace checks:

```text
php -l lanes/pandoc/src/CompoundFileBinary.php
No syntax errors detected in lanes/pandoc/src/CompoundFileBinary.php

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
writer. The upstream Pandoc runner remains gated on hydrating the pinned
Pandoc checkout with its Cabal project/package files.

## Non-Overlap

This does not repeat recent legacy DOC/CFB work for CFB header preflight,
directory-sector checks, encrypted FIB rejection, fExtChar text decoding, CLX
piece-table decoding, PCD flag validation, field-code hyperlink output,
standard bookmark tables, footnote/endnote reference PLCs, PlcfSed section
descriptor parsing, OLE property metadata, CFB directory timestamps, embedded
object reporting, macro preflight, or mini-stream handling. It owns only CFB
directory CLSID and state-bit provenance.

## Follow-Up

Keep CFB storage CLSID interpretation, full MS-OVBA `dir` decompression policy,
embedded OLENativeStream display-name parsing, footnote/endnote body
extraction, header/footer section relationships, style/list tables,
revision-mark property inspection, image extraction policy, encrypted DOC
password/decryption policy, and full upstream Pandoc runner parity as separate
bounded slices.
