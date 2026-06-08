# markerPDF pdftext dictionary ref dedupe boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260608T075056Z`

## Source Truth

- Upstream `sddai/markerPDF` is pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(...)`, then enumerates only the returned page dictionaries into Marker page/span structures: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>.
- Upstream `pdftext` link/reference handling creates page reference rows through `PageReference.add_ref(page, coord)`, which reuses an existing reference when the target page and coordinate already match instead of creating duplicate anchors: <https://raw.githubusercontent.com/datalab-to/pdftext/master/pdftext/schema.py>.

## Implemented Behavior

- `PdfTextBlockConverter::pdftextRefs()` now de-duplicates upstream-shaped page-reference rows by target page and coordinate before adding them to `pdftext_source.refs`.
- The first accepted reference keeps its synthesized `#page-N-idx` anchor. Later stale duplicate rows cannot replace it with unsafe URLs, stale `ref` values, or private payload metadata.
- External/non-coordinate ref rows remain preserved as review metadata, so the boundary only normalizes pdftext `PageReference`-shaped internal anchors.
- Added a WordPress smoke proving duplicate internal refs are collapsed while external review refs and visible text remain intact without Python, pdftext runtime, models, or external PDF tools.

## Verification

Red before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryRefDuplicateCoordinateBoundaryCurrentBaseTest.php
=> 1 test files, 3 assertions, 2 failures
```

Green after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryRefDuplicateCoordinateBoundaryCurrentBaseTest.php
=> 1 test files, 11 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryRefDuplicateCoordinateBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
=> 4 test files, 808 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-ref-dedupe-currentbase.php
=> duplicate_coordinate_refs_deduplicated=true; external_ref_preserved=true; stale_unsafe_duplicate_excluded=true; executes_python_or_models=false; executes_external_pdf_tools=false
```

Focused delta: +2 focused PASS cases and +11 focused assertions.

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, page-source ref sanitation, safe-URI review policy, Markdown block merging, and the WordPress smoke path. Live `pdftext`, pypdfium/PDFium, Surya/Torch/OCR/layout/order models, Texify, tabled-pdf, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.

## Non-Overlap

This does not repeat accepted dictionary_output envelope unwrapping, malformed `pdftext` envelope rejection, direct page wrapping, JSON object normalization, disable-links behavior, keep-chars validation, font flags, character indexes, Unicode repair, normalized bbox scaling, layout/order artifact matching, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations, forms, security preflight, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is duplicate internal pdftext page-reference rows at the core native conversion boundary.
