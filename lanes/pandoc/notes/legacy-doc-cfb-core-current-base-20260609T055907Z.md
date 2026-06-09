# Legacy DOC CFB CHPX Direct Text Formatting

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260609T055907Z`
Accepted base: `7ed2f69b027c00a8c9af1b63d2dfcdebbab97ac6`

## Source Truth

This slice stays within the legacy Word DOC/CFB support-library path. It maps direct CHPX character property modifiers stored in `PlcBteChpx` / `ChpxFkp` formatting runs into bounded metadata-only review state.

Covered SPRMs:

- `sprmCFBold`
- `sprmCFItalic`
- `sprmCFStrike`
- `sprmCFSmallCaps`
- `sprmCFCaps`
- `sprmCFVanish`
- `sprmCKul`

Non-overlap: this does not repeat accepted CFB invariant checks, FIB Unicode text extraction, encryption preflight, picture placeholders, ObjectPool/OLE review metadata, field-code family mapping, revision-mark author linkage, list-table metadata, or PDF/ODF/DOCX support work. It adds only direct CHPX text-property extraction from real formatting table pages.

## Implementation

- `LegacyDocReader` now scans CHPX grpprls for direct character-formatting SPRMs while parsing character formatting table runs.
- Formatting-run metadata now includes `textProperties`, `textPropertyCount`, and `textPropertyExtractionPolicy` when a run carries supported direct text properties.
- Document metadata now reports `textPropertyFormattingRunCount` and `textPropertyFormattingPolicy`.
- Toggle-style SPRMs preserve the raw operand, normalized state (`off`, `on`, `toggle`, `preserve`, or `unknown`), enabled boolean, source SPRM, and metadata-only extraction policy.
- Underline preserves the raw `sprmCKul` code plus a bounded style name such as `single`, `double`, `dotted`, or `wave`.
- Markdown and WordPress writers keep only visible text for this slice; CHPX formatting metadata is review data and is not rendered as literal output.

## Evidence

Baseline before this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 2282 assertions, 0 failures
```

Focused verification after this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 2312 assertions, 0 failures

php lanes/pandoc/examples/wordpress-legacy-doc-character-formatting-handoff.php --self-test
legacy doc character-formatting handoff self-test ok
```

Manifest/status delta:

- `phpPass`: `2411` -> `2412`
- `benchmarkDenominator.mapped`: `2800` -> `2801`
- `legacyDocCfbCoreCases`: `7` -> `8`
- `mappedLegacyDocCfbCoreCases`: `7` -> `8`
- `legacyDocCfbCoreAssertions`: `64` -> `94`

## Dependency Closure

No new support component is needed. This reuses the native PHP CFB parser, `LegacyDocReader` FIB/formatting-table parsing, Pandoc-like AST, Markdown writer, and WordPress block writer.

## Exclusions

Did not run Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, ZipArchive, TeX/PDF engines, Typst, browser renderers, external converters, external validators, online services, live provider tests, or live-service provider tests. Root harness was not run for this isolated micro-slice.

## Next Task

A useful non-overlapping follow-up would map paragraph-property metadata, stylesheet-linked character formatting resolution, or safe visible inline-formatting application into AST spans without invoking external office tools.
