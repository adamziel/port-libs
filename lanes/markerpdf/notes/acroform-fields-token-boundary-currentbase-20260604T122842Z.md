# markerPDF AcroForm Token-Aware Field Boundary Current Base

Lane: `markerpdf`
Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260604T122842Z`
Base accepted HEAD: `bd54fb7d930532514192670615b391c021f2db96`

## Source Truth

Upstream markerPDF relies on PDF parser layers before conversion. This native no-GPU slice maps the relevant parser boundary for AcroForm dictionaries: PDF name keys may use hex escapes such as `/Fie#6Cds`, while field-like names inside literal strings, comments, arrays, and nested action dictionaries are values, not direct field-tree keys.

No OCR, Surya, Texify, Torch, PDFium rendering, model worker, browser, or external PDF tool was executed.

## Behavior

`PdfAcroFormExtractor::valueAfterName()` now scans direct dictionary keys token by token before field extraction:

- escaped name keys are decoded before comparison, so `/Fie#6Cds` is accepted as `/Fields`;
- literal strings and hex strings are skipped before matching names;
- nested arrays and dictionaries are skipped before matching direct keys;
- PDF comments are skipped while scanning dictionary and array boundaries.

The focused fixture proves `/DA (/Fields [99 0 R])`, tooltip text containing `/V (Decoy token title)` and `/Kids [99 0 R]`, and nested action `/Fields [99 0 R]` do not redirect AcroForm field discovery or current-value review. The real field remains `article.token`, `/V (Real token title)` stays review metadata, and the page text remains the only visible WordPress paragraph.

## Red-First Evidence

Before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs AcroForm field discovery from page owned widget annotations only
FAIL uses token aware AcroForm field keys before WordPress review metadata
Expected: array (0 => 'article.token')
Actual: array (0 => 'decoy.literal', 1 => 'article.token.decoy.literal')
1 test files, 31 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs AcroForm field discovery from page owned widget annotations only
PASS uses token aware AcroForm field keys before WordPress review metadata
1 test files, 48 assertions, 0 failures
```

Adjacent AcroForm family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*Test.php
Focused test run: 24 selected test files (root lock skipped)
24 test files, 2223 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-token-boundary-currentbase.php
```

Emitted `field_names=["article.token"]`, `field_value="Real token title"`, `widget_objects=[8]`, `decoy_literal_field_imported=false`, `visible_text_contains_form_value=false`, and all execution flags false.

## Non-Overlap

This does not repeat accepted page-owned widget field discovery, AcroForm field hierarchy inheritance, `/MaxLen` review, widget appearance state, submit/reset action review, signature/XFA widget review, or page annotation widget link promotion. The bounded behavior is token-aware direct-key parsing inside the AcroForm extractor before field tree and value-state review.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, dictionary/array parser, field hierarchy builder, action walker, page widget map, and WordPress smoke path. Full upstream model/OCR/rendering parity remains intentionally out of scope under the current no-GPU markerPDF directive.
