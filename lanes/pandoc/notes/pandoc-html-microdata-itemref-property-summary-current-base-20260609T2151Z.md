# Pandoc HTML Microdata Itemref Property Summary Slice

Micro-slice: `pandoc-html-microdata-itemref-property-summary-current-base-20260609T2151Z`

## Scope

`Html5DomFragment` now performs a post-normalization pass over sanitized HTML
nodes so scoped microdata items can merge property-summary metadata from
resolved external `itemref` IDs.

The pass:

- Builds an ID map from sanitizer-owned normalized nodes.
- Merges only resolved references outside the owning item subtree.
- Skips descendant references to avoid double-counting properties already in
  the normal scoped item summary.
- Keeps missing IDs as inventory-only metadata from the existing itemref review.
- Preserves the bounded inert `data-pandoc-*` handoff without building a full
  browser microdata graph.

## Verification

Syntax checks:

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`

Focused checks:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: 1 file, 2513 assertions, 0 failures

Full Pandoc PHP gate:

- `php tools/run-tests.php lanes/pandoc/tests`
- Result after rebasing on `origin/main`: 42 files, 57463 assertions, 0 failures
- Rebased lane counters: `phpPass` 2851 -> 2852 and `suiteProgress` 754 -> 755

## Non-Overlap

This does not implement JSON-LD export, RDFa graph construction, Schema.org
validation, recursive itemref expansion, browser DOM execution, Pandoc execution,
or external validation. It only merges resolved external itemref property
summaries into already-normalized native HTML reader metadata.
