# markerPDF classic xref stream-owned trailer boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T065757Z`

Base accepted HEAD: `13a03f44f03f1a17e55a3c59df211c0698381848`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF extraction through parser-backed `pdftext`/PDFium behavior before OCR/model fallback. In this no-GPU native PHP lane, classic xref rebuild is a parser dependency boundary before WordPress import.

PDF classic xref table trailers are top-level syntax after xref subsection rows. A byte sequence spelling `trailer << ... >>` inside a direct stream object body is payload, not the xref table trailer, and must not redirect page text, XMP/Info metadata, EmbeddedFiles, or attachment preflight roots.

## Implementation

`PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now pass direct-object definition ranges into classic xref table trailer scanning. The scanner skips byte ranges owned by direct object bodies while looking for the table's `trailer` keyword.

The focused fixture keeps current and decoy catalog/page/metadata/attachment objects, writes a classic xref table, places a direct stream object containing a fake decoy trailer before the real trailer, and damages `startxref`. Before the fix, the native scanner selected the decoy stream-owned trailer. After the fix, all four import surfaces select the current trailer.

## Evidence

Pre-fix probe:

```text
array (
  0 =>
  array (
    0 => 'Stream trailer decoy page',
    1 => 'Stream-owned trailer leak',
  ),
  1 => 'Stream Trailer Decoy Title',
  2 => 'Stream Trailer Decoy Info',
  3 => NULL,
)
```

Focused test after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS skips stream-owned trailer dictionaries during classic xref rebuild before WordPress imports
1 test files, 343 assertions, 0 failures
```

WordPress smoke after fix:

```text
php lanes/markerpdf/examples/wordpress-pdf-classic-xref-stream-trailer-boundary-currentbase.php
stream_owned_trailer_skipped=true
decoy_stream_trailer_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

## Non-Overlap

This is not a repeat of accepted damaged-startxref rebuild, stale valid startxref repair, EOF-bounded rebuild, comment/array/composite/name/literal xref decoys, malformed xref row rejection, stream-owned startxref table offset rejection, xref-stream owner boundaries, or `/Prev` chain repair. The bounded behavior is specifically stream-owned `trailer` dictionary bytes encountered while scanning a classic xref table body.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, classic xref table parser, trailer parser, metadata extractor, embedded-file extractor, attachment preflight path, text extractor, and WordPress smoke renderer. GPU/model/OCR, pdftext execution, pypdfium2/PDFium execution, PIL, Surya/Torch, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external PDF tools were not run and remain intentionally out of scope for this no-GPU native PHP slice.
