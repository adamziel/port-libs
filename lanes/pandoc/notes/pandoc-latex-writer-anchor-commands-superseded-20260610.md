# Pandoc LaTeX Writer Anchor Commands Superseded

MR: `plib-wisp-04g4`
Source issue: `plib-n4ee`

## Summary

The worker slice proposed heading `\hypertarget`/`\label` output and local
`#id` link rendering for `LatexWriter`.

Current `main` already contains a broader LaTeX anchor implementation:

- heading identifiers emit `\label{...}` and are wrapped by block-level
  `\hypertarget` output;
- same-document `#id` links render as `\hyperlink{...}{...}`;
- block anchors also cover `div` identifiers; and
- inline anchors cover `span` identifiers with `\protect\hypertarget`.

Because the current implementation and regression test are broader than the
worker patch, the source/test changes were skipped during rebase to avoid
replacing the newer `\hyperlink` and block/inline anchor coverage with the
older, narrower variant.

## Verification

- `php -l lanes/pandoc/src/LatexWriter.php`
- `php -l lanes/pandoc/tests/LatexWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/LatexWriterTest.php`
  - Result: 1 file, 7 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 42 files, 58399 assertions, 0 failures.

No metric counters changed for this note-only merge.
