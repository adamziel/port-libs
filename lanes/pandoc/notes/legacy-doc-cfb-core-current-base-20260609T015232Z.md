# Legacy DOC/CFB Master Subdocument References

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260609T015232Z`
Base accepted HEAD: `21742a408faf47b66c5937f3cfd9d335c203497c`
Date: 2026-06-09 UTC

## Source Truth

- Microsoft MS-DOC `SttbFnm` stores external filenames referenced by a binary
  Word document.
- Microsoft MS-DOC `FNIF` appends `FNPI` metadata to each filename record.
- Microsoft MS-DOC `FNPI.fnpt = 5` classifies an external filename as a
  subdocument, which is the master-document link shape this native slice
  preserves for review.

References:

- https://learn.microsoft.com/fr-ch/openspecs/office_file_formats/ms-doc/996b1475-4a09-4893-be9c-13a71b1f3935
- https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/17f5604f-c6ea-4cb6-b3c7-e06ccda51d64
- https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/3bac1319-0f5c-4734-8a79-229c63bd6109

## Implementation

- `LegacyDocReader` now derives `subdocumentReferences` from already validated
  `SttbFnm` external-file records whose `referenceType` is `subdocument`.
- The result metadata, document attrs, and top-level result expose
  metadata-only master-document reference records with:
  - external-file reference index;
  - document index and `FNPI` value;
  - path kind, basename, optional relative path, and file-system class;
  - `relationshipRole = master-subdocument-link`;
  - `canExposeBytes = false` and `metadata-only-native-review`.
- The WordPress legacy DOC handoff smoke includes the new inventory while
  continuing to assert that subdocument filenames are not rendered into block
  text and external bytes are never fetched.

## Evidence

Red-first focused run after adding the expected assertions:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 1861 assertions, 1 failures
```

Final focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 1924 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test
legacy doc handoff self-test ok
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2089 -> 2090`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2501 -> 2502`.
- `legacyDocCfbCoreCases`: `7 -> 8`.
- `mappedLegacyDocCfbCoreCases`: `7 -> 8`.
- `legacyDocCfbCoreAssertions`: `64 -> 127`.

## Dependency Closure

No new support component is needed. This reuses native PHP
`CompoundFileBinary`, `LegacyDocReader` `SttbFnm` parsing, `AstNode`,
`MarkdownWriter`, `WordPressBlockWriter`, focused lane tests, and the existing
WordPress legacy DOC handoff example.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, zip/unzip, external office tool, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted `SttbFnm` external-file parsing, include-field to
`SttbFnm` linkage, `MERGEFIELD`/`DATA` mail-merge source linkage,
`SttbfAssoc`, RouteSlip, captions, reserved hyperlink metadata, piece-table
subdocument text extraction, or CFB allocation/header preflight work. It owns
only explicit master subdocument-reference classification derived from existing
`SttbFnm` records.

## Follow-Up

Potential next legacy DOC/CFB gaps: mail-merge settings tables beyond explicit
field/source linkage, richer piece-table edge cases, encrypted DOC preflight, or
another bounded CFB/DOC invariant not already covered by accepted allocation
and metadata slices.
