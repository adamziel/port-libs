## Page Resource Indirect Null Current Base

Slice: `markerpdf-page-resource-inheritance-current-base-20260605T002807Z`

Accepted base: `a3a1253c64da4206cd42417144520cca3b0fe590`

## Source Truth

- PDF page `/Resources` is an inheritable page-tree attribute. A missing or null page resource value inherits from the nearest ancestor page-tree dictionary; an explicit dictionary, including an empty dictionary, is authoritative and does not merge parent resource categories.
- The native no-GPU markerPDF boundary maps this parser behavior before pdftext/PDFium page text extraction: visible text, marked content, and Form XObject lookup must use the effective page resource dictionary without falling back to stale global streams or nested private dictionaries.

## Implementation

- `PdfTextExtractor::pageResourceDictionaryResolution()` now treats an indirect resource object whose current xref-selected body is `null` as an inheritable null instead of a malformed dictionary.
- `PdfPagePropertyExtractor::effectivePageResourcesMetadata()` applies the same indirect-null inheritance rule, so WordPress page-boundary review reports the inherited resource owner/object instead of a false malformed resource row.
- Explicit indirect empty resource dictionaries remain explicit resource dictionaries and still block parent `/Font` and `/XObject` lookup.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
FAIL inherits ancestor resources through indirect null page Resources but keeps indirect empty dictionaries explicit
Expected: ['Indirect null inherited font text', 'Indirect null inherited form text']
Actual: ['A']
```

After the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
1 test files, 41 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-indirect-null-currentbase.php
```

The smoke emits `indirect_null_resources_inherit_parent=true`, `inherited_font_decoded=true`, `inherited_xobject_expanded_once=true`, `explicit_empty_resources_block_parent_xobject=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, xref-selected object bodies, page-tree lineage traversal, stream decoder, Type0 CMap text mapping, Form XObject expansion, and page-boundary review metadata. GPU/model OCR, Surya, Texify, Torch, PDFium rendering, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF lane.

## Non-Overlap

This does not repeat accepted parent page `/Resources` font inheritance, top-level `/Resources null`, leaf resource override, malformed missing resource-reference fail-closed behavior, inherited Form XObject lookup, Form omitted-`/Resources` fallback, page `/Contents` non-inheritance, nested private resource exclusion, optional-content visibility, or page-boundary resource category metadata. The new boundary is specifically indirect object `null` values used as page `/Resources`, while indirect empty dictionaries remain explicit.
