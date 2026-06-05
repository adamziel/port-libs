# markerPDF pdftext dictionary Reference anchor current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T055622Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260605T055622Z`
Base accepted HEAD: `faa78576a7e937b1a3569a086f4da2a3cae63756`

## Source Truth

- Upstream markerPDF remains pinned at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(...)` and stores page `blocks`/`refs` before Marker page conversion: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- `pdftext.pdf.links.PageReference` stores internal page references as `idx`, `page`, and `coord`; its `ref` and `url` are computed as `page-{page}-{idx}` and `#{ref}`: https://raw.githubusercontent.com/datalab-to/pdftext/master/pdftext/schema.py and https://raw.githubusercontent.com/datalab-to/pdftext/master/pdftext/pdf/links.py

## Change

`PdfTextBlockConverter::pdftextRefs()` now synthesizes internal pdftext reference anchors for upstream-shaped Reference dictionaries that provide `page` and `idx` but no concrete `url` or `ref` fields.

The converter still fails closed for unsafe supplied `url` values: if a ref row explicitly supplies `javascript:` or another unsafe URL, the URL is omitted instead of being replaced with a synthesized clickable anchor. Private/debug ref payload keys remain excluded from `pdftext_source.refs`.

## Red First

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php`

Result: `FAIL synthesizes pdftext reference anchors from upstream Reference dictionaries`; expected `page-9-2`, actual `NULL`.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php` => `1 test files, 70 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-link-ref-currentbase.php` emitted `upstream_reference_anchor_synthesized=true`, `unsafe_supplied_reference_url_not_synthesized=true`, `pdftext_ref_payload_excluded=true`, and no Python/model/external PDF tool execution.

## Non-Overlap

This does not repeat accepted pdftext span URL promotion, supplied `ref`/`url` preservation, keep-chars sanitation, character font key filtering, bbox normalization, block sorting, blank-page handling, sparse layout/order matching, OCR/table supplied-boundary routing, parser/xref recovery, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, or runtime preflight. The bounded behavior is only anchor synthesis for upstream-shaped pdftext `Reference` dictionaries at the native dictionary-output boundary.

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, safe-URI policy, page-reference metadata sanitation, Markdown/WordPress smoke rendering, and focused PHP tests. Live `pdftext`, pypdfium/PDFium, Surya/Torch/OCR/layout/order models, Texify, tabled-pdf, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.
