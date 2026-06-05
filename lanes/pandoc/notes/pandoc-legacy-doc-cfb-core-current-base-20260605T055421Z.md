# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T055421Z`
Base accepted HEAD: `b200c72fb083964db2fd5be8a33cdd135a93ce67`
Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

- Added bounded CFB directory FILETIME decoding in `CompoundFileBinary`.
- `LegacyDocReader` now exposes:
  - `streamDirectory` for stream path, storage path, byte size, and directory id;
  - `directoryEntries` / `cfbDirectoryEntries` for root, storage, and stream
    entries, including decoded `createdAt` / `modifiedAt` timestamps when
    recorded;
  - metadata counts for `cfbStreamCount` and
    `cfbTimestampedDirectoryEntryCount`.
- The focused fixture and WordPress smoke prove root and ObjectPool storage
  timestamps are preserved while stream objects do not get invented timestamps.

## Source Truth

- Microsoft MS-CFB `Compound File Directory Entry` defines the Creation Time
  and Modified Time fields as 8-byte UTC FILETIME values and records zero as
  not recorded:
  `https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/60fe8611-66c3-496b-b70d-a504c94c9ace`
- The same MS-CFB directory-entry source distinguishes storage/root timestamp
  metadata from stream byte-location metadata, so this slice exposes timestamp
  provenance on directory entries and keeps stream inventory separate.

No Pandoc, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF
engine, Haskell runner, Cabal build, or online service was used.

## Verification

Baseline before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 238 assertions, 0 failures
```

Red check after adding directory timestamp expectations:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 239 assertions, 1 failures
Failure: CFB directory timestamps were not exposed
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 260 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test
legacy doc handoff self-test ok
```

Full focused lane verification:

```text
php tools/run-tests.php lanes/pandoc/tests
20 test files, 7808 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS '
673
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
standard bookmark tables, footnote/endnote reference PLCs, OLE property
metadata, embedded object reporting, macro preflight, or mini-stream handling.
It owns only bounded CFB directory-entry timestamp provenance.

## Follow-Up

Keep CFB CLSID/state-bit provenance, full MS-OVBA `dir` decompression policy,
embedded OLENativeStream display-name parsing, footnote/endnote body
extraction, style/list tables, revision marks, image extraction policy,
encrypted DOC password/decryption policy, cross-part field tables, and full
upstream Pandoc runner parity as separate slices.
