# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T062740Z`
Base accepted HEAD: `5f8b8c0d546a115699c0adf82d3e94d711c1439a`
Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

- Added bounded legacy Word `PlcfSed` parsing in `LegacyDocReader`.
- The reader now exposes `sections` on the returned result, the document AST
  attributes, and metadata, with `sectionCount` for WordPress review queues.
- Section descriptors preserve CP ranges, distinguish default-section
  descriptors from SEPX-backed descriptors, and record bounded SEPX byte-count
  provenance without interpreting section-property SPRMs.
- Malformed section PLCs fail closed before rendering: invalid length,
  duplicate/unsorted CPs, missing required section-break character for
  non-final sections, final CP before extracted main text, and invalid SEPX
  pointers/counts are rejected.
- The WordPress legacy DOC handoff smoke now includes single-section SEPX
  provenance in the same CFB fixture as CLX text, bookmarks, field-code links,
  footnote/endnote references, OLE metadata, embedded-object preflight, and
  macro preflight.

## Source Truth

- Microsoft MS-DOC `FibRgFcLcb97` records the FIB `fcPlcfsed` /
  `lcbPlcfsed` table-stream pair:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/0c9df81f-98d0-454e-ad84-b612cd05b1a4`
- Microsoft MS-DOC `PlcfSed` defines sorted section CP boundaries and the
  required end-of-section character for non-final sections:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/68a959a6-11e0-4f3e-9a99-76ca8cc4dddc`
- Microsoft MS-DOC `Sed` defines each 12-byte section descriptor and its
  `fcSepx` pointer into the WordDocument stream:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/ae1ec5fc-e3c0-4e27-956e-9ceedc41cc2a`
- Microsoft MS-DOC `Sepx` defines the section-property exception byte-count
  envelope used here for bounded provenance only:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/1cc8d6e1-17e2-4667-99b6-39b2c70a0ebe`

No Pandoc, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF
engine, Haskell runner, Cabal build, or online conversion service was used.

## Verification

Focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 284 assertions, 0 failures
```

Focused assertion delta over the previous legacy DOC/CFB slice: `260 -> 284`
(`+24`) with two new PASS cases.

Example smoke:

```text
php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test
legacy doc handoff self-test ok
```

Full pandoc lane verification:

```text
php tools/run-tests.php lanes/pandoc/tests
20 test files, 8055 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS '
688
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
legacy DOC FIB/table-stream parsing path, AST nodes, Markdown writer, and
WordPress block writer. Upstream Pandoc runner parity remains gated on
hydrating the pinned Pandoc checkout with Cabal project/package files.

## Non-Overlap

This does not repeat recent legacy DOC/CFB work for CFB header preflight,
directory-sector checks, encrypted FIB rejection, fExtChar text decoding, CLX
piece-table decoding, PCD flag validation, field-code hyperlink output,
standard bookmark tables, footnote/endnote reference PLCs, OLE property
metadata, CFB directory timestamps, embedded object reporting, macro preflight,
or mini-stream handling. It owns only bounded `PlcfSed` section descriptor and
SEPX byte-count provenance.

## Follow-Up

Keep full SEPX SPRM decoding, page geometry from section properties,
header/footer relationship to section ranges, style/list tables, revision-mark
property inspection, image extraction policy, encrypted DOC password/decryption
policy, footnote/endnote body extraction, and full upstream Pandoc runner
parity as separate bounded slices.
