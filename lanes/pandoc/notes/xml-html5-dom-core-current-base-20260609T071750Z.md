# XML/HTML5 DOM Core Current-Base Slice - 2026-06-09T071750Z

Lane: pandoc
Micro-slice: pandoc-xml-html5-dom-core-current-base-20260609T071750Z
Base accepted HEAD: 606e24ec818a38feb2a796c2f2b7d182ce531afd

## Behavior

This slice keeps the existing HTML5 sanitizer behavior and adds DOM source-line
provenance to already-emitted helper metadata diagnostics:

- `style` unsafe/review metadata diagnostics.
- `track` `kind` and `srclang` unsafe metadata diagnostics.
- `time datetime` unsafe/review metadata diagnostics.
- `data`, `meter`, and `progress` value metadata diagnostics.
- `output for/form/name` unsafe/review metadata diagnostics.

The source lines are preserved on both `Html5DomFragment::diagnostics()` and
the raw HTML AST packet produced by `toRawHtmlAst()`, so WordPress review
handoffs can point reviewers back to the offending source line without changing
the sanitized HTML output.

## Red-First Evidence

Before the patch, a multi-line fragment containing unsafe style, track, time,
progress, and output metadata produced the expected diagnostics but several had
no `line` key. The focused baseline passed:

`php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`

Result before edit: `1 test files, 2216 assertions, 0 failures`.

The diagnostic probe showed missing `line` values on the helper diagnostics
while adjacent URL diagnostics already carried line provenance.

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php`
  - `No syntax errors detected in lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-html5-dom-handoff.php`
  - `No syntax errors detected in lanes/pandoc/examples/wordpress-html5-dom-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - `1 test files, 2229 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php lanes/pandoc/tests/XmlHtml5DomTest.php`
  - `5 test files, 2714 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`
  - `wordpress-html5-dom-handoff self-test passed`

Final required whitespace/JSON checks are recorded in the worker final report.

## Status Delta

- Added 1 focused PHP PASS case.
- Added 13 focused assertions in `Html5DomFragmentTest.php`.
- Updated XML/HTML5 DOM static manifest mapping from 8 to 9 cases.
- Updated `phpPass` from 2482 to 2483.

## Dependency Closure

No new support component is needed. This reuses the existing
`Html5DomFragment::diagnosticWithSourceLine()` helper, the native DOM fragment
sanitizer, raw HTML AST handoff, and WordPress block writer. No Pandoc,
Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external template engine,
external converter, TeX/PDF engine, browser renderer, online service, live
provider test, or live-service provider test was run.

## Non-Overlap

This does not repeat the accepted XML/HTML5 DOM clusters for DTD/entity
rejection, foreign-content casing, RCDATA/raw text/plaintext/template handling,
table foster parenting, media/image resource policy, base URL metadata,
semantic metadata, document metadata, iframe/image-map source lines, responsive
image filtering, portal/source pruning, or fragment sanitizer diagnostics. It
only adds missing source-line provenance to helper metadata diagnostics that
already existed.
