# markerpdf named destinations non-XYZ null coordinate boundary

Slice: `markerpdf-named-destinations-boundary-current-base-20260606T052507Z`
Base: `acf12984b3f1531972a266d07322821b4a812a25`
Date: 2026-06-06 UTC

## Source truth

PDF explicit destinations allow `null` coordinate operands for `/XYZ` left, top, and zoom values so viewers can retain the current position or zoom. The same nullable boundary does not apply to required coordinates for `/FitH`, `/FitV`, `/FitR`, `/FitBH`, or `/FitBV`; those operands must resolve to numeric coordinates.

This patch applies that boundary consistently across:

- named-destination extraction;
- document destination metadata;
- outline destination review;
- GoTo action and annotation review;
- WordPress link promotion.

The change is native searchable-PDF parser behavior only. It does not invoke OCR, Surya, Texify, Torch, external PDF tools, or model workers.

## Implementation

- `PdfNamedDestinationExtractor`, `PdfMetadataExtractor`, `PdfOutlineExtractor`, and `PdfActionReviewExtractor` now preserve nullable `/XYZ` operands but reject null required coordinate operands for non-XYZ explicit views.
- Existing valid `/FitH` and `/FitBH` test/example fixtures now use numeric coordinates instead of relying on the invalid null boundary.
- `PdfNamedDestinationNonXyzNullCoordinateBoundaryCurrentBaseTest.php` covers valid `/XYZ null null null`, valid numeric non-XYZ destinations, invalid names-tree destinations, invalid legacy `/Dests`, invalid GoTo action destinations, outline filtering, annotation safety, link promotion, and visible text exclusion.
- `wordpress-pdf-named-destination-non-xyz-null-coordinate-currentbase.php` is the WordPress smoke for destination metadata and link-promotion behavior.

## Verification

Red before the parallel validator fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationNonXyzNullCoordinateBoundaryCurrentBaseTest.php
# 1 test files, 12 assertions, 2 failures
```

Green focused checks:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationNonXyzNullCoordinateBoundaryCurrentBaseTest.php
# 1 test files, 55 assertions, 0 failures

php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfNamedDestination.*Test\.php$|/PdfOutlineNamedDestination.*Test\.php$|/PdfOutline.*Destination.*Test\.php$|/PdfAction.*Destination.*Test\.php$|/PdfOutlineExtractorTest\.php$|/PdfActionReviewExtractorTest\.php$' | sort)
# 51 test files, 2225 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfOutlineDestinationFitActionChainCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationNonXyzNullCoordinateBoundaryCurrentBaseTest.php
# 3 test files, 422 assertions, 0 failures
```

Example smokes:

```bash
php lanes/markerpdf/examples/wordpress-pdf-indirect-nametree-destinations-import.php >/tmp/markerpdf-indirect-nametree.out
php lanes/markerpdf/examples/wordpress-pdf-page-label-viewer-prefs-boundaries.php >/tmp/markerpdf-page-label-viewer.out
php lanes/markerpdf/examples/wordpress-pdf-outline-destination-fit-action-chain-currentbase.php >/tmp/markerpdf-outline-fit-action.out
php lanes/markerpdf/examples/wordpress-pdf-named-destination-non-xyz-null-coordinate-currentbase.php >/tmp/markerpdf-non-xyz-null.out
wc -c /tmp/markerpdf-indirect-nametree.out /tmp/markerpdf-page-label-viewer.out /tmp/markerpdf-outline-fit-action.out /tmp/markerpdf-non-xyz-null.out
# 1288, 2939, 4314, 1826 bytes; 10367 total
```

## Dependency closure

No new support component is required. This reuses the existing native PDF tokenizer, object resolver, named-destination extraction, outline/action review, annotation extraction, and Markdown post-processing paths.

## Non-overlap

This avoids the accepted outline duplicate navigation-key metadata slice and does not touch OCR/model execution, scanned-PDF live recognition, stream filters, xref repair, encryption preflight, forms, or unrelated markerPDF parser surfaces.
