# markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260606T230606Z

## Scope

This isolated markerPDF slice covers one native no-GPU supplied-boundary behavior:
searchable-PDF adapters may provide one selected layout/order artifact whose model
payload is stored directly under a `pdftext` envelope after page-range trimming.
`LayoutAnnotator` and `LayoutOrderer` now treat that shape like the existing
single direct `pages` / `dictionary_output` payload envelopes, while preserving
the fail-closed multi-dictionary behavior and trusted outer page metadata.

Source truth remains markerPDF's upstream handoff shape around
`marker/pdf/extract_text.py::get_text_blocks`, `marker/layout/layout.py::surya_layout`,
and `marker/layout/order.py::surya_order`: pdftext pages are selected first, then
layout/order artifacts are zipped to selected pages. This port reuses supplied
layout/order artifacts and does not execute Surya, OCR, Texify, Torch, Python,
CUDA, multiprocessing, shell commands, or external PDF tools.

## Red-First Evidence

Before the source fix, the new focused cases failed because direct `pdftext`
geometry was ignored:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
FAIL unwraps direct pdftext order payload envelopes after selected page assignment
Expected: First direct pdftext order column, Second direct pdftext order column
Actual: Second direct pdftext order column, First direct pdftext order column
FAIL unwraps direct pdftext layout and order payload envelopes for WordPress imports
String does not contain '# First Converter Direct Pdftext Payload Heading.'
1 test files, 658 assertions, 2 failures
```

## Passing Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
1 test files, 678 assertions, 0 failures
```

The slice adds 2 focused PASS cases and 32 focused assertions over the boundary
test's previous local coverage.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-direct-pdftext-payload-currentbase.php
```

The smoke reports:

- `layout_direct_pdftext_payload_unwrapped=true`
- `order_direct_pdftext_payload_unwrapped=true`
- `heading_before_body=true`
- `payload_excluded=true`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

## Non-Overlap

This does not repeat prior page-list `pdftext.pages` envelope selection,
typed `layout_result` / `order_result` wrapper handling, direct `pages` /
`dictionary_output` envelopes, page-range marker behavior, non-finite geometry,
inline image Decode, or any model/OCR/GPU slice. The behavior is limited to the
single direct `pdftext` payload envelope path inside the supplied layout/order
sanitizers.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
pdftext dictionary, selected-page artifact selector, supplied layout/order, and
WordPress conversion scaffolding.

## Next Task

Continue with non-overlapping native searchable-PDF behavior: fonts/CMaps,
stream filters, xref repair, metadata, annotations/forms, page geometry,
image/filter metadata, or supplied-boundary table/equation handoffs.
