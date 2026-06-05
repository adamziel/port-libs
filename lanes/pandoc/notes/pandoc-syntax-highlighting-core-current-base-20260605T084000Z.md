# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T084000Z`

Base accepted HEAD: `ef4cf6dacf5f14d3905927d3fba9b6ca3557990c`

## Behavior Added

- Extended bounded native `SyntaxHighlighter` TOML configuration handoff:
  - normalizes `toml` and `Cargo.lock` code-block classes into canonical
    `toml` highlighting;
  - tokenizes bounded TOML review snippets for first-non-space comments,
    table and array-table headers, dotted keys, assignment operators,
    booleans, quoted strings, datetimes, integers, arrays, and inline tables
    using the existing Pandoc/Skylighting-style short classes;
  - preserves Pandoc numbered-source wrappers, `startFrom` counters, and
    WordPress raw HTML style metadata for static-export configuration review
    packets.
- Updated the WordPress syntax-highlighting fixture and example self-test with
  a numbered TOML review snippet so migration reviewers can inspect config
  source without invoking Pandoc, Skylighting, TOML parsers, external
  highlighters, browser renderers, or online conversion services.

## Source Truth

- Pandoc `Text.Pandoc.Highlighting` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` delegates code-block
  highlighting to Skylighting syntax lookup by code-block classes and carries
  `startFrom`, `numberLines`, `lineAnchors`, `lineIdPrefix`, source code
  classes, and built-in styles through formatter options.
- Skylighting's TOML XML syntax definition declares `TOML`, `*.toml`, and
  `Cargo.lock`, and gives table headers, keys, assignment, booleans, strings,
  arrays, inline tables, dates, integers, floats, and comments visible
  highlighting categories. This slice ports a bounded token handoff, not the
  full KDE XML syntax engine.
- Sources checked:
  - `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Highlighting.hs`
  - `https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-core/xml/toml.xml`
- No Pandoc binary, Cabal solver/build/test command, Haskell runner,
  Skylighting runtime, external TOML parser, external highlighter, browser
  renderer, online sanitizer, office tool, archive tool, TeX/PDF engine,
  Typst, roff, or online conversion service was executed.

## Verification

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 408 assertions, 0 failures`
- Red-first focused probe:
  - `php -r 'require "tools/bootstrap.php"; $h = new \PortLibs\Pandoc\SyntaxHighlighter(); $result = $h->highlight("[tool]\nenabled = true\n", "toml"); var_export([\PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("toml"), $result["language"], $result["diagnostics"]]); echo "\n";'`
  - Result: `normalizeLanguage("toml")` returned `NULL`, language was empty,
    and diagnostics contained `unsupported-language`.
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 431 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`
- Syntax:
  - `php -l lanes/pandoc/src/SyntaxHighlighter.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php`
  - Result: no syntax errors.
- JSON validity:
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`
  - Result: both JSON files decoded successfully.
- Diff hygiene:
  - `git diff --check -- lanes/pandoc`
  - Result: no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing `AstNode`,
`MarkdownReader`, `WordPressBlockWriter`, and bounded native
`SyntaxHighlighter` support row. Full upstream runner parity remains gated on
hydrating the Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`
and producing a Cabal solver/build plan for `test-pandoc` and
`test-pandoc-lua-engine`.

## Non-Overlap

This patch does not repeat accepted syntax-highlighting coverage for base
language/style/token support, line anchors, WordPress writer opt-in, Haskell,
TeX/LaTeX, diff/patch, Markdown-family, Ruby/Rake, Lua/Pandoc-Lua,
TypeScript, Python, C/C++, Dockerfile/Containerfile, Makefile, JSX/React, R,
INI/config, or Pandoc JSON `.theme` support. It also avoids Markdown/HTML
reader coverage, XML/HTML5 DOM support, EPUB3 package handoff, DOCX/ODT
parsing, ZIP/OPC, archive compression, PDF engine diagnostics, BibTeX/CSL,
YAML, doctemplate, table geometry, math/TeX conversion, charset/Unicode, and
legacy DOC/CFB slices. It owns only bounded TOML/Cargo.lock
syntax-highlighting alias and token handoff.

## Follow-Up

Keep Perl, Java, XML-specific highlighting, shell parser state, TOML multiline
string edge parity, token title attributes, parser-state-aware embedded
language highlighting, writer-wide default highlighting policy, and full
KDE/Skylighting XML syntax-definition parity as separate bounded slices.
