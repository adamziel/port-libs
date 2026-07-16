# Classic xref name-offset startxref boundary

Slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T040115Z`

Accepted base: `02fcdbdf4622561a72beafa8b0451f7fae48dcd2`

## Source truth

markerPDF's searchable-PDF path obtains page text and document metadata through PDFium/pdftext-style PDF parsing before model execution. In the native no-GPU scope, classic xref recovery must therefore treat `xref` and `startxref` as PDF keywords, not name tokens, and repair damaged classic startxref pointers to the latest real top-level classic xref table before the selected final `startxref` token.

## Behavior

This patch adds a boundary where the final valid `startxref` numeric value points inside the file to the `x` in a top-level `/xref` name-token decoy appended after the current classic table. Before the fix, the text extractor selected the decoy page, and after the first parser fix the duplicated metadata/embedded-file parsers could still select decoy XMP and attachment roots.

The native parser now:

- rejects explicit xref-table offsets unless they land on a real `xref` keyword boundary;
- rebuilds an invalid in-file startxref pointer before the final `startxref` token to the latest prior real classic table;
- applies the same boundary in text, metadata, and embedded-file extraction paths.
- preserves the accepted xref-stream `/Encrypt null` boundary so previous encrypted trailer `/Info` dictionaries are not inherited through metadata fallback.

## Evidence

Red-first focused run before the source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php`

Result: `1 test files, 184 assertions, 1 failures`; the new case imported `Name-offset xref decoy page` / `Name-offset root leak`.

After the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php`

Result: `1 test files, 204 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-name-offset-currentbase.php`

Result: emits `uses_current_page_text=true`, `repairs_name_token_startxref_offset=true`, `metadata_title_current=true`, `info_title_current=true`, `embedded_file_current=true`, `excludes_decoy_metadata=true`, `excludes_decoy_attachment=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Adjacent xref/trailer group:

`php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/(PdfXref|PdfParserXref|PdfMetadata.*Trailer|PdfAttachmentTrailer|PdfAcroFormFieldsTrailer|PdfNamedDestinationTrailer|PdfOutlineMetadataTrailer).*Test\.php$' | sort)`

Result: `66 test files, 1518 assertions, 0 failures`.

## Dependency closure

No new support component is needed. This reuses the existing native PHP PDF tokenizer, xref-table parser, metadata extractor, and EmbeddedFiles extractor. GPU/OCR/model execution remains intentionally out of scope for this markerPDF lane.

## Non-overlap

This does not repeat accepted damaged-out-of-file startxref rebuild, stale older-table startxref rebuild, post-EOF xref garbage, commented xref/startxref, array/composite-contained decoys, name-token `/startxref`, or `xref/Decoy` name-delimited pseudo-table coverage. It specifically covers a final numeric startxref value that points to the `x` inside a top-level `/xref` name token.
