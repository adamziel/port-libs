# markerPDF AcroForm Non-Field Parent Boundary

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260606T025943Z`

Base accepted HEAD: `218f7be316686ea5b2005dbccc9e20ca989dc733`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` consumes searchable PDF text, PDF parser metadata, and form-review metadata before OCR/layout/model stages. The native no-GPU PHP lane owns the AcroForm field dictionary boundary before WordPress import review.

PDF field dictionaries can be hierarchical, and untyped parent nodes with `/Kids` can validly provide inheritable field attributes. Typed document-structure dictionaries such as `/Type /Pages`, `/Type /Page`, `/Type /Catalog`, and non-widget `/Type /Annot` are not AcroForm field dictionaries, even when malformed PDFs include field-like keys or `/Kids` arrays on them.

## Red-First Evidence

Before the source change, a real field `6 0 R` with `/Parent 2 0 R` inherited `/DV` and `/MaxLen` from object `2 0 R`, even though object `2` was the page tree root:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsNonFieldParentBoundaryCurrentBaseTest.php
1 test files, 8 assertions, 1 failures
Expected: null
Actual:   'Page tree default must not surface'
```

## Implementation

- `PdfAcroFormExtractor::fieldsFromObject()` now drops resolved object bodies that are not AcroForm field dictionary candidates before merging inherited attributes.
- `PdfAcroFormExtractor::collectFieldNamesByObject()` applies the same candidate boundary when deriving fully qualified field names.
- `isFieldDictionaryCandidate()` now rejects typed Catalog/Pages/Page/non-widget Annot dictionaries while preserving Widget annotations and untyped AcroForm grouping nodes.

## Focused Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsNonFieldParentBoundaryCurrentBaseTest.php
1 test files, 48 assertions, 0 failures
```

The focused test proves:

- a `/Type /Pages` object with `/Kids`, `/TU`, `/TM`, `/V`, `/DV`, and `/MaxLen` cannot become a field parent;
- the terminal field keeps only its local field value, widget page object, page index, and annotation index;
- an untyped anonymous AcroForm grouping node still provides inherited `/FT`, `/DV`, and `/MaxLen`;
- form values and page-tree decoy metadata stay out of visible WordPress text.

Adjacent AcroForm boundary coverage also passed:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsNonFieldParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsParentOwnershipBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsParentReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsChildBranchBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsBranchRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsPageTreeIndirectKidsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectPageWidgetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsPageWidgetParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectWidgetParentNoKidsBoundaryCurrentBaseTest.php
10 test files, 897 assertions, 0 failures
```

## WordPress Smoke

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-nonfield-parent-boundary-currentbase.php
```

The smoke emits `non_field_page_tree_parent_excluded=true`, `page_tree_metadata_excluded_from_review=true`, `anonymous_grouping_parent_preserved=true`, `field_values_review_only=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, token-aware dictionary/name parsing, AcroForm field hierarchy walker, page Widget metadata collection, and WordPress smoke output. Live OCR, Surya/Texify/Torch/model execution, PDFium/pdftext parity runs, form action execution, JavaScript execution, and external PDF tools remain intentionally out of scope under the current markerPDF no-GPU directive.

## Non-Overlap

This does not repeat accepted AcroForm direct dictionaries, direct widgets, field scalar generation boundaries, parent ownership through explicit `/Kids`, parent-without-`/Kids` page-widget repair, child-branch traversal, branch repair, page-tree indirect `/Kids`, token-aware `/Fields` arrays, duplicate-key boundaries, generation boundaries, XFA/signature/action review, submit/reset/action review, or default resource appearance metadata. The bounded behavior is only excluding typed non-field dictionaries from field-parent inheritance while preserving valid untyped grouping parents.
