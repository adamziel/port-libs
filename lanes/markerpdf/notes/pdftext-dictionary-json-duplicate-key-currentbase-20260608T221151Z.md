# markerPDF pdftext dictionary raw JSON duplicate-key current-base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260608T221151Z`

Accepted base: `b5a355b21ceda7875c2975dde96ac65abe5fde9b`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` consumes the ordered page dictionaries returned by `pdftext.extraction.dictionary_output(...)` in `marker/pdf/extract_text.py::get_text_blocks()`.
- Native PHP adapters may cache that `pdftext`/`dictionary_output` page-list payload as raw JSON. A JSON object with duplicate page-map keys is ambiguous because the decoder keeps the last member, so the native boundary must fail closed before stale or replacement page text reaches WordPress paragraphs.
- This slice stays inside the no-GPU markerPDF scope: it does not run live `pdftext`, pypdfium/PDFium, Surya/OCR/layout/table models, Texify, Torch, multiprocessing, or external PDF tools.

## Implementation

- `PdfTextDocumentExtractor::decodeSuppliedDictionaryJsonEnvelope()` now confirms valid JSON envelopes have unique decoded object keys before `stdClass`/array normalization.
- The duplicate-key scan recurses through JSON objects and arrays, decodes string keys so escaped aliases such as `"17"` and `"\u0031\u0037"` compare equal, and throws `InvalidArgumentException` at the supplied pdftext cache boundary.
- Unique raw JSON page maps still import through the existing ordered page-list path.

## Verification

Red-first focused check before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreJsonDuplicateKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects exact duplicate raw JSON pdftext page-map keys before stale adapter pages
FAIL rejects exact duplicate raw JSON one-page entry envelopes before stale wrapper text
PASS continues importing unique raw JSON pdftext page maps at the core boundary

1 test files, 15 assertions, 2 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreJsonDuplicateKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects exact duplicate raw JSON pdftext page-map keys before stale adapter pages
PASS rejects exact duplicate raw JSON one-page entry envelopes before stale wrapper text
PASS continues importing unique raw JSON pdftext page maps at the core boundary

1 test files, 15 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-json-duplicate-key-currentbase.php
```

Expected smoke flags: `duplicate_json_page_keys_rejected=true`, `unique_json_page_map_imported=true`, `duplicate_stale_payload_excluded=true`, `raw_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, JSON cache envelope normalization, Markdown postprocessor, and WordPress smoke path. Live PDF text extraction and model/OCR execution remain intentionally out of scope for this no-GPU markerPDF lane.

## Non-Overlap

This does not repeat accepted BOM JSON envelope handling, per-page JSON list-entry unwraps, nested pages/page_map/pageMap envelopes, normalized duplicate numeric page-map key detection, overflow/negative page-map key validation, layout/order page-map precedence, parser/xref repair, font/CMap/width behavior, image/filter metadata, annotations/forms/security review, or supplied table/equation handoffs. The bounded behavior is raw JSON duplicate object-key detection before pdftext dictionary page-list normalization.
