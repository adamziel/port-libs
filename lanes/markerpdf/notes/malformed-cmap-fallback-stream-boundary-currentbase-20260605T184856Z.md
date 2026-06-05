# Malformed CMap Fallback Stream Boundary - 2026-06-05

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T184856Z`

Base: `914b332d7eebe887dadaebd70eaf01b1b51bdf62`

## Behavior

Stream-only fallback extraction now skips streams that are referenced as CMaps
or whose stream dictionary is CMap-shaped. This prevents a `/Type /CMap`
stream with an all-null filter stack from being decoded again as visible page
content when a malformed PDF has no usable page tree.

The dedicated CMap review path is unchanged: the CMap stream is still decoded
and reported by `extractCMapStreamFilterLengthOwnerReview()`, including the
all-null filter stack, ignored unresolved DecodeParms operand, post-`endcmap`
payload exclusion, and no Python/model or external PDF tool execution.

## Evidence

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFallbackStreamBoundaryCurrentBaseTest.php`

Result before source edit: `1 test files, 1 assertions, 1 failures`; the
CMap payload was emitted as fallback visible text.

After fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFallbackStreamBoundaryCurrentBaseTest.php`

Result: `1 test files, 29 assertions, 0 failures`.

Adjacent CMap/filter regression group:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFallbackStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUnknownFilterNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectScalarFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNullFilterLengthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php`

Result: `7 test files, 1713 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-fallback-stream-boundary-currentbase.php --self-test`

Result: emits `OK markerpdf-malformed-cmap-fallback-stream-boundary-currentbase`
and flags `cmap_skipped_from_visible_fallback=true`,
`cmap_review_still_decodes=true`, and
`post_endcmap_payload_excluded_by_cmap_parser=true`.

## Non-Overlap

This is not another malformed filter operand, DecodeParms, EOD, owner-length,
or post-`endcmap` CMap parser case. It targets the separate stream-only
fallback path that decodes every non-page stream when the page tree is absent.
Existing accepted CMap review/decode behavior remains covered by the adjacent
regression group.

## Dependency Closure

No new dependency or support component is needed. The patch reuses native
`pdf-text-dictionary-core` stream dictionary classification and existing CMap
reference/dictionary detection. No OCR, Surya, Texify, Torch, Python model, or
external PDF tool path was invoked.
