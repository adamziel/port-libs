# markerPDF Page Form XObject StructTree Clip Current Base

## Scope

Implemented a bounded native `PdfTextExtractor` slice for page clipping across Form XObject expansion and tagged-PDF reading order:

- tracks rectangular clipping state while collecting marked-content MCID segments;
- wraps rebuilt StructTree segments in the active page clip before replaying them in `/StructTreeRoot /K` order;
- preserves the already accepted Form XObject `/Matrix`, `/BBox`, scoped resource-font aliasing, and cyclic form guard behavior;
- prevents clipped form glyphs and clipped `/ActualText` replacements from leaking into WordPress paragraphs after StructTree replay.

## Source Truth

Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text through `marker/pdf/extract_text.py`, where `get_text_blocks()` delegates page text dictionaries to `pdftext.extraction::dictionary_output`, and `naive_get_text()` uses pypdfium page text extraction. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

The marker conversion pipeline calls `get_text_blocks()` before downstream layout/order/cleanup. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py

Relevant PDF parser behavior for this slice: `q` / `Q` save and restore the graphics state, `re` appends a rectangle path, `W` / `W*` intersect the clipping path, `n` clears the current path, `/Subtype /Form` XObjects execute within the caller graphics state, and tagged PDF `/StructTreeRoot` MCID ordering must not make clipped content visible.

## Red-First Observation

Before the source change, a one-off current-base probe with a page clip, invoked form `/Matrix`, StructTree order `[hidden, visible]`, and hidden `/ActualText` emitted:

```text
array (
  0 => 'Hidden replacement leak',
  1 => 'Visible form body',
)
```

After the source change the same probe emits only `Visible form body`.

## Evidence

- `php tools/run-tests.php lanes/markerpdf/tests/PdfPageFormXObjectStructTreeClipCurrentBaseTest.php` passed: `1 test files, 11 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfPageArtifactMarkedContentClipCurrentBaseTest.php lanes/markerpdf/tests/PdfPageFormXObjectStructTreeClipCurrentBaseTest.php` passed: `3 test files, 618 assertions, 0 failures`.
- `php -l lanes/markerpdf/src/PdfTextExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfPageFormXObjectStructTreeClipCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-page-form-xobject-structtree-clip-currentbase.php` passed.
- `php -r '$files=["lanes/markerpdf/lane-status.json","lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " OK\n"; }'` passed for both JSON files.
- `php lanes/markerpdf/examples/wordpress-pdf-page-form-xobject-structtree-clip-currentbase.php` passed and emitted `visible_form_mcid_imported=true`, `clipped_form_actualtext_excluded=true`, `clipped_form_glyphs_excluded=true`, and one Gutenberg paragraph for `Visible form body`.
- `git diff --check -- lanes/markerpdf` passed.

## Counters

- `phpPass`: `799 -> 800`
- mapped focused semantics: `565 -> 566 / 78`

## Dependency Closure

No new support component is needed. This slice reuses native PDF object parsing, stream decoding, content tokenization, graphics-state clipping, Form XObject expansion, StructTree MCID ordering, and marked-content replacement handling. Full upstream runner parity remains dependency-gated by pdftext, pypdfium2/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and benchmark tooling; none were executed for this bounded PHP slice.

## Non-Overlap

This does not repeat accepted direct page artifact clipping, Form XObject `/Matrix` and `/BBox` clipping, StructTreeRoot MCID reading order, RoleMap replacement replay, optional-content visibility, annotation appearance Form XObject clipping, or parser/xref stream-boundary slices. The new behavior is only preserving the current page clipping path when expanded Form XObject MCIDs are rebuilt for StructTree ordering.
