# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T085207Z`

Accepted base: `0ecf84ad404315cb58c4b0b6e028a4e3a9dcf224`

## Implementation

- Added trusted HTML base URL handling to `Html5DomFragment::fromHtml()`.
- The fragment sanitizer now records a resolved base URL from the first safe
  `<base href>` in the fragment, or from a caller-supplied absolute HTTP(S)
  document URL.
- Safe relative URL attributes are resolved against that base for reviewer
  handoff, including `href`, `cite`, fetch attributes such as `src`, and
  `srcset` candidates.
- Unsafe URL schemes are still filtered before any rewriting. `<base>` itself
  remains blocked from serialized WordPress raw HTML output.
- Raw HTML AST nodes now carry `baseUrl` metadata for review packets.
- Updated the WordPress HTML5 DOM fragment smoke to prove base URL resolution,
  unsafe `srcset` candidate removal, base-element dropping, and raw HTML block
  handoff.

## Source Truth

The upstream Pandoc runner remains unavailable in this isolated worktree, so
this uses the accepted lane static manifest and the bounded HTML reader support
contract already recorded for XML/HTML5 DOM support. This slice maps the
HTML-reader need to preserve document-base URL semantics for raw HTML fragment
handoff without invoking Pandoc or browser parsing.

## Focused Evidence

Baseline before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php
3 test files, 443 assertions, 0 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php
1 test files, 264 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php
3 test files, 467 assertions, 0 failures

php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test
html5 dom fragment handoff self-test ok
```

Delta:

- Adds 1 focused PHP PASS case.
- Adds 24 focused assertions to the XML/HTML5 DOM family.
- Updates `phpPass` from 786 to 787.
- Updates mapped upstream/static inventory coverage from 1246 to 1247.

## Dependency Closure

No new support component is needed. This reuses the lane-local
`Html5DomFragment`, `AstNode`, and `WordPressBlockWriter` support boundary plus
PHP DOM/libxml parsing with network access disabled. No Pandoc, Cabal build,
Haskell runner, browser renderer, online sanitizer, external converter, or
online service was executed.

## Non-Overlap

This does not repeat accepted XML DTD/entity rejection, RCDATA/raw-text
protection, HTML5 boolean attributes, foreign-content integration points,
table foster-parenting, form/embed/template fallback unwrapping, extended URL
attribute filtering, or candidate-aware `srcset` unsafe-candidate filtering.
It only adds trusted base URL resolution for already-safe relative URL
attributes and `srcset` candidates.

## Follow-Up

Keep full HTML5 tree-builder parity, broader sanitizer policy, resource
fetching, CSS/media policy, complete HTML-reader AST conversion, and EPUB
XHTML-to-AST conversion as separate bounded slices.
