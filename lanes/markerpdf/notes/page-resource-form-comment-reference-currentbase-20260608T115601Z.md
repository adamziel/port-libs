# markerPDF page resource form comment-reference current base

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260608T115601Z`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF extraction through page-scoped native text extraction before OCR/model fallback: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- In the no-GPU PHP boundary, page `/Resources` is inherited through the page tree, while Form XObject `/Resources` can be explicit, omitted, or null. PDF comments are lexical whitespace, so comments between indirect-reference operands must not change resource scope.

## Change

- `PdfTextExtractor::pageResourceDictionaryResolution()` and `resourceDictionaryBody()` now resolve indirect `/Resources` operands through the shared comment-aware indirect-reference parser instead of a compact regex.
- The focused fixture covers a Form XObject with a comment-delimited explicit local `/Resources` reference and a sibling Form XObject whose `/Resources` resolves through a comment-delimited wrapper to `null`, causing it to inherit the invoking page's font and nested XObject resources.
- The WordPress smoke emits four Gutenberg paragraphs and records native-only flags proving no Python, model, OCR, raster, or external PDF tool path ran.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceFormCommentReferenceCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves comment-delimited Form XObject Resources and inherits through null wrappers

1 test files, 18 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-form-comment-reference-currentbase.php
```

The smoke exits 0 and emits:

- `form_comment_resource_reference_resolved=true`
- `form_null_wrapper_inherits_page_resources=true`
- `nested_form_inherits_invoking_page_resources=true`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

## Dependency Closure

No new support component is needed. This reuses the native object table, PDF comment-aware tokenization, exact resource-object generation lookup, Form XObject expansion, inherited page resource dictionary resolution, CMap/font maps, and WordPress smoke path. OCR/model execution, PDFium rendering, live upstream model parity, decryption/password validation, JavaScript/action execution, and external PDF tools remain intentionally out of scope for this markerPDF lane.

## Non-Overlap

This does not repeat accepted page-tree inherited resources, page `/Resources` comment-delimited references, resource-wrapper objects, direct/null page resources, Form XObject omitted/null resource inheritance, malformed Form resource blocking, image XObject inheritance, Type3 resource fallback, or encrypted preflight. The bounded behavior is specifically Form XObject `/Resources` reference tokenization and null-wrapper inheritance at the invoking page resource boundary.
