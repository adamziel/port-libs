# markerPDF parser object-stream streamdict generation current-base

Micro-slice: `parser-object-stream-streamdict-generation-currentbase`

Base accepted HEAD: `d5484d08da8e3bf2726a4fddd0260f208a15e7d9`

## Source Truth

Upstream markerPDF at pinned `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes page text through `marker/pdf/extract_text.py::get_text_blocks()` via `pdftext.extraction.dictionary_output(...)`, and through `naive_get_text()` via pypdfium page text:

https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

That keeps native PHP parser work at the pdftext/PDFium dependency boundary before WordPress paragraph extraction. The relevant PDF parser behavior is object-stream structure: object streams use `/Type /ObjStm`, `/N`, `/First`, and stream `/Length`/`/Filter`/`/DecodeParms`; compressed member objects are generation zero and the xref-selected generation of indirect operands must be honored. QPDF documents the same object-stream constraints and member-table shape:

https://allstar.jhuapl.edu/repo/p4/amd64/qpdf/doc/qpdf-manual.html

## Behavior

This slice adds `PdfTextExtractor::extractObjectStreamStreamDictionaryGenerationReview()`. It reports object-stream dictionary operand ownership before WordPress import:

- `/N`
- `/First`
- `/Length`
- `/Filter`
- `/DecodeParms`

The focused fixture includes stale generation-0 helpers for all five operands and current generation-1 helpers selected by the current xref stream. Native extraction already emitted the correct two paragraphs on this base; the new implementation makes that parser boundary inspectable as review metadata with `5` xref-selected operands and `0` unresolved operands.

The WordPress smoke emits:

- `Current object stream streamdict generation`
- `Current N First Length Filter DecodeParms applied`

It excludes stale helper names (`ASCIIHexDecode`, `Twelve`) and the object-stream dictionary note text.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamStreamDictGenerationCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses current object-stream stream dictionary helper generations before WordPress extraction

1 test files, 52 assertions, 0 failures
```

Adjacent parser/xref gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamStreamDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterXrefOwnerCurrentBaseTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 172 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-parser-object-stream-streamdict-generation-currentbase.php
```

The smoke metadata reported:

- `uses_current_object_stream_page=true`
- `current_streamdict_helpers_applied=true`
- `stale_helper_names_excluded=true`
- `stale_streamdict_note_excluded=true`
- `object_stream_review_count=1`
- `xref_selected_operand_count=5`
- `unresolved_operand_count=0`
- `decoded_with_current_operands=true`

Lint, JSON, and patch hygiene:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserObjectStreamStreamDictGenerationCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-parser-object-stream-streamdict-generation-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
git diff --check -- lanes/markerpdf
```

## Status Delta

- `phpPass`: `798 -> 799`
- `wordpressScenarios`: `798 -> 799`
- mapped parser semantics: `564 -> 565 / 78`
- added focused test: `PdfParserObjectStreamStreamDictGenerationCurrentBaseTest.php`
- added WordPress smoke: `wordpress-pdf-parser-object-stream-streamdict-generation-currentbase.php`

## Non-Overlap

This does not repeat accepted parser stream-dictionary generation ownership for page content streams, xref-selected object-stream filter-chain expansion, object-stream indirect `/Length`/`/Filter` helper recovery, object-stream inline-image filter repair, xref object-stream generation-zero member guards, xref `/Prev` object-stream generation review, stream-owned xref offset rejection, or xref-stream `/Filter`/`/Length` owner review.

The bounded behavior here is only object-stream stream dictionary operand-generation review for `/N`, `/First`, `/Length`, `/Filter`, and `/DecodeParms`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref table/stream selector, generation-aware indirect object resolver, object-stream decoder, stream filter dispatcher, DecodeParms predictor path, page-tree walker, content-token extractor, and WordPress smoke renderer. Full upstream runner parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark tooling, and external OCR/rendering helpers.
