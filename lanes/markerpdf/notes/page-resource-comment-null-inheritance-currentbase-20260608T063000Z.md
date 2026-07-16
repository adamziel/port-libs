# markerPDF page-resource comment-null inheritance current-base

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260608T063000Z`
Session: `port-dev-markerpdf-resource-inherit-20260608T063000Z`
Base accepted HEAD: `438528b634afbda7e3bf494e099a7c4adfc51318`

## Source Truth

Upstream markerPDF consumes searchable PDF parser output before OCR/model stages. At this native no-GPU PDF parser boundary, `/Resources` is an inheritable page-tree attribute, and PDF comments are whitespace. A page-local `/Resources` reference whose exact indirect object body is comments plus a single `null` token should behave like `/Resources null`: it does not define local resources and must continue ancestor lookup.

## Behavior

- `PdfTextExtractor` now treats exact-generation resource object bodies containing only PDF comments and `null` as inheritable null resource values.
- `PdfPagePropertyExtractor` uses the same comment-aware null detection before reporting page-boundary resource provenance.
- Parent page-tree Font, XObject, and Properties resources now drive text extraction, Form XObject expansion, marked-content ActualText replacement, and page review metadata when a leaf page points `/Resources` at a comment-wrapped null object.
- The WordPress smoke confirms physical glyph fallback text, resource names, CMap names, Python/model execution, and external PDF tools stay out of the output.

## Red-First Evidence

Before source edits, a local focused probe against the current worktree produced only direct page text and malformed resource metadata:

```text
extractTextLines => ['Comment Null Page Text']
resources.status => unresolved_or_malformed
resources.resource_owner_object => 3
resources.resource_object => 11
```

The inherited Form XObject text was missing because the page-local comment-wrapped null resource object blocked parent resource lookup.

## Verification

Focused regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceCommentNullInheritanceCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS inherits ancestor resources through comment-wrapped null page Resources objects
1 test files, 22 assertions, 0 failures
```

Adjacent page-resource family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*CurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
Focused test run: 43 selected test files (root lock skipped)
43 test files, 1250 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-comment-null-inheritance-currentbase.php
```

The smoke exits 0 and emits `comment_wrapped_null_resources_inherit=true`, `resource_lookup_objects=[3,2]`, `font_names=[F1]`, `xobject_names=[ParentForm]`, `properties_names=[NullActual]`, `physical_glyph_text_excluded=true`, `resource_names_excluded_from_paragraphs=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct `/Resources null`, indirect null resource wrappers without comments, null-byte whitespace references, comment-delimited resource references, malformed page `/Resources` fail-closed handling, direct or indirect resource dictionary tail rejection, duplicate `/Resources`, escaped `/Kids`, parent/Kids generation checks, resource category wrappers, stream-valued category rejection, ProcSet review metadata, image XObject inheritance review, Form XObject null-resource inheritance, page `/Contents` non-inheritance, xref repair, metadata, forms, annotations, tables, or OCR/model handoffs. The bounded behavior is only exact indirect page `/Resources` object bodies where PDF comments surround a single `null` token before ancestor page-resource inheritance.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, generation-exact resource reference resolver, page-tree resource inheritance resolver, page-boundary metadata extractor, content stream text parser, CMap/font lookup, Form XObject expansion, marked-content Properties handling, and WordPress smoke harness. Live OCR, PDFium rendering, Surya/Texify/Torch model execution, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.
