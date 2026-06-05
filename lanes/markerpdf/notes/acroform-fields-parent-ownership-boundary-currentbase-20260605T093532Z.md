# AcroForm Parent Ownership Boundary Current Base

## Scope

This slice maps a native AcroForm field-tree boundary: a field dictionary's `/Parent` inheritance is trusted only when the parent field's `/Kids` tree owns the child. A detached parent decoy must not provide inherited `/T`, `/FT`, `/V`, `/DV`, `/MaxLen`, `/TU`, or `/TM` review metadata for a child that is directly listed in `/AcroForm /Fields`.

The fixture also keeps a valid owned parent branch in the same PDF to prove legitimate child-field inheritance still works.

## Source Truth

AcroForm hierarchy is represented by field dictionaries linked through `/Kids` and `/Parent`. The root `/AcroForm /Fields` array identifies field-tree roots, and terminal field values remain review metadata for WordPress import rather than visible page text. This no-GPU markerPDF slice keeps the behavior inside native PHP PDF parsing and does not run pdftext, PDFium, Surya, Texify, OCR, table models, or external PDF tools.

## Implementation

- `PdfAcroFormExtractor::fieldReferenceAncestorContext()` now stops parent inheritance unless the immediate parent `/Kids` array contains the child object.
- `PdfAcroFormExtractor::pageWidgetRootFieldCandidate()` applies the same ownership check while normalizing direct Widget `/Fields` entries to root fields.
- The new helper `fieldParentOwnsChild()` reuses the existing token-aware `/Kids` reference parser, so literal/comment/nested-array decoys remain excluded by existing boundaries.

## Evidence

Red-first focused run on the current accepted base failed as expected:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsParentOwnershipBoundaryCurrentBaseTest.php
FAIL bounds AcroForm Parent inheritance to parent Kids ownership before WordPress field review
Expected: email, valid.profile.title
Actual: decoy.profile.email, valid.profile.title
1 test files, 1 assertions, 1 failures
```

Green focused run after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsParentOwnershipBoundaryCurrentBaseTest.php
1 test files, 55 assertions, 0 failures
```

Adjacent AcroForm field run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsParentOwnershipBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsChildBranchBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsCommentReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsCycleBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsObjectTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsTrailerRootBoundaryCurrentBaseTest.php
7 test files, 729 assertions, 0 failures
```

Broader AcroForm run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*Test.php
30 test files, 2938 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-parent-ownership-currentbase.php
```

The smoke emits `detached_parent_inheritance_rejected=true`, `detached_parent_decoy_excluded=true`, `owned_parent_inheritance_preserved=true`, `field_values_review_only=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted AcroForm token-aware `/Fields` parsing, page-widget repair, child branch normalization, generation checks, comment-reference boundaries, cycle guards, trailer-root selection, action review, widget appearance review, XFA review, signature seed/lock review, submit/reset review, or Type3/font/text extraction work. The new behavior is specifically `/Parent` inheritance bounded by parent `/Kids` ownership.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, dictionary parser, token-aware array/reference parser, AcroForm field traversal, page widget review, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, Streamlit/FastAPI workers, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
