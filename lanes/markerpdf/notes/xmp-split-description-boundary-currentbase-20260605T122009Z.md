# XMP Split Description Boundary Current Base

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T122009Z`

Base: `2eb3d4038b9e93816e26565fe8d737d48cc80c63`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDFs through a
document metadata extraction boundary before OCR/layout/model stages. In this
native no-GPU lane, catalog `/Metadata` XMP streams remain the source for
WordPress document metadata when they are `/Type /Metadata /Subtype /XML`.

XMP packets commonly split document metadata across several top-level
`rdf:Description` nodes. Empty `dc:creator` or `dc:subject` containers in an
earlier description are absent values; they must not block later non-empty list
properties from the same XMP packet.

## Behavior

`PdfMetadataExtractor::xmpListValues()` now continues past empty top-level XMP
list properties before selecting authors or keywords:

- empty `dc:creator` and `dc:subject` RDF containers are ignored;
- later non-empty top-level descriptions supply authors and keywords;
- rejected XML metadata-stream summaries count the later non-empty authors and
  keywords without exposing the actual text values;
- trailing XMP packet decoys stay outside metadata and visible WordPress text.

## Red-First Evidence

Before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpSplitDescriptionBoundaryCurrentBaseTest.php`

Result: `1 test files / 17 assertions / 2 failures`

Failures:

- accepted document XMP fell back to Info author instead of later XMP creators;
- rejected stream summary omitted `authors` and `keywords` from `field_names`.

## Verification

Focused split-description XMP boundary:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpSplitDescriptionBoundaryCurrentBaseTest.php`

Result: `1 test files / 42 assertions / 0 failures`

Adjacent metadata/XMP family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php`

Result: `24 test files / 1854 assertions / 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-split-description-boundary-currentbase.php`

Result: passed. The smoke emits `title_from_xmp=true`,
`authors_from_later_description=true`, `keywords_from_later_description=true`,
`packet_boundary_applied=true`, `trailing_decoy_excluded=true`,
`visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

PHP lint:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php`
- `php -l lanes/markerpdf/tests/PdfMetadataXmpSplitDescriptionBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-split-description-boundary-currentbase.php`

Result: no syntax errors.

Required whitespace check:

`git diff --check -- lanes/markerpdf`

Result: passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1814 -> 1816` from two new focused TestRunner PASS cases.
- `wordpressScenarios`: `1649 -> 1650` from the new WordPress split-description
  XMP smoke.

## Non-Overlap

This does not repeat accepted catalog `/Metadata` null/direct/unresolved/
unreadable boundaries, non-metadata XML stream rejection, packet padding,
complete-packet fallback, unpaired-begin handling, instruction filtering,
DTD/entity rejection, CDATA/comment root selection, namespace wrapper
filtering, self-closing/empty roots, compact RDF attributes, language
alternatives, qualified/nested values, FileSpec XMP generation exactness,
encrypted metadata source priority, OutputIntent/PieceInfo/name-tree metadata
review, xref repair, CMap/font/text extraction, images, annotations, forms,
OCR, or model execution.

The bounded behavior is only continuing past empty top-level XMP list fields
before selecting later non-empty split-description authors and keywords.

## Dependency Closure

No new support component is needed. This slice reuses native PHP PDF stream
decoding, catalog metadata boundary validation, DOM-based XMP parsing, trailer
Info fallback, text extraction, and the existing WordPress smoke pattern.
GPU/model/OCR/PDFium/Python execution remains intentionally out of scope under
the current no-GPU markerPDF directive.
