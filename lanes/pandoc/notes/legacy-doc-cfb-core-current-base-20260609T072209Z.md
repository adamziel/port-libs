# Legacy DOC CFB OLE 16-Bit Value Padding

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260609T072209Z`
Accepted base: `93c7fe92d8764429cde901a465ac3a9266aec0d4`

## Source Truth

This stays inside the bounded legacy Word DOC/CFB support-library path. OLE
property-set typed values are DWORD-aligned. The existing reader already
validated the reserved padding word after the VT type and consumed zeroed
padding for variable-length values. This slice applies the same fail-closed
rule to fixed 16-bit scalar values used by legacy DOC metadata:

- `VT_I2`
- `VT_BOOL`
- `VT_UI2`

Malformed SummaryInformation or DocumentSummaryInformation streams now fail
before WordPress review metadata is exposed.

## Implementation

- `LegacyDocReader` now routes 16-bit OLE property values through
  `readPadded16TypedValue()`.
- The helper preserves signed `VT_I2`, boolean `VT_BOOL`, and unsigned
  `VT_UI2` values while rejecting nonzero trailing 16-bit alignment padding.
- `LegacyDocReaderTest.php` adds a red-first guard for dirty SummaryInformation
  codepage padding and dirty DocumentSummaryInformation bool padding.
- The WordPress legacy DOC thumbnail smoke now uses current CFB fixture hygiene
  for unallocated directory entries and MiniFAT FREESECT padding, and its
  `--self-test` mutates the codepage value padding to prove the malformed
  metadata path is rejected.

## Evidence

Red-first focused verification before the implementation guard:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 2346 assertions, 1 failures
```

Focused verification after this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 2347 assertions, 0 failures

php lanes/pandoc/examples/wordpress-legacy-doc-thumbnail-handoff.php --self-test
Legacy DOC thumbnail handoff self-test passed
```

Manifest/status delta:

- `phpPass`: `2489` -> `2490`
- `benchmarkDenominator.mapped`: `2867` -> `2868`
- `legacyDocCfbCoreCases`: `7` -> `8`
- `mappedLegacyDocCfbCoreCases`: `7` -> `8`
- `legacyDocCfbCoreAssertions`: `64` -> `66`

## Non-Overlap

This does not repeat accepted CFB directory/FAT/MiniFAT/DIFAT validation, FIB
Unicode/encryption preflight, typed-value reserved-padding validation,
property-set directory guards, LPSTR codepage decoding, dictionary-name
validation, CHPX/PAPX formatting metadata, field-code mapping, inline picture
or OLE object metadata, or DOCX/ODF/PDF/EPUB support work. It only closes the
fixed 16-bit OLE scalar value-padding guard.

## Dependency Closure

No new support component is needed. This reuses the native PHP CFB parser, OLE
property-set reader, `LegacyDocReader` metadata handoff, the Pandoc-like AST,
and `WordPressBlockWriter`.

## Exclusions

Did not run Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip,
ZipArchive, external template engines, external converters, TeX/PDF engines,
Typst, browser renderers, external validators, online services, live provider
tests, or live-service provider tests. Root harness was not run for this
isolated micro-slice.

## Next Task

A useful non-overlapping follow-up would add more OLE scalar/vector guard
coverage, stylesheet-linked character formatting resolution, or list/numbering
handoff metadata without invoking external office tools.
