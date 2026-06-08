# AcroForm NeedAppearances indirect current-base slice

Session: `port-dev-markerpdf-acroform-fields-20260608T210621Z`
Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260608T210621Z`
Base accepted HEAD: `abc313637c76f7f217fa1dc23516e40d06807602`

## Behavior

PDF AcroForm `/NeedAppearances` is a boolean form-level entry. This slice keeps
the no-GPU native parser boundary focused on AcroForm metadata review:

- complete indirect boolean objects such as `/NeedAppearances 30 0 R` with
  object `30 0 obj true endobj` now resolve before field review;
- exact object generation still matters;
- tailed indirect boolean objects such as `true /BadOperand` fail closed;
- AcroForm field values remain review-only metadata and are not promoted into
  visible WordPress text.

## Evidence

Red-first:

A one-off current-base probe before the source edit reported
`need_appearances=false fields=1` for an AcroForm dictionary with
`/NeedAppearances 30 0 R` and object `30 0 obj true endobj`.

After the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsNeedAppearancesIndirectBoundaryCurrentBaseTest.php`

Result: `1 test files, 29 assertions, 0 failures`.

Adjacent AcroForm scalar/reference boundary subset:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsNeedAppearancesIndirectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsIndirectReferenceOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsScalarObjectTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectParentNoKidsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectChildParentWidgetBoundaryCurrentBaseTest.php`

Result: `5 test files, 232 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-acroform-needappearances-indirect-currentbase.php --self-test`

Result: exits `0`; reports `valid_indirect_need_appearances=true`,
`tailed_indirect_need_appearances=false`,
`visible_text_excludes_field_values=true`, and
`tailed_operand_excluded=true`.

## Non-overlap

This does not repeat AcroForm `/Fields` array parsing, direct parent/kids
repair, widget/page ownership repair, indirect reference operand wrappers,
duplicate key handling, scalar object-tail field value handling, calculation
order, action/XFA/signature review, or visible text extraction. The new surface
is only the AcroForm root `/NeedAppearances` indirect boolean boundary.

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP PDF
object parsing, exact-generation reference checks, complete-object scalar
validation, AcroForm extraction, and text extraction. No Python, OCR/model,
GPU/Torch, external PDF tools, PDF action execution, or live provider services
are involved.

## Next Task

A useful follow-up is a distinct AcroForm metadata boundary, such as indirect
boolean/name handling for another form-level key, or a separate non-overlapping
annotation/forms parser edge.
