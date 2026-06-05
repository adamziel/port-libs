# Named Destination Alias Boundary - 2026-06-05

Slice: `markerpdf-named-destinations-boundary-current-base-20260605T180328Z`  
Base accepted HEAD: `2c19a701a31b0f790d90d0420fa2b95cd56a6265`

## Source Truth

Upstream markerPDF keeps PDF navigation metadata as parser/review data before OCR or model stages. This slice stays inside the native searchable-PDF boundary: catalog `/Names /Dests`, legacy catalog `/Dests`, outline destination lookup, page-tree indices, and visible-text exclusion. No Python, OCR, Surya, Texify, Torch, pypdfium, PIL, or external PDF tools are required for this boundary.

The local source already handled explicit named-destination arrays, page-only destinations, `/Limits`, generation-exact kids, object-stream/xref selection, and duplicate name-tree rows. The missing current-base behavior was alias values: a named destination can point at another named destination through a PDF string, PDF name, or local GoTo dictionary `/D` value.

## Behavior Added

- `PdfNamedDestinationExtractor` now resolves valid alias destinations while preserving the alias row's original name and source.
- Alias targets can cross from `/Names /Dests` into legacy `/Dests`.
- Missing aliases and cyclic alias chains fail closed and are kept out of destination review metadata.
- Alias names remain review-only metadata and do not leak into native visible text extraction.
- `PdfOutlineExtractor` now validates name-tree destination rows with page indexes while collecting outline maps, preserving the last valid duplicate target when a later malformed duplicate row appears.

## Red-First Evidence

Initial focused command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationAliasBoundaryCurrentBaseTest.php
```

Result before implementation:

```text
1 test files, 22 assertions, 1 failures
```

The extractor emitted only `Actual Target` and `LegacyTarget`; `String Alias`, `Name Alias`, `Action Alias`, `Names To Legacy`, and `LegacyAlias` were missing.

## Verification

Focused alias test after implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationAliasBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 36 assertions, 0 failures
```

Focused alias plus duplicate-boundary guard:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationAliasBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationDuplicateKeyBoundaryCurrentBaseTest.php
```

Result:

```text
2 test files, 75 assertions, 0 failures
```

Adjacent named-destination family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php lanes/markerpdf/tests/PdfNamedDestinationActionDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationActionViewModeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationAliasBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationByteLimitsActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationByteStringLimitsCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationCoordinateBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationGenerationBodyCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationIndirectArraysCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationIndirectPageIndexBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationIntermediateLimitsCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationInternalLeafBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationInternalNodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationKidGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationKidsReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationLimitsFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationNameKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationNameTreeKeyActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationPageOnlyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationPageOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationStreamKeywordBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationViewModeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationXrefOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationXrefStreamPrevCurrentBaseTest.php
```

Result:

```text
27 test files, 763 assertions, 0 failures
```

Syntax and JSON checks:

```bash
php -l lanes/markerpdf/src/PdfNamedDestinationExtractor.php
php -l lanes/markerpdf/src/PdfOutlineExtractor.php
php -l lanes/markerpdf/tests/PdfNamedDestinationAliasBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-named-destination-alias-boundary-currentbase.php
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'
```

Result: all PHP files reported no syntax errors, and both JSON files decoded successfully.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-named-destination-alias-boundary-currentbase.php
```

Result: emitted non-empty Gutenberg list output and review comment with `string_alias_resolved=true`, `name_alias_resolved=true`, `goto_dictionary_alias_resolved=true`, `cross_source_alias_resolved=true`, `legacy_alias_resolved=true`, `missing_and_cyclic_aliases_excluded=true`, `visible_text_excludes_destination_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2129 -> 2131`.
- `lane-status.json` `wordpressScenarios`: `1835 -> 1836`.
- `UPSTREAM_TEST_MANIFEST.json` `pdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- No benchmark denominator change.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF object parser, object reference resolver, name-tree walker, page-tree indexer, text extractor, metadata extractor, outline extractor, and lane-local WordPress smoke path. GPU/model/OCR execution, external PDF tools, and live services remain intentionally out of scope.

## Non-Overlap

This does not repeat accepted named-destination `/Limits`, byte-limit, coordinate, page-only, page-operand, view-mode, action-dictionary, duplicate-key, generation, kid-generation, kids-reference, object-stream, xref-offset, xref-stream `/Prev`, trailer-root, stream-keyword, or PDF-name-key rejection slices. The new behavior is bounded to alias resolution and the adjacent outline map validation needed to preserve existing duplicate-boundary behavior.
