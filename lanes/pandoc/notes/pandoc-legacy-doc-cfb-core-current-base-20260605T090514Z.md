# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T090514Z`
Base accepted HEAD: `b7eb4436cc00c20501446e6d6af9fd4e35e89bc5`
Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

- Added bounded legacy Word stylesheet parsing in `LegacyDocReader`.
- The reader now follows `FibRgFcLcb97.fcStshf` / `lcbStshf` into the selected
  table stream, parses the STSH `LPStshi` header and non-empty `LPStd`
  records, and decodes each `STD` `Xstz` style name.
- Style review metadata now exposes `styles` on the returned result and
  document AST attrs, plus `metadata.styleCount` / `metadata.styles` for
  WordPress import queues.
- Each style preserves `istd`, type, primary name, aliases, invariant style id,
  built-in/user-defined status, `cupx`, `nextIstd`, optional `basedOnIstd`,
  and length mirror provenance.
- Malformed stylesheets fail closed before exposing style metadata: invalid
  STSH header lengths, invalid fixed-style counts, truncated `LPStd` records,
  negative style lengths, missing Xstz terminators, duplicate style names,
  invalid based-on references, and inheritance loops are rejected.
- The WordPress legacy DOC handoff smoke now includes a bounded stylesheet with
  paragraph and character styles alongside existing text, metadata, ObjectPool,
  macro, bookmark, note, field-code, section, and directory-provenance checks.

## Source Truth

- Microsoft MS-DOC `FibRgFcLcb97` defines `fcStshf` / `lcbStshf` as the table
  stream offset and size of the STSH stylesheet:
  `https://learn.microsoft.com/fr-fr/openspecs/office_file_formats/ms-doc/0c9df81f-98d0-454e-ad84-b612cd05b1a4`
- Microsoft MS-DOC `STSH` defines the stylesheet, `LPStshi`, and `LPStd` style
  definition array:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/c8ee0f39-02c3-4caa-b27a-6a97600130fe`
- Microsoft MS-DOC `Stshif` defines `cstd`, `cbSTDBaseInFile`,
  `fStdStylenamesWritten`, and `istdMaxFixedWhenSaved`:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/0e246123-e907-4ad1-9dfc-558512e2b052`
- Microsoft MS-DOC `LPStd`, `StdfBase`, `STD`, `Xst`, and `Xstz` define the
  length-prefixed style record, style type/base/next fields, and null
  terminated Unicode style names:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/8ff6f5f0-ee65-48e3-ab1e-f15deeb24355`
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/df0f4654-071d-442f-8563-752d7e0285ef`
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/dd73d580-cce7-445f-b692-3421060b9682`
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/4acc83cc-44b3-4ef7-a2f7-d01d3aecb6a5`
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/a3c8358d-4674-4751-898b-6261b54906df`

No Pandoc, Word, LibreOffice, OLE handler, macro engine, zip/unzip, external
office tooling, external template engine, TeX/PDF engine, Haskell runner,
Cabal build, or online conversion service was used.

## Verification

Focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 376 assertions, 0 failures
```

Focused delta over the previous legacy DOC/CFB run: `35 -> 37` PASS cases and
`352 -> 376` assertions (`+24`).

Example smoke:

```text
php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test
legacy doc handoff self-test ok
```

Full pandoc lane verification:

```text
php tools/run-tests.php lanes/pandoc/tests
20 test files, 9462 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS '
776
```

Syntax and JSON checks:

```text
php -l lanes/pandoc/src/LegacyDocReader.php
No syntax errors detected in lanes/pandoc/src/LegacyDocReader.php

php -l lanes/pandoc/tests/LegacyDocReaderTest.php
No syntax errors detected in lanes/pandoc/tests/LegacyDocReaderTest.php

php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-legacy-doc-handoff.php

php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'
pandoc json ok
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native CFB reader,
legacy DOC FIB/table-stream parsing path, AST nodes, Markdown writer, and
WordPress block writer. Full upstream Pandoc runner parity remains gated on
hydrating the pinned Pandoc checkout with Cabal project/package files.

## Non-Overlap

This does not repeat recent legacy DOC/CFB work for CFB header preflight,
directory-sector checks, directory timestamps, CLSID/state-bit provenance,
encrypted FIB rejection, nonzero `lKey` guard, fExtChar Unicode text decoding,
CLX piece-table decoding, PCD flag validation, field-code hyperlink output,
non-hyperlink field provenance, standard bookmark tables, footnote/endnote
reference PLCs, PlcfSed section descriptors, OLE property metadata,
ObjectPool stream-role grouping, ObjInfo format preflight, Ole10Native
metadata parsing, CompObj parsing, macro preflight, mini-stream handling, or
ZIP/OPC package work. It owns only bounded STSH stylesheet metadata extraction
and malformed stylesheet guards.

## Follow-Up

Keep applying parsed style ids to paragraph/character ranges, full SPRM style
formatting expansion, latent style data, list tables, revision-mark property
inspection, picture extraction, embedded-object extraction/export policy, VBA
`dir` decompression/signature trust, encrypted DOC password/decryption policy,
and full upstream Pandoc runner parity as separate bounded slices.
