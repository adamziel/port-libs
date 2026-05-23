# difftastic WordPress Scenario

Readable diffs for blocks, render callbacks, templates, theme.json, code snippets, and structured documents.

## Current Native Slice

Native token-level differ that avoids raw line-only comparison, classifies comments separately, carries delimiter open/close anchors, filters comments on request, normalizes trailing commas before closing delimiters with language-specific Python tuple handling, reports recursive changes inside bracketed syntax lists, applies upstream-style word/subword splitting for punctuation, Unicode words, and number boundaries, and renders escaped HTML for token, word/subword, and syntax-list changes.

Plain-text mode now maps the upstream `sample_files/added_line_*.txt` line-parser shape. Explicit `text`/`plain` syntax-list diffs report changed, inserted, and deleted lines under `$text.line[...]` paths without a fallback marker or a false "No syntactic changes" result when there are no delimiters.

Plain-text JSON display now maps the upstream `sample_files/insert_blank_*.txt` golden shard. Blank-line-only changes stay visible as `changed` file envelopes with zero-based aligned lines and an empty deletion-side line chunk instead of being hidden by token-level equality.

The WordPress plugin readme fixture applies that to `readme.txt` release notes. It reports a stable-tag update, a description wording update, and an inserted changelog section under `$text.line[...]` paths while keeping retained older changelog entries matched.

The WordPress plugin readme blank-line fixture applies the same display slice to release notes where a spacing-only changelog cleanup still matters to reviewers. Compact JSON output reports the deleted blank line for `wp-content/plugins/acme-events/readme.txt` instead of returning `unchanged`.

Plain-text line splitting now maps upstream `src/lines.rs` `split_on_newlines` trailing EOF behavior and the `sample_files/repeated_line_no_eol_*.txt` fixture. Text mode preserves trailing empty lines and appended repeated final lines instead of trimming them away before `$text.line[...]` output.

The WordPress import-log no-EOL fixture applies that to migration output under `wp-content/uploads/migration/import.log`. Compact JSON output preserves a final appended import record even when neither side ends with a newline.

Rust mode now adds a targeted upstream `slider_*.rs` mapping for method and statement sliders. It splits block items at method boundaries and semicolon-terminated statements so code-review excerpts keep retained methods and follow-up statements stable while reporting inserted setup calls and fields as focused additions.

Python mode now adds a targeted upstream `if_*.py` mapping for indentation-sensitive blocks. It keeps a stable `if` header and retained body lines aligned when a statement moves out of the indented body, reporting the moved statement separately under `$py.if[...]` and `$py.root[...]` paths.

Python mode also maps upstream `ignore_trailing_tokens` behavior from `src/parse/tree_sitter_parser.rs`: list, dict, set, argument-list, and parameter trailing commas are formatting-only, while tuple commas are still semantic. This keeps migration-script formatting churn quiet without hiding `("classic-editor",)` becoming a grouped string.

Python mode now maps a targeted upstream directory fixture excerpt from `sample_files/dir_*/has_many_hunk.py` for `def` block header updates. It reports `def function041()` to `def function041(**args)` at `$py.def["function041"]/header` while keeping stable neighboring functions out of the change stream.

The same indentation-block slice now recognizes bounded `for`, `while`, and `with` headers. The WordPress loop migration fixture applies this to a content migration loop where `hydrate_featured_media(post)` moves out of `for post in posts`, reporting the loop-body deletion and top-level insertion without deleting the retained loop header.

Python mode now recursively parses nested indentation suites instead of flattening every indented line into its nearest top-level block. A targeted upstream `sample_files/dir_*/has_many_hunk.py` excerpt maps `function081` gaining an `if True:` child block while retaining its direct `pass` and neighboring functions.

The WordPress nested migration fixture applies that to a migration function where `migrate_posts()` loops over posts and gains a featured-media guard. The diff reports the inserted guard at `$py.def["migrate_posts"].for["post in posts"].if[...]` without deleting the retained function, loop, `normalize_blocks(post)`, or `save_post(post)`.

Python mode now recognizes compound clauses from the same upstream parser boundary: `elif`, `else`, `try`, `except`, and `finally`. Continuation clauses are attached to the preceding `if`, `for`, `while`, or `try` path, so inserted branches and cleanup clauses do not force retained branch bodies to appear deleted.

The WordPress compound migration fixture applies that to a migration helper where a raw-HTML sanitization branch is inserted after a legacy-builder branch and a `finally` cleanup is added after a retained exception handler. The diff reports `$py.def["migrate_post"].if[...].elif[...]` and `$py.def["migrate_post"].try[...].finally[...]` paths while preserving the original `if`, `else`, `try`, and `except` blocks.

