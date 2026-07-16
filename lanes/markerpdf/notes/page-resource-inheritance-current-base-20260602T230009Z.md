# Page Resource Inheritance Current Base, 2026-06-02

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260602T230009Z`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts PDF text through `pdftext.extraction.dictionary_output()` and pypdfium bounded page text before Marker block conversion: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF page-tree source truth: `/Resources` is an inheritable page attribute. If omitted or null on a page, the nearest ancestor resource dictionary applies; an explicit page resource dictionary, including an empty one, overrides the inherited value rather than merging by resource category. Adobe PDF Reference 1.3 table 3.17 records `/Resources` as required/inheritable and describes omitted/null inheritance: https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.3.pdf

## Change

- `PdfTextExtractor` now expands page content `Do` operators against the effective page resource dictionary selected by page-tree inheritance, not only the leaf page dictionary.
- Marked-content replacement dictionaries and optional-content property visibility now use that same single effective page resource dictionary, preserving the already accepted leaf-resource override boundary.
- The focused test covers both sides:
  - a leaf page without `/Resources` inherits parent `/Font`, `/XObject`, and `/Properties`, so an inherited Form XObject and inherited ActualText reach WordPress text extraction;
  - a sibling leaf with its own `/Resources` keeps local `/Font` and `/Properties`, does not merge the parent `/XObject`, and does not inherit the parent ActualText replacement.
- `wordpress-pdf-resource-inheritance-import.php` now emits inherited Form XObject text in addition to inherited page-tree font text.

## Verification

Red-first before source change:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php`
  - failed `uses effective inherited page resources for XObject forms without merging leaf overrides`;
  - actual text omitted `Inherited Form Text`.

Focused verification after source change:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php`
  - `1 test files, 605 assertions, 0 failures`.

Final verification for the handoff is recorded in the worker final response.

## Dependency Closure

No new support component is needed. This reuses the native PDF object inventory, page-tree lineage walker, effective resource dictionary resolver, Form XObject expansion, marked-content replacement parser, optional-content resource parser, stream decoder, and WordPress smoke path. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark tooling, and external OCR/rendering dependencies.

## Non-Overlap

This does not repeat accepted page-tree font inheritance, page `/Contents` non-inheritance, leaf `/Resources` font ToUnicode/width override, indirect `/Kids` traversal, cyclic page-tree guards, Form XObject local resource scoping, optional-content visibility basics, or page-review resource metadata. The new boundary is specifically inherited page resource dictionaries feeding page-level Form XObject lookup and marked/optional content replacement while preserving explicit leaf-resource override semantics.
