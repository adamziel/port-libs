# markerPDF Metadata Trailer Info Name-Tree Review Current Base

Session: `port-dev-markerpdf-meta46-20260602T2023Z`

Micro-slice: `metadata-trailer-info-name-tree-currentbase`

Base accepted HEAD: `1d0255efc342976ccd01090ebca142bc846d342a`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text through `marker/pdf/extract_text.py::get_text_blocks()` into `pdftext.extraction.dictionary_output(...)`, while `convert.py::convert_single_pdf()` stores conversion metadata separately from visible Markdown output.
- The local upstream cache for markerPDF is not present in this isolated worktree, so the source-truth evidence for this slice is the pinned lane manifest, the upstream raw source links above, and the current accepted native PHP metadata/name-tree fixtures.
- PDF-side behavior for this slice: the latest `startxref` xref stream supplies the current trailer `/Root` and `/Info`; catalog `/Names` subtrees such as `/JavaScript` and `/URLS` are document metadata/name-tree review surfaces, not visible page text and not executable actions.

## Red Baseline

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataTrailerInfoNameTreeCurrentBaseTest.php
FAIL preserves current trailer Info review values and catalog name-tree review metadata
Expected: 'False'
Actual: NULL
1 test files, 5 assertions, 1 failures
```

## Implemented

- `PdfMetadataExtractor` now parses trailer `/Info` dictionaries through the same top-level dictionary resolver used by current xref-stream trailer handling.
- Standard Info string fields remain available as before, while all typed top-level Info values are also exposed under `trailer_info_review`. This preserves `/Trapped /False`, integer extension keys, name arrays, nested review dictionaries, and standard title/author/producer review values without using them as active behavior.
- Catalog `/Names` subtrees other than specialized `/Dests` and `/EmbeddedFiles` now emit `document_name_trees` review metadata.
- JavaScript and URL action dictionaries found through `/Names /JavaScript` and `/Names /URLS` are summarized as non-executing, payload-omitted review rows. Script and URI strings are represented only by source type, byte length, and SHA-256 where relevant.
- Name-tree `/Limits`, `/Kids`, depth, and cycle guards are reused so stale out-of-limits and stale appended current-base rows do not leak into metadata or visible paragraphs.
- Added WordPress smoke `examples/wordpress-pdf-metadata-trailer-info-name-tree-currentbase.php`.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataTrailerInfoNameTreeCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS preserves current trailer Info review values and catalog name-tree review metadata
1 test files, 26 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataTrailerInfoNameTreeCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfJavaScriptActionInspectorTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 908 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-metadata-trailer-info-name-tree-currentbase.php
```

Passed. The smoke emitted `source=["info","catalog"]`, `trapped="False"`, `name_tree_names=["JavaScript","URLS"]`, `javascript_name_rows=["import-init","review-close"]`, `urls_name_rows=["source-url"]`, `payload_included=false`, and visible text `Current Trailer Info NameTree Body`.

Required final checks were also run after this note:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfMetadataTrailerInfoNameTreeCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-metadata-trailer-info-name-tree-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lanes/markerpdf/lane-status.json: valid JSON\n";'
git diff --check -- lanes/markerpdf
```

## Status Delta

- Behavior tests move `773 -> 774`.
- WordPress scenarios move `773 -> 774`.
- `lane-status.json` updated for the isolated current-base slice.

## Dependency Closure

No new support component is needed. This reuses the native PDF direct-object scanner, current `startxref` xref-stream trailer resolver, dictionary/array/name/string parser, existing name-tree limit walker, stream boundary exclusion, and WordPress smoke renderer.

Full upstream markerPDF runner parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch models, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, OCR/raster helpers, and external rendering/validation tooling. This slice did not execute Python, models, PDF actions, JavaScript, decryption, signature validation, or external PDF tools.

## Non-Overlap

This does not repeat accepted catalog XMP extraction, root PDF/A OutputIntent extraction, DSS validation-stream review, `/Names /EmbeddedFiles` FileSpec review, `/Names /Dests` destination review, outline name-tree Limits, catalog `/AF`, Portfolio `/Collection`, PieceInfo private metadata, encrypted metadata priority, trailer `/ID` fingerprint metadata, or JavaScript action-chain execution review.

The bounded behavior is current xref-stream trailer `/Info` typed review metadata plus generic catalog name-tree review rows for `/JavaScript` and `/URLS`, while script/URI payloads and stale appended objects stay out of WordPress paragraph text.