HTML and XML modes add upstream-style angle-bracket delimiters without changing default code tokenization, where `<` and `>` remain punctuation/operators. This lets block markup, saved post content, and XML export diffs report tag-list changes such as class mutations, inserted `id` attributes, newly inserted inline tags, and namespaced metadata tags while still escaping the rendered review HTML.

JSON mode aligns object items by their string property key before comparing values. This maps the upstream `sample_files/json_*.json` pair and keeps WordPress `block.json`/`theme.json` metadata reviews focused on changed values and nested arrays instead of whole-object churn.

Emacs Lisp mode now keeps reader quotes and semicolon comments distinct from strings, and splits flat quoted string/comment lists as individual items. This maps a targeted upstream `strings_*.el` excerpt so large keyword-list changes stay item-focused while preserving the existing Lisp outer-wrapper behavior used to validate WordPress template-wrapper diffs.

The current WordPress fixture compares a block render callback where a PHP comment changes at the same time as the escaping API changes from `esc_html` to `wp_kses_post`. With `ignoreComments`, the diff hides the comment-only churn but still reports the security-relevant API change.

The render-callback return-type fixture applies the upstream `sample_files/hack_*.php` shape to a PHP block callback where `string` becomes `?string` and an empty-title branch starts returning `null`. The syntax-list renderer reports the nullable return type at `$php.function.acme_render_card.return_type` and keeps stable returned markup out of the change stream.

The recursive list fixture compares nested `register_block_type` arrays so block support changes such as `html => false` becoming `html => true` and new alignment support show up at the nested array path instead of as a single flattened line replacement.

The subword fixture compares a `register_block_style` slug change from `legacy-cta-v2` to `modern-cta-v3`. The number-aware word diff reports the changed slug words and version number separately, matching the upstream `src/words.rs` behavior used for readable inline changes.

The HTML fixture combines a block-style subword diff with a `theme.json` palette change. It emits escaped `<span>`, `<del>`, and `<ins>` markers with operation and syntax-list path metadata so a WordPress UI can embed reviewable diffs without trusting source text as HTML.

The block-markup fixture compares saved block HTML where a group block gains an `is-style-card` class, a heading gains an `id`, and the paragraph text is wrapped in `<strong>`. The syntax-list renderer reports the tag-level changes and escapes source tags as text for safe embedding in review UIs.

The WXR XML fixture compares namespaced `wp:postmeta` tags in a migration export. XML mode now maps the upstream `sample_files/xml_*.xml` tag insertion shape and renders changed/inserted postmeta tags as escaped text, so a browser-based migration review surface does not execute or trust source XML.

The block.json fixture compares block metadata where the title changes, a `viewScriptModule` is added, HTML support changes, and `full` alignment is added. JSON key alignment keeps the `supports` object matched while reporting nested changes.

The JSON display fixture emits compact machine-readable review data for that same `block.json` path. It mirrors upstream `src/display/json.rs` by returning the language, path, lowercase status, aligned line pairs, and chunks with per-side line numbers and highlighted novel token spans. This gives a WordPress code review or migration UI data it can render itself without trusting source text as HTML.

The block-copy JSON display fixture compares a block description string where only `legacy` changes to `modern`. It applies upstream `ReplacedString` word splitting from `src/parse/syntax.rs`, so machine-readable review data reports the changed words inside the string rather than replacing the whole description.

The i18n block-copy JSON display fixture applies the upstream `sample_files/multibyte_*.py` display span shape to translated block metadata. It reports `legacy` to `modern` after a Japanese `カード` prefix with byte offsets that preserve the UTF-8 text, so browser review tools can locate changed copy without splitting multibyte characters.

The multiline render doc-comment fixture compares PHP block comments beside a WordPress render callback where `legacy` changes to `modern`. Token byte spans let the JSON renderer project paired multiline comment word diffs back onto line/column spans, so browser review data keeps those words comment-highlighted instead of treating them as normal line text.

The plugin workflow YAML fixture compares a GitHub Actions release step for a block plugin. YAML mode treats `run: |` command bodies as block-scalar string atoms, so WP-CLI command changes such as `make-json` to `make-pot` stay string-highlighted in machine-readable JSON review data instead of falling back to normal per-line token highlighting.

