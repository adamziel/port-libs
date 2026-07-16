# markerPDF XMP Sparse List Fallback Boundary Current Base

Date: 2026-06-05 17:15 UTC

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T171501Z`

Accepted base: `601ffedf79c2212413bd91bec50d947b009a257d`

## Behavior

`PdfMetadataExtractor` now skips empty RDF list placeholder candidate sets
before falling through to later resource-wrapped XMP metadata arrays. This
covers sparse producer output such as:

- `dc:creator -> rdf:Seq -> empty rdf:li`, followed by
  `rdf:Description -> rdf:Seq` with the actual authors;
- `dc:subject -> rdf:Bag -> empty rdf:li`, followed by
  `rdf:Description -> rdf:Bag` with the actual keywords;
- `dc:title -> rdf:Alt -> empty rdf:li`, followed by
  `rdf:Description -> rdf:Alt` with the default title.

The fix prevents concatenated fallback text such as
`Sparse List Author OneSparse List Author Two` and keeps XMP payload text out
of visible WordPress paragraphs.

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at
  `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- MarkerPDF/PDFium-style document metadata extraction happens before OCR,
  layout, and model stages. Under the current no-GPU markerPDF lane scope,
  native searchable-PDF XMP parsing is in scope while OCR/model execution is
  intentionally out of scope.
- XMP RDF arrays are document metadata, not body text. Empty array entries are
  not meaningful metadata values and should not block a later valid RDF array
  representation in the same property.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpSparseListFallbackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL falls through empty XMP RDF list placeholders to resource-wrapped metadata arrays
Expected: 'Current Sparse List XMP Title'
Actual: 'Titre sparse ignoreCurrent Sparse List XMP Title'
FAIL summarizes rejected sparse-list XMP streams without concatenating hidden list text
Expected: 2
Actual: 1
1 test files, 16 assertions, 2 failures
```

## Patch

`xmpRdfCollectionItems()`, `xmpRdfContainerItems()`, and
`xmpRdfResourceWrappedCollectionItems()` now return a candidate item set only
when at least one item has an extractable XMP value. Empty direct `rdf:li`
placeholder sets no longer short-circuit a later valid RDF container or
resource-wrapper fallback.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpSparseListFallbackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS falls through empty XMP RDF list placeholders to resource-wrapped metadata arrays
PASS summarizes rejected sparse-list XMP streams without concatenating hidden list text
1 test files, 48 assertions, 0 failures
```

Full focused XMP sweep:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php
30 test files, 1296 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xmp-sparse-list-fallback-currentbase.php
```

Passed. The smoke emits `title_from_sparse_list_fallback=true`,
`authors_not_concatenated=true`, `keywords_not_concatenated=true`,
`empty_placeholder_excluded=true`, `decoy_xmp_excluded=true`,
`visible_text_excludes_xmp=true`, `packet_boundary_applied=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and whitespace:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfMetadataXmpSparseListFallbackBoundaryCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-sparse-list-fallback-currentbase.php` passed.
- `git diff --check -- lanes/markerpdf` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PHP behavior tests add 2 new PASS cases.
- Focused assertions add 48 in
  `PdfMetadataXmpSparseListFallbackBoundaryCurrentBaseTest.php`.
- WordPress scenarios add 1 smoke:
  `wordpress-pdf-xmp-sparse-list-fallback-currentbase.php`.
- No root coordination, dashboard, or publication files were edited.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP PDF
object scanner, stream decoder, DOM XMP parser with `LIBXML_NONET`, redacted
XMP review summarizer, metadata merger, text extractor, and WordPress smoke
path. No Python, pdftext, pypdfium/PDFium, OCR, Surya, Texify, Torch, online
service, or external PDF tool execution was run.

## Non-Overlap

This does not repeat accepted XMP packet begin/end priority, complete packet
fallback, namespace spoofing, empty/self-closing root skipping, compact RDF
attributes, typed nodes, qualified `rdf:value` extraction, direct nested
qualifier exclusion, resource-wrapped list extraction, split-description empty
field fallback, inherited language alternatives, text subject splitting,
declared encoding, metadata stream filter dictionary boundaries, catalog
`/Metadata` null/direct/unreadable/reference boundaries, encrypted metadata
source priority, OutputIntent/PieceInfo/name-tree metadata review, xref repair,
forms, annotations, OCR, or model execution.

The new bounded behavior is specifically empty RDF list placeholder candidate
sets inside an XMP property before a later valid resource-wrapped RDF list.

## Next Task

Continue with non-overlapping native markerPDF parser behavior around fonts,
CMaps, xref repair, annotations, forms, page geometry, image/filter metadata,
or supplied-boundary table/equation handoffs under the no-GPU scope.
