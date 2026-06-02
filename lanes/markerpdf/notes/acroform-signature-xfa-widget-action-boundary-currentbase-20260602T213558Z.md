# AcroForm Signature XFA Widget Action Boundary Current Base

## Source truth

- Upstream markerPDF manifest source remains `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream conversion delegates PDF structure and annotation discovery to the PDF parser layer (`pdftext`/PDFium before marker conversion). This slice preserves that parser boundary natively: page `/Annots` widget dictionaries are authoritative annotation objects even when the terminal AcroForm field omits `/Kids`.
- PDF action dictionaries are metadata only for this port. SubmitForm, URI, GoToR, JavaScript, Hide, signing, validation, XFA JavaScript, Python/model execution, and external PDF tooling remain disabled.

## Behavior

`PdfAcroFormExtractor` now attaches page-referenced Widget annotations to their parent AcroForm field when:

- the widget is discovered from a page `/Annots` array;
- the widget dictionary has `/Subtype /Widget`;
- the widget has `/Parent <field> 0 R`;
- the parent field object is already part of the catalog `/AcroForm /Fields` tree.

The same page-only widget object is also mapped back to the parent field name for action review field-target resolution. This covers signature widgets whose selected `/AP /N` state, page annotation order, XFA packet references, and widget `/A` or `/AA` actions were previously invisible unless the field tree also listed the widget in `/Kids`.

## Non-overlap

This does not repeat the earlier XFA packet decoder, signature field state review, seed/lock review, field/widget action walker, action-cycle guard, widget appearance extraction, page widget link promotion, or page annotation StructParent work. The new behavior is the missing page-annotation-to-parent-field attachment path for terminal AcroForm fields without `/Kids`.

## Evidence

- `php -l lanes/markerpdf/src/PdfAcroFormExtractor.php`
- `php -l lanes/markerpdf/tests/PdfAcroFormSignatureXfaWidgetActionBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-acroform-signature-xfa-widget-action-boundary-currentbase.php`
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-signature-xfa-widget-action-boundary-currentbase.php`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormSignatureXfaWidgetActionBoundaryCurrentBaseTest.php`
  - `1 test files, 53 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormSignatureXfaWidgetActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormSignatureXfaWidgetActionReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormXfaWidgetCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php lanes/markerpdf/tests/PdfAcroFormActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormSignatureFieldActionStateCurrentBaseTest.php`
  - `6 test files, 972 assertions, 0 failures`
- `git diff --check -- lanes/markerpdf`
  - passed

The metadata files also parse as JSON after the update.

## Dependency closure

No new support component is needed. The slice reuses the existing native parser object table, page widget map, AcroForm field traversal, XFA packet metadata decoder, action review walker, stream decoder, and text extractor. Full upstream Python/model runner parity remains unavailable because it requires the upstream ML/PDF stack and live runtime dependencies.

## Next

Continue AcroForm parity with field-tree edge cases that still affect real import fidelity: inherited widget resources from mixed field/widget dictionaries, multi-widget radio/signature field ordering, and additional action target resolution boundaries. Keep action execution disabled.
