# markerPDF Named Destinations Indirect View Operands

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T004819Z`
Session: `port-dev-markerpdf-named-destinations-20260605T004819Z`
Base accepted HEAD: `e25ad1f2ac6eaccdea2f6b6dc8a510504a91892b`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries PDF destination/navigation metadata through the native PDF parsing boundary before OCR/model handoff.
- PDF explicit destination arrays may contain indirect operands for the view mode name and view parameters. WordPress named-destination metadata should resolve those object references the same way the richer outline destination-view path already does.
- This remains in the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, Python model worker, browser, pypdfium/PIL, or external PDF tool execution.

## Implementation

- `PdfNamedDestinationExtractor` now resolves indirect view-mode operands before accepting `/Fit*` and `/XYZ` named-destination rows.
- Numeric view parameters are resolved through the same exact-generation object resolver before `coordinates` are emitted.
- Page operands still use the original page reference for generation-aware page-index validation, preserving the existing stale-generation rejection behavior.
- The WordPress named-destination smoke now includes a `section-indirect-review` destination whose `/FitH` and `top` coordinate are stored in indirect objects.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves indirect destination view operands before WordPress named destination metadata
Expected names: Indirect FitH, Indirect XYZ, Indirect FitR
Actual names: []
1 test files, 47 assertions, 1 failures
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
php -r '$files=["lanes/markerpdf/lane-status.json","lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " OK\n"; }'
lanes/markerpdf/lane-status.json OK
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json OK
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php lanes/markerpdf/tests/PdfNamedDestinationGenerationBodyCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationLimitsFallbackCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
12 PASS cases
3 test files, 84 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destinations-import.php
Emits destination_count=6, indirect_destination_operands_resolved=true, pdfdocencoded_destination_name_decoded=true, out_of_limits_destination_filtered=true, generation_mismatch_destinations_filtered=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

```text
git diff --check -- lanes/markerpdf
passed
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1205 -> 1206`.
- WordPress scenarios: `1185 -> 1186`.
- `pdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- `mappedPdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- Focused named-destination family assertions: `77 -> 84`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, name-tree walker, exact-generation resolver, destination normalizer, and WordPress smoke renderer. Full upstream model parity remains intentionally out of scope under the no-GPU markerPDF directive.

## Non-Overlap

This does not repeat accepted named-destination `/Limits` pruning, malformed child-limit fallback, PDFDocEncoding key decoding, generation-exact object body selection, Fit/XYZ direct operand normalization, outline destination action/page-review enrichment, xref repair, font/text geometry, metadata, or runtime conversion preflight work. The bounded new behavior is specifically indirect view-mode and numeric coordinate operands inside standalone catalog named-destination arrays.
