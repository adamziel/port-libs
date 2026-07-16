# Pandoc doctemplates core current-base 2026-06-08T09:15:00Z

Slice: `pandoc-doctemplates-core-current-base-20260608T091500Z`
Base accepted HEAD: `c303a56d375bb853af92574fd5fe105ac0feb97b`
Lane: `pandoc`

## Source Truth

- No current `port-pandoc` rework note existed for this slice.
- The local pinned Pandoc upstream checkout was absent from
  `/home/claude/port-libs/.upstream-cache/pandoc`, matching recent lane audit
  notes.
- Primary source used: the upstream `jgm/pandoc-templates` inventory lists
  `default.muse`, and its raw `default.muse` template contains the bounded
  author/title/lang/LISTtitle/subtitle/SORTauthors/SORTtopics/date/notes/source
  metadata preamble, header/include hooks, body insertion, and include-after
  hook.
- No Pandoc, Cabal solver/build/test command, Haskell runner, external template
  engine, browser renderer, online conversion service, live provider test, or
  live-service provider test was executed.

## Implementation

- `PortLibs\Pandoc\DocTemplate` now exposes a bounded native
  `templates/default.muse` default resource.
- `templates/default` with format `muse` or extension-qualified `muse+...`
  resolves to the Muse default unless a caller supplies an exact custom
  resource.
- The WordPress doctemplate review-packet smoke now checks Muse default
  fallback metadata and body handoff.

## Evidence

- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 690 assertions, 0 failures`.
- Red-first probe:
  `php -r 'require "tools/bootstrap.php"; $r = new PortLibs\Pandoc\DocTemplate(); echo $r->renderResource("templates/default", [], ["title"=>"Muse Review", "body"=>"Body"], null, "muse");'`
  failed with `Missing doctemplate resource templates/default`.
- Source-template check:
  `curl -fsSL https://raw.githubusercontent.com/jgm/pandoc-templates/master/default.muse | sed -n '1,120p'`
  returned the bounded Muse default-template source used for the PHP resource.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 708 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- PHP lint passed for:
  `lanes/pandoc/src/DocTemplate.php`,
  `lanes/pandoc/tests/DocTemplateTest.php`, and
  `lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Focused delta: `+1` PHP PASS case, `+18` focused assertions, mapped
  denominator `2009 -> 2010`, lane `phpPass` `1589 -> 1590`.

## Non-Overlap

This slice does not change doctemplate comments, dollar delimiters,
conditionals, loops, separators, `$it$`, explicit or automatic nesting,
breakable spaces, pipes, map-pairs ordering, partial resolution, filesystem
resource loading, source-location diagnostics, Unicode/colon/digit metadata
keys, extension-qualified output-format lookup, or already accepted default
HTML5/Markdown/CommonMark/AsciiDoc/plain/RST/BBCode/LaTeX/ConTeXt/man/ms/
Beamer/Reveal.js/legacy HTML slide/OpenXML/OpenDocument/EPUB3/ICML/DocBook5/
JATS/Typst resources.

It owns only the bounded Muse default-template resource fallback.

## Dependency Closure

No new support component is needed. This reuses the native PHP `DocTemplate`
resource resolver, existing default-template rendering path, focused
`DocTemplateTest.php` coverage, and the WordPress doctemplate review-packet
example. Full upstream Pandoc runner parity, exact Haskell doctemplates runner
parity, external template engines, browser renderers, online services, live
provider tests, and live-service provider tests remain out of scope.

Root harness: not run - isolated micro-slice.
