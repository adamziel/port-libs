# Pdftext Dictionary JSON Object Boundary

Slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260606T022219Z`
Base accepted HEAD: `b3073538ae316638d17d31f0bf6aebce71759cd8`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` uses `pdftext.extraction.dictionary_output` as the text-layer handoff. Upstream pdftext documents structured JSON output as page dictionaries with `bbox`, `rotation`, `page`, and `blocks`; nested block, line, span, and optional character dictionaries carry bbox/text/font/link metadata. PHP callers commonly receive that output through `json_decode()` where dictionaries are `stdClass` objects by default.

## Behavior

`PdfTextDocumentExtractor` now normalizes supplied `stdClass` pdftext dictionaries recursively before the existing core whitelist and validation run.

Before this slice, default JSON-decoded pdftext pages were rejected with `Supplied pdftext page entries must be arrays.` before link safety checks, ref redaction, and WordPress Markdown rendering. The new boundary accepts those JSON object dictionaries while preserving the existing sanitizer: safe URLs can be promoted, `javascript:` span URLs remain review-only, page refs are reduced to core metadata, and hidden adapter/ref/action payloads do not cross into document output.

## Red-First Evidence

Before source edits, after adding the focused assertion:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
FAIL accepts json decoded pdftext dictionaries at the core boundary
Supplied pdftext page entries must be arrays.
1 test files, 198 assertions, 1 failures
```

After source/test/example edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
1 test files, 206 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-json-object-boundary-currentbase.php
```

The smoke reports `safe_link_promoted=true`, `unsafe_link_review_only=true`, `json_adapter_payload_excluded=true`, `refs=[{"url":"#page-3-xy","page":3,"dest_pos":[72,96]}]`, and `executes_python_or_models=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted dictionary link/ref safety, keep-chars character rows, normalized bbox scaling, source page/dimension/rotation validation, sort, blank-page, layout-order, Unicode repair, metadata, xref, image, annotation, form, security, or OCR/model slices. The bounded behavior is specifically default PHP JSON-decoded `stdClass` pdftext dictionaries at the native dictionary core boundary.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary sanitizer, page/block/span converter, Markdown postprocessor, and WordPress smoke path. Live OCR, Surya/Texify/Torch, pypdfium/PDFium, PIL, Streamlit/FastAPI model workers, and external PDF tools remain intentionally out of scope for this markerPDF lane.
