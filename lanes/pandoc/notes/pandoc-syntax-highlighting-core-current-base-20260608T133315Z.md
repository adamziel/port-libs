# Pandoc Syntax Highlighting Core Current Base - Vue SFC

Slice: `pandoc-syntax-highlighting-core-current-base-20260608T133315Z`
Base accepted HEAD: `1ae0859e60102323dd11b913da6001a073a626eb`

## Behavior

- Added native SyntaxHighlighter support for Vue single-file component code blocks.
- Normalizes `vue`, `vuejs`, `vue-sfc`, `vue-component`, and `html-vue` to `vue`.
- Tokenizes Vue template comments, tags, component tags, `v-*` directives, `:`, `@`, and `#` shorthand attributes, moustache interpolation expressions, embedded `<script setup lang="ts">` TypeScript, and embedded `<style>` CSS.
- Added a WordPress review fixture block for a Vue import card and wired the example smoke to verify the highlighted WordPress raw HTML block.

## Source Truth

Pandoc delegates source highlighting to Skylighting-style language alias and token handoff. This slice ports the bounded contract needed by the PHP lane: preserve code-block language metadata, line numbering, token classes, and WordPress block handoff for a Vue SFC fixture. The isolated worktree has no local Pandoc checkout under `.upstream-cache/pandoc`, and no external runner was used.

## Evidence

- Rework-note check: no `port-pandoc-*.needs-lane-rework.md` note existed for this lane before editing.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 1655 assertions, 0 failures`.
- Red-first probe before implementation: `SyntaxHighlighter::normalizeLanguage('vue')` returned `NULL`; highlighting Vue produced unsupported-language diagnostics.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 1690 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test` passed with `syntax highlighting handoff self-test ok`.
- Lane status delta: `phpPass` increases from `1654` to `1655`.
- Manifest delta: `benchmarkDenominator.mapped` increases from `2074` to `2075`; `mappedSyntaxHighlightingVueSfcCases` is `1`.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP components: `SyntaxHighlighter`, `MarkdownReader`, `AstNode` code-block metadata, and WordPress raw HTML block handoff. Full upstream Pandoc/Skylighting parity remains a separate upstream-runner concern.

## Non-Overlap

This does not repeat earlier accepted syntax-highlighting slices for CSS, Rust, AsciiDoc, HCL/Terraform, Typst, Kotlin, Clojure/EDN, Scala, Elixir, HTML embedded CSS/JavaScript/PHP, GraphQL, TSX, JavaScript, or general TypeScript snippets. The only TypeScript tokenizer extension is the bounded generic-call handoff required by Vue `defineProps<{...}>()` inside embedded SFC script content.

## Exclusions

Did not run Pandoc, Cabal solver/build/test commands, Haskell runners, Skylighting, Vue compilers, Vite, browser renderers, JavaScript runtimes, external highlighters, online sanitizers, online services, live provider tests, or live-service provider tests.
