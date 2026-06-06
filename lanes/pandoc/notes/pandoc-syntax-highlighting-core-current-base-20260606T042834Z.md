# Pandoc Syntax Highlighting CMake Slice

Date: 2026-06-06 UTC
Lane: pandoc
Micro-slice: pandoc-syntax-highlighting-core-current-base-20260606T042834Z
Accepted base: 9fecdcbe71562bc1bac82854e69d6378cb0f5882

## Scope

This slice adds bounded native PHP syntax-highlighting support for CMake review snippets. It maps `cmake`, `CMakeLists.txt`, and `language-cmake` aliases to a CMake tokenizer and carries the highlighted HTML through Markdown fenced-code attributes and the WordPress syntax-highlighting handoff example.

The tokenizer covers the conversion-relevant contract for CMake build-review blocks:

- line comments and bracket comments
- command calls such as `cmake_minimum_required`, `project`, `set`, `option`, `add_library`, `target_compile_definitions`, `target_include_directories`, and `install`
- keyword arguments, cache/type constants, booleans, variables, generator expressions, strings, attributes, numbers, and operators
- source wrappers, line numbering, style metadata, and WordPress block attributes already used by the existing syntax-highlighting handoff

## Source Truth

Pandoc's highlighting path delegates language definitions to Skylighting. The bounded format contract here follows the Skylighting/Pandoc CMake handoff shape for CMake/CMakeLists code blocks and ports only the PHP support-library behavior needed by the local Markdown/WordPress syntax-highlighting lane.

No local hydrated Pandoc checkout was available in this isolated worktree or the shared upstream cache, and no external CMake parser/highlighter was executed. No Pandoc, Cabal build, Haskell runner, CMake executable, compiler, browser renderer, JavaScript, online sanitizer, online service, or live provider test was executed.

## Red-First Evidence

Before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 977 assertions, 0 failures
```

Unsupported-language probe:

```text
normalizeLanguage("cmake") => NULL
language => ''
diagnostics[0].code => unsupported-language
```

After adding the focused fixture/test, the first implementation failed:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 999 assertions, 1 failures
```

The failure showed `${CMAKE_CURRENT_SOURCE_DIR}/include` incorrectly tokenized the path segment `include` as a function. The command scanner now only classifies CMake command names when they are followed by `(`.

## Verification

Focused test after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1005 assertions, 0 failures
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

- Focused SyntaxHighlighter assertions: 977 -> 1005.
- Focused PHP PASS cases: +1.
- `lanes/pandoc/lane-status.json` `phpPass`: 1194 -> 1195.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped checks: 1641 -> 1642.

## Non-Overlap

This slice does not repeat existing syntax-highlighting coverage for CSS, Rust, Nix, SCSS/Sass, Go, PowerShell, DOT, JavaScript, C#, SQL/Postgres, Apache, Lua long brackets, PHP heredoc, RST, or TSX. It owns only bounded CMake/CMakeLists alias and token handoff behavior.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `SyntaxHighlighter`, `MarkdownReader`, and `WordPressBlockWriter` handoff paths.

The upstream-runner blocker remains unchanged: full upstream Pandoc runner parity still needs a hydrated Pandoc checkout at 0640c4c9859aa5a3ede082c190fcd5883c24ac83 plus Cabal project/package files and Haskell Tasty executable builds for `test-pandoc` and `test-pandoc-lua-engine`.

## Follow-Up

Keep full Skylighting XML parity, broader CMake policy/property state handling, deeper nested generator-expression parsing, variable interpolation inside quoted strings, embedded language highlighting, and complete theme coverage as separate bounded syntax-highlighting slices.
