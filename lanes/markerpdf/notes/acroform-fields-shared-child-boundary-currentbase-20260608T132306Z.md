# markerpdf-acroform-fields-boundary-current-base-20260608T132306Z

## Scope

This isolated markerPDF slice maps one native no-GPU PDF parser boundary for AcroForm field trees: a field dictionary with no explicit `/Parent` can be used as a compact child when exactly one field parent lists it in `/Kids`, but the same parentless child listed under multiple field parents is ambiguous. The PHP extractor now excludes that shared child from inherited branch review instead of importing it as `first_parent.child`.

Source-truth boundary: upstream markerPDF routes searchable PDF/native PDF structures before OCR/model fallback; under the current lane override this PHP port owns native AcroForm field review and never executes form actions, JavaScript, signing, OCR, Surya/Texify/Torch, PDFium rendering, or external PDF tools.

## Changes

- `PdfAcroFormExtractor` now checks parentless field-tree child ownership before accepting a `/Kids` child or page-widget repair candidate.
- Parentless children with zero or one field-tree owner remain accepted, preserving compact single-parent AcroForm trees.
- Parentless children with multiple field-tree owners are rejected as ambiguous so inherited `/V`, `/DV`, `/MaxLen`, labels, widget references, and field names do not leak from the first listed branch.
- Added focused test coverage and a WordPress smoke for the shared-child boundary.

## Red-First Evidence

Before the extractor patch:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsSharedChildBoundaryCurrentBaseTest.php
```

Failed with:

```text
Expected: ['billing', 'shipping']
Actual: ['billing.email']
1 test files, 22 assertions, 1 failures
```

After the patch:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsSharedChildBoundaryCurrentBaseTest.php
```

Passed:

```text
PASS rejects parentless AcroForm child dictionaries shared by multiple field parents
PASS keeps a single parentless AcroForm child branch usable for compact field trees
1 test files, 58 assertions, 0 failures
```

Adjacent AcroForm field boundary family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFields*CurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormIndirectFieldSelectionArraysCurrentBaseTest.php
```

Passed:

```text
54 test files, 2865 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-shared-child-boundary-currentbase.php
```

Passed with metadata flags:

- `ambiguous_child_excluded=true`
- `parent_fields_preserved=true`
- `shared_child_payload_hidden=true`
- `executes_form_actions=false`
- `executes_javascript=false`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

## Non-Overlap

This does not repeat accepted AcroForm page-widget discovery, direct field materialization, duplicate `/Fields` or `/Kids` key selection, generation matching, object-stream expansion, tailed object rejection, parent ownership references, direct widget parent dictionaries, action field selection arrays, choice top-index review, widget appearance/action review, XFA/signature review, or null/comment token boundaries. The bounded behavior is specifically ambiguous parentless child ownership across multiple field parents.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PDF tokenizer/object graph, AcroForm field-tree parser, page-widget repair path, and WordPress smoke harness. No GPU/model/OCR/PDFium/external-service dependency is activated.

## Next

Continue with non-overlapping native markerPDF parser behavior around xref repair, object-stream filter metadata, fonts, CMaps, annotations/forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs. Do not repeat AcroForm shared parentless-child ownership ambiguity.
