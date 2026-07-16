# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T091118Z`

Base accepted HEAD: `a45ca97f406d7ee0c5dd0511dc2a10ff6abec006`

## Behavior Added

- Extended bounded native `SyntaxHighlighter` Perl handoff:
  - normalizes `perl`, `pl`, `PL`, and `pm` code-block classes into canonical
    `perl` highlighting;
  - tokenizes bounded Perl migration-review snippets for shebangs, comments,
    pragmas, package and sub declarations, variables, hash dereferences,
    substitution forms, word and symbolic operators, strings, constants,
    numbers, and built-in functions using the existing Pandoc/Skylighting-style
    short classes;
  - preserves Pandoc numbered-source wrappers, `startFrom` counters, and
    WordPress raw HTML style metadata for Perl import scripts.
- Updated the WordPress syntax-highlighting fixture and example self-test with
  a numbered Perl review snippet so migration reviewers can inspect legacy
  helper scripts without invoking Pandoc, Skylighting, Perl, external
  highlighters, browser renderers, or online conversion services.

## Source Truth

- Pandoc `Text.Pandoc.Highlighting` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` delegates code-block
  highlighting to Skylighting syntax lookup by code-block classes, carries the
  built-in style names, and preserves `numberLines`, `lineAnchors`, and
  `startFrom` through formatter options. Its listings mapping also maps
  `perl` to `Perl`.
- Skylighting's Perl XML syntax definition declares `Perl`, alternative name
  `PL`, `*.pl`, `*.PL`, and `*.pm`, and provides categories for control flow,
  keywords, operators, functions, pragmas, shebangs, comments/POD, numbers,
  strings, variables, here documents, regex patterns, substitutions, and
  package/sub names. This slice ports a bounded token handoff, not the full KDE
  XML state machine.
- Sources checked:
  - `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Highlighting.hs`
  - `https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-core/xml/perl.xml`
- No Pandoc binary, Cabal solver/build/test command, Haskell runner,
  Skylighting runtime, Perl runtime, external highlighter, browser renderer,
  online sanitizer, office tool, archive tool, TeX/PDF engine, Typst, roff, or
  online conversion service was executed.

## Verification

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 431 assertions, 0 failures`
- Red-first focused probe:
  - `php -r 'require "tools/bootstrap.php"; $code = base64_decode("dXNlIHN0cmljdDsKbXkgJHRpdGxlID0gJHBhY2tldC0+e3RpdGxlfTsK"); $h = new \PortLibs\Pandoc\SyntaxHighlighter(); $result = $h->highlight($code, "perl"); var_export([\PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("perl"), \PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("pl"), \PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("pm"), $result["language"], $result["diagnostics"]]); echo "\n";'`
  - Result: `perl`, `pl`, and `pm` normalized to `NULL`, language was empty,
    and diagnostics contained `unsupported-language`.
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 459 assertions, 0 failures`
- Focused lane directory:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 9609 assertions, 0 failures`
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
INI/config, TOML/Cargo.lock, or Pandoc JSON `.theme` support. It also avoids
Markdown/HTML reader coverage, XML/HTML5 DOM support, EPUB3 package handoff,
DOCX/ODT parsing, ZIP/OPC, archive compression, PDF engine diagnostics,
BibTeX/CSL, YAML, doctemplate, table geometry, math/TeX conversion,
charset/Unicode, and legacy DOC/CFB slices. It owns only bounded Perl/PL/PM
syntax-highlighting alias and token handoff.

## Follow-Up

Keep Java, XML-specific highlighting, shell parser state, multiline TOML
string edge parity, Perl here-document and full regex-state parity, token
title attributes, parser-state-aware embedded language highlighting,
writer-wide default highlighting policy, and full KDE/Skylighting XML
syntax-definition parity as separate bounded slices.
