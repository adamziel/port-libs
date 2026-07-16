# Pandoc Markdown Fenced Div Reader Slice

## Scope

This slice maps a bounded native PHP reader branch for Pandoc Markdown
`fenced_divs` blocks. The existing Markdown writer already emitted Div AST nodes
as colon-fenced Pandoc Markdown, but the Markdown reader only recognized HTML
`<div>` containers.

`MarkdownReader` now accepts block-level colon fences of length three or more:

- Braced Pandoc attributes such as `{#review-packet .wp-import data-source="batch-84"}`.
- Simple class shortcut syntax such as `::: sidebar`.
- Nested Markdown block content, reusing the existing recursive reader path.

The AST uses the existing `div` node shape and attribute helpers, so
`MarkdownWriter` and `WordPressBlockWriter` can preserve the same metadata
without new support components.

## Evidence

Red-first focused run after adding the test failed as expected:

```text
FAIL maps pandoc markdown fenced div extension reader writer and wordpress handoff
Expected: 'div'
Actual: 'paragraph'
```

After implementation:

```text
php -l lanes/pandoc/src/MarkdownReader.php
php -l lanes/pandoc/tests/MarkdownReaderTest.php
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
```

Result after the final rebase: 1 test file, 6419 assertions, 0 failures.

Post-rebase full Pandoc PHP gate:

```text
php tools/run-tests.php lanes/pandoc/tests
```

Result after the final rebase onto current `origin/main`: 42 test files, 58270
assertions, 0 failures.

## Accounting

After the final rebase onto current `origin/main`, `lane-status.json` moved
`phpPass` from 2899 to 2900 and `suiteProgress` from 802 to 803. `phpFail`
remains 0.

During integration, the shortcut example was changed from `warning` to
`sidebar` because current `MarkdownWriter` intentionally canonicalizes
`warning` Divs as Markdown alert blockquotes. The neutral class keeps the
fenced-div reader coverage focused on the reader path without conflicting with
that accepted writer behavior.

No Pandoc binary, Cabal/Haskell runner, browser renderer, external validator,
online service, Node tooling, zip/unzip, office suite, or TeX/PDF engine was
invoked.
