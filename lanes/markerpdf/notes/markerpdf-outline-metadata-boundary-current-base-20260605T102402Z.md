# markerpdf outline metadata scalar boundary current base

Slice: `markerpdf-outline-metadata-boundary-current-base-20260605T102402Z`
Base: `339f124190d9d276d42f196db494286344048c17`

## Source-truth behavior

This no-GPU markerPDF slice maps the native PDF outline/title boundary used before WordPress document-outline, TOC/navigation, and remote-action review metadata. An indirect outline `/Title` object must contain one top-level string/name scalar token. A comment-only tail remains PDF whitespace, but an indirect object such as `(Title) /A 12 0 R /Next 99 0 R` is malformed for title ownership and must not leak a partial bookmark title while dropping action/sibling tokens.

## Patch

- `PdfMetadataExtractor` now resolves outline titles through an outline-specific single-token string/name guard before `document_outline` metadata.
- `PdfOutlineExtractor` records whether parsed direct objects and object-stream members consumed a single top-level value, and requires that boundary for indirect outline title references used by TOC/navigation/remote-action review.
- Added focused coverage for malformed indirect title scalar objects with trailing action tokens plus a valid comment-tailed indirect title object.
- Added WordPress smoke `wordpress-pdf-outline-metadata-scalar-boundary-currentbase.php`.

## Evidence

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataScalarBoundaryCurrentBaseTest.php`

Result before implementation: `1 test files, 9 assertions, 2 failures`; the malformed title object produced `item_count=3` and TOC titles `["Scalar Boundary Spoof","Stale Child Under Malformed Scalar","Scalar Boundary Appendix"]` instead of the single valid appendix row.

Green:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataScalarBoundaryCurrentBaseTest.php`

Result after implementation: `1 test files, 37 assertions, 0 failures`.

Adjacent outline gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataScalarBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataTitleBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataMalformedUtf16TitleBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataLastBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php`

Result: `7 test files, 616 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-scalar-boundary-currentbase.php`

Result: emits `malformed_scalar_rejected=true`, `malformed_child_excluded=true`, `malformed_action_excluded=true`, `outline_titles=["Import Scalar Appendix"]`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted PageLabels prefix scalar handling, outline `/Last`, `/Prev`, root type, missing parent, malformed UTF-16 title, titleless bridge, comment operand, xref-owner, remote action, named-destination, or page-review outline slices. It is specifically the indirect outline `/Title` scalar object boundary shared by document metadata and navigation extractors.

## Dependency closure

No new support component is needed. The patch reuses the existing native PHP PDF dictionary/object parser and no-GPU metadata/navigation review paths. GPU/OCR/Surya/Texify/model execution remains intentionally out of scope.

## Next

Continue with non-overlapping native searchable-PDF parser behavior around metadata, outlines, fonts/CMaps, stream filters, xref repair, annotations/forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