The upstream `trailling_newline_*.yaml` mapping covers the same GitHub Actions review surface when a YAML block scalar changes from an expression such as `${{ BAR }}` to literal command text. The JSON display keeps the expression braces string-highlighted instead of treating them as executable code delimiters.

The plugin workflow step YAML fixture compares a release workflow where a WordPress test environment setup step is inserted and the translation generation step is renamed. The YAML syntax-list renderer aligns nested `jobs.release.steps` block-sequence items so stable checkout/build steps stay out of the change stream while inserted/deleted release steps are shown with `$yaml.jobs.release.steps[...]` paths.

The theme-variation fixture applies the upstream `slider_at_end` JSON list-deletion shape to `theme.json`. Deprecated button variations are reported as focused deletions while retained variations stay out of the rendered change stream.

The template-wrapper fixture applies upstream `nested_slider` wrapper correction to a WordPress block template helper call. When `coreParagraph('Hero introduction')` is wrapped in `coreGroup(...)`, the retained paragraph call stays out of the deletion stream and the diff reports the wrapper as a focused syntactic insertion.

The upstream Emacs Lisp `nested_slider` fixture now exercises the opposite outer-delimiter preference used for Lisp-family languages. This keeps the existing WordPress template-wrapper inner-delimiter behavior honest by proving the implementation can choose the delimiter direction from the mapped language instead of using one wrapper strategy for every syntax.

The upstream Emacs Lisp `change_outer` fixture now exercises changed outer delimiters. The WordPress block allow-list fixture applies the same behavior to PHP array syntax modernization, where `array('core/paragraph', 'core/image')` becomes `['core/paragraph', 'core/image']` without rendering the retained block names as changed.

The upstream Python `if` fixture now exercises indentation-block movement. The WordPress Python migration fixture applies this to a content migration script where `purge_builder_shortcodes(post)` moves out of a `post.get("legacy_builder")` guard; the diff reports the guarded-body deletion and top-level insertion without deleting the retained guard header or `normalize_blocks(post)` call.

The targeted upstream Python trailing-comma config applies to migration scripts that collect WordPress blocks. The WordPress trailing-comma fixture hides added list/dict/call commas around `collect_blocks(...)` while preserving the tuple comma deletion in `legacy_marker = ("classic-editor",)`.

The upstream Python directory fixture excerpt now exercises function-header updates. The WordPress Python loop fixture applies the broader block-header path to a migration script where `hydrate_featured_media(post)` moves out of a post loop; the diff reports `$py.for["post in posts"][1]` and `$py.root[1]` changes without presenting the loop as deleted and re-added.

The upstream Python directory fixture now also exercises a nested block added inside a retained function. The WordPress nested migration fixture applies this to `migrate_posts()` and reports the featured-media guard under `$py.def["migrate_posts"].for["post in posts"].if[...]`, so nested migration-control changes remain reviewable without noisy function or loop replacement.

The targeted Python compound-clause fixture now exercises `elif`, `else`, `try`, `except`, and `finally` boundaries. The WordPress compound migration fixture applies this to a migration helper that adds a raw-HTML branch and a temporary-media cleanup, keeping retained branch and exception-handler bodies stable.

The upstream CSS fixture now exercises selector-block alignment and declaration property matching. The WordPress block-style CSS fixture applies this to global style review: a `.wp-block-acme-card` custom-property color changes, `border-radius` is added, and a query-title selector is introduced while a reordered `.wp-block-image` rule stays out of the rendered change stream.

The upstream Tailwind CSS and simple SCSS fixtures now exercise CSS at-rule item signatures plus SCSS mixin selector/header matching. The WordPress block-editor SCSS fixture applies this to block style mixins: changed mixin defaults and nested `var(--wp--preset--color--*)` references stay focused while the whole `@mixin acme-card(...)` body remains matched.

The upstream HTML style sample now contributes a targeted CSS `@media` extraction. The WordPress nested at-rule fixture applies that container shape to block styles under `@media` and `@supports`, keeping a reordered stable `.wp-block-image` child rule out of the rendered change stream while reporting padding, radius, gap, and grid-template-column changes under the retained `.wp-block-acme-card` paths.

HTML mode now maps the upstream `style_element` sub-language rule from `src/parse/tree_sitter_parser.rs` by extracting `<style>` raw text and parsing it with the native CSS rule matcher. The inline block-template style fixture applies this to saved block markup that carries embedded CSS: color/padding/gap changes are rendered under `$html.style.css[...]` paths, an added query-title rule is shown as a CSS insertion, and reordered stable image rules remain matched at the CSS sub-language layer.

