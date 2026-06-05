# XMP Attribute Boundary Current Base

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T114650Z`

Base: `b0b72874e66840fd6a7239e395a47d03eb6b09cc`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDFs through a
document metadata extraction boundary before OCR/layout/model stages. In this
native no-GPU lane, catalog `/Metadata` XMP streams remain the source for
WordPress document metadata when they are `/Type /Metadata /Subtype /XML`.

Compact RDF attributes on a top-level `rdf:Description` are XMP property
values. A `dc:creator` attribute is a single literal creator value, so a
comma-bearing name such as `Doe, Jane` must not be split as two authors.
`dc:subject` attributes remain a keyword string and continue to split into
review keywords.

## Behavior

`PdfMetadataExtractor::xmpListValues()` now handles top-level XMP attributes by
property:

- `dc:creator="Doe, Jane"` yields one author, `Doe, Jane`;
- `dc:subject="wordpress, xmp-attribute; compact-rdf"` still yields three
  keywords;
- accepted document XMP and rejected XML stream summaries use the same
  compact-RDF boundary;
- trailing XMP packet decoys stay outside metadata and visible WordPress text.

## Red-First Evidence

Before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpAttributeBoundaryCurrentBaseTest.php`

Result: `1 test files / 18 assertions / 2 failures`

Failures:

- accepted compact RDF metadata split `Doe, Jane` into `Doe` and `Jane`;
- rejected stream summary reported `author_count=2` instead of `1`.

## Verification

Focused XMP attribute boundary:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpAttributeBoundaryCurrentBaseTest.php`

Result: `1 test files / 43 assertions / 0 failures`

Adjacent metadata/XMP family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php`

Result: `23 test files / 1812 assertions / 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-attribute-boundary-currentbase.php`

Result: passed. The smoke emits `title_from_xmp_attribute=true`,
`creator_comma_preserved=true`, `subject_keywords_split=true`,
`packet_boundary_applied=true`, `trailing_decoy_excluded=true`,
`visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

PHP lint:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php`
- `php -l lanes/markerpdf/tests/PdfMetadataXmpAttributeBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-attribute-boundary-currentbase.php`

Result: no syntax errors.

Required whitespace check:

`git diff --check -- lanes/markerpdf`

Result: passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1787 -> 1789` from two new focused TestRunner PASS cases.
- `wordpressScenarios`: `1627 -> 1628` from the new WordPress compact-RDF XMP
  attribute smoke.

## Non-Overlap

This does not repeat accepted catalog `/Metadata` null/direct/unresolved/
unreadable boundaries, non-metadata XML stream rejection, packet padding,
complete-packet fallback, unpaired-begin handling, instruction filtering,
DTD/entity rejection, CDATA/comment root selection, namespace wrapper
filtering, self-closing/empty roots, typed-node parsing, language alternatives,
qualified/nested values, FileSpec XMP generation exactness, encrypted metadata
source priority, OutputIntent/PieceInfo/name-tree metadata review, xref repair,
CMap/font/text extraction, images, annotations, forms, OCR, or model
execution.

The bounded behavior is only compact RDF XMP attribute parsing for document
metadata and rejected-stream summaries.

## Dependency Closure

No new support component is needed. This slice reuses native PHP PDF stream
decoding, catalog metadata boundary validation, DOM-based XMP parsing, trailer
Info fallback, text extraction, and the existing WordPress smoke pattern.
GPU/model/OCR/PDFium/Python execution remains intentionally out of scope under
the current no-GPU markerPDF directive.
