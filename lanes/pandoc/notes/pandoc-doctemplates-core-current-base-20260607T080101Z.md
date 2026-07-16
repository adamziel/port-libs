# Pandoc Doctemplates Current-Base Default AsciiDoc Fallback

## Scope

Implemented one bounded DocTemplate support-library slice on accepted base
`912c56d812f68fca8f6ea91b90c49265da9a9a1d`: native default-template fallback
for `templates/default.asciidoc`, direct resource lookup, `asciidoctor` and
`asciidoc_legacy` writer aliases, and caller override preservation.

## Source Truth

- Pinned Pandoc default AsciiDoc template:
  `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/data/templates/default.asciidoc`
- Pinned Pandoc template dispatch source:
  `https://github.com/jgm/pandoc/blob/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Templates.hs`

## Evidence

- Baseline focused test before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 379 assertions, 0 failures`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 398 assertions, 0 failures`.
- Focused assertion delta: `+19`.
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- PHP lint passed for:
  `lanes/pandoc/src/DocTemplate.php`,
  `lanes/pandoc/tests/DocTemplateTest.php`, and
  `lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed with no output.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice avoids the accepted doctemplate parameterized pipes, partial
rebinding, braced separator, Markdown/CommonMark default fallback, Beamer
default fallback, man default fallback, and ms default fallback clusters. It
does not run Pandoc, Cabal, Haskell runners, external template engines, TeX/PDF
engines, browser renderers, online services, live provider tests, or
live-service provider tests.

## Dependency Closure

No new support component is needed. The patch reuses native PHP `DocTemplate`
parsing/rendering, built-in resource fallback lookup, focused PHP tests, and
the existing WordPress doctemplate review-packet example. Remaining upstream
Pandoc/Haskell runner parity and additional default-template resources stay
bounded follow-up work.