HTML mode now also maps the upstream `script_element` sub-language rule from `src/parse/tree_sitter_parser.rs` by extracting `<script>` raw text and parsing focused JavaScript call arguments. The WordPress Interactivity-style fixture applies this to inline block state bootstrapping: `wp.interactivity.store(...)` state property changes render under `$html.script.js.call[...]` paths, so review UIs can show changed labels and booleans without relying only on raw HTML script text.

HTML root-list comparison now strips `<style>` and `<script>` raw bodies before generic tag/list diffing. The same WordPress Interactivity-style fixture asserts changed state labels and booleans appear under the JavaScript sub-language path without duplicate root `$[...]` raw script churn in the rendered review data.

Indexed HTML raw-text comparison now handles multiple attributed inline asset blocks separately. The multi-inline asset fixture compares a block template that inserts a notice `<style>` block and an analytics `<script type="module">` while retaining existing card and gallery assets. The diff reports the inserted notice rule under `$html.style[0].css[...]`, the changed card CSS under `$html.style[1].css[...]`, the spacing CSS under `$html.style[2].css[...]`, and the inserted analytics store under `$html.script[1].js.call[...]` without treating the retained gallery script as changed.

Standalone JavaScript mode now maps the upstream `sample_files/javascript_simple_*.js` statement shape. The WordPress block view-script fixture applies this to a `view.js` change where existing calls are wrapped in an `if (window.wp)` guard, a block action array gains `share`, and hydration booleans change. The renderer reports the guard under `$js.block[...]`, the action under `$js.array[...]`, and retained following actions stay out of the deletion stream.

Standalone JavaScript mode now also maps the larger upstream `sample_files/javascript_*.js` repeated callback shape. Named callback calls such as Jest `describe(...)` / `test(...)` and WordPress `wp.hooks.addAction(...)` / `addFilter(...)` use their first string label plus enclosing named callback labels when matching repeated calls. The WordPress hook-registration fixture applies this to a block plugin `view.js` change where a new analytics action is inserted before the retained `acme.card.init` callback; the diff reports the new hook and the added `bindCard()` call without pairing the retained init hook with the analytics hook by callee name alone.

Standalone JavaScript mode now also maps a targeted upstream `sample_files/load_*.js` function-declaration shape. Function declarations become call scopes instead of fake `functionName()` calls, so repeated calls inside different helpers are not paired by callee name alone. The WordPress block registration fixture applies this to repeated `wp.blocks.registerBlockType(...)` calls: an inserted `registerQueryBlock()` reports as a new function-scoped registration while the retained gallery registration remains matched.

JavaScript mode now also maps upstream parse-error fallback semantics from `DEFAULT_PARSE_ERROR_LIMIT=0`, `to_syntax_with_limit`, and the CLI `yaml_parse_errors` fallback test. The WordPress block editor syntax-error fixture compares a partial `registerPlugin(...)` edit with an unclosed object literal. Because the combined native delimiter parse-error count exceeds the limit, syntax-list output switches to escaped line-oriented `$text.line[...]` changes and compact JSON display labels the file as `Text (... exceeded DFT_PARSE_ERROR_LIMIT)` instead of showing misleading `$js.call[...]` structural matches.

Supported-language byte-limit fallback now maps upstream `DEFAULT_BYTE_LIMIT`, `to_tree_with_limit`, and `TextFallback` behavior. The WordPress render metadata example lowers the limit to exercise the path with a bounded PHP block metadata change: render callback and support changes are shown as escaped `$text.line[...]` changes, and JSON display labels the file as `Text (... exceeded DFT_BYTE_LIMIT)` instead of attempting an incomplete structural diff.

Supported-language graph-limit fallback now maps upstream `DEFAULT_GRAPH_LIMIT`, `--graph-limit`/`DFT_GRAPH_LIMIT`, `ExceededGraphLimit`, and `TextFallback` behavior. The WordPress block variation example lowers the graph limit to exercise the path with a bounded `registerBlockVariation` change: variation insertions and edits are shown as escaped `$text.line[...]` changes, and JSON display labels the file as `Text (exceeded DFT_GRAPH_LIMIT)` instead of attempting a partial structural `$js.array[...]` diff after the graph budget is exceeded.

Strip-CR normalization now maps upstream `--strip-cr=on` default behavior from `src/options.rs` and `src/main.rs`. The WordPress CRLF render example compares a Windows-edited plugin render file against LF-only output and returns an unchanged compact JSON status by default, while `stripCr => false` preserves CR-only changes for callers that explicitly review line endings.

