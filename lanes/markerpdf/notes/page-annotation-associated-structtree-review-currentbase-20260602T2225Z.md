# Page Annotation Associated StructTree Review Current Base

Session: `port-dev-markerpdf-page75-20260602T222511Z`

Base accepted HEAD: `dea63aa7e627de2d478a25a4f111e872b79036af`

## Source Truth

- Upstream markerPDF keeps PDF extraction page-local: page text, spans, and blocks are routed from page-scoped PDF parser/pdftext/PDFium boundaries before Markdown output.
- Tagged PDF parser behavior: a page annotation can be associated with a structure element either through annotation `/StructParent` plus `/StructTreeRoot` ParentTree rows, or through a StructElem `/K` OBJR object reference. StructElem `/AF` FileSpecs are provenance/review metadata and must not become visible paragraph text.
- This slice uses native PHP parser behavior only. No Python models, pypdfium, pdftext dictionary execution, OCR, external PDF tools, attachment execution, or PDF action execution were run.

## Implemented Behavior

`PdfAnnotationExtractor` now builds a current-document inverse map from StructTree StructElem `/K` OBJR references to page annotation object numbers. Page annotation review rows use that map when:

- an annotation has no `/StructParent` but is referenced by a StructElem OBJR;
- an annotation has `/StructParent`, but the ParentTree key is missing or does not point back to the current annotation object.

The fallback preserves StructElem role/title/alternate metadata and associated FileSpec checksum/provenance metadata as `structure_parent` review data, keeps action/link destination metadata non-executing, and does not promote annotation contents, StructElem text, FileSpec names, or embedded payloads into visible WordPress text.

## Red-First Evidence

Before the source edit, the new focused test failed because the OBJR-only annotation had no `structure_parent` review row:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotationAssociatedStructTreeReviewCurrentBaseTest.php
Expected: 'annotation_struct_tree_objr'
Actual: NULL
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotationAssociatedStructTreeReviewCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses StructTree OBJR annotation associations when annotation StructParent rows are missing
1 test files, 87 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotationAssociatedStructTreeReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedActionCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationStructTreeAssociatedTransitionCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructTreeAnnotationPieceInfoCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationStructTreeLayoutPreviewBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfPageParentTreeActionAnnotationCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 1045 assertions, 0 failures
```

```text
php -l lanes/markerpdf/src/PdfAnnotationExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAnnotationExtractor.php

php -l lanes/markerpdf/tests/PdfPageAnnotationAssociatedStructTreeReviewCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfPageAnnotationAssociatedStructTreeReviewCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-page-annotation-associated-structtree-review-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-page-annotation-associated-structtree-review-currentbase.php
```

```text
php lanes/markerpdf/examples/wordpress-pdf-page-annotation-associated-structtree-review-currentbase.php
exit 0; rendered the markerpdf-page-annotation-associated-structtree-review-currentbase review comment, two visible WordPress paragraphs, and a markerpdf:annotation-associated-structtree-review comment with OBJR/associated-file metadata.
```

```text
git diff --check -- lanes/markerpdf
passed
```

Root harness: not run - isolated micro-slice.

## Status Delta

- Behavior tests: `910 -> 911` pass / `0` fail.
- Mapped semantics: `640 -> 641 / 78`.
- WordPress scenarios: `910 -> 911`.

## Non-Overlap

This does not repeat accepted annotation `/StructParent` ParentTree association, ParentTree action enrichment, transition target context, page PieceInfo/annotation rows, layout preview bundles, text-markup context, page StructParents MCID reading order, link annotation target context, widget inherited StructParent review, or thread/action page review. The new boundary is inverse StructTree `/K` OBJR association fallback when the annotation `/StructParent`/ParentTree path is absent or incomplete.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, StructTree metadata extraction, existing OBJR child parsing, page `/Annots` traversal, FileSpec checksum review, link-span application, and page-local text extraction boundaries. Full upstream markerPDF runner parity remains blocked by the heavy Python/pdftext/pypdfium/Surya/OCR/PIL/runtime stack and was not required for this isolated micro-slice.
