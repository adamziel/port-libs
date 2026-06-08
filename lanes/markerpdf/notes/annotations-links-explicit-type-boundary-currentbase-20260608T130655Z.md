# markerpdf annotations-links explicit Type boundary current base

Slice: `markerpdf-annotations-links-boundary-current-base-20260608T130655Z`
Base: `a6a533bea4c4d3662d74d680c80c05f4d7dc212d`

## Source truth

This no-GPU markerPDF slice stays inside the native searchable-PDF parser and WordPress review pipeline. PDF page annotation dictionaries may omit `/Type`, so the importer must not require `/Type /Annot` for every valid link. However, when a page `/Annots` entry points at a dictionary with an explicit non-annotation `/Type`, that dictionary is not a page annotation even if it contains `/Subtype /Link` or a text-markup subtype.

The boundary is especially important for WordPress import because link annotations become clickable Gutenberg span metadata and text-markup annotations become review metadata. A nested or misplaced `/Type /Filespec /Subtype /Link` dictionary must not become a public link, and a `/Type /XObject /Subtype /Highlight` dictionary must not become review metadata.

## Red-first evidence

Before the extractor change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkExplicitTypeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects explicitly non annotation dictionaries before link and markup promotion
Expected: [7, 8, 11]
Actual: [7, 8, 9, 11, 10]
1 test files, 2 assertions, 1 failures
```

Objects `9` and `10` were explicit non-annotation decoys:

- `9 0 obj`: `/Type /Filespec /Subtype /Link` with a URI action.
- `10 0 obj`: `/Type /XObject /Subtype /Highlight` with markup review fields.

## Implementation

`PdfAnnotationExtractor`, `PdfLinkAnnotationExtractor`, and `PdfMarkupAnnotationExtractor` now apply the same collection-time guard before page ownership checks:

- omitted `/Type` is still accepted, preserving type-omitted `/Subtype /Link` annotations;
- `/Type /Annot` is accepted;
- any other explicit resolved `/Type` is rejected before review/link/markup promotion.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkExplicitTypeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects explicitly non annotation dictionaries before link and markup promotion
1 test files, 37 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-annotation-link-explicit-type-boundary-currentbase.php
```

The smoke exits `0` and reports `annotation_objects=[7,8,11]`, `promoted_link_objects=[7,8]`, `filespec_link_excluded=true`, `xobject_markup_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This slice does not repeat prior accepted annotation/link work around escaped annotation keys, escaped page `/Annots`, duplicate annotation keys, tailed operands, `/P` page ownership, object streams, indirect action subtype resolution, primary action arrays/scalars, previous URI replacement, or page generation boundaries. It specifically covers the explicit dictionary `/Type` boundary before page annotation promotion.

## Dependency closure

No new support component is needed. The implementation reuses the existing native PDF dictionary/name parsing helpers already present in the three markerPDF annotation extractors. GPU/OCR/model/PDFium execution remains intentionally out of scope under the current markerPDF no-GPU directive.
