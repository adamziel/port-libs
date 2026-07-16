# pandoc-syntax-highlighting-core-current-base-20260606T004724Z

## Scope

- Lane: `pandoc`
- Accepted base: `6a963ce5aa23fa0dee2a787b94434d4275b2c577`
- Ownership: bounded syntax-highlighting support under `lanes/pandoc/**`
- Behavior cluster: reStructuredText/RST language aliases and token handoff for WordPress review-packet code blocks.

## Source Truth

- Pandoc delegates code block highlighting through its syntax-highlighting layer and preserves source-code language classes, styles, and line-numbering attributes for writers.
- Skylighting's `rest.xml` declares the language as `reStructuredText` with `*.rst` extension and `text/x-rst` MIME data, and defines highlighting contexts for comments, directives, code blocks, fields, hyperlink targets, inline literals, roles/interpreted text, references, and standalone links.
- The upstream source checked for this bounded implementation was `https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-core/xml/rest.xml`.
- `javascript-react.xml` was also checked and explicitly removes TSX extension handling, so this slice avoided adding a TSX alias and stayed with the RST ownership.
- No Pandoc, Cabal solver/build/test command, Haskell runner, Skylighting runtime, external highlighter, browser renderer, JavaScript, online sanitizer, online service, or live provider test was executed.

## Implementation

- `SyntaxHighlighter::normalizeLanguage()` now accepts `rst`, `rest`, `reStructuredText`, and `language-restructured-text` via the existing class-prefix normalization path.
- `SyntaxHighlighter` now tokenizes bounded RST review snippets:
  - comments;
  - section underline markers;
  - field lists;
  - directives and hyperlink targets;
  - indented `code-block` bodies;
  - inline literals;
  - substitution and explicit references;
  - roles/interpreted text;
  - standalone HTTP/FTP links;
  - bold/italic emphasis markers.
- The WordPress syntax-highlighting fixture and example now include an RST review block with line-number metadata and raw HTML block output.

## Evidence

- Red-first probe before implementation:
  - `SyntaxHighlighter::normalizeLanguage("rst")` returned `null`.
  - Highlighting an RST `code-block` snippet produced `unsupported-language`.
- Baseline before adding the RST case:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 917 assertions, 0 failures`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 947 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`.

## Status Delta

- Focused SyntaxHighlighter coverage: `917 -> 947` assertions.
- PHP PASS cases: `+1` focused PASS case.
- Lane `phpPass`: `1124 -> 1125`.
- Manifest mapped checks: `1576 -> 1577`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP `SyntaxHighlighter`, `MarkdownReader`, `AstNode`, `WordPressBlockWriter`, syntax-highlighting fixture, and WordPress handoff example.

The upstream-runner blocker is unchanged: full Pandoc runner parity still requires a hydrated Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal project/package files and runner dependency closure before any bounded non-mutating Haskell runner plan can be marked ready.

## Non-Overlap

This does not repeat accepted CSS, Rust, Nix, SCSS, Go, PowerShell, DOT, JavaScript, C#, SQL, PostgreSQL, Apache, Lua long-bracket, or PHP heredoc syntax-highlighting slices. It only adds bounded reStructuredText/RST alias and token handoff behavior.

## Follow-Up

- Parser-state-aware embedded language highlighting inside RST `code-block` bodies.
- Directive option parsing and nested directive/comment state beyond this bounded scanner.
- Additional language grammars and full Skylighting XML parity in separate syntax-highlighting slices.
