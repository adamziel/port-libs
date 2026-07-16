# AcroForm Root Default Tail Boundary - 2026-06-08

Slice: `markerpdf-acroform-fields-boundary-current-base-20260608T233318Z`

Base accepted HEAD: `9eb676a5cd9add619cf3b6f2435447962ecbfb04`

## Source Truth

PDF dictionary entries such as AcroForm root `/DA`, `/DR`, and `/Q` are complete object values. A valid trailing PDF comment is outside the value, but an extra top-level operand after the value is malformed and must not be consumed as part of the inherited default appearance, default resource, or quadding state.

This slice keeps fields reviewable, but fails closed before malformed root defaults can seed inherited form metadata. It does not execute form actions, JavaScript, XFA, OCR, Python models, raster backends, or external PDF tools.

## Implementation

`PdfAcroFormExtractor::acroFormDefaults()` now reuses the tokenizer-backed top-level value span boundary and rejects root `/DA`, `/DR`, and `/Q` values when a trailing top-level operand follows the first value. Comment-only tails remain valid and still seed inherited default appearance/resource/quadding review.

## Evidence

Red-first focused run before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsRootDefaultTailBoundaryCurrentBaseTest.php
FAIL: rejects tailed AcroForm root defaults before inherited field appearance review
Expected default_resources source/object/font_count/fonts to be null/null/0/[]; actual default_resources came from acroform object 30 with TailFont/Courier.
```

Green focused run after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsRootDefaultTailBoundaryCurrentBaseTest.php
1 test files, 56 assertions, 0 failures
```

Adjacent AcroForm family check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsRootDefaultTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectAttributeTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsDuplicateAttributeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php
4 test files, 1004 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-root-default-tail-currentbase.php
exits 0; marker metadata reports tailed_default_resources_rejected=true, tailed_default_appearance_rejected=true, tailed_quadding_rejected=true, field_value_review_only=true, and all action/model/external-tool execution flags false.
```

## Non-Overlap

This is not the accepted direct field attribute tail guard, scalar object tail guard, direct `/Fields` or `/Kids` array tail guard, indirect reference tail guard, duplicate-attribute boundary, default-resource review, AcroForm generation boundary, XFA/signature/action review, object-stream/xref repair, or image/filter/form appearance slice. It only covers root AcroForm `/DA`, `/DR`, and `/Q` inherited default tails.

## Dependency Closure

No new support component is needed. The patch reuses native PDF tokenization, dictionary value span parsing, AcroForm default-resource review, and the existing WordPress example path. No GPU/model/OCR/runtime worker dependency is activated.

Root harness: not run - isolated micro-slice.
