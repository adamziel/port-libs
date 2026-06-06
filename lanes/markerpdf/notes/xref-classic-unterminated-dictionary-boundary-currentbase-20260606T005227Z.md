# Classic XRef Rebuild Boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260606T005227Z`

Accepted base: `c966e5ff0216e9268907832b43b9f7429fe085a0`

## Source Truth

Pinned upstream markerPDF delegates searchable-PDF text and document metadata extraction to parser-backed PDF dependencies before any OCR/model fallback. Under the current no-GPU markerPDF scope, native PHP classic xref rebuild must therefore only select top-level `xref`, `trailer`, and `startxref` tokens, and incremental xref repair must keep explicit xref rows authoritative over unreferenced direct-object decoys.

## Behavior

This slice covers an adjacent boundary to the accepted unterminated literal-string xref guard. A current classic xref table/trailer is followed by decoy catalog, page, metadata, and EmbeddedFiles objects, then an unclosed top-level dictionary containing a plausible classic `xref`, `trailer`, and damaged `startxref`.

Before the fix, text extraction selected the decoy page because malformed `<<`/`[` composite ownership fell through while unterminated literal strings were already fail-closed. `PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now reject classic xref table candidates that occur after an unbalanced array or dictionary opener in the same top-level scan.

The focused fixture proves current page text, XMP title, Info title/author, EmbeddedFiles payload, checksum state, and attachment summary remain rooted at the current table. Decoy page text, decoy metadata, and decoy attachment filenames are excluded.

While widening verification, the adjacent hybrid classic-table `/XRefStm` + indirect `/Prev` helper fixture exposed an omitted-row repair boundary. Classic tables that resolve `/Prev` through a hybrid companion xref stream now stop promoting same-generation omitted direct objects in the current update window, so post-helper direct decoys cannot shadow inherited previous rows.

## Evidence

Red-first focused run before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicUnterminatedDictionaryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL skips unterminated dictionary xref decoys before WordPress imports
Expected: Current unterminated dictionary xref page / Open dictionary xref skipped
Actual: Unterminated dictionary xref decoy page / Open dictionary root leak
1 test files, 4 assertions, 1 failures
```

Focused run after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicUnterminatedDictionaryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS skips unterminated dictionary xref decoys before WordPress imports
1 test files, 30 assertions, 0 failures
```

Targeted xref boundary regression set:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicUnterminatedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainHybridTableCompressedPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationCompressedPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectPrevObjectStreamCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 593 assertions, 0 failures
```

Adjacent classic xref family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfXrefClassic.*CurrentBaseTest\.php$' | sort)
Focused test run: 11 selected test files (root lock skipped)
11 test files, 984 assertions, 0 failures
```

Wider xref/trailer adjacency sweep:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/(PdfXref|PdfParserXref|PdfMetadata.*Trailer|PdfAttachmentTrailer|PdfAcroFormFieldsTrailer|PdfNamedDestinationTrailer|PdfOutlineMetadataTrailer).*Test\.php$' | sort)
Focused test run: 109 selected test files (root lock skipped)
109 test files, 3335 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-unterminated-dictionary-currentbase.php
```

The smoke emits `uses_current_classic_trailer_root=true`, `keeps_current_metadata_root=true`, `keeps_current_info_root=true`, `imports_current_attachment=true`, `attachment_summary_current_only=true`, `current_attachment_checksum_matches=true`, `excludes_unterminated_dictionary_page=true`, `excludes_unterminated_dictionary_metadata=true`, `excludes_unterminated_dictionary_attachment=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, with two current Gutenberg paragraph comments.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted damaged or stale numeric `startxref` repair, malformed/missing/signed/tail `startxref` operands, commented `xref` or `startxref`, name/composite/literal/string/stream-owned decoys, unterminated literal-string decoys, post-EOF or post-startxref trailers, malformed rows, punctuation row suffixes, zero-count sections, trailing subsections, overdeclared counts, header garbage, malformed hex openers, form-feed/NUL/comment whitespace, generation-offset repair, forward `/Prev` repair, ordinary xref-stream/object-stream repair, metadata-only trailer root selection, font/CMap/image/filter/annotation/form/security behavior, OCR/model work, or supplied table/equation handoffs.

The bounded behavior is malformed top-level array/dictionary opener ownership while scanning for classic xref table rebuild boundaries, plus the adjacent hybrid classic-table indirect `/Prev` handoff where companion xref-stream rows must not allow omitted direct-object decoys to shadow inherited rows.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, classic xref table parser, startxref rebuild selection, trailer root selection, metadata extractor, embedded-file extractor, attachment preflight summarizer, stream decoder, and WordPress smoke path. Live OCR, pypdfium/PDFium rendering, Surya/Torch/Texify models, Streamlit/FastAPI workers, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
