# markerPDF AcroForm Parent Without Kids Boundary

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T145540Z`

Base accepted HEAD: `6fb41b960e3eb9591894e3407a7c54adffc9ab61`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` consumes searchable PDF text and metadata before OCR/layout/model stages. The native no-GPU PHP lane owns the parser boundary for PDF catalog `/AcroForm`, field dictionaries, and page-owned Widget annotations before WordPress import review.

For split AcroForm widgets, a page annotation Widget can carry `/Parent` pointing at the terminal field dictionary. Some PDFs omit the reverse `/Kids` pointer on that parent field. Native review should recover that page-owned field when `/Parent` is explicit and the widget belongs to the page, while still rejecting malformed explicit `/Kids` trees that do not own the widget.

## Red-First Evidence

Before the patch, a page `/Annots [8 0 R]` Widget with `/Parent 6 0 R` was dropped when field object `6` had `/FT /Tx`, `/T`, and `/V` but no `/Kids`:

```text
array (
)
```

## Implementation

- `PdfAcroFormExtractor::fieldReferencesWithPageWidgetBoundaries()` now accepts a page-owned Widget `/Parent` candidate when the parent field omits `/Kids`.
- Explicit `/Kids` collections remain authoritative: if a parent field declares `/Kids`, the widget must still be reachable through that field tree.
- Added `fieldHasKids()` as a small token-aware helper so absent `/Kids` and explicit empty/mismatched `/Kids` stay distinct.

## Focused Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
PASS repairs page widget parent fields when the parent omits Kids

1 test files, 502 assertions, 0 failures
```

The added test proves:

- `parent.nokids` is repaired from a page-owned Widget `/Parent` even though the field lacks `/Kids`.
- the repaired field keeps `/TU`, `/TM`, `/V`, widget rectangle, page object, page index, and page annotation index as review metadata;
- `explicit.empty.kids` remains excluded when a parent field declares `/Kids []` but a page Widget points at it;
- form field values and alternate names do not leak into visible WordPress text.

## WordPress Smoke

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-boundary-currentbase.php
```

The smoke emits `parent_without_kids_repaired=true`, `explicit_empty_kids_parent_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, token-aware dictionary/array parser, page-tree walker, page annotation Widget map, AcroForm field hierarchy walker, and WordPress smoke path. Live OCR, Surya/Texify/Torch/model execution, PDFium/pdftext parity runs, form action execution, JavaScript execution, and external PDF tools remain intentionally out of scope under the current markerPDF no-GPU directive.

## Non-Overlap

This does not repeat accepted AcroForm field discovery from page-owned widgets with explicit owning `/Kids`, direct Widget entries in `/Fields`, child-root normalization, token-aware `/Fields` and `/Kids` arrays, generation-exact field/widget references, indirect scalar/numeric/type operands, comment-only Widget subtype rejection, wrong-page `/P` rejection, unowned widget parent rejection, object-stream field recovery, XFA/signature/action review, submit/reset review, or default resource appearance metadata. The bounded behavior is only omitted parent-field `/Kids` during page-owned Widget `/Parent` repair.
