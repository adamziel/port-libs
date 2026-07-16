# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T094739Z`

Base accepted HEAD: `54d4990abd113041d05e6000e22de0cf52a8be6c`

## Behavior Added

- Extended bounded native `SyntaxHighlighter` Java handoff:
  - normalizes `java` code-block classes into canonical `java` highlighting;
  - tokenizes bounded Java migration-review snippets for package/import
    declarations, comments, annotations, class and constructor declarations,
    fields, primitive and common library types, generics, `throws` clauses,
    contextual `var`, static method calls, instance method calls, control
    flow, strings, and operators using the existing Pandoc/Skylighting-style
    short classes;
  - preserves Pandoc numbered-source wrappers, `startFrom` counters, and
    WordPress raw HTML style metadata for Java helper-script review packets.
- Updated the WordPress syntax-highlighting fixture and example self-test with
  a numbered Java review snippet so migration reviewers can inspect Java
  import helpers without invoking Pandoc, Java, Skylighting, external
  highlighters, browser renderers, or online conversion services.

## Source Truth

- Pandoc `Text.Pandoc.Highlighting` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` delegates code-block
  highlighting to Skylighting syntax lookup by code-block classes, carries the
  built-in style names, and preserves `numberLines`, `lineAnchors`, and
  `startFrom` through formatter options. Its listings-language map includes
  `java` -> `Java`.
- Skylighting's Java XML syntax definition declares `Java`, extension
  `*.java`, and token categories for Java keywords, comments, annotations,
  strings/chars, integer/float literals, primitive types, common library
  types/classes, functions, and operators. This slice ports a bounded token
  handoff, not the full KDE XML state machine.
- Sources checked:
  - `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Highlighting.hs`
  - `https://raw.githubusercontent.com/jgm/skylighting/refs/heads/master/skylighting-core/xml/java.xml`
- No Pandoc binary, Cabal solver/build/test command, Haskell runner,
  Skylighting runtime, Java runtime/compiler, external highlighter, browser
  renderer, online sanitizer, office tool, archive tool, TeX/PDF engine,
  Typst, roff, or online conversion service was executed.

## Verification

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 459 assertions, 0 failures`
- Red-first focused probe:
  - `php -r 'require "tools/bootstrap.php"; $h = new \PortLibs\Pandoc\SyntaxHighlighter(); $result = $h->highlight("class ReviewPacket { }", "java"); var_export([\PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("java"), $result["language"], $result["diagnostics"]]); echo "\n";'`
  - Result: `normalizeLanguage("java")` returned `NULL`, language was empty,
    and diagnostics contained `unsupported-language`.
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 482 assertions, 0 failures`
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
INI/config, TOML/Cargo.lock, Perl/PL/PM, or Pandoc JSON `.theme` support. It
also avoids Markdown/HTML reader coverage, XML/HTML5 DOM support, EPUB3
package handoff, DOCX/ODT parsing, ZIP/OPC, archive compression, PDF engine
diagnostics, BibTeX/CSL, YAML, doctemplate, table geometry, math/TeX
conversion, charset/Unicode, and legacy DOC/CFB slices. It owns only bounded
Java syntax-highlighting alias and token handoff.

## Follow-Up

Keep XML-specific highlighting, shell parser state, multiline TOML string edge
parity, Perl here-document and full regex-state parity, token title
attributes, parser-state-aware embedded-language highlighting, writer-wide
default highlighting policy, and full KDE/Skylighting XML syntax-definition
parity as separate bounded slices.
