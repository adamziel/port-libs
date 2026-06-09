# XML/HTML5 DOM Global Metadata Source Lines

Session: `port-dev-pandoc-xml-html5-dom-20260609T083503Z`
Micro-slice: `pandoc-xml-html5-dom-core-current-base-20260609T083503Z`
Base accepted HEAD: `436db66ac9717cbf75ff2ec29905ae0ddef22b3a`

## Behavior

This slice keeps the XML/HTML5 DOM work bounded to native PHP fragment
sanitizer diagnostics before WordPress raw HTML handoff.

`Html5DomFragment` already converts active global HTML metadata into inert
review attributes and diagnostics. This patch adds the missing source-line
provenance for that diagnostic cluster:

- `lang`, `xml:lang`, and `dir` diagnostics;
- `popover` diagnostics, including invalid state fallback;
- `contenteditable`, `draggable`, and `spellcheck` diagnostics;
- `translate` diagnostics;
- `tabindex`, `accesskey`, and `autofocus` diagnostics.

Invalid live metadata remains diagnostic-only, and sanitized WordPress review
HTML still receives only inert `data-pandoc-*` metadata attributes.

## Evidence

Red-first check after adding the focused case:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 2328 assertions, 1 failures`
- Failure: the new global metadata diagnostics had `NULL` source-line values.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 2333 assertions, 0 failures`

WordPress smoke:

- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
- Result: `html5 dom fragment handoff self-test ok`

Syntax checks:

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`
- Result: no syntax errors.

Status delta:

- Added 1 focused PHP PASS case.
- Added 11 focused assertions in `Html5DomFragmentTest.php`.
- Updated `phpPass` from `2530` to `2531`.
- Updated mapped Pandoc static inventory from `2898` to `2899`.
- Updated XML/HTML DOM static manifest mapping from 8 to 9 cases.

## Dependency Closure

No new support component is needed. This reuses native PHP `DOMDocument`
fragment parsing, existing `Html5DomFragment` sanitizer diagnostics,
source-line helper plumbing, raw HTML AST handoff, and `WordPressBlockWriter`.

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
object param metadata, document metadata source lines, `html`/`body` language
and direction metadata, or hidden/details/dialog review-state diagnostics.
The new behavior is limited to source-line provenance for global HTML metadata
diagnostics that were already emitted.

## Next Task

For a follow-up XML/HTML5 DOM slice, choose a non-overlapping native fragment
gap such as another source-position diagnostic cluster not already covered by
global metadata, URL, iframe, semantic, image, media, object/embed source,
object param, document metadata, body metadata, or review-state diagnostics.