File-content decoding now maps upstream `src/files.rs` UTF-16 byte-order-mark handling and the `sample_files/utf16_*.py` pair. The WordPress UTF-16 WXR example compares byte-order-marked export XML bytes and renders `_old_builder`, `_wp_page_template`, and `_thumbnail_id` postmeta changes as normal XML JSON chunks instead of reporting the export as a binary file.

File-content decoding now also maps the upstream `src/files.rs` Windows-1252 fallback branch and `sample_files/windows1251_*.txt`. The WordPress legacy encoded readme example compares plugin metadata bytes from an ISO-8859-1/Windows-1252 source, keeps decoded text such as `müller`, `Löst`, and `Blöcke` readable, and reports `alte` to `moderne` release copy as normal text chunks instead of a binary status.

File-content decoding now maps the upstream `src/files.rs` mostly-valid UTF-8 branch and the `tests/cli.rs` `slightly_invalid_utf8` boundary. The WordPress slightly-invalid WXR example compares export XML bytes with one corrupt UTF-8 byte, keeps replacement-character text instead of reinterpreting it as Windows-1252 punctuation, and still reports `Legacy` to `Modern` title changes plus inserted `_wp_page_template` metadata as XML review chunks.

TypeScript mode now maps the upstream `sample_files/typescript_*.ts` type literal shape. The WordPress block editor props fixture applies this to a `BlockEditProps` interface change, reporting an inserted top-level `context: "edit"` member and nested `mediaId: number` attribute member while keeping retained props such as `clientId`, `attributes`, `title`, and `ctaText` aligned.

TypeScript mode now also maps module import/export declaration lists using the upstream TypeScript parser configuration's delimiter-list semantics. The WordPress block module fixture applies this to `index.ts` registration code where `BlockConfiguration`, `sprintf`, and `deprecatedSave` are inserted into existing import/export lists. Retained imports such as `__` and retained exports such as `save` stay aligned under `$ts.import[...]` and `$ts.export.local[...]` paths instead of being shown as deleted and re-added whole module statements.

TypeScript module mode now maps default imports, namespace imports, and re-export source changes in the same upstream parser boundary. The WordPress block module asset fixture compares a block entry point that adds a named `supports` import alongside retained default `metadata`, renames an `@wordpress/block-editor` namespace alias, and moves the `save` re-export to `./frontend/save`. The diff reports these as `$ts.import[...]`, `$ts.import.namespace[...]`, and `$ts.export.source[...]` changes without deleting the retained metadata import or retained `save` specifier.

TypeScript module mode now maps export-star declarations, namespace re-exports, and import assertion/attribute lists in that parser boundary. The WordPress block import attribute fixture compares `block.json` metadata imports moving from `assert { type: "json" }` to `with { type: "json" }`, a retained default `metadata` import gaining a named `supports` import, `export * as icons` becoming `export * as blockIcons`, and `export type * from "./types"` moving to `./frontend/types`. The diff reports those as `$ts.import[...]`, `$ts.import.attributes[...]`, `$ts.export.namespace[...]`, and `$ts.export.type.source["*"]` changes without deleting the retained default import or treating the attribute object as a named import list.

TypeScript module metadata mode now maps dynamic `import()` option objects for block asset loading. The WordPress dynamic metadata fixture compares `import("./block.json", { assert: { type: "json" } })` moving to `with`, a retained `view.js` dynamic import changing from `javascript` to `module`, and an inserted `supports.json` metadata import. Syntax-list output reports retained dynamic import option changes under `$ts.import.dynamic.attributes[...]` instead of only as generic `$js.call["import"]` argument churn, and JSON display output emits machine-readable TypeScript review chunks for the same `assets.ts` fixture.

Compact JSON display now maps upstream `src/display/json.rs` keyword/type highlight variants for common language atoms. The WordPress TypeScript metadata example emits `keyword` spans for inserted `type` and `const` declarations and `type` spans for primitive `string`, `number`, and `boolean` annotations, so a block-review UI can style code semantics without reparsing the file in JavaScript.

Compact JSON display now also maps upstream `src/display/json.rs` `tree_sitter_error` highlight output for parser-error atoms. The WordPress parser-error display example compares block registration JavaScript with an extra `}` and, when the parse-error budget allows structural display, exposes that delimiter as a `tree_sitter_error` span for editor review tools instead of treating it as ordinary punctuation.

