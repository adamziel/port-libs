# markerPDF Named Destination Generation Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260604T220742Z`
Session: `port-dev-markerpdf-named-destinations-20260604T220742Z`
Base accepted HEAD: `231d3efa92cc0a29adf08866a9cc60e2e946e9b6`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` imports searchable PDF structure at the native pdftext/PDF parser boundary before any model handoff.
- PDF indirect references are generation-bearing `object generation R` operands. Named-destination review should resolve `/Names /Dests` name-tree children, indirect name strings, destination dictionaries, destination page references, and legacy `/Dests` rows only when the referenced generation matches the selected object.
- This slice stays in the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, Python model worker, browser, or external PDF tool execution.

## Implementation

- `PdfNamedDestinationExtractor` now preserves the generation component while parsing `N G R` references.
- Reference resolution fails closed when the available object generation does not match the reference generation.
- Name-tree traversal skips mismatched-generation `/Kids`; indirect name strings and destination dictionaries with mismatched generations are ignored; destination arrays with mismatched-generation page refs are rejected; legacy `/Dests` rows with mismatched indirect destination dictionaries are skipped.
- Matching nonzero-generation references still resolve normally, preserving current valid PDFs.
- The WordPress named-destination smoke now proves out-of-limits and mismatched-generation rows are both excluded before import metadata is emitted.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL honors indirect object generations before WordPress named destination import
Expected names: CurrentDirect, IndirectCurrent, ReviewOk, LegacyOk
Actual included stale rows: MismatchedName, BadPageGen, BadDestDictGen, WrongKidGen, LegacyStale
1 test files, 29 assertions, 1 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfNamedDestinationExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfNamedDestinationExtractor.php
```

```text
php -l lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php
```

```text
php -l lanes/markerpdf/examples/wordpress-pdf-named-destinations-import.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-named-destinations-import.php
```

```text
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
json ok
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
6 PASS cases
1 test files, 40 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destinations-import.php
Emits named_destinations ["migration-start","media-cleanup","review-summary","legacy-review"], generation_mismatch_destinations_filtered=true, executes_python_or_models=false, executes_external_pdf_tools=false.
```

```text
git diff --check -- lanes/markerpdf
passed
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1095 -> 1096`.
- WordPress scenarios: `1095 -> 1096`.
- `pdfNamedDestinationExtractorCurrentBaseBehaviors`: `2 -> 3`.
- `mappedPdfNamedDestinationExtractorCurrentBaseBehaviors`: `2 -> 3`.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, name-tree parser, destination normalizer, PDF text-string/name decoding, and existing WordPress review metadata smoke. Full upstream model parity remains intentionally out of scope under the no-GPU markerPDF directive.

## Non-Overlap

This does not repeat accepted named-destination `/Limits` pruning, Fit/XYZ operand normalization, indirect name-tree destination parsing, outline destination action/page-review enrichment, xref-stream generation repair, metadata xref/current-trailer handling, or runtime conversion preflight work. The new boundary is specifically generation-exact indirect reference handling inside the standalone catalog named-destination extractor.
