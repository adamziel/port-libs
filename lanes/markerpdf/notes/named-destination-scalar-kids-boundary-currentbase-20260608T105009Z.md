# markerpdf named-destination scalar Kids boundary current base

Slice: `markerpdf-named-destinations-boundary-current-base-20260608T105009Z`

Accepted base: `fbbf77ccf66375064bf8cc4ae8c93b638b9fe9b6`

## Source truth

Upstream markerPDF delegates searchable PDF navigation/text extraction to PDF parser dependencies at this boundary. PDF name-tree nodes are either leaf nodes with `/Names` arrays or intermediate nodes with `/Kids` arrays; `/Kids` is an array operand. A present scalar `/Kids` operand is malformed and must not be treated as an absent child array while local `/Names` rows are promoted into WordPress review metadata.

This no-GPU slice keeps the behavior native PHP only. It does not run Python, PDFium, pypdfium2, PIL, OCR, Surya, Texify, Torch, JavaScript/PDF actions, raster rendering, or external PDF tools.

## Implementation

- `PdfNamedDestinationExtractor` now rejects destination name-tree nodes whose `/Kids` key is present but does not resolve to a list array.
- `PdfActionReviewExtractor` applies the same gate before building the local destination map used by annotation/link promotion.
- `PdfOutlineExtractor` applies the gate in both destination-view and action-destination name-tree walkers.
- `PdfMetadataExtractor` applies the same raw-operand gate for document destination metadata.

The WordPress smoke fixture proves a malformed catalog `/Names /Dests` node with `/Kids /ScalarKid` no longer exposes its local `Scalar Kids Target` and `Review Summary` rows, while `/Dests << /LegacyOk ... >>`, URI links, and searchable page text remain intact.

## Red-first evidence

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationScalarKidsBoundaryCurrentBaseTest.php`

Before implementation:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects scalar Kids operands before named-destination metadata and outline review
FAIL keeps scalar Kids name-tree rows out of annotation promotion and visible WordPress text

1 test files, 4 assertions, 2 failures
```

After implementation:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS rejects scalar Kids operands before named-destination metadata and outline review
PASS keeps scalar Kids name-tree rows out of annotation promotion and visible WordPress text

1 test files, 45 assertions, 0 failures
```

Focused family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfNamedDestination*Test.php' -o -name 'PdfOutlineNamedDestination*Test.php' -o -name 'PdfOutlineMetadataLightweightNamedDestinationBoundaryCurrentBaseTest.php' -o -name 'PdfLinkAnnotation*Destination*Test.php' -o -name 'PdfAnnotationLinkDestination*Test.php' \) | sort)

67 test files, 2304 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-scalar-kids-currentbase.php
```

Exited `0` and emitted review metadata with:

- `destination_names=["LegacyOk"]`
- `document_destination_names=["LegacyOk"]`
- `toc_titles=["Legacy Outline"]`
- `promoted_link_objects=[8,9]`
- `scalar_kids_destinations_rejected=true`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`
- `executes_pdf_actions=false`

## Non-overlap

This does not repeat accepted named-destination `/Limits`, direct/indirect `/Kids` arrays, invalid kids-array entries, direct child dictionaries, sparse `/Names` arrays, missing-value pair resynchronization, duplicate catalog `/Names`, duplicate `/Dests`, duplicate destination dictionary keys, object-stream, xref, alias, PDFDocEncoding, malformed UTF-16/UTF-8, view-mode, coordinate, page-operand, or outline metadata slices. The bounded behavior is only a present-but-scalar `/Kids` operand on a destination name-tree node.

## Dependency closure

No new support component is needed. The slice reuses the native PDF object parser, raw metadata dictionary reader, destination name-tree walkers, outline/action review maps, link span promotion, supplied pdftext page arrays, and WordPress smoke renderer. Full upstream model/OCR parity remains intentionally out of scope under the current markerPDF no-GPU directive.
