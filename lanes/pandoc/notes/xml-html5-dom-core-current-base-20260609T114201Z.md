# XML/HTML5 DOM Iframe Srcdoc Provenance

Session: `port-dev-pandoc-xml-html5-dom-20260609T114201Z`
Micro-slice: `pandoc-xml-html5-dom-core-current-base-20260609T114201Z`
Base accepted HEAD: `6015fe7e84dc103ae25bd946b46459a21033d320`

## Behavior

This slice keeps the XML/HTML5 DOM work bounded to native PHP fragment
sanitization before WordPress raw HTML handoff.

`Html5DomFragment` already sanitized iframe `srcdoc` contents by reparsing the
attribute as a nested HTML fragment with its own base URL. That lost the iframe
boundary because the sanitized `srcdoc` children were spliced directly into the
parent document. This patch preserves the boundary as inert reviewer metadata:

- valid iframe `srcdoc` content is wrapped in a generated
  `div data-pandoc-iframe-srcdoc="true"` review container;
- nested `base href` inside `srcdoc` is still resolved and surfaced as
  `data-pandoc-iframe-srcdoc-base-url`;
- safe iframe policy attributes are copied to inert `data-pandoc-iframe-*`
  review metadata on the wrapper;
- valid literal-empty and sanitizer-empty `srcdoc` suppress fallback iframe
  `src` and children, matching `srcdoc` precedence while preserving review
  provenance;
- invalid `srcdoc` still falls back to the existing safe iframe source-link
  path.

## Evidence

Pre-change targeted probe:

- Command:
  `php -r 'require "tools/bootstrap.php"; $f=PortLibs\Pandoc\Html5DomFragment::fromHtml("<base href=\"https://source.example.test/post.html\"><iframe srcdoc=\"<base href=&quot;https://frame.example.test/base/&quot;><p><a href=&quot;note.html&quot;>Frame note</a></p>\" title=\"Frame Review\"></iframe><a href=\"after.html\">after</a>"); echo $f->serialize(), "\n"; var_export($f->diagnosticCodes()); echo "\n"; var_export($f->nodes());'`
- Result before implementation: sanitized `srcdoc` children serialized as
  `<p><a href="https://frame.example.test/base/note.html">Frame note</a></p>`
  directly in the parent stream, with no iframe/srcdoc provenance wrapper.

Baseline before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 2346 assertions, 0 failures`

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 2385 assertions, 0 failures`

Adjacent XML/HTML DOM family verification:

- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php`
- Result: `5 test files, 2870 assertions, 0 failures`

WordPress smoke:

- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
- Result: `html5 dom fragment handoff self-test ok`

Syntax and artifact checks:

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`
- Result: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
- Result: `json ok`
- `git diff --check -- lanes/pandoc`
- Result: clean.

Status delta:

- Added 1 focused PHP PASS case.
- Added 39 focused assertions in `Html5DomFragmentTest.php`.
- Updated `phpPass` from `2712` to `2713`.
- Updated mapped Pandoc static inventory from `2919` to `2920`.
- Updated XML/HTML DOM static manifest mapping from 8 to 9 cases.

## Dependency Closure

No new support component is needed. This reuses native PHP `DOMDocument` /
libxml fragment parsing with `LIBXML_NONET`, existing `Html5DomFragment`
source-line diagnostics, iframe policy normalization, Pandoc-like raw HTML AST
handoff, and `WordPressBlockWriter`.

The full upstream Pandoc runner remains out of scope for this isolated
support-library slice. No Pandoc, Cabal solver/build/test command, Haskell
runner, Word, LibreOffice, zip/unzip, tar, external converter, external
template engine, TeX/PDF engine, browser renderer, online service, live
provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM slices for unsafe XML/DTD
preflight, HTML5 named references, RCDATA/plaintext/template handling,
SVG/MathML integration points, iframe source-link fallback, iframe source-line
diagnostics, portal/source handoff, image/media resource policy diagnostics,
semantic metadata source lines, image-map helper diagnostics, table
insertion-mode repair, form/select metadata, generic URL source-line
diagnostics, object/embed source diagnostics, object param metadata, document
metadata source lines, `html`/`body` language and direction metadata, or
hidden/details/dialog review-state diagnostics.

The new behavior is limited to preserving sanitized iframe `srcdoc` provenance
and precedence during WordPress raw HTML handoff.

## Next Task

For a follow-up XML/HTML5 DOM slice, choose a non-overlapping native fragment
gap such as another source-position diagnostic cluster, parser repair edge, or
safe review-metadata handoff not already covered by iframe source links,
iframe `srcdoc`, object/embed, global/body metadata, hidden/details/dialog
state, image maps, or resource-policy diagnostics.
