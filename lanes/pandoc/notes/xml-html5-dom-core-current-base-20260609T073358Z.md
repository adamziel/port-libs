# XML/HTML5 DOM object param metadata

Session: `port-dev-pandoc-xml-html5-dom-20260609T073358Z`
Micro-slice: `pandoc-xml-html5-dom-core-current-base-20260609T073358Z`
Base accepted HEAD: `4d33e428da4780248f05e2619ed97a382cb59fe0`

## Behavior

This slice keeps the XML/HTML5 DOM work bounded to native PHP fragment
sanitization before WordPress raw HTML handoff.

`Html5DomFragment` already converted safe `object[data]` resources into inert
reviewer links and unwrapped object fallback content. This patch adds bounded
direct-child `param` metadata handoff for safe object sources:

- safe object `param name/value` pairs become inert
  `data-pandoc-object-param-*` reviewer spans;
- `valuetype=ref` and URL-like param names resolve safe relative values against
  the fragment base URL;
- unsafe param values, invalid names, and source-authored
  `data-pandoc-object-param-*` spoofing stay diagnostic-only;
- live `<object>` and `<param>` tags remain stripped from WordPress HTML blocks.

## Evidence

Baseline before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 2264 assertions, 0 failures`

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 2287 assertions, 0 failures`

Adjacent XML/HTML DOM family verification:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php lanes/pandoc/tests/XmlHtml5DomTest.php`
- Result: `5 test files, 2772 assertions, 0 failures`

WordPress smoke:

- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
- Result: `html5 dom fragment handoff self-test ok`

PHP lint:

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- Result: `No syntax errors detected in lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `No syntax errors detected in lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`
- Result: `No syntax errors detected in lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`

JSON validation:

- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $file . " ok" . PHP_EOL; }'`
- Result: both lane JSON files decoded successfully.

Whitespace:

- `git diff --check -- lanes/pandoc`
- Result: no output, no whitespace errors.

Status delta:

- Added 1 focused PHP PASS case.
- Added 23 focused assertions in `Html5DomFragmentTest.php`.
- Updated XML/HTML DOM static manifest mapping from 8 to 9 cases.
- Updated `phpPass` from 2503 to 2504.

## Dependency Closure

No new support component is needed. This reuses native PHP `DOMDocument`
fragment parsing, `Html5DomFragment` URL normalization and source-line
diagnostic helpers, the Pandoc-like raw HTML AST handoff, and
`WordPressBlockWriter`.

The full upstream Pandoc runner remains out of scope for this isolated support
library slice. No Pandoc, Cabal solver/build/test command, Haskell runner,
Word, LibreOffice, zip/unzip, tar, external converter, external template
engine, TeX/PDF engine, browser renderer, online service, live provider test,
or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM slices for unsafe XML/DTD
preflight, HTML5 named references, RCDATA/plaintext/template handling,
SVG/MathML integration points, iframe source/policy diagnostics, portal/source
handoff, image/media resource policy diagnostics, semantic metadata source
lines, image-map helper diagnostics, table insertion-mode repair, form/select
metadata, generic URL source-line diagnostics, or object/embed source
diagnostics. The new behavior is limited to direct object `param` metadata for
objects whose `data` source is already safe enough to expose as a review link.

## Next Task

For a follow-up XML/HTML5 DOM slice, choose a non-overlapping native fragment
gap such as parser-level HTML fragment metadata handoff or another
source-position helper diagnostic not already covered by URL, iframe, semantic,
image, media, object/embed source, and object param diagnostics.
