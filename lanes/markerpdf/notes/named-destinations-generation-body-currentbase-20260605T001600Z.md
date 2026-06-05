# markerPDF Named Destination Generation Body Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T001600Z`
Session: `port-dev-markerpdf-named-destinations-20260605T001600Z`
Base accepted HEAD: `f80a0fb055bdf9c94c78bd667269631893b15fb7`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` obtains PDF text blocks and TOC metadata through `marker/pdf/extract_text.py::get_text_blocks()` and `marker/cleaners/toc.py::get_pdf_toc()`, before OCR/model stages.
- PDF name dictionaries can include `/Dests` name trees whose values are destination arrays or destination dictionaries. Destination operands are indirect references of the form `object generation R`, so the native PHP parser must bind object bodies and page-tree leaves by exact generation.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, Python model worker, pypdfium/PDFium, browser, or external PDF tool execution.

## Red Evidence

Before the patch, a manual current-base reproduction with:

- `/Names /Dests` entry `(current) 20 1 R`;
- valid destination dictionary `20 1 obj << /D [4 1 R /FitH 640] >>`;
- valid page-tree leaf `4 1 R`;
- later stale same-number bodies `20 0 obj` and `4 0 obj`;

returned only the stale generation-zero destination body and lost the current generation-one row:

```text
array (
  0 =>
  array (
    'name' => 'stale',
    'page' => 0,
    'page_object_id' => 3,
    'fit' => 'FitH',
    'coordinates' =>
    array (
      'top' => 100.0,
    ),
    'source' => 'names-tree',
  ),
)
```

## Implementation

- `PdfNamedDestinationExtractor` now stores direct object bodies by object number and generation.
- Indirect reference resolution uses the exact referenced generation for destination dictionaries, indirect name strings, page-tree `/Kids`, and destination page operands.
- Page-tree indexes are keyed by `object:generation`, preventing a stale page generation from inheriting the current page index.
- Existing fallback scanning still preserves the previous selected-body behavior for direct unreferenced object discovery.

## Verification

```text
php -l lanes/markerpdf/src/PdfNamedDestinationExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfNamedDestinationExtractor.php

php -l lanes/markerpdf/tests/PdfNamedDestinationGenerationBodyCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfNamedDestinationGenerationBodyCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-named-destination-generation-body-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-named-destination-generation-body-currentbase.php
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationGenerationBodyCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses exact-generation destination dictionaries before stale same-number bodies
PASS filters stale page generations from WordPress named-destination review rows

1 test files, 15 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php lanes/markerpdf/tests/PdfNamedDestinationLimitsFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationGenerationBodyCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
11 PASS cases
3 test files, 77 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-generation-body-currentbase.php
```

Emitted `destination_count=3`, `destination_names=["Current Review","Direct Current Page","LegacyCurrent"]`, `generation_specific_destination_body_selected=true`, `stale_page_generation_filtered=true`, `stale_same_number_body_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1171 -> 1173`.
- WordPress scenarios: `1157 -> 1158`.
- `pdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- `mappedPdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, generation-exact reference resolver, name-tree walker, page-tree indexer, destination normalizer, and WordPress smoke path. Full upstream parity remains dependency-gated by live `pdftext`, `pypdfium2`/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, and benchmark tooling; none were executed here.

## Non-Overlap

This does not repeat accepted named-destination `/Limits` pruning, malformed child-limit fallback, PDFDocEncoding key decoding, simple generation mismatch rejection, Fit/XYZ operand normalization, outline action destination resolution, metadata catalog destination summaries, xref `/Prev` repair, encrypted PDF preflight, or runtime conversion work. The bounded behavior is specifically resolving exact-generation object bodies and page-tree leaves when stale same-number generation bodies are present in the standalone named-destination extractor.
