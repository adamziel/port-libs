# Pandoc formats merge lane 1 markdown reader fold

Date: 2026-07-02
Hook: plib-iufsz
Target: integration/pandoc-formats

Folded the ready Markdown reader leaf work from
integration/pandoc-formats-markdown-reader into the formats integration parent.
The fold keeps the parent-side aggregate status metadata and carries the reader
behavior, focused fixtures, notes, and tests for:

- CommonMark top-level `<pre>` raw HTML blocks.
- Escaped Markdown delimiter boundaries.
- Closing raw HTML blocks.
- Complete raw tag and raw closing boundary blocks.
- Details tags with quoted attributes.
- CommonMark `noscript`/`xmp` raw containers.
- HTML5 native div landmark parsing.
- Markdown title block profile gating.
- Ordered-list HTML import and table caption boundaries.

Validation:

- `php -l` over all changed PHP source and test files: pass.
- `php tools/run-tests.php` over the 10 changed Markdown reader focused test
  files: 10 files, 833 assertions, 0 failures.

Skipped leaves:

- `integration/pandoc-formats-html` and `integration/pandoc-formats-office`
  are already patch-equivalent to the parent.
- Media, registry, PDF/Typst, and small-format leaves already have recent
  parent fold commits; this shard deliberately claimed the Markdown reader
  subset to avoid duplicating those folds.
