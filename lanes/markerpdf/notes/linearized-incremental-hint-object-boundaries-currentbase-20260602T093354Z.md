# markerPDF Linearized Incremental Hint Object Boundaries

Slice: `linearized-incremental-hint-object-leak-boundaries-currentbase-20260602T093354Z`

## Source Truth

Upstream `sddai/markerPDF` at the lane manifest commit delegates native page text extraction to `pypdfium2` text pages and `pdftext.extraction.dictionary_output` in `marker/pdf/extract_text.py`, so PDF linearization hint tables remain parser metadata and must not become visible document text.

PDF linearization `/H` entries describe primary/overflow hint-table byte ranges. The previously accepted slice handled direct `/H [offset length]` operands. This current-base slice covers the adjacent incremental-update boundary where `/H` operands are indirect numeric objects and the current trailer/root page graph accidentally or maliciously references the hint stream as page content.

## Implementation

`PdfTextExtractor` now resolves indirect numeric operands inside the first linearized dictionary `/H` array before constructing hint byte ranges. It also removes direct stream objects whose object header, range start, or range end intersects a hint byte range, so page `/Contents` arrays cannot reintroduce hint-stream text through the live object map before fallback extraction.

The red-first fixture on current base leaked `Linearized indirect hint object leak` before the fix.

## WordPress Path

`examples/wordpress-pdf-linearized-incremental-hint-object-import.php` models a damaged linearized upload with an incremental current xref/root page whose `/Contents` array includes both the hint stream and the real current content stream. It emits only `Incremental current page` and `Hint object boundary`, with `excludes_hint_object_text=true`, and executes no Python/models or external PDF tools.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PDF tokenizer, direct-object parser, xref-chain handling, page `/Contents` resolver, and stream decoder. Full upstream Python/model/benchmark parity remains dependency-gated by pdftext, pypdfium2, Surya, tabled, Texify, Torch, and live benchmark/runtime dependencies.

## Verification

- Red-first current-base check before the fix leaked `Linearized indirect hint object leak` before `Incremental current page`.
- `php -l lanes/markerpdf/src/PdfTextExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-linearized-incremental-hint-object-import.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php` passed: 1 file / 473 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-linearized-incremental-hint-object-import.php` emitted `Incremental current page`, `Hint object boundary`, and `excludes_hint_object_text=true`.
- `php tools/run-tests.php lanes/markerpdf/tests` passed: 61 files / 3015 assertions / 0 failures.
- `git diff --check -- lanes/markerpdf` passed.

## Non-Overlap

This does not repeat accepted direct linearized `/H` range exclusion, xref stream `/Index` and zero-width `/W`, latest startxref precedence, object-stream nested token boundaries, object-generation free-entry reuse guards, indirect page `/Contents` arrays, embedded-file payload exclusion, stream-filter error boundaries, image-filter exclusions, collection checksum metadata, decimal CIDFont widths, or soft-mask `/Decode` opacity metadata. The new behavior is specifically indirect `/H` operand resolution plus hint-object pruning before incremental current-root page text extraction.
