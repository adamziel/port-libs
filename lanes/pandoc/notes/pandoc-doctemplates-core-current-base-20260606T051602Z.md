# Pandoc doctemplates core current-base 2026-06-06T05:16:02Z

## Slice

- Added bounded source-location diagnostics to the native
  `PortLibs\Pandoc\DocTemplate` renderer.
- Direct templates, in-memory resource templates, filesystem-loaded templates,
  and included partial resources now carry source labels plus line/column
  positions through tokenization.
- Parser/render exceptions for unclosed dollar directives, unclosed
  breakable-space regions, unclosed control blocks, unsupported pipes, and
  broken included partials now report the relevant template or partial source
  location.
- Resource-map partial aliases now remember their actual resource path, so a
  failing `${ components/footer() }` partial can report
  `review-packets/components/footer.html:line:column`.
- Updated the WordPress doctemplate review-packet smoke with a malformed
  included component diagnostic check.

## Source Truth

- Upstream `Text.DocTemplates.Parser.compileTemplate` runs the parser with a
  template path as the source name and returns Parsec error text, which includes
  source-position context:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
- Upstream partial parsing resets the parser source position to the included
  partial path before parsing the partial body:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
- Upstream `Text.DocTemplates.Internal` keeps templates as parsed structures and
  delegates parse failures to the parser layer; this slice ports only the
  bounded source-label and line/column diagnostic contract.
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Internal.hs
- This slice used only the lane-local native PHP renderer. No Pandoc binary,
  Cabal build/solver/test command, Haskell runner, external template engine,
  browser renderer, JavaScript, online sanitizer, online service, or live
  provider test was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused coverage before this slice was
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` at
  `1 test files, 192 assertions, 0 failures`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 198 assertions, 0 failures`.
  - Delta: `+2` focused PHP PASS cases and `+6` focused assertions.
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
in-memory resource-map partial discovery, path-style partial lookup, applied
partial variable rebinding, partial recursion guards, braced pipe quoted-string
braces, braced separator parsing, alpha overflow labels, boolean false output,
Unicode identifier parsing, multiline control boundary newline swallowing,
empty standalone partial line swallowing, `chomp` traversal, breakable-space
rendering/wrapping, dedented nesting termination, final newline stripping for
included partials, extensionless custom-template output-format fallback,
unclosed ordinary-dollar rejection without position context, built-in
default-template fallbacks, default HTML style partials, unclosed `$~$`
breakable-space rejection without position context, default HTML5 void tag
serialization, or rooted filesystem discovery.

It only adds bounded source labels and line/column diagnostics around the
existing native renderer and resource/partial lookup behavior. It does not
touch ZIP/OPC package primitives, YAML metadata, Citation/CSL, BibTeX/CSL,
Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB package
parsing, legacy-DOC parsing, table geometry, math conversion, PDF handoff
planning, archive compression, syntax highlighting, XML/HTML5 DOM,
charset/Unicode, or upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`DocTemplate` renderer, resource-map rendering, rooted filesystem loader, and
WordPress doctemplate review-packet example. Fuller upstream default-template
data files and partials, HTTP-backed template discovery, parser recovery/context
diagnostics beyond bounded line/column positions, full doclayout value modeling,
and full upstream Pandoc runner parity remain separate bounded slices.

Root harness: not run - isolated micro-slice.
