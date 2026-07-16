# AcroForm Typed Dictionary Boundary Current Base

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260608T224737Z`
Base accepted HEAD: `c992bb947324f7207d596c6abc6496ba6a35dd32`

## Behavior

The native AcroForm field extractor now treats dictionaries with a non-empty
`/Type` as typed non-field dictionaries unless they are real widget
annotations (`/Subtype /Widget`). This keeps `/Type /Filespec` attachment
descriptors and standalone `/Type /Sig` signature value dictionaries from being
promoted as field nodes when they are incorrectly listed under `/AcroForm
/Fields` or nested `/Kids`.

The focused fixture preserves:

- a normal `/FT /Tx` field with a widget child;
- a mixed `/Type /Annot /Subtype /Widget` field/widget dictionary;
- `/Type /Filespec` and standalone `/Type /Sig` decoys carrying `/T`, `/V`,
  and `/Kids` operands that must stay out of WordPress form review and visible
  text.

## Evidence

- `php -l lanes/markerpdf/src/PdfAcroFormExtractor.php && php -l lanes/markerpdf/tests/PdfAcroFormTypedDictionaryBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-acroform-fields-typed-dictionary-currentbase.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormTypedDictionaryBoundaryCurrentBaseTest.php`:
  `1 test files, 26 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormSignatureXfaWidgetActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php`:
  `2 test files, 585 assertions, 0 failures`.
- `php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfAcroForm.*Test\.php$')`:
  `95 test files, 5872 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-typed-dictionary-currentbase.php`:
  exits 0 and emits `typed_non_field_dictionaries_excluded=true`,
  `filespec_dictionary_excluded=true`,
  `signature_value_dictionary_excluded=true`, and
  `form_values_visible_in_text=false`.
- `git diff --check -- lanes/markerpdf`: passed with no output.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PDF
object parser, AcroForm dictionary scanner, and focused PHP test runner. No GPU,
OCR/model execution, external PDF tools, JavaScript execution, signing, or
signature validation is required.

## Non-Overlap

This patch does not repeat recent accepted AcroForm direct-parent, no-Kids,
action-dictionary, non-widget subtype, scalar tail, dictionary tail, or
generation-boundary slices. It targets a separate typed-dictionary boundary for
non-field `/Type` dictionaries encountered during field-tree candidate
selection.
