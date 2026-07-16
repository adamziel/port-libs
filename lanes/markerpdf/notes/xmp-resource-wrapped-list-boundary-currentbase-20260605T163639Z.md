# markerPDF XMP Resource-Wrapped List Boundary Current Base

Date: 2026-06-05 16:36 UTC

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T163639Z`

Accepted base: `ccb9e1a776eb191b19ab2067dcd5ea739b241b75`

## Behavior

`PdfMetadataExtractor` now recognizes XMP RDF list containers nested directly
inside `rdf:Description` resource wrappers. This covers producer shapes such as
`dc:creator -> rdf:Description -> rdf:Seq`, `dc:subject -> rdf:Description ->
rdf:Bag`, and language alternatives wrapped as `rdf:Alt -> rdf:Description ->
rdf:Alt`.

Nested qualifier lists under non-RDF properties such as `xmp:roles`,
`xmp:labels`, or `pdf:Producer` remain excluded from document metadata and from
rejected-stream review counts. Trailing XMP packets remain bounded out of the
current metadata packet, and XMP payload text remains excluded from visible
WordPress paragraphs.

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at
  `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- MarkerPDF/PDFium-style metadata extraction happens before OCR/layout/model
  stages, so this native no-GPU PHP lane owns searchable-PDF XMP parsing.
- RDF/XMP properties may represent a resource value with an `rdf:Description`
  wrapper. Direct RDF containers under that wrapper are still the property
  collection; nested qualifier containers under non-RDF properties are not.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpResourceWrappedListBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL extracts XMP list containers nested in RDF resource wrappers without qualifier leakage
Values are not identical
Expected: 'Current Resource Wrapped XMP Title'
Actual: 'Titre de ressource ignoreCurrent Resource Wrapped XMP Title'
FAIL summarizes rejected resource-wrapped XMP lists without exposing list text
Values are not identical
Expected: 2
Actual: 1

1 test files, 16 assertions, 2 failures
```

## Patch

`xmpRdfCollectionItems()` now checks direct RDF containers through
`xmpRdfContainerItems()` and direct `rdf:Description` resource wrappers through
`xmpRdfResourceWrappedCollectionItems()`. The traversal only follows RDF
namespace children, preserving the existing qualifier-exclusion boundary.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpResourceWrappedListBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS extracts XMP list containers nested in RDF resource wrappers without qualifier leakage
PASS summarizes rejected resource-wrapped XMP lists without exposing list text

1 test files, 48 assertions, 0 failures
```

Adjacent structural XMP tests:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpQualifiedValueBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpNestedQualifierBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpTypedNodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpAttributeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpSplitDescriptionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpTextSubjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpInheritedLanguageBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpLangAltBoundaryCurrentBaseTest.php
8 test files, 344 assertions, 0 failures
```

Full XMP focused sweep:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php
29 test files, 1248 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xmp-resource-wrapped-list-boundary-currentbase.php
```

Passed. The smoke emits `title_from_resource_wrapped_alt=true`,
`authors_from_resource_wrapped_seq=true`,
`keywords_from_resource_wrapped_bag=true`,
`nested_qualifier_text_excluded=true`, `decoy_xmp_excluded=true`,
`visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PHP behavior tests add 2 new PASS cases.
- Focused assertions add 48 in
  `PdfMetadataXmpResourceWrappedListBoundaryCurrentBaseTest.php`.
- WordPress scenarios add 1 smoke:
  `wordpress-pdf-xmp-resource-wrapped-list-boundary-currentbase.php`.
- No root coordination, dashboard, or publication files were edited.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP PDF
object scanner, stream decoder, DOM XMP parser with `LIBXML_NONET`, redacted
XMP review summarizer, metadata merger, text extractor, and WordPress smoke
path. No Python, pdftext, pypdfium/PDFium, OCR, Surya, Texify, Torch, online
service, or external PDF tool execution was run.

## Non-Overlap

This does not repeat accepted XMP packet padding, comments, CDATA, entity
rejection, namespace root selection, typed nodes, compact attributes,
`rdf:value` qualified-value extraction, nested qualifier direct collection
exclusion, text subject splitting, inherited language alternatives, catalog
`/Metadata` null/direct/unreadable/reference boundaries, or encrypted metadata
source priority. The new behavior is specifically direct RDF list containers
inside resource-wrapper `rdf:Description` nodes.

## Next Task

Continue with non-overlapping native markerPDF metadata/parser work around
fonts, CMaps, xref repair, annotations, forms, page geometry, image/filter
metadata, or supplied-boundary table/equation handoffs under the no-GPU scope.
