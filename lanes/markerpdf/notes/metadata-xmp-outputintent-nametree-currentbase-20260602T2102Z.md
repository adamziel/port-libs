# markerPDF metadata XMP OutputIntent NameTree currentbase

Micro-slice: `metadata-xmp-outputintent-nametree-currentbase`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF extraction page/block based: `marker/pdf/extract_text.py::get_text_blocks()` delegates to `pdftext.extraction.dictionary_output(...)`, while output assembly keeps extracted content and metadata separated in `marker/output.py`.

Source links:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/output.py`

Relevant PDF parser behavior for this slice: catalog `/Names` trees can map names to arbitrary dictionaries, including action dictionaries and generic extension dictionaries. Those value dictionaries can carry nested `/Metadata` XMP streams and `/OutputIntents`, but they are local review metadata. They must not override catalog-level XMP/PDF-A roots, emit ICC/XMP payload bytes, run actions, or become visible WordPress paragraph text.

## Behavior

The focused fixture builds a current xref-stream selected catalog with:

- root `/Metadata` and root `/OutputIntents`;
- catalog `/Names` entries for `/JavaScript`, `/IDS`, and `/Dests`;
- name-tree value dictionaries with nested `/Metadata 5 0 R` and `/OutputIntents [13 0 R]`;
- stale appended objects after the current `%%EOF` that try to replace the catalog, Info dictionary, content stream, JavaScript entries, XMP packet, and OutputIntent.

`PdfMetadataExtractor` now adds `metadata_review` and `output_intents_review` to generic catalog name-tree dictionary review rows by reusing the existing native metadata-stream and OutputIntent provenance helpers. The review rows include hashes, compact XMP field summaries, PDF/A identifiers, and profile hashes, while keeping payload text and profile bytes redacted.

Document-level metadata remains rooted at the current catalog XMP and root OutputIntent. The visible WordPress text is still only `Current XMP OutputIntent NameTree Body`; nested name-tree XMP titles, ICC profile bytes, JavaScript payloads, and stale appended payloads stay out of visible text and encoded document metadata.

## Evidence

Red-first focused test:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpOutputIntentNameTreeCurrentBaseTest.php
```

Before the source change, after correcting the fixture limits:

```text
FAIL keeps name-tree XMP and OutputIntent dictionaries review-only on current xref catalog
Expected: 5
Actual: NULL
1 test files, 12 assertions, 1 failures
```

Focused result:

```text
PASS keeps name-tree XMP and OutputIntent dictionaries review-only on current xref catalog
1 test files, 34 assertions, 0 failures
```

Regression set:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpOutputIntentNameTreeCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataTrailerInfoNameTreeCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPdfaAssociatedNameTreeCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataNameTreePieceInfoOutputIntentCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result:

```text
6 test files, 1569 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-metadata-xmp-outputintent-nametree-currentbase.php
```

The smoke emits `markerpdf-metadata-xmp-outputintent-nametree-currentbase`, `metadata_review_object=5`, `metadata_payload_included=false`, `name_tree_pdfa_identifiers=["NameTree Review sRGB"]`, `root_pdfa_identifiers=["Current Document Root sRGB"]`, and the paragraph `Current XMP OutputIntent NameTree Body`.

Additional checks:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfMetadataXmpOutputIntentNameTreeCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-metadata-xmp-outputintent-nametree-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
```

All passed. Root harness was not run for this isolated micro-slice.

## Non-Overlap

This does not repeat prior root catalog XMP extraction, root PDF/A OutputIntent extraction, FileSpec-associated OutputIntent review, PieceInfo review, embedded-file name-tree handling, action execution blocking, or current-xref stale-object exclusion by itself.

The bounded behavior here is specifically generic catalog `/Names` value dictionaries that carry nested `/Metadata` XMP streams and `/OutputIntents`, with current-base xref selection proving the nested review rows stay local and redacted.

## Dependency Closure

No new support component is needed. This slice reuses the native xref-stream parser, catalog and name-tree walkers, indirect object resolver, Flate stream decoder, XMP summarizer, OutputIntent provenance summarizer, text content extractor, and WordPress smoke path. Full upstream markerPDF parity remains gated by external Python/model/runtime dependencies including `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI paths, benchmark/model downloads, and OCR/rendering helpers.
