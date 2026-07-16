# Legacy DOC CFB OLE Typed-Value Padding

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260609T062840Z`
Accepted base: `d9055a06d30a55d79eba71a2d656134139a1a3c6`

## Source Truth

This slice stays within the bounded legacy Word DOC/CFB support-library path. OLE property-set `TypedPropertyValue` records carry a two-byte reserved padding field after the VT type. The existing fixtures already emit zero padding; this patch rejects nonzero padding before SummaryInformation or DocumentSummaryInformation metadata is exposed.

Non-overlap: this does not repeat accepted CFB directory/FAT/MiniFAT/DIFAT validation, FIB Unicode/encryption preflight, LPSTR codepage decoding, property-set directory guards, dictionary-name validation, CHPX/PAPX revision metadata, field-code mapping, inline picture/OLE object metadata, or PDF/ODF/DOCX support work. It adds only typed-value reserved-padding validation in the existing OLE property-set reader.

## Implementation

- `LegacyDocReader` now checks the reserved padding word in `readTypedPropertyValueWithSize()` before dispatching scalar, string, vector, blob, FILETIME, CLSID, and numeric property values.
- Malformed SummaryInformation and DocumentSummaryInformation streams now fail closed instead of silently accepting dirty typed-property packets.
- The WordPress legacy-DOC handoff example now mutates the title property's typed-value padding during `--self-test` and expects the reader to reject it before metadata handoff.

## Evidence

Red-first focused verification before the implementation guard:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 2313 assertions, 1 failures
```

Focused verification after this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 2315 assertions, 0 failures

php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test
legacy doc handoff self-test ok
```

Manifest/status delta:

- `phpPass`: `2447` -> `2448`
- `benchmarkDenominator.mapped`: `2835` -> `2836`
- `legacyDocCfbCoreCases`: `7` -> `8`
- `mappedLegacyDocCfbCoreCases`: `7` -> `8`
- `legacyDocCfbCoreAssertions`: `64` -> `67`

## Dependency Closure

No new support component is needed. This reuses the native PHP CFB parser, OLE property-set reader, `LegacyDocReader` metadata handoff, Pandoc-like AST, and WordPress block writer.

## Exclusions

Did not run Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, ZipArchive, TeX/PDF engines, Typst, browser renderers, external converters, external validators, online services, live provider tests, or live-service provider tests. Root harness was not run for this isolated micro-slice.

## Next Task

A useful non-overlapping follow-up would add more property-set scalar/vector guard coverage, paragraph-property metadata, stylesheet-linked character formatting review, or safe visible inline-formatting application without invoking external office tools.
