# Pandoc doctemplates core current-base 2026-06-06T20:24:36Z

## Slice

- Added bounded native Pandoc default Beamer template fallback to
  `PortLibs\Pandoc\DocTemplate`.
- `renderResource('templates/default', ..., 'beamer')` and direct
  `templates/default.beamer` requests now resolve through the existing
  built-in default-template resource path.
- The fallback covers Beamer class options, geometry, navigation, section-title
  hooks, theme/color/font/inner/outer theme metadata, title/subtitle/author/
  date/institute/titlegraphic/logo handoff, TOC frames, include-before/after
  fragments, natbib/biblatex bibliography frames, and caller override
  preservation.
- Updated the WordPress doctemplate review-packet smoke with a Beamer
  default-template handoff check.

## Source Truth

- Pinned Pandoc `getDefaultTemplate` maps writer names to
  `templates/default.<format>` and leaves `beamer` as `templates/default.beamer`:
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Templates.hs
- Pinned Pandoc `data/templates/default.beamer` is the source shape for the
  bounded Beamer class/theme/title/TOC/body/bibliography handoff:
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/data/templates/default.beamer
- Upstream doctemplates documents output-format-agnostic rendering, partials,
  conditionals, loops, separators, nesting, breakable spaces, and pipes:
  https://raw.githubusercontent.com/jgm/doctemplates/master/README.md
- This slice used only the lane-local native PHP renderer. No Pandoc binary,
  Cabal solver/build/test command, Haskell runner, external template engine,
  TeX/PDF engine, browser renderer, online service, live provider test, or
  live-service provider test was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 273 assertions, 0 failures`.
- Red-first focused command after adding the Beamer default-template
  expectation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result before implementation: `1 test files, 273 assertions, 1 failures`.
  - Failure was `Missing doctemplate resource templates/default` because the
    default Beamer fallback resource did not exist.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 316 assertions, 0 failures`.
  - Delta: `+1` focused PHP PASS case and `+43` focused assertions.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  - Result: `OK wordpress doctemplate review packet`.
- PHP lint:
  - `php -l lanes/pandoc/src/DocTemplate.php`
  - `php -l lanes/pandoc/tests/DocTemplateTest.php`
  - `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
  - Result: no syntax errors.
- Lane JSON validation:
  `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both files valid.
- `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, dollar delimiters,
conditionals, loop scoping, separators, `$it$`, `$^$`, automatic multiline
nesting, parameter-free pipes, deterministic map-pairs ordering, parameterized
block pipes, Unicode display-width padding, missing/null pipe handling,
in-memory or filesystem resource discovery, path-style partial lookup,
applied-partial variable rebinding, partial recursion guards, braced pipe
quoted-string braces, braced separator parsing, alpha overflow labels,
boolean false output, Unicode identifier parsing, multiline control boundary
newline swallowing, empty standalone partial line swallowing, `chomp`
traversal, breakable-space rendering/wrapping, dedented nesting termination,
final newline stripping for included partials, extensionless custom-template
output-format fallback, source-location diagnostics, default HTML/Markdown/
CommonMark/LaTeX/OpenXML/OpenDocument/EPUB3/Typst resources, default HTML
style partials, unclosed `$~$` breakable-space rejection, or default HTML5
void tag serialization.

It only adds bounded `templates/default.beamer` fallback resource coverage and
the matching WordPress review-packet smoke path.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`DocTemplate` renderer, built-in resource lookup, in-memory resources, and the
WordPress doctemplate review-packet example. Full Pandoc runner parity, exact
full upstream template data-file refresh automation, TeX/PDF engine execution,
Beamer rendering, richer doclayout value modeling, external template engines,
online services, live provider tests, and live-service provider tests remain
separate bounded follow-up work.

Root harness: not run - isolated micro-slice.
