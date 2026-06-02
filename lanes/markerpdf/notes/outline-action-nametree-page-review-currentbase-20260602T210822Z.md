# markerPDF Outline Action Name-Tree Page Review

Slice: `outline-action-nametree-page-review-currentbase`

## Source Truth

- Upstream markerPDF at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF page text through `marker/pdf/extract_text.py::get_text_blocks`, which calls `marker.cleaners.toc.get_pdf_toc(doc)` before `pdftext.extraction.dictionary_output(...)`: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>
- The pinned upstream `marker/cleaners/toc.py::get_pdf_toc()` delegates outline extraction to `doc.get_toc(max_depth=max_depth)` and records only `title`, `level`, and `page`: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/cleaners/toc.py>
- The native PHP boundary therefore keeps non-TOC outline action dictionaries as review metadata, matching the existing lane rule that PDF actions are not executed and action operands stay out of visible WordPress paragraphs.

## Implementation

- `PdfOutlineExtractor` now handles an outline `/A << /S /GoTo /D /Name >>` where `/Name` resolves through catalog `/Names /Dests` to a `/S /Thread` action dictionary.
- The fallback runs only after normal local page-destination resolution fails, preserving accepted local GoTo/name-tree destination behavior.
- The named Thread action and bounded `/Next` followups are surfaced as non-executing review rows with `destination_action_name`, target page label, transition metadata, article beads, page `PieceInfo`, and page-associated FileSpec review context.
- Same-document TOC output remains empty for Thread action targets, and visible WordPress text contains only page content.

## Red-First Evidence

Before the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineActionNameTreePageReviewCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL propagates outline GoTo name-tree Thread action target page review to review rows
The named Thread action, its /Next row, and the outer GoTo /Next row are review metadata.
Expected: 3
Actual: 2
FAIL carries the name-tree Thread target context onto chained action rows
Expected: 1
Actual: NULL
1 test files, 16 assertions, 2 failures
```

After the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineActionNameTreePageReviewCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS propagates outline GoTo name-tree Thread action target page review to review rows
PASS carries the name-tree Thread target context onto chained action rows
PASS keeps outline GoTo name-tree Thread action operands out of visible WordPress text
1 test files, 56 assertions, 0 failures
```

Example smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-action-nametree-page-review-currentbase.php
```

passed and emitted `visible_text_excludes_action_operands=true`, `outline_action_types=["Thread","URI","JavaScript"]`, and `thread_action_page_label="Article 48"`.

## Status Delta

- Current-base markerPDF behavior tests move `818 -> 821` pass / `0` fail.
- Mapped markerPDF semantics move `575 -> 576` with `mappedPdfOutlineActionNameTreePageReviewCurrentBaseBehaviors`.
- WordPress scenario count moves `818 -> 821` through the focused TestRunner cases and local smoke.

## Final Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineActionNameTreePageReviewCurrentBaseTest.php
1 test files, 56 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfOutline*Test.php
16 test files, 1086 assertions, 0 failures

php -l lanes/markerpdf/src/PdfOutlineExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfOutlineExtractor.php

php -l lanes/markerpdf/tests/PdfOutlineActionNameTreePageReviewCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfOutlineActionNameTreePageReviewCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-outline-action-nametree-page-review-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-outline-action-nametree-page-review-currentbase.php

php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $f . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $f . " ok\n"; }'
lanes/markerpdf/lane-status.json ok
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json ok

php lanes/markerpdf/examples/wordpress-pdf-outline-action-nametree-page-review-currentbase.php >/tmp/markerpdf-outline-action-nametree-page-review-currentbase.out && wc -l /tmp/markerpdf-outline-action-nametree-page-review-currentbase.out
24 /tmp/markerpdf-outline-action-nametree-page-review-currentbase.out

git diff --check -- lanes/markerpdf
passed with no output
```

## Non-Overlap

This does not repeat accepted direct `/S /Thread` outline actions, `/Dest` values resolving to GoTo action dictionaries, name-tree limits, launch-thread target propagation, remote-thread action stacks, OpenAction page-review propagation, or local link destination context. The new case is specifically an outline GoTo action whose `/D` operand names a name-tree entry that is itself a Thread action dictionary.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object parser, destination name-tree resolver, bounded action reviewer, article-thread bead reader, page transition/action metadata, page review metadata, associated-file review, and WordPress smoke path. Full upstream parity remains gated on live `pdftext`, `pypdfium2`/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, and benchmark tooling.

## Blocker And Next Task

Root and full upstream markerPDF runner parity remain blocked on the heavy Python/model/runtime dependency stack above. Next task is to integrate this current-base outline action/name-tree page-review patch, then continue with a non-overlapping source-backed PDF import-fidelity slice such as remaining outline action edge review, annotation/form target context, or page metadata repair.
