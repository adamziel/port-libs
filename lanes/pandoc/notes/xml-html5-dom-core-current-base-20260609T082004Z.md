# XML/HTML5 DOM Body Metadata

Session: `port-dev-pandoc-xml-html5-dom-20260609T082004Z`
Micro-slice: `pandoc-xml-html5-dom-core-current-base-20260609T082004Z`
Base accepted HEAD: `e8462716baed1244ed5b9f195429af80b17d479b`

## Behavior

This slice keeps the XML/HTML5 DOM work bounded to native PHP fragment
sanitization before WordPress raw HTML handoff.

`Html5DomFragment` already strips source-authored `html` and `body` wrappers
from complete-document fragments while preserving safe `html` language and
direction metadata. This patch adds the missing body-wrapper metadata handoff:

- safe source `body lang` / `body xml:lang` becomes an inert
  `body-language` metadata span;
- safe source `body dir` becomes an inert `body-direction` metadata span;
- invalid body language/direction values stay diagnostic-only;
- source-owned live `html`/`body` wrappers and spoofed `data-pandoc-*`
  attributes remain stripped from WordPress block output.

## Evidence

Baseline before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 2287 assertions, 0 failures`

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 2322 assertions, 0 failures`

WordPress smoke:

- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
- Result: `html5 dom fragment handoff self-test ok`

Status delta:

- Added 1 focused PHP PASS case.
- Added 35 focused assertions in `Html5DomFragmentTest.php`.
- Updated `phpPass` from `2524` to `2525`.
- Updated mapped Pandoc static inventory from `2895` to `2896`.
- Updated XML/HTML DOM static manifest mapping from 8 to 9 cases.

## Dependency Closure

No new support component is needed. This reuses native PHP `DOMDocument`
fragment parsing, existing `Html5DomFragment` document metadata extraction,
language/direction normalization, Pandoc-like raw HTML AST handoff, and
`WordPressBlockWriter`.

The full upstream Pandoc runner remains out of scope for this isolated
support-library slice. No Pandoc, Cabal solver/build/test command, Haskell
runner, Word, LibreOffice, zip/unzip, tar, external converter, external
template engine, TeX/PDF engine, browser renderer, online service, live
provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM slices for unsafe XML/DTD
preflight, HTML5 named references, RCDATA/plaintext/template handling,
SVG/MathML integration points, iframe source/policy diagnostics, portal/source
handoff, image/media resource policy diagnostics, semantic metadata source
lines, image-map helper diagnostics, table insertion-mode repair, form/select
metadata, generic URL source-line diagnostics, object/embed source diagnostics,
object param metadata, or `html` element language/direction metadata. The new
behavior is limited to source `body` language and direction metadata from
stripped complete-document fragment wrappers.

## Next Task

For a follow-up XML/HTML5 DOM slice, choose a non-overlapping native fragment
gap such as source-position helper diagnostics not already covered by URL,
iframe, semantic, image, media, object/embed source, object param, document
metadata, or body metadata diagnostics.
