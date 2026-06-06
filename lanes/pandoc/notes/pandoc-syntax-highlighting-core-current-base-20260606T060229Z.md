# Pandoc Syntax Highlighting Core Current Base - Mustache/Handlebars

Slice: `pandoc-syntax-highlighting-core-current-base-20260606T060229Z`
Base accepted HEAD: `93b3b4a17fab2567420adc36472c6c9eb55618e0`

## Summary

- Added canonical `mustache` syntax highlighting with `mustache`, `handlebars`, `hbs`, `ractive`, `hogan`, `hulk`, `html.mst`, `html.mu`, `html.rac`, and HTML-Handlebars aliases.
- Tokenizes bounded HTML-template review snippets: HTML tags and attributes, Handlebars comments, sections, else branches, helpers, variables and dotted paths, triple-stash raw expressions, partials, hash arguments, strings, and numbers.
- Updated the WordPress syntax-highlighting fixture and self-test example so imported template review packets keep line numbers, style metadata, and raw HTML handoff output.

## Source Truth

- Pandoc delegates syntax highlighting to Skylighting. The upstream Skylighting `mustache.xml` language definition names `Mustache/Handlebars (HTML)` and lists Mustache, Handlebars, HBS, Ractive, Hogan, Hulk, and HTML-template extensions.
- Source consulted: `https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-core/xml/mustache.xml`.
- Relevant upstream contexts include Mustache comments, Handlebars comments, sections, partials, raw blocks, variables, helpers, strings, numbers, attributes, and HTML tag contexts.

## Pre-Edit Probe

`php -r 'require "tools/bootstrap.php"; $h = new \PortLibs\Pandoc\SyntaxHighlighter(); $result = $h->highlight("{{#if title}}{{title}}{{/if}}", "hbs"); var_export([\PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("hbs"), \PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("handlebars"), $result["language"], $result["diagnostics"]]); echo "\n";'`

Result: `hbs` and `handlebars` normalized to `NULL`; highlighting returned an unsupported-language diagnostic.

## Verification

- Baseline before edit: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 1062 assertions, 0 failures`.
- After implementation: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 1092 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test` passed with `syntax highlighting handoff self-test ok`.
- Syntax checks passed for `lanes/pandoc/src/SyntaxHighlighter.php`, `lanes/pandoc/tests/SyntaxHighlighterTest.php`, and `lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'` passed with `pandoc json ok`.
- `git diff --check -- lanes/pandoc` passed with no output.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP `SyntaxHighlighter`, `MarkdownReader`, `AstNode`, `WordPressBlockWriter`, and lane-local manifest/status machinery. Full upstream runner parity remains blocked on a hydrated Pandoc checkout plus Cabal/Tasty runner dependency closure.

No Pandoc, Cabal solver/build/test command, Haskell runner, Skylighting runtime, Handlebars/Mustache template engine, browser renderer, JavaScript runtime, online sanitizer, online service, or live provider test was executed.

## Non-Overlap

This slice does not repeat accepted CSS, Rust, Nix, SCSS, Go, PowerShell, DOT, JavaScript, C#, SQL/PostgreSQL, Apache, Lua, PHP heredoc, RST, TSX, CMake, Nginx, or Twig syntax-highlighting support. It owns only bounded Mustache/Handlebars HTML-template alias and token handoff.

## Follow-Up

Keep full Skylighting XML parity, parser-state-aware embedded JavaScript/CSS inside HTML templates, richer Mustache raw-block state, adjacent-expression source locations, additional language grammars, and writer-wide default highlighting policy as separate bounded slices.
