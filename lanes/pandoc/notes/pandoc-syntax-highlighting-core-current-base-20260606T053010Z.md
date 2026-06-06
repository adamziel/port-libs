# Pandoc Syntax Highlighting Twig Slice

Date: 2026-06-06 UTC
Lane: pandoc
Micro-slice: pandoc-syntax-highlighting-core-current-base-20260606T053010Z
Accepted base: 5461910d04f397e37087574b1ad2209244ea6334

## Scope

This slice adds bounded native PHP syntax-highlighting support for Twig/Timber review snippets. It maps `twig`, `timber`, `html+twig`, `html-twig`, and `twig-html` aliases to a Twig tokenizer and carries highlighted HTML through Markdown fenced-code attributes and the WordPress syntax-highlighting handoff example.

The tokenizer covers the conversion-relevant contract for WordPress theme review blocks:

- Twig comments, template delimiters, control tags, variables, dotted paths, object keys, constants, operators, strings, and numbers.
- Twig filters/functions such as `default`, `e`, `raw`, `function`, and `include`.
- Embedded HTML tags and common attributes inside Twig template snippets.
- Source wrappers, line numbering, style metadata, and WordPress block attributes already used by the existing syntax-highlighting handoff.

## Source Truth

Pandoc's highlighting path delegates language definitions to Skylighting. The bounded format contract here follows the Skylighting/Pandoc handoff shape for Twig template code blocks and ports only the PHP support-library behavior needed by the local Markdown/WordPress syntax-highlighting lane.

No local hydrated Pandoc checkout was available in this isolated worktree or the shared upstream cache, and no external Twig parser/highlighter was executed. No Pandoc, Cabal build, Haskell runner, Skylighting runner, Twig runtime, PHP template engine, browser renderer, JavaScript, online sanitizer, online service, or live provider test was executed.

## Red-First Evidence

Baseline before this slice:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1037 assertions, 0 failures
```

After adding the focused Twig fixture/test and before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1039 assertions, 1 failures
```

The failure showed `SyntaxHighlighter::normalizeLanguage('twig')` returned `NULL`, so Twig fenced-code attributes fell through to the unsupported-language path.

## Verification

Focused test after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1062 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

Syntax and diff checks were run before handoff:

```text
php -l lanes/pandoc/src/SyntaxHighlighter.php
php -l lanes/pandoc/tests/SyntaxHighlighterTest.php
php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php
git diff --check -- lanes/pandoc
```

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused SyntaxHighlighter assertions: `1037 -> 1062`.
- Focused PHP PASS cases: `+1`.
- `lanes/pandoc/lane-status.json` `phpPass`: `1210 -> 1211`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped checks: `1656 -> 1657`.

## Non-Overlap

This slice does not repeat existing syntax-highlighting coverage for CSS, Rust, Nix, SCSS/Sass, Go, PowerShell, DOT, JavaScript, C#, SQL/Postgres, Apache/htaccess, Lua long brackets, PHP heredoc, RST, TSX, CMake, or Nginx. It owns only bounded Twig/Timber template review-packet behavior.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `SyntaxHighlighter`, `MarkdownReader`, and `WordPressBlockWriter` handoff paths.

The upstream-runner blocker remains unchanged: full upstream Pandoc runner parity still needs a hydrated Pandoc checkout at 0640c4c9859aa5a3ede082c190fcd5883c24ac83 plus Cabal project/package files and Haskell Tasty executable builds for `test-pandoc` and `test-pandoc-lua-engine`.

## Follow-Up

Keep full Skylighting XML parity, broader Twig tag/test/operator coverage, embedded HTML/context-sensitive highlighting, complete theme coverage, and upstream Haskell runner comparison as separate bounded syntax-highlighting slices.
