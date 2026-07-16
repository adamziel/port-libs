# markerPDF annotations links indirect subtype tail boundary current base

- Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260608T112739Z`
- Session: `port-dev-markerpdf-annotations-links-20260608T112739Z`
- Base accepted HEAD: `d4e1c6e37b4f2ee07da5d4369183d41cf268bfc1`
- Scope: native no-GPU markerPDF annotation/link parser behavior only.

## Source Truth

PDF annotation `/Subtype` is a name operand. When a subtype is supplied through an indirect object, the selected object must resolve to one top-level PDF name. A tailed object body such as `/Link 30 0 R` or `/Highlight 30 0 R` is malformed for this boundary and must not donate a Link or text-markup subtype to WordPress span promotion.

This maps markerPDF's safe searchable-PDF import boundary under the no-GPU scope: annotations remain review metadata, but malformed subtype operands cannot become Markdown links or markup review spans. No PDF action, JavaScript, media playback, OCR, pypdfium/PDFium rendering, Python model, or external PDF tool is executed.

## Implementation

- `PdfAnnotationExtractor::pdfNameValueAfterName()` now rejects direct selected name values with extra top-level operands and indirect name objects whose resolved body contains a second top-level operand.
- `PdfLinkAnnotationExtractor::nameValueAfterName()` applies the same boundary before accepting `/Subtype /Link`, highlight-mode names, optional-content names, and related name operands used by link review.
- `PdfMarkupAnnotationExtractor::nameValueAfterName()` now shares the same resolved-object tail guard so tailed indirect `/Subtype /Highlight` objects cannot attach markup review metadata to supplied spans.
- Added focused test coverage and a WordPress smoke fixture with a clean direct Link/Highlight sibling plus tailed indirect Link/Highlight subtype decoys.

## Evidence

Red-first on accepted base:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectSubtypeTailBoundaryCurrentBaseTest.php
FAIL rejects tailed indirect annotation Subtype names before WordPress link and markup promotion
Expected: ["Link","Unknown","Unknown","Highlight"]
Actual: ["Link","Link 30 0 R","Highlight 30 0 R","Highlight"]
1 test files, 3 assertions, 1 failures
```

After the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectSubtypeTailBoundaryCurrentBaseTest.php
1 test files, 28 assertions, 0 failures
```

Adjacent focused family:

```text
mapfile -t files < <(rg --files lanes/markerpdf/tests | rg '/Pdf(LinkAnnotation|AnnotationLink|MarkupAnnotation).*Test\.php$' | sort); php tools/run-tests.php "${files[@]}"
59 test files, 2120 assertions, 0 failures
```

Extractor-focused check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
4 test files, 1042 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-link-annotation-indirect-subtype-tail-currentbase.php --self-test
```

Result: exits 0 and emits `annotation_subtypes=["Link","Unknown","Unknown","Highlight"]`, `promoted_link_objects=[7]`, `markup_objects=[10]`, `tailed_subtype_link_promoted=false`, `tailed_subtype_markup_attached=false`, `visible_text_imported=true`, `annotation_payload_text_visible=false`, `executes_pdf_actions=false`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP PDF tokenizer/object resolver, annotation extractor, link span promoter, text-markup span reviewer, supplied marker/pdftext page model, Markdown merge path, and WordPress smoke harness. Full live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium rendering, JavaScript/PDF action execution, media playback, and exact upstream model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.

## Non-Overlap

This does not repeat accepted page `/Annots` ownership, duplicate page `/Annots` keys, tailed page `/Annots` operands, escaped annotation keys, exact annotation/page generation selection, optional-content link exclusion, clean indirect annotation `/Subtype` names, clean indirect action `/S` names, duplicate action subtype/key handling, direct or indirect tailed `/A` and `/Dest` objects, primary action array/scalar rejection, URI Base resolution, IsMap, remote GoToR, name-tree Limits, object-stream action selection, QuadPoints/rotation/UserUnit geometry, widget field action inheritance, or annotation dictionary duplicate-key selection. The bounded behavior is only tailed indirect name objects used as annotation `/Subtype` operands before WordPress link and markup promotion.
