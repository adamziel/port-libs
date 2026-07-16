# Legacy DOC CFB Built-In Field Handoff

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260609T054134Z`
Accepted base: `50ff75128f57e5d1c91c6f6643df81bffbb2e704`

## Source Truth

This slice stays within the legacy Word DOC/CFB support-library path and maps built-in Word document information and statistic field codes around cached field results. The implementation is bounded to native PHP parsing of field instructions already extracted from the WordDocument stream.

Non-overlap: this does not repeat accepted CFB transaction preflight, exact stream-chain sizing, Unicode FIB extraction, encryption rejection, Pms/SttbFnm handling, source-location/include aliases, DOCPROPERTY/INFO named-field handling, QUOTE/SHAPE fields, or SECTION fields. It adds the built-in field-code family `AUTHOR`, `TITLE`, `SUBJECT`, `KEYWORDS`, `COMMENTS`, `LASTSAVEDBY`, `REVNUM`, `NUMWORDS`, `NUMCHARS`, and `EDITTIME`.

## Implementation

- `LegacyDocReader::dataFieldAttrs()` now recognizes the built-in field-code family and maps it to `document-info` or `document-statistic` metadata.
- Built-in spans carry `data-legacy-doc-data-field-built-in="true"`, `data-legacy-doc-data-field-policy="cached-result-native-review"`, and a bounded result-kind such as `text`, `revision-number`, `word-count`, `character-count`, or `editing-minutes`.
- Cached field results remain visible in the AST, Markdown writer output, and WordPress block output while hidden field instructions remain metadata.
- The existing WordPress info-field smoke fixture now zero-pads unallocated CFB directory entries to match current CFB directory hygiene and adds `TITLE` plus `NUMWORDS` built-in field coverage.

## Evidence

Baseline before this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 2157 assertions, 0 failures
```

Focused verification after this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 2282 assertions, 0 failures

php lanes/pandoc/examples/wordpress-legacy-doc-info-field-handoff.php --self-test
legacy doc info-field handoff self-test ok
```

Manifest/status delta:

- `phpPass`: `2390` -> `2391`
- `benchmarkDenominator.mapped`: `2783` -> `2784`
- `legacyDocCfbCoreCases`: `7` -> `8`
- `mappedLegacyDocCfbCoreCases`: `7` -> `8`
- `legacyDocCfbCoreAssertions`: `64` -> `189`

## Dependency Closure

No new support component is needed. This reuses the native PHP CFB parser, `LegacyDocReader` field parser, Pandoc-like AST, Markdown writer, WordPress block writer, and existing focused legacy DOC fixture builders.

## Exclusions

Did not run Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, ZipArchive, TeX/PDF engines, Typst, browser renderers, external converters, external validators, online services, live provider tests, or live-service provider tests. Root harness was not run for this isolated micro-slice.

## Next Task

A useful non-overlapping follow-up would map another bounded legacy DOC field-code family, stylesheet/character formatting handoff, or SummaryInformation property stream metadata into review spans without invoking external office tools.
