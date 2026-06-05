# Pandoc doctemplates core current-base 2026-06-05T10:34:08Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` explicit `$^$` nesting with upstream
  doctemplates when the nested template starts with literal text before the
  first multiline value.
- `$^$` now remains pending through non-multiline literal chunks and is
  consumed when a multiline rendered variable or partial needs indentation.
- Added focused coverage for a reviewer label before a multiline variable and
  a label before a multiline partial.
- Updated the WordPress doctemplate review-packet smoke with a labeled
  multiline note so reviewer packets preserve source note indentation.

## Source Truth

- Upstream `Text.DocTemplates.Parser` parses `$^$` as a `Nested` template and
  keeps the nested column active while parsing the following template content:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
- Upstream `Text.DocTemplates.Internal` models `Nested` as a template node,
  not as a one-token modifier:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Internal.hs
- No Pandoc binary, Haskell test binary, Cabal build, Word, LibreOffice,
  `zip`/`unzip`, `tar`, `lz4`, external template engine, TeX/PDF engine,
  browser renderer, roff, Typst, MathJax, KaTeX, online sanitizer, online
  conversion service, or live service was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Red-first probe before the implementation:
  `php -r 'require "tools/bootstrap.php"; $r = new \PortLibs\Pandoc\DocTemplate(); $template = "<p>\$^\$Label: \$body\$</p>"; $out = $r->render($template, ["body" => "First\nSecond"]); fwrite(STDOUT, str_replace("\n", "\\n\n", $out));'`
  printed `<p>Label: First\nSecond</p>`, proving the continuation was not
  nested after the literal label consumed the marker.
- Focused verification:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 59 assertions, 0 failures.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- PHP lint:
  `php -l lanes/pandoc/src/DocTemplate.php`,
  `php -l lanes/pandoc/tests/DocTemplateTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
  all passed with no syntax errors.
- Lane JSON validation:
  `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, "$f: " . json_last_error_msg() . PHP_EOL); exit(1); } } echo "pandoc json ok\n";'`
  passed with `pandoc json ok`.
- `git diff --check -- lanes/pandoc` passed with no output.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
conditionals, loop scoping, separators before or after pipes, `$it$`,
automatic standalone multiline nesting, `$~$` breakable-space whitespace
reflow, parameter-free pipes, parameterized block pipes, display-width
padding, missing/null pipe handling, resource-map partial discovery,
path-style partial lookup, applied partial parsing and rebinding, partial
final-newline handling, partial recursion guards, braced directive tokenizer
behavior, alpha overflow labels, boolean false output rendering, Unicode
identifier parsing, multiline control boundary newline swallowing, empty
standalone partial line swallowing, deterministic map-pairs ordering, trailing
separators after piped variables, included-partial final-LF omission, or
display-width column calculation for immediate `$^$` multiline values. It only
keeps explicit nesting pending through literal prefixes before a multiline
value or partial.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode
source primitives, or upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`pandoc-doctemplates-core` renderer and its existing display-width nesting
primitive. Full source-position nested-template parsing across dedented source
lines, full doclayout width-sensitive wrapping, richer source-location
diagnostics, filesystem-backed template discovery beyond the existing resource
map, writer-extension template selection, default-template parity, and full
upstream Pandoc runner parity remain separate activation slices.

Root harness: not run - isolated micro-slice.
