# CMap Codespace Bfrange Source-Width Fallback

Slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T161929Z`

Base accepted HEAD: `78ba96e2ccf27f2883686e79476f3757e7b854f9`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable PDF text extraction to PDF parser/text components that honor CMap code spaces before grouping text by glyph widths. The native PHP port already had source-width CID fallback tests for sparse CMap code spaces; this slice extends the same boundary to ToUnicode `beginbfrange` text mapping.

No GPU, OCR, Surya, Texify, Torch, pypdfium, or external PDF tool execution was used.

## Behavior

Sparse same-width CMap code spaces such as `<3030> <3232>` contain valid two-byte codes `3030`, `3031`, `3032`, `3130`, ... `3232`. A ToUnicode range `<3030> <3232> <0041>` should map those valid sequence entries to `A`, `B`, `C`, ..., rather than incrementing raw integer offsets through invalid byte pairs.

The port now:

- filters ToUnicode range expansion through same-width code-space ranges;
- records code-space ranges on lazy ToUnicode range fallback entries;
- computes lazy bfrange text offsets by valid sequence order;
- preserves existing source-width span separation and inherited CMap discovery.

## Evidence

Red-first focused command before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php`

Result: `1 test files, 291 assertions, 1 failures`; expected `ABC GHI`, actual raw-offset code points `U+0241 U+0242 U+0243`.

After implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php` => `1 test files, 301 assertions, 0 failures`
- Adjacent CMap/font family command => `11 test files, 1003 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-cmap-codespace-bfrange-source-width-currentbase.php` emits Gutenberg paragraph `ABC GHI` and smoke flags for codespace bfrange sequence text, source-width spans, integer-offset decoy exclusion, no Python/models, and no external PDF tools.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the already accepted Type0 CMap source-width fallback, large CID range source-width fallback, predefined vertical CMap, surrogate width, or usecmap vertical-width slices. The new behavior is specifically ToUnicode bfrange target advancement over sparse valid codespace sequence entries before source-width text grouping.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP PDF text/CMap parser and WordPress smoke harness. Remaining model/OCR parity is intentionally outside the current markerPDF no-GPU scope.
