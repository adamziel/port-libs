# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T093935Z`
Base accepted HEAD: `f43374100703845d1f334e1745142ca65dc85bf6`
Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

- Added bounded MS-CFB header/root identity preflight in `CompoundFileBinary`
  before any legacy DOC stream bytes are exposed.
- The parser now rejects:
  - non-null CFB header CLSID bytes;
  - nonzero reserved header bytes;
  - mini-stream cutoff sizes other than 4096 bytes;
  - directory chains whose stream ID 0 is not the root storage; and
  - root storage entries not named `Root Entry`.
- The WordPress legacy DOC handoff smoke now mutates the generated CFB fixture
  through the same corrupt-container cases and verifies they fail closed through
  `LegacyDocReader`.

## Source Truth

- Microsoft MS-CFB Compound File Header:
  `https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/05060311-bfce-4b12-874d-71fd4ce63aea`
- Microsoft MS-CFB Root Directory Entry:
  `https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/026fde6e-143d-41bf-a7da-c08b2130d50e`
- Microsoft MS-CFB Stream ID 0 example:
  `https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/5af03a5e-66dc-469c-8970-7229a11e2a3f`

No Pandoc, Word, LibreOffice, OLE handler, macro engine, zip/unzip, external
office tooling, external template engine, TeX/PDF engine, Haskell runner,
Cabal build, or online conversion service was used.

## Verification

Baseline focused verification before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 376 assertions, 0 failures
```

Red-first focused check after adding the new expectations:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 377 assertions, 1 failures
Expected exception RuntimeException was not thrown
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 380 assertions, 0 failures
```

Focused delta over the previous legacy DOC/CFB run: `38 -> 39` PASS cases and
`376 -> 380` assertions (`+4`).

Example smoke:

```text
php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test
legacy doc handoff self-test ok
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native CFB reader,
legacy DOC reader, Pandoc-like AST, Markdown writer, and WordPress block writer.
Full upstream Pandoc runner parity remains gated on hydrating the pinned Pandoc
checkout with Cabal project/package files.

## Non-Overlap

This does not repeat OPC package work, ZIP/package parsing, OLE property
metadata, ObjectPool/Ole10Native/CompObj handling, macro stream policy, FIB
encryption guards, fExtChar direct Unicode extraction, CLX piece-table parsing,
PCD flag validation, field-code rendering, bookmark/note/section PLC parsing,
stylesheet metadata extraction, or CFB directory timestamp/CLSID/state-bit
reporting. It owns only mandatory MS-CFB header and root-storage identity
validation before stream lookup.

## Follow-Up

Keep applying parsed style ids to paragraph/character ranges, full SPRM style
formatting expansion, latent style data, list tables, revision-mark property
inspection, picture extraction, embedded-object extraction/export policy, VBA
`dir` decompression/signature trust, encrypted DOC password/decryption policy,
and optional stricter CFB verifier checks as separate bounded slices.
