# markerPDF AcroForm duplicate field-name boundary current base

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260608T105613Z`
Session: `port-dev-markerpdf-acroform-fields-20260608T105613Z`
Base accepted HEAD: `50273d18c947bc38084848571a39b31e1d8d4c3d`

## Source Truth

- PDF AcroForm field review is keyed by fully qualified field names, so malformed or stale duplicate terminal fields with the same full name must not create duplicate WordPress review rows.
- This native no-GPU slice keeps field values, default values, labels, and widget geometry as review metadata only; it does not render appearances, run form actions, execute JavaScript, launch external PDF tools, or invoke OCR/model workers.

## Implementation

- `PdfAcroFormExtractor::uniqueFieldsByObject()` now also deduplicates non-empty terminal field names after preserving the existing object-number duplicate guard.
- When the AcroForm tree lists duplicate fully qualified names, the later terminal field replaces the earlier stale field for WordPress review metadata.
- The field's visible page text extraction remains independent, so AcroForm values and labels stay out of Gutenberg paragraph text.

## Red First

Before the source change, with the new duplicate-name boundary test in place:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDuplicateNameBoundaryCurrentBaseTest.php
FAIL deduplicates AcroForm terminal fields by fully qualified name before WordPress review
Expected: 1
Actual: 2
1 test files, 2 assertions, 1 failures
```

The failure showed both stale and current `article.title` terminal fields being imported into review metadata.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDuplicateNameBoundaryCurrentBaseTest.php
1 test files, 32 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfAcroFormFields.*CurrentBaseTest\.php' | sort)
52 test files, 2726 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-duplicate-name-currentbase.php
```

The smoke emitted `field_names=["article.title"]`, `field_object=10`, `field_value_review_only=true`, `stale_duplicate_metadata_imported=false`, `visible_text_imported=true`, and all execution flags false.

PHP lint passed for `lanes/markerpdf/src/PdfAcroFormExtractor.php`, `lanes/markerpdf/tests/PdfAcroFormFieldsDuplicateNameBoundaryCurrentBaseTest.php`, and `lanes/markerpdf/examples/wordpress-pdf-acroform-fields-duplicate-name-currentbase.php`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP `PdfAcroFormExtractor`, `PdfTextExtractor`, object dictionary parsing, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDF appearance rendering, JavaScript/form-action execution, and external PDF tools remain intentionally out of scope under the current markerPDF lane rule.

## Non-Overlap

This does not repeat accepted AcroForm token-key parsing, parent ownership, child-branch repair, scalar-generation resolution, choice top-index review, non-widget subtype exclusion, widget appearance/action review, security preflight action correlation, xref repair, annotation review, metadata, image/filter, Type3 font, CMap, stream-filter, or pdftext dictionary boundary work. The bounded behavior is duplicate fully qualified AcroForm terminal field names collapsing to the later current review field before WordPress import.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser and converter boundaries around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
