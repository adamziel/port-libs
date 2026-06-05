# markerPDF Named Destination Kid Generation Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T022818Z`
Session: `port-dev-markerpdf-named-destinations-20260605T022818Z`
Base accepted HEAD: `314b4b94d04b24d343511693d1f213bac248820d`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` gets PDF text blocks and TOC/navigation metadata through `marker/pdf/extract_text.py::get_text_blocks()` and the underlying PDF stack before OCR/model stages.
- PDF indirect references include an object number and generation. A name-tree `/Kids` traversal guard must therefore treat `9 0 R` and `9 1 R` as distinct nodes while still stopping exact `object:generation` cycles.
- This slice stays in the native no-GPU scope: no OCR, Surya, Texify, Torch, Python model worker, PDFium/pypdfium, browser, or external PDF tool execution.

## Red Evidence

Before the extractor edit, the focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationKidGenerationBoundaryCurrentBaseTest.php
```

failed with:

```text
Expected: ["Current Review","Summary Review","LegacyFallback"]
Actual: ["Summary Review","LegacyFallback"]

1 test files, 6 assertions, 1 failures
```

The failing fixture had a catalog `/Names /Dests` root whose `/Kids` array pointed to `9 0 R`, and that generation-zero node pointed to child `9 1 R`. The old traversal cycle guard stored only object number `9`, so the valid generation-one child was skipped.

## Implementation

- `PdfNamedDestinationExtractor::collectNameTreeEntries()` now records seen name-tree nodes by `object:generation` instead of object number only.
- Destination normalization, duplicate-name precedence, `/Limits` pruning, and legacy `/Dests` fallback behavior are unchanged.
- The WordPress smoke emits the resolved named-destination review list with the nested generation-one child present and destination labels excluded from visible text.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationKidGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps nested same-object name-tree Kids distinct by generation before WordPress named destination review
PASS keeps same-number name-tree generation labels out of visible WordPress text

1 test files, 13 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestination*Test.php
Focused test run: 6 selected test files (root lock skipped)
19 PASS cases
6 test files, 139 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-kid-generation-boundary-currentbase.php
```

The smoke emitted `nested_generation_kid_resolved=true`, `sibling_destination_preserved=true`, `legacy_fallback_preserved=true`, `destination_labels_excluded_from_visible_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1299 -> 1301`.
- WordPress scenarios: `1256 -> 1257`.
- `pdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- `mappedPdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, generation-exact reference resolver, name-tree walker, page-tree indexer, destination normalizer, text extractor, and WordPress smoke path. Full upstream parity remains dependency-gated by live `pdftext`, `pypdfium2`/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, and benchmark tooling; none were executed here.

## Non-Overlap

This does not repeat accepted named-destination `/Limits` pruning, malformed child-limit fallback, PDFDocEncoding key decoding, simple generation mismatch rejection, exact-generation destination body/page-ref selection, Fit/XYZ operand normalization, trailer-root catalog selection, outline action destination resolution, metadata catalog destination summaries, xref repair, encrypted PDF preflight, font-width behavior, or runtime conversion work. The bounded behavior is specifically generation-aware name-tree `/Kids` traversal for nested same-object references.
