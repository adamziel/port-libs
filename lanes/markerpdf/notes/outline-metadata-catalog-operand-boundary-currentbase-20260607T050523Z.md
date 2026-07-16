# markerpdf outline metadata catalog operand boundary current-base

Slice: `markerpdf-outline-metadata-boundary-current-base-20260607T050523Z`

Accepted base: `230391565eeb5c055dbe4eb14a2f8aac16d9405b`

## Behavior

Catalog `/Outlines` is a single-value native PDF dictionary boundary. A
malformed catalog entry such as `/Outlines 5 0 R 8 0 R /PageMode /UseOutlines`
now fails closed before bookmark metadata is promoted. The importer records a
payload-free `document_outline_boundary_review` with the selected and trailing
reference object numbers, while document outline rows, TOC/navigation rows,
lightweight `pdf_toc` rows, action targets, and outline-local metadata streams
remain suppressed.

This maps the no-GPU markerPDF searchable-PDF path: malformed outline metadata
is review-only parser metadata, not WordPress paragraph text and not executable
PDF action content.

## Evidence

Red-first before source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataCatalogOperandBoundaryCurrentBaseTest.php`

Result: `1 test files, 3 assertions, 2 failures`; failures showed the malformed
catalog `/Outlines` entry still imported `Ambiguous Catalog Outline Chapter`
into metadata and TOC rows.

After source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataCatalogOperandBoundaryCurrentBaseTest.php`

Result: `1 test files, 28 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-outline-catalog-operand-boundary-currentbase.php`

Result: emitted
`review_status=rejected_malformed_catalog_outlines_operand`,
`outlines_operand_count=2`, `toc_suppressed=true`,
`lightweight_toc_suppressed=true`, `navigation_actions_suppressed=true`,
`payload_redacted=true`, `visible_text_imported=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP PDF dictionary
scanning, outline extraction, metadata review, and lightweight text extraction.
It does not require Python, OCR, Surya, Texify, Torch, GPU execution,
pypdfium/PIL rendering, external PDF tools, live services, or model workers.

## Non-Overlap

This does not repeat accepted duplicate top-level catalog `/Outlines` root
selection, outline root stream rejection, root/item `/Metadata` stream review,
duplicate root/item metadata keys, outline traversal duplicate keys, xref
repair, PageLabels, named destinations, action-chain review, annotations,
forms, security, image/filter, font/CMap, or supplied table/equation slices.
The bounded behavior is only extra top-level operands attached to the selected
catalog `/Outlines` entry.
