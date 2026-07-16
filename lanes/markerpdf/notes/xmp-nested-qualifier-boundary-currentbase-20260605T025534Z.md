# markerPDF XMP Nested Qualifier Boundary Current Base

Date: 2026-06-05 02:55 UTC

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T025534Z`

Accepted base: `e6bb1432de0348523f05e21080b5bfcf976c59be`

## Behavior

`PdfMetadataExtractor` now reads XMP list fields and language alternatives
from direct RDF collection items only. Nested qualifier collections inside
qualified XMP properties no longer leak into document authors, keywords,
language alternative fallback, or rejected-stream review counts.

Accepted catalog `/Metadata` streams still promote document XMP only from
`/Type /Metadata /Subtype /XML`. Rejected XML-like streams still produce
redacted `catalog.metadata_stream_review.xmp_summary` rows without exposing
XMP title, author, keyword, or qualifier text.

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at
  `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`; searchable-PDF text and metadata
  are loaded before OCR/layout/model stages, so this PHP lane owns the native
  metadata parser boundary under the no-GPU scope.
- XMP/RDF qualified properties can carry the actual property value in
  `rdf:value` while sibling elements are qualifiers. Qualifiers may themselves
  contain RDF collections, but those nested `rdf:li` nodes are not members of
  the enclosing `dc:creator`, `dc:subject`, or `rdf:Alt` property collection.

## Red-First Evidence

Before the source edit, a direct probe with a valid catalog XMP packet returned
nested qualifier list items as document metadata:

```text
authors: ["Ada Editor", "copy editor qualifier", "Data Liberation Team"]
keywords: ["wordpress", "nested-qualifier", "internal qualifier keyword"]
```

The failure came from descendant-wide `rdf:li` traversal in XMP list extraction.

## Patch

`xmpListValues()` and `preferredAltText()` now use the existing
`xmpRdfCollectionItems()` helper. That helper returns direct `rdf:li` children
or the direct `rdf:Bag`/`rdf:Seq`/`rdf:Alt` item list for the current property,
while `xmpQualifiedTextValue()` still extracts `rdf:value` from qualified
property wrappers.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpNestedQualifierBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS extracts direct XMP RDF collection values without nested qualifier list leakage
PASS summarizes rejected XMP streams using direct RDF collection counts only

1 test files, 47 assertions, 0 failures
```

Adjacent XMP metadata family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
11 test files, 1245 assertions, 0 failures
```

Broader metadata family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadata*Test.php
23 test files, 1784 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xmp-nested-qualifier-boundary-currentbase.php
```

Passed. The smoke emits `authors_exclude_nested_qualifier_lists=true`,
`keywords_exclude_nested_qualifier_lists=true`,
`nested_qualifier_text_excluded=true`, `decoy_xmp_excluded=true`,
`visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Syntax:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfMetadataXmpNestedQualifierBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xmp-nested-qualifier-boundary-currentbase.php
```

All reported no syntax errors.

Diff hygiene:

```text
git diff --check -- lanes/markerpdf
```

Run after this note/status update as final verification.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PHP behavior tests move `1319 -> 1321` with 2 new PASS cases.
- Focused assertions add 47 in
  `PdfMetadataXmpNestedQualifierBoundaryCurrentBaseTest.php`.
- WordPress scenarios move `1273 -> 1274` with the nested-qualifier XMP smoke.
- No dashboard/root publication files were edited.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object
scanner, stream decoder, DOM-based XMP parser with `LIBXML_NONET`, metadata
merger, redacted review summarizer, text extractor, and WordPress smoke path.
No Python, pdftext, pypdfium/PDFium, Surya, Texify, Torch, OCR, image raster,
online service, or external PDF tool execution was run.

## Non-Overlap

This does not repeat accepted catalog `/Metadata` type/subtype validation,
packet padding/appended-decoy trimming, XML comment/DOCTYPE/CDATA false-root
handling, BOM-less UTF-16 decoding, undeclared encoding fallback,
generation-exact FileSpec XMP provenance, encrypted metadata source priority,
PDF/A extension schema review, XMP qualified `rdf:value` extraction, or
metadata xref/current-trailer selection. The new behavior is specifically
direct RDF collection-item boundaries when qualified properties contain nested
qualifier collections.

## Next Task

Continue with non-overlapping native metadata/parser boundaries such as
annotation/form metadata review, page geometry, image/filter metadata, xref
repair behavior, or remaining catalog/page review metadata under the no-GPU
markerPDF scope.