JSX/TSX mode now maps the upstream `sample_files/jsx_*.jsx` tag-list shape. The WordPress block editor TSX fixture applies this to an `edit.tsx` sidebar control change, reporting the `PanelBody` title and `initialOpen` attribute change while keeping the retained `TextControl` tag out of the rendered change stream.

TSX mode now maps the upstream `sample_files/whitespace_*.tsx` formatting shape. The WordPress block editor whitespace fixture applies this to editor controls where Prettier or manual formatting moves `{" "}` spacer expressions around retained text. The renderer reports no syntactic changes, keeping retained `ToolbarButton` markup and screen-reader copy out of the review stream.

Run:

```sh
php lanes/difftastic/examples/wordpress-render-callback-diff.php
php lanes/difftastic/examples/wordpress-render-return-type-diff.php
php lanes/difftastic/examples/wordpress-subword-diff.php
php lanes/difftastic/examples/wordpress-plugin-readme-text-diff.php
php lanes/difftastic/examples/wordpress-plugin-readme-blank-display.php
php lanes/difftastic/examples/wordpress-import-log-no-eol-display.php
php lanes/difftastic/examples/wordpress-html-diff.php
php lanes/difftastic/examples/wordpress-inline-style-html-diff.php
php lanes/difftastic/examples/wordpress-block-interactivity-script-diff.php
php lanes/difftastic/examples/wordpress-multi-asset-html-diff.php
php lanes/difftastic/examples/wordpress-view-script-js-diff.php
php lanes/difftastic/examples/wordpress-hook-registration-js-diff.php
php lanes/difftastic/examples/wordpress-block-registration-functions-js-diff.php
php lanes/difftastic/examples/wordpress-block-editor-syntax-error-js-diff.php
php lanes/difftastic/examples/wordpress-byte-limit-fallback-diff.php
php lanes/difftastic/examples/wordpress-graph-limit-fallback-diff.php
php lanes/difftastic/examples/wordpress-python-migration-if-diff.php
php lanes/difftastic/examples/wordpress-python-loop-migration-diff.php
php lanes/difftastic/examples/wordpress-python-nested-migration-diff.php
php lanes/difftastic/examples/wordpress-python-compound-migration-diff.php
php lanes/difftastic/examples/wordpress-python-trailing-comma-diff.php
php lanes/difftastic/examples/wordpress-block-edit-props-ts-diff.php
php lanes/difftastic/examples/wordpress-block-module-imports-ts-diff.php
php lanes/difftastic/examples/wordpress-block-module-assets-ts-diff.php
php lanes/difftastic/examples/wordpress-block-import-attributes-ts-diff.php
php lanes/difftastic/examples/wordpress-block-dynamic-metadata-ts-display.php
php lanes/difftastic/examples/wordpress-typescript-highlight-display.php
php lanes/difftastic/examples/wordpress-tree-sitter-error-display.php
php lanes/difftastic/examples/wordpress-strip-cr-display.php
php lanes/difftastic/examples/wordpress-block-edit-jsx-diff.php
php lanes/difftastic/examples/wordpress-block-editor-whitespace-tsx-diff.php
php lanes/difftastic/examples/wordpress-block-style-css-diff.php
php lanes/difftastic/examples/wordpress-block-editor-scss-diff.php
php lanes/difftastic/examples/wordpress-nested-at-rule-css-diff.php
php lanes/difftastic/examples/wordpress-block-markup-html-diff.php
php lanes/difftastic/examples/wordpress-block-json-diff.php
php lanes/difftastic/examples/wordpress-block-json-display.php
php lanes/difftastic/examples/wordpress-block-copy-display.php
php lanes/difftastic/examples/wordpress-i18n-block-copy-display.php
php lanes/difftastic/examples/wordpress-multiline-comment-display.php
php lanes/difftastic/examples/wordpress-plugin-workflow-yaml-display.php
php lanes/difftastic/examples/wordpress-plugin-workflow-steps-yaml-diff.php
php lanes/difftastic/examples/wordpress-theme-variation-json-diff.php
php lanes/difftastic/examples/wordpress-template-wrapper-diff.php
php lanes/difftastic/examples/wordpress-block-array-syntax-diff.php
php lanes/difftastic/examples/wordpress-wxr-xml-diff.php
php lanes/difftastic/examples/wordpress-utf16-wxr-display.php
php lanes/difftastic/examples/wordpress-legacy-encoding-display.php
php lanes/difftastic/examples/wordpress-slightly-invalid-wxr-display.php
```

## Next Task

Map another upstream CLI display golden shard such as `align_footer` or `changes_at_end` into explicit PHP display expectations.
