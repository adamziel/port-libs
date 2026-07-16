# markerpdf outline named-destination action map boundary current base

Slice: `markerpdf-outline-metadata-boundary-current-base-20260605T162331Z`

Accepted base: `ce9bbc82b5199bc19298a0100094fa67dd50c31d`

## Behavior

PDF name trees can store local page destinations and action dictionaries behind `/Names << /Dests ... >>`. The native outline review path now keeps two bounded maps:

- local page destination map: used for TOC and page-view metadata, filtered after the full name-tree candidate set is available so a named `/GoTo` action dictionary can resolve its own named `/D` target;
- action-review destination map: used only by review/security metadata so named `/Thread`, `/GoToR`, `/URI`, `/JavaScript`, `/Launch`, and chained `/Next` action dictionaries are surfaced as review-only rows without being promoted into the local TOC.

This preserves upstream-style page destination behavior while keeping WordPress imports aware of review-only named action chains.

## Evidence

Red-first current-base evidence before the source edit:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineActionNameTreePageReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineRemoteDestinationActionReviewCurrentBaseTest.php`
- Current base dropped the name-tree `/Thread` and `/GoToR` action dictionaries from review resolution because `destinationMap()` filtered them as non-page destinations. The observed name-tree action review rows were only unsupported local GoTo plus the outer JavaScript follow-up instead of the named Thread action, its `/Next` URI, and the outer `/Next` JavaScript row.

Focused evidence after the patch:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionMapBoundaryCurrentBaseTest.php` => `1 test files, 32 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionMapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionContextCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionPageLabelStructureCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionTransitionCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataDestinationActionChainCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionThreadReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNamedDestinationTransitionThreadSecurityCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineRemoteDestinationActionReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineStructureDestinationActionContextCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineActionNameTreePageReviewCurrentBaseTest.php` => `10 test files, 546 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-outline-named-destination-action-map-boundary-currentbase.php` emits `outline_action_types=["GoTo","URI","Thread","JavaScript"]`, `destination_action_names=["ReviewAction","ReviewAction","ThreadAction","ThreadAction"]`, `toc_titles=["Local review action destination"]`, `visible_text_excludes_outline_operands=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. This uses the existing native PHP PDF object parser, name-tree traversal, outline extractor, article-thread review metadata, page-label extraction, and text extraction. No OCR, Surya, Texify, Torch, external PDF tools, or live model workers were run.

## Non-overlap

This does not touch xref repair, stream filters, fonts/CMaps, annotations, forms, image extraction, encrypted preflight, OCR/model behavior, dashboard files, or root coordination files. It avoids the previous accepted xref `/Prev` malformed-root cluster and focuses only on outline named-destination action metadata boundaries.
