# Pandoc doctemplates core current-base 2026-06-06T17:43:05Z

## Slice

- Added bounded Pandoc default Typst template support to
  `PortLibs\Pandoc\DocTemplate`.
- `templates/default` now resolves to `templates/default.typst` for Typst
  output-format fallback, and `templates/default.typst` gets a native
  `templates/template.typst` partial for the default `conf` helper.
- The focused coverage renders title/subtitle/authors, keywords, date,
  language/region, abstract, margins, paper/font/page metadata, columns, TOC,
  body, nocite, bibliography style, bibliography files, include-before/after,
  smartquote disabling, highlighting definitions, header includes, custom
  external `template` import, and custom default-template override.
- The WordPress doctemplate review-packet smoke now exercises the same Typst
  default-template fallback without running Typst.

## Source Truth

- Pinned Pandoc default Typst template:
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/data/templates/default.typst
- Pinned Pandoc default Typst `conf` partial:
  https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/data/templates/template.typst
- Upstream `doctemplates` parser resolves partials relative to the current
  template path and keeps explicit partial extensions:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
- Upstream `doctemplates` README documents conditionals, loops, partials,
  separators, nesting, breakable spaces, and pipes:
  https://raw.githubusercontent.com/jgm/doctemplates/master/README.md
- No Pandoc binary, Typst executable, Cabal solver/build/test command, Haskell
  runner, external template engine, browser renderer, online sanitizer, online
  service, live provider test, or live-service provider test was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result before edits: `1 test files, 229 assertions, 0 failures`.
- First focused run after adding the Typst assertions:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 251 assertions, 1 failures`; the only mismatch was
    the expected margin pair separator, corrected to the upstream trailing
    comma shape.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 273 assertions, 0 failures`.
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
- Whitespace check:
  `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, dollar delimiters,
conditionals, loop scoping, separators, `$it$`, `$^$`, automatic multiline
nesting, parameter-free pipes, deterministic map-pairs ordering,
parameterized block pipes, Unicode display-width padding, missing/null pipe
handling, in-memory resource-map partial discovery, filesystem resource
discovery, path-style partial lookup, applied partial variable rebinding,
partial recursion guards, braced pipe quoted-string braces, braced separator
parsing, alpha overflow labels, boolean false output, Unicode identifier
parsing, multiline control boundary newline swallowing, empty standalone
partial line swallowing, `chomp` traversal, breakable-space rendering/wrapping,
dedented nesting termination, final newline stripping for included partials,
extensionless custom-template output-format fallback, unclosed ordinary-dollar
diagnostics, default HTML/Markdown/CommonMark/LaTeX/OpenXML/OpenDocument/EPUB3
fallbacks, default HTML style partials, unclosed `$~$` breakable-space
rejection, or default HTML5 void tag serialization.

It only adds bounded default Typst template and `template.typst` partial
rendering to the existing native `DocTemplate` resource machinery.

## Dependency Closure

No new support component is needed. This reuses native PHP `DocTemplate`
resource rendering and the existing WordPress doctemplate smoke. Full Typst
engine execution, exact upstream data-file refresh automation, HTTP-backed
template discovery, fuller doclayout value modeling, and full upstream Pandoc
runner parity remain separate bounded follow-up work.

Root harness: not run - isolated micro-slice.
