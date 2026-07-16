# Page Resource Comment Reference Current Base

Lane: `markerpdf`
Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260605T231328Z`
Base accepted HEAD: `5d88bf01234ea0ed3d4e3dd6fcfdd802280f154c`

## Source Truth

- Upstream markerPDF relies on native PDF object/resource lookup before OCR/model fallback.
- PDF comments are lexical whitespace, so indirect references written as `N % comment EOL G % comment EOL R` must resolve like `N G R`.
- `/Resources` is an inheritable page-tree attribute when omitted or null, while explicit page resources remain leaf-local and do not merge ancestor categories.
- Current no-GPU markerPDF scope applies: no OCR, Surya, Texify, Torch, PDFium/pypdfium2 rendering, browser service, Streamlit/FastAPI model worker, or external PDF tool was run.

## Behavior

`PdfPagePropertyExtractor` now tokenizes indirect references with PDF comments between the object number, generation number, and `R` marker. The same helper is used for standalone resource-wrapper objects, so page-resource metadata follows comment-delimited wrappers before deciding inherited/local ownership.

`PdfTextExtractor` now uses its existing comment-aware indirect-reference tokenizer when resolving resource-wrapper objects, so the converter path can use the same comment-delimited inherited resource dictionaries for visible text and Form XObject lookup.

The focused fixture proves:

- an ancestor `/Pages /Resources 10 %... 0 %... R` is inherited by a page that omits `/Resources`;
- a leaf `/Page /Resources 20 %... 0 %... R` stays local and does not merge ancestor resources;
- a `/Resources 12 0 R` wrapper object whose body is `10 %... 0 %... R` resolves before inherited lookup;
- stale private `/PieceInfo` resources and stale form names stay excluded from visible WordPress text and page-review metadata.

## Evidence

Red-first current-base command:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceCommentReferenceCurrentBaseTest.php
```

Before the property extractor change, the direct comment-split resource reference rendered text but page metadata did not resolve the inherited resource object:

```text
Expected: 10
Actual: NULL
```

Before the text extractor wrapper change, the comment-split resource wrapper metadata resolved but visible text fell back to the raw glyph:

```text
Expected: ['Comment wrapper inherited font text', 'Comment wrapper inherited form text']
Actual: ['A']
```

Verification after the fix:

```text
php -l lanes/markerpdf/src/PdfPagePropertyExtractor.php
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfPageResourceCommentReferenceCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-resource-comment-reference-currentbase.php
```

All reported no syntax errors.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceCommentReferenceCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats PDF comments as whitespace inside page Resources references before inherited lookup
PASS resolves comment-delimited page Resources wrapper objects before inherited lookup

1 test files, 35 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfPageResource.*CurrentBaseTest\.php$' | sort)
Focused test run: 17 selected test files (root lock skipped)
17 test files, 523 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-comment-reference-currentbase.php
```

The smoke exits 0 and emits `direct_resource_comment_split_resolved=true`, `local_resource_comment_split_resolved=true`, `wrapper_resource_comment_split_resolved=true`, `wrapper_stale_private_resource_excluded=true`, and all Python/model/external-tool execution flags false.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused markerPDF PHP pass count: `2266 -> 2268`.
- Added one WordPress page-resource comment-reference smoke scenario.
- No dashboard/root coordination files were edited.

## Non-Overlap

This does not repeat accepted page-resource null/empty inheritance, generation-exact resource references, escaped `/Kids` or `/Type` lineage, parent/kids catalog boundaries, malformed resource operands, stream resource rejection, category stream rejection, ProcSet metadata, optional-content wrappers, form resource null/malformed handling, duplicate `/Resources` precedence, AcroForm comment-reference parsing, Type3 CharProc comment-reference parsing, xref repair, stream filters, image metadata, CMaps, outlines, attachments, annotations, forms, runtime planners, or OCR/model behavior. The bounded behavior is only comment-as-whitespace parsing for page `/Resources` indirect references and resource-wrapper objects.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object table, dictionary/value scanners, PDF comment skipping, generation-valid reference resolution, page-tree resource inheritance, text extractor resource lookup, and the lane-local WordPress smoke path. Full upstream live OCR/model/rendering parity remains intentionally out of scope under the current no-GPU markerPDF direction.
