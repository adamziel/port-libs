# markerPDF Outline Metadata Boundary Current Base

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260602T230009Z`

Base accepted HEAD: `1c11c94b45001e6d7041475e1155fe1067d73191`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/cleaners/toc.py::get_pdf_toc` delegates bookmark extraction to the PDF engine with `doc.get_toc(max_depth=...)`, then returns TOC rows as title, level, and page metadata: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/cleaners/toc.py`.
- Upstream `marker/pdf/extract_text.py::get_text_blocks` returns page blocks and TOC metadata separately, keeping outline/navigation metadata out of page text blocks: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`.
- Upstream `marker/output.py::save_markdown` writes `out_metadata` to a sidecar JSON file instead of mixing it into Markdown body text: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/output.py`.
- PDF outline source truth for this slice: catalog `/Outlines` points at the document outline hierarchy; outline dictionaries carry `/Title`, sibling/child references, `/Count`, `/Dest`, and mutually exclusive `/A` actions. This is review/navigation metadata and should not become visible WordPress paragraph text.

## Implementation

- `PdfMetadataExtractor` now emits `catalog.document_outline` and top-level `document_outline` metadata from the current xref-selected catalog `/Outlines`.
- The summary records root object, first/last item objects, declared visible count, item count, resolved/unresolved destination counts, max depth, titles, and per-item structure/destination rows.
- Outline item rows resolve named destinations through current `/Names /Dests` and legacy `/Dests`, direct explicit destination arrays, and local `/A << /S /GoTo /D ... >>` action dictionaries.
- The implementation stays inside `PdfMetadataExtractor` so stale duplicate outline, XMP, Info, or page-content objects appended after the current EOF do not override the selected xref stream.

## Verification

Focused test:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS summarizes current xref-selected catalog Outlines in document metadata
PASS keeps outline metadata and stale appended objects out of visible WordPress text

1 test files, 66 assertions, 0 failures
```

Adjacent metadata/outline gate:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPdfaCatalogAssociatedOutlineCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataCatalogOutlineAssociatedSecurityBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataTrailerInfoNameTreeCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfOutlineStructureDestinationActionContextCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationThreadActionMetadataCurrentBaseTest.php
```

Result: `7 test files, 1210 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-boundary-currentbase.php
```

Result: emitted `outline_root_object=40`, `outline_titles=["Import Runbook","Collapsed Review Child","Media Appendix"]`, `resolved_destination_count=3`, `stale_outline_excluded=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Behavior tests move `945 -> 947` pass / `0` fail. Mapped upstream semantics stay `664 / 78`.

## Non-Overlap

This does not repeat accepted outline action chains, destination action context, page transition/action context, page labels, article-thread target context, page associated-file review, catalog OpenAction review, generic catalog name-tree review, catalog associated-file PDF/A review, or current xref-selected XMP/Info/OutputIntent ownership alone. The bounded behavior is catalog `/Outlines` as current xref-selected document metadata in `PdfMetadataExtractor`.

## Dependency Closure

No new support component is needed. This reuses the native PDF xref stream/table selection, direct object parser, dictionary/value reader, destination name-tree resolver, and text extractor. Full upstream runner parity remains gated by the existing heavy Python/PDF stack: pdftext, pypdfium2/PDFium, Surya/OCR, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, multiprocessing conversion, benchmark tooling, and external OCR/Pandoc/XeLaTeX helpers.

## Next Task

Continue with non-overlapping markerPDF current-base slices around remaining parser, font, security, page, image, runtime, table, and outline boundaries. For outline specifically, avoid duplicating `document_outline`; next work should target an unported PDF outline edge such as structure-element `/SE` association if a focused current-base fixture needs it.
