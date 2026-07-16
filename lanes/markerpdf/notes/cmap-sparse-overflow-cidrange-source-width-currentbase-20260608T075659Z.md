# markerpdf CMap sparse overflow CID range source-width fallback

Session: `port-dev-markerpdf-source-width-20260608T075659Z`  
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260608T075659Z`  
Accepted base: `f7ac0a85e1fac9551aa46d1e1dabc4c1e6766a6c`

## Source truth

This stays inside the current no-GPU markerPDF scope. Upstream markerPDF delegates searchable-PDF text extraction to its PDF text/parser path before any OCR/model fallback; the native PHP port must preserve PDF CMap source-code to CID mapping and CIDFont width behavior for searchable Type0 fonts. PDF CMap codespace ranges bound which source codes are valid. A `begincidrange` whose source range tail extends outside the active same-width codespace must still be checked against the count of valid in-codespace source codes before accepting its sequential CID target range.

## Behavior

`PdfTextExtractor::cMapMappedSourceCountForRange()` now falls back to a bounded scan of valid in-codespace source keys when the fast code-space sequence offset cannot rank a sparse range tail. That lets the parser reject a later malformed sparse range such as `<1000> <10FF> 65534` under codespace `<1000> <1003>` because the four valid mapped source codes would overflow CID `0xffff`. The earlier valid range `<1000> <1003> 32` remains authoritative for source-width fallback and CIDFont `/W` widths.

Before this patch, the later sparse overflow range partially overrode the earlier range and collapsed the styled span bbox from `[0.0,0.0,72.0,12.0]` to `[0.0,0.0,30.0,12.0]`.

## Evidence

- Red-first focused run before source fix:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSparseOverflowCidRangeSourceWidthCurrentBaseTest.php`
  failed with expected bbox `[0.0,0.0,72.0,12.0]`, actual `[0.0,0.0,30.0,12.0]`.
- Focused run after fix:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSparseOverflowCidRangeSourceWidthCurrentBaseTest.php`
  => `1 test files, 10 assertions, 0 failures`.
- Adjacent family:
  `php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(CMap|Font).*Width.*CurrentBaseTest\.php$' | sort)`
  => `57 test files, 1856 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-cmap-sparse-overflow-cidrange-source-width-currentbase.php`
  exits `0` and emits one Gutenberg paragraph `ABCD` with `sparse_codespace_overflow_cidrange_rejected=true`, `prior_cidrange_widths_preserved=true`, `overflow_cid_width_override_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- Status delta: `phpPass` `2980 -> 2981`; `wordpressScenarios` `2472 -> 2473`; mapped upstream manifest coverage unchanged.

## Non-overlap

This does not repeat accepted CMap source-width slices for zero-padded source widths, predefined Identity/UCS2 fallbacks, high or large CID ranges, sparse codespace ordering, delayed valid codespaces, same-range CID overflow, invalid CID targets, notdef ranges, declared-count parsing, array decoys, stream filters, xref repair, images, annotations, forms, encryption preflight, OCR, model execution, or external PDF tools.

## Dependency closure

No new support component is needed. This reuses the native PDF object/stream parser, CMap parser, CIDFont width fallback, styled span geometry, and WordPress smoke path. OCR/model execution, Python `pdftext`, external PDF binaries, raster rendering, and GPU dependencies remain intentionally out of scope for this markerPDF lane slice.
