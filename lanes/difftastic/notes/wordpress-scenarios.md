# difftastic WordPress Scenario

Readable diffs for blocks, render callbacks, templates, theme.json, code snippets, and structured documents.

## Current Native Slice

Native token-level differ that avoids raw line-only comparison, classifies comments separately, carries delimiter open/close anchors, filters comments on request, normalizes trailing commas before closing delimiters with language-specific Python tuple handling, reports recursive changes inside bracketed syntax lists, applies upstream-style word/subword splitting for punctuation, Unicode words, and number boundaries, and renders escaped HTML for token, word/subword, and syntax-list changes.

Plain-text mode now maps the upstream `sample_files/added_line_*.txt` line-parser shape. Explicit `text`/`plain` syntax-list diffs report changed, inserted, and deleted lines under `$text.line[...]` paths without a fallback marker or a false "No syntactic changes" result when there are no delimiters.

Plain-text JSON display now maps the upstream `sample_files/insert_blank_*.txt` golden shard. Blank-line-only changes stay visible as `changed` file envelopes with zero-based aligned lines and an empty deletion-side line chunk instead of being hidden by token-level equality.

The WordPress plugin readme fixture applies that to `readme.txt` release notes. It reports a stable-tag update, a description wording update, and an inserted changelog section under `$text.line[...]` paths while keeping retained older changelog entries matched.

The WordPress plugin readme blank-line fixture applies the same display slice to release notes where a spacing-only changelog cleanup still matters to reviewers. Compact JSON output reports the deleted blank line for `wp-content/plugins/acme-events/readme.txt` instead of returning `unchanged`.

The upstream `align_footer` text fixture now maps display alignment where a changed line and a deleted line are followed by a stable footer. Compact JSON output keeps the retained footer aligned as context and leaves the unchanged opposite side with an empty `changes` array instead of fabricating novel text.

The WordPress readme footer fixture applies that to plugin `readme.txt` reviews. A description wording change and deleted beta-only note are reported, while the stable FAQ footer heading remains aligned context instead of appearing as chunk content.

The upstream CLI `changes_at_end` fixture now maps a text display shape where the final changed block reaches the end of the file. The JSON display keeps the terminal EOF context line aligned outside the changed chunk instead of treating it as novel content.

The WordPress readme end-changes fixture applies that to plugin `readme.txt` changelog review. Stable-tag and final release-note edits plus an inserted audit note are visible, while the retained terminal EOF context stays aligned outside the changed chunk.

The upstream `text_*.txt` fixture now maps difftastic's display hunk grouping boundary from `src/display/hunks.rs`. Nearby text changes separated by short retained context are emitted as one compact JSON chunk, while the retained context remains aligned instead of appearing in `changes`.

The WordPress readme nearby-hunks fixture applies that to plugin metadata review. An inserted `Requires PHP` line and a nearby `legacy` to `modern` description update stay grouped for review without marking the stable tag line as changed content.

The upstream `big_text_hunk_*.txt` fixture now maps dense inserted plain-text hunks, and the `many_newlines_*.txt` empty-LHS shape maps created-file JSON status. The native syntax-list output emits pure inserted lines for empty-side text diffs rather than pairing the first real line with a synthetic empty old line.

The WordPress created import-report fixture applies that to Data Liberation CSV reports under `wp-content/uploads/migration`. JSON display returns a `created` file envelope, while syntax-list review surfaces can still show each inserted report row under `$text.line[...]`.

Plain-text line splitting now maps upstream `src/lines.rs` `split_on_newlines` trailing EOF behavior and the `sample_files/repeated_line_no_eol_*.txt` fixture. Text mode preserves trailing empty lines and appended repeated final lines instead of trimming them away before `$text.line[...]` output.

The WordPress import-log no-EOL fixture applies that to migration output under `wp-content/uploads/migration/import.log`. Compact JSON output preserves a final appended import record even when neither side ends with a newline.

Makefile mode now maps the upstream CLI `makefile_text_as_atom` boundary. Native syntax-list output treats Makefile assignment text as review atoms under `$make.text[...]`, so one-line build flag changes do not disappear as an empty delimiter diff.

The WordPress plugin build Makefile fixture applies that to shared-hosting build metadata. It reports `CCFLAGS` hardening changes and inserted block asset entries such as `build/view.js` while keeping the mode distinct from generic `$text.line[...]` fallback output.

Side-by-side display now maps upstream `src/display/style.rs` tab-width handling and the `sample_files/tab_*.txt` / `sample_files/tab_*.c` fixtures. Tabs are expanded to the configured width before rendering, long lines are split by display width, left-column fragments are padded, and continuation rows use dotted line-number markers.

The WordPress tabbed block metadata fixture applies that to tab-indented `block.json` review. `title`, `viewScriptModule`, and nested `supports.html` changes render with deterministic spaces so a browser or terminal review surface does not depend on local tab stops.

Side-by-side display now also maps upstream `--context` behavior from `src/options.rs` plus the context/hunk helpers in `src/display/context.rs`, `src/display/hunks.rs`, and `src/display/side_by_side.rs`. It uses three context lines by default, accepts `contextLines`, merges nearby hunk windows, and marks omitted distant stable lines with a separator.

The WordPress block-pattern context fixture applies that to PHP block pattern registration arrays. A compact review can show changed hero/footer pattern metadata while omitting stable testimonial/gallery registrations that are outside the context window.

Side-by-side display now maps upstream empty-side behavior from `src/options.rs` and `src/display/side_by_side.rs`: created or deleted files render as one numbered source column by default, while callers can request `showBoth` for padded two-column output.

The WordPress created import-report side-by-side example applies that to generated Data Liberation CSV output. Newly generated report rows are shown without a blank opposite column.

Side-by-side display now also maps upstream novel line/span coloring from `src/display/side_by_side.rs` and `src/display/style.rs`. When `useColor` is enabled, changed line numbers and intraline changed words are emitted with red/green ANSI styling while retained prefixes, suffixes, and context lines remain uncolored.

The WordPress highlighted readme example applies that to plugin release-review copy. A `legacy` to `modern` wording change is highlighted inline while the stable FAQ footer context remains visible but uncolored.

Unified inline display now maps upstream `src/display/inline.rs` and header styling from `src/display/style.rs`. It emits path/language headers for every hunk, hunk ordinals when separated changes produce multiple hunks, optional renamed-file extra info on the first hunk, tab-expanded lines, and LHS-before/RHS-after context windows with colored line numbers when requested.

The WordPress readme inline example applies that to plugin release notes. A compact terminal or browser review can keep `wp-content/plugins/acme-review-tools/readme.txt` visible in the header, show a `legacy` to `modern` copy change and nearby FAQ footer context, and omit distant stable metadata.

Git external-diff metadata now maps the upstream `git_style_arguments_rename` and `git_style_arguments_new_file` CLI boundaries. Native inline rendering can parse Git's 7-argument and 9-argument invocation shapes, show the renamed display path, place `Renamed from ... to ...` under the first hunk header, append known mode changes, and suppress false permission warnings when Git uses `.` for an unknown new-file mode.

The WordPress Git-backed plugin rename example applies that to a render callback moved from `wp-content/plugins/acme-card/src/render-card.php` to `wp-content/plugins/acme-card/includes/render-card.php` with an executable-mode change. A review UI for Git-backed deployments can show the path/mode change and the PHP content change together without shelling out to difftastic.

Git CLI path display now maps upstream `build_display_path`, `drop_different_path_starts`, and single-argument unmerged-file reporting. Native inline rendering can select the common suffix for ordinary two-path comparisons, use the real RHS path when Git passes a temporary blob path, follow upstream no-common-suffix fallbacks, and return `Unmerged path: ...` under Git single-path invocations.

The WordPress Git common-path example applies that to release-root comparisons of `wp-content/plugins/acme-card/block.json`. A review UI can show the stable repository suffix instead of noisy deployment roots such as `/srv/releases/old` and `/srv/releases/new`.

Native command status now maps upstream `--check-only` and `--exit-code` behavior from `tests/cli.rs`. Changed diffs still return exit `0` by default, `exitCode` changes that to exit `1`, supported-language check-only output says `Has syntactic changes.`, and plain text check-only output says `Has changes.`.

The WordPress check-only example applies that to a block metadata gate for `wp-content/plugins/acme-card/block.json`. A plugin review or CI surface can ask for a fast native status, show the path/language header plus `Has syntactic changes.`, and decide whether to fail the gate from the returned exit code without invoking the Rust binary.

Native language listing now maps upstream `--list-languages` behavior from `tests/cli.rs`, `src/options.rs`, `src/main.rs`, and `src/parse/guess_language.rs`. The PHP runner emits override rows before built-in languages, keeps upstream display names and globs such as `TOML` / `*.toml`, accepts case-insensitive language names, accepts the special `text` override, and reports bad-argument status for unknown override languages.

The WordPress list-languages example applies that to plugin/theme review configuration. It shows how a PHP review surface can expose overrides for `*.blade.php`, generated `*.asset.php`, and `.wp-env` JSON files alongside the native built-in language table.

TOML mode now maps the upstream `sample_files/toml_*.toml` parser sample with table-qualified key paths, scalar value updates, array item insertions/deletions, and multiline string entry deletion. The WordPress plugin TOML config example applies that to release/build/Playground metadata so `requires_wp`, build targets, PHP runtime, plugin lists, and review notes are shown under `$toml...` paths rather than generic line fallback output.

TOML mode now also descends into inline tables and indexes repeated array-table entries. The WordPress plugin release matrix example applies that to `[[plugins]]` release records and nested Playground blueprint metadata, reporting changed plugin slugs, statuses, autoload flags, review labels, PHP/WP versions, and plugin arrays under paths such as `$toml.plugins[1].config.autoload`.

Language detection now applies the same override list before built-in globs, Emacs mode headers, shebangs, Hack/PHP and Objective-C header checks, and XML header detection. The first matching override wins, and `text` intentionally routes a file through plain-text review.

The WordPress language-override directory example applies that to generated block asset metadata and Blade templates. `build/index.asset.php` is reviewed as Text rather than PHP, while `templates/card.blade.php` is reviewed as HTML, so a plugin/theme review UI can make configured language choices once and use them for both catalog display and directory JSON output.

Language override command parsing now also maps upstream `DFT_OVERRIDE` plus `DFT_OVERRIDE_1` through `DFT_OVERRIDE_9` aggregation, adjacent same-language grouping, and invalid-override bad-argument behavior. The WordPress env language-override command example passes a caller-supplied environment array with `DFT_OVERRIDE=*.asset.php:text` and `DFT_OVERRIDE_1=*.blade.php:HTML`, proving configured language choices reach file and directory byte review without reading live process environment values.

Display option command parsing now maps upstream `DFT_DISPLAY`, `DFT_CONTEXT`, `DFT_TAB_WIDTH`, and `DFT_WIDTH` environment-style configuration from `src/options.rs`. Invalid numeric values return bad-argument status before review, and explicit PHP options override caller-provided environment values like CLI arguments override environment variables upstream.

The WordPress env display-options command example applies that to tabbed `block.json` review. A caller-provided environment array selects `side-by-side-show-both`, zero context, two-space tab expansion, and a narrow width so changed `title`, `viewScriptModule`, and `supports.html` metadata are wrapped deterministically without inspecting the live process environment.

Guarded unstable JSON display command parsing now maps upstream `--display=json` plus `DFT_UNSTABLE` behavior from `src/options.rs`, `src/main.rs`, and `src/display/json.rs`. Without a caller-provided `DFT_UNSTABLE` entry, the PHP runner returns bad-argument status before review; with the guard, it emits the native compact JSON file envelope.

The WordPress env unstable JSON command example applies that to `wp-content/plugins/acme-card/block.json`. A caller-provided environment array selects JSON display and emits aligned lines plus chunks for `title`, `viewScriptModule`, and `supports` changes so a block editor or migration UI can consume review data directly.

Guarded JSON directory command output now maps upstream `print_unchanged = !skip-unchanged` semantics from `src/options.rs`, `src/main.rs`, and `src/display/json.rs`. By default, command-level directory JSON includes unchanged file envelopes; a caller-provided `DFT_SKIP_UNCHANGED=true` filters them.

The WordPress env JSON directory command example applies that to a plugin directory review. It keeps unchanged `src/render.php` visible as an `unchanged` PHP status while changed `.wp-env.json` and `block.json` entries stay machine-readable for review UIs that need a full file inventory.

Display-control command parsing now maps upstream `DFT_BACKGROUND`, `DFT_SYNTAX_HIGHLIGHT`, and `DFT_SORT_PATHS` environment-style configuration from `src/options.rs`, `src/main.rs`, and `src/display/style.rs`. Invalid background, syntax-highlight, or sort-path values return bad-argument status before review, and explicit PHP options override caller-provided environment values.

The WordPress env display-controls command example applies that to PHP render callback review and generated asset/template directory review. A caller-provided environment array selects dark-background bright ANSI colors, validates `DFT_SYNTAX_HIGHLIGHT=off`, and sorts directory JSON paths without inspecting the live process environment.

ANSI terminal display now maps the upstream syntax-highlighting toggle beyond novel spans. With color enabled, side-by-side and inline output bolds PHP keywords/types, colors strings, and italicizes comments by default; `DFT_SYNTAX_HIGHLIGHT=off` or explicit `syntaxHighlight => false` removes those syntax styles while retaining red/green changed-word colors.

The WordPress syntax-highlight control command example applies that to `wp-content/plugins/acme-card/src/render.php`, showing a reviewer-facing render callback diff with syntax highlighting on and off from a caller-provided environment array.

ANSI parser-error display now maps upstream `src/display/style.rs` `TreeSitterError` purple styling for syntax-highlighted terminal output. The WordPress parser-error ANSI command example compares block registration JavaScript with an extra `}` and, when `parseErrorLimit` allows structural display, colors that parser-error span for terminal reviewers while `DFT_SYNTAX_HIGHLIGHT=off` suppresses the parser-error syntax style.

Command flag parsing now maps upstream `DFT_CHECK_ONLY`, `DFT_EXIT_CODE`, `DFT_SKIP_UNCHANGED`, `DFT_IGNORE_COMMENTS`, `DFT_STRIP_CR`, and `DFT_COLOR` environment-style configuration from `src/options.rs`, `src/main.rs`, and `src/display/style.rs`. Invalid values return bad-argument status before review, and explicit PHP options override caller-provided environment values.

The WordPress env CI-flags command example applies that to a block render callback gate. A caller-provided environment array requests check-only output, exit-code behavior, comment ignoring, and unchanged-output policy so the escaping API change is reported while comment-only churn remains filtered.

Resource-limit command parsing now maps upstream `DFT_BYTE_LIMIT`, `DFT_GRAPH_LIMIT`, and `DFT_PARSE_ERROR_LIMIT` environment-style configuration from `src/options.rs` and `src/main.rs`. Invalid numeric values return bad-argument status before review, explicit PHP options override caller-provided environment values, and parsed limits are applied to text, JSON file-byte, and directory JSON review paths.

The WordPress env resource-limits command example applies that to block render metadata. A caller-provided `DFT_BYTE_LIMIT=80` forces oversized PHP metadata into escaped line-oriented fallback output, so a review surface can enforce safety budgets without inspecting the live process environment.

Inline binary display now maps upstream `tests/cli.rs` `binary_changed` / `binary_override` and the binary branch in `src/main.rs`. The WordPress binary asset example applies this to `wp-content/plugins/acme-card/assets/logo.png`, showing a path/language header plus `Binary file modified` size metadata for changed plugin media instead of attempting a misleading text diff.

Binary override globs now map upstream `src/options.rs` `--override-binary` and `src/files.rs` `guess_content` precedence before text heuristics. The WordPress binary-override directory example applies this to generated `build/index.min.js` assets, returning a `Binary changed` JSON envelope without text chunks even though the bytes are valid UTF-8 JavaScript.

Binary override command parsing now also maps upstream `DFT_OVERRIDE_BINARY` plus `DFT_OVERRIDE_BINARY_1` through `DFT_OVERRIDE_BINARY_9` aggregation and invalid-glob bad-argument behavior. The WordPress env binary-override command example passes a caller-supplied environment array with `DFT_OVERRIDE_BINARY_1=*.min.js`, proving generated block assets can be reviewed as binary from command configuration without reading live process environment values.

Oversized single-line display now maps the upstream `long_line_*.txt` stress shape without copying those multi-megabyte fixtures into this lane. The side-by-side renderer wraps by display width from a moving byte offset, so one-line generated files do not repeatedly rescan the entire remaining source while rendering continuation rows.

The WordPress large asset manifest example applies that to generated block asset metadata. A one-line JSON manifest that gains a `view.js` asset and changes its version remains bounded to the configured side-by-side column width instead of producing an unreadable or prohibitively slow review line.

Display wrapping now also maps upstream `src/display/style.rs` Unicode width cases used by `split_string_by_width`: emoji and CJK characters consume two display columns, combining marks and joiners consume zero, and wrapped rows are split without cutting UTF-8 byte sequences.

The WordPress minified asset map example applies that to a generated one-line asset manifest with Japanese labels and package emoji. Inserted `view.js` assets remain readable in bounded side-by-side rows even when multibyte labels appear throughout the physical line.

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

HTML display highlighting now maps doctype identifiers as upstream keyword-style spans. The WordPress full-page template example compares a theme `front-page.html` wrapper and emits compact JSON with `DOCTYPE`/`doctype` highlighted as keywords while keeping ordinary landing copy normal, which helps reviewers distinguish document-prolog changes from block content edits.

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

The Python keyword/builtin display example now also covers upstream `variable.builtin` receiver captures. Migration helper assignments such as `self.report = None` and `cls.enabled = False` emit keyword-style spans for `self`, `cls`, `None`, and `False`, while `print`, `len`, and `dict` calls remain normal so review UIs do not over-highlight ordinary runtime calls.

The Lua build-helper display example maps the same upstream keyword/constant display promotion boundary for plugin tooling scripts. Review data for `wp-content/plugins/acme-card/tools/register-blocks.lua` highlights Lua control-flow keywords and `nil`/`false` constants while leaving ordinary helper names and library calls such as `register_blocks` and `ipairs` normal.

The upstream CSS fixture now exercises selector-block alignment and declaration property matching. The WordPress block-style CSS fixture applies this to global style review: a `.wp-block-acme-card` custom-property color changes, `border-radius` is added, and a query-title selector is introduced while a reordered `.wp-block-image` rule stays out of the rendered change stream.

The upstream Tailwind CSS and simple SCSS fixtures now exercise CSS at-rule item signatures plus SCSS mixin selector/header matching. The WordPress block-editor SCSS fixture applies this to block style mixins: changed mixin defaults and nested `var(--wp--preset--color--*)` references stay focused while the whole `@mixin acme-card(...)` body remains matched.

The upstream HTML style sample now contributes a targeted CSS `@media` extraction. The WordPress nested at-rule fixture applies that container shape to block styles under `@media` and `@supports`, keeping a reordered stable `.wp-block-image` child rule out of the rendered change stream while reporting padding, radius, gap, and grid-template-column changes under the retained `.wp-block-acme-card` paths.

HTML mode now maps the upstream `style_element` sub-language rule from `src/parse/tree_sitter_parser.rs` by extracting `<style>` raw text and parsing it with the native CSS rule matcher. The inline block-template style fixture applies this to saved block markup that carries embedded CSS: color/padding/gap changes are rendered under `$html.style.css[...]` paths, an added query-title rule is shown as a CSS insertion, and reordered stable image rules remain matched at the CSS sub-language layer.

HTML mode now also maps the upstream `script_element` sub-language rule from `src/parse/tree_sitter_parser.rs` by extracting `<script>` raw text and parsing focused JavaScript call arguments. The WordPress Interactivity-style fixture applies this to inline block state bootstrapping: `wp.interactivity.store(...)` state property changes render under `$html.script.js.call[...]` paths, so review UIs can show changed labels and booleans without relying only on raw HTML script text.

HTML root-list comparison now strips `<style>` and `<script>` raw bodies before generic tag/list diffing. The same WordPress Interactivity-style fixture asserts changed state labels and booleans appear under the JavaScript sub-language path without duplicate root `$[...]` raw script churn in the rendered review data.

Indexed HTML raw-text comparison now handles multiple attributed inline asset blocks separately. The multi-inline asset fixture compares a block template that inserts a notice `<style>` block and an analytics `<script type="module">` while retaining existing card and gallery assets. The diff reports the inserted notice rule under `$html.style[0].css[...]`, the changed card CSS under `$html.style[1].css[...]`, the spacing CSS under `$html.style[2].css[...]`, and the inserted analytics store under `$html.script[1].js.call[...]` without treating the retained gallery script as changed.

Standalone JavaScript mode now maps the upstream `sample_files/javascript_simple_*.js` statement shape. The WordPress block view-script fixture applies this to a `view.js` change where existing calls are wrapped in an `if (window.wp)` guard, a block action array gains `share`, and hydration booleans change. The renderer reports the guard under `$js.block[...]`, the action under `$js.array[...]`, and retained following actions stay out of the deletion stream.

The PHP `$this` highlight display example maps the tree-sitter-php `relative_scope` / `variable.builtin` boundary into the lane's promoted keyword-style display path. A block renderer class that starts calling `$this->enqueue_assets()` and `$this->normalize_attributes(...)` highlights `this` while leaving ordinary WordPress/PHP calls such as `render_block` and method identifiers normal.

Standalone JavaScript mode now also maps the larger upstream `sample_files/javascript_*.js` repeated callback shape. Named callback calls such as Jest `describe(...)` / `test(...)` and WordPress `wp.hooks.addAction(...)` / `addFilter(...)` use their first string label plus enclosing named callback labels when matching repeated calls. The WordPress hook-registration fixture applies this to a block plugin `view.js` change where a new analytics action is inserted before the retained `acme.card.init` callback; the diff reports the new hook and the added `bindCard()` call without pairing the retained init hook with the analytics hook by callee name alone.

Standalone JavaScript mode now also maps a targeted upstream `sample_files/load_*.js` function-declaration shape. Function declarations become call scopes instead of fake `functionName()` calls, so repeated calls inside different helpers are not paired by callee name alone. The WordPress block registration fixture applies this to repeated `wp.blocks.registerBlockType(...)` calls: an inserted `registerQueryBlock()` reports as a new function-scoped registration while the retained gallery registration remains matched.

JavaScript mode now also maps upstream parse-error fallback semantics from `DEFAULT_PARSE_ERROR_LIMIT=0`, `to_syntax_with_limit`, and the CLI `yaml_parse_errors` fallback test. The WordPress block editor syntax-error fixture compares a partial `registerPlugin(...)` edit with an unclosed object literal. Because the combined native delimiter parse-error count exceeds the limit, syntax-list output switches to escaped line-oriented `$text.line[...]` changes and compact JSON display labels the file as `Text (... exceeded DFT_PARSE_ERROR_LIMIT)` instead of showing misleading `$js.call[...]` structural matches.

Supported-language byte-limit fallback now maps upstream `DEFAULT_BYTE_LIMIT`, `to_tree_with_limit`, and `TextFallback` behavior. The WordPress render metadata example lowers the limit to exercise the path with a bounded PHP block metadata change: render callback and support changes are shown as escaped `$text.line[...]` changes, and JSON display labels the file as `Text (... exceeded DFT_BYTE_LIMIT)` instead of attempting an incomplete structural diff.

Huge multi-line byte-limit fallback now maps the upstream `huge_cpp_*.cpp` stress metadata without copying the 22 MB fixtures into this lane. Text fallback, compact JSON display, and side-by-side display share a native line differ that keeps exact LCS for small inputs and switches to bounded prefix/suffix plus unique-line anchors for larger line sets.

The WordPress generated C++ build artifact example applies that to plugin build output under `wp-content/plugins/acme-card/build/generated`. Separated generated asset edits and an inserted view asset stay visible as line-oriented fallback changes, while retained generated rows stay out of JSON chunks.

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

Parser-specific syntax highlighting now maps a narrow upstream `tree_highlights` slice for markup tags, CSS keyword contexts, and keyword-ish booleans/constants/operators. The WordPress TSX tag-highlight example emits inserted `PanelBody` and `TextControl` component tags as `type` spans plus inserted `&&`, `true`, and `false` as `keyword` spans in compact JSON, while ANSI display bolds HTML tag names, CSS `@media` / `!important`, and keyword-ish operator/literal contexts when syntax highlighting is enabled. Attribute/property-style captures remain normal, matching the upstream highlight enum boundary.

TypeScript syntax highlighting now also maps upstream constructor/type captures for custom identifiers. The WordPress block controller display example emits inserted `BlockVariationController` annotation and constructor spans as `type` while keeping `new` as `keyword`, so block-editor TypeScript review data can style custom controllers without a JavaScript-side parser.

JavaScript and TypeScript syntax highlighting now maps upstream uppercase identifier capture priority from difftastic's parser query dependencies. The WordPress block registry display example emits inserted `BlockRegistry` as a `type` span outside a type annotation and `WP_BLOCK_API_VERSION` as a `keyword` span, matching upstream's constructor/type and constant bucket ordering for editor review data.

JavaScript and TypeScript syntax highlighting now also maps upstream `variable.builtin` captures from the exact tree-sitter JavaScript query used by difftastic. The WordPress browser-globals example emits `window`, `document`, `console`, `module`, `arguments`, `this`, and `super` as `keyword` spans while leaving `wp` and `require` normal, so block review UIs can distinguish host globals from ordinary plugin namespaces without a browser-side parser.

Python syntax highlighting now maps the upstream constructor/decorator capture edge. The WordPress Python decorator example emits `CacheWarmup` and `MigrationRunner` as `type` spans while keeping `staticmethod` normal, so migration tooling review data can style imported helper classes without incorrectly promoting builtin function decorators.

Python syntax highlighting now also maps the upstream keyword and builtin-function boundary from the exact tree-sitter Python query. The WordPress Python keyword/builtin example emits `nonlocal`, `match`, `case`, and `True` as `keyword` spans while keeping `print`, `len`, and `dict` calls normal, so migration helper review data can highlight control-flow semantics without over-styling ordinary builtin calls.

Python syntax highlighting now tightens builtin type-name promotion to annotation contexts. The WordPress Python keyword/builtin example emits `dict`, `list`, `str`, and `int` as `type` spans in parameter/return/generic annotations while a local `list` identifier remains normal, preserving upstream's distinction between type captures and `function.builtin` or ordinary identifier captures.

Ruby syntax highlighting now maps the upstream keyword, constant, operator, constructor, and function-method boundary from the exact tree-sitter Ruby query. The WordPress Ruby migration helper example emits `class`, `def`, `do`, `next`, `unless`, `rescue`, and `nil` as `keyword` spans, `ImportRunner` as a `type` span, `DEFAULT_LIMIT` as a keyword constant, and `require` as normal, so migration-script review data can style Ruby control flow without over-styling builtin method calls.

Compact JSON display now also maps upstream `src/display/json.rs` `tree_sitter_error` highlight output for parser-error atoms. The WordPress parser-error display example compares block registration JavaScript with an extra `}` and, when the parse-error budget allows structural display, exposes that delimiter as a `tree_sitter_error` span for editor review tools instead of treating it as ordinary punctuation.

JSX/TSX mode now maps the upstream `sample_files/jsx_*.jsx` tag-list shape. The WordPress block editor TSX fixture applies this to an `edit.tsx` sidebar control change, reporting the `PanelBody` title and `initialOpen` attribute change while keeping the retained `TextControl` tag out of the rendered change stream.

TSX mode now maps the upstream `sample_files/whitespace_*.tsx` formatting shape. The WordPress block editor whitespace fixture applies this to editor controls where Prettier or manual formatting moves `{" "}` spacer expressions around retained text. The renderer reports no syntactic changes, keeping retained `ToolbarButton` markup and screen-reader copy out of the review stream.

Directory walking now maps upstream `tests/cli.rs` `directory_arguments` and `walk_hidden_items` through native PHP directory traversal. It includes dotfiles and dot-directories, excludes `.git`, uses relative per-file display paths, reports one-sided files as created/deleted, and filters unchanged files by default while keeping an opt-in unchanged mode for review tools.

The WordPress plugin directory fixture applies that to a checkout where hidden `.wp-env.json` tooling and `wp-content/plugins/acme-card/block.json` changed while `src/render.php` did not. The JSON output keeps hidden local development configuration visible for review and omits unchanged plugin source files by default.

Run:

```sh
php lanes/difftastic/examples/wordpress-render-callback-diff.php
php lanes/difftastic/examples/wordpress-render-return-type-diff.php
php lanes/difftastic/examples/wordpress-subword-diff.php
php lanes/difftastic/examples/wordpress-plugin-readme-text-diff.php
php lanes/difftastic/examples/wordpress-plugin-readme-blank-display.php
php lanes/difftastic/examples/wordpress-readme-footer-alignment-display.php
php lanes/difftastic/examples/wordpress-readme-end-changes-display.php
php lanes/difftastic/examples/wordpress-readme-nearby-hunks-display.php
php lanes/difftastic/examples/wordpress-created-import-report-display.php
php lanes/difftastic/examples/wordpress-import-log-no-eol-display.php
php lanes/difftastic/examples/wordpress-plugin-build-makefile-diff.php
php lanes/difftastic/examples/wordpress-tabbed-block-json-side-by-side.php
php lanes/difftastic/examples/wordpress-pattern-context-side-by-side.php
php lanes/difftastic/examples/wordpress-created-import-report-side-by-side.php
php lanes/difftastic/examples/wordpress-highlighted-side-by-side.php
php lanes/difftastic/examples/wordpress-readme-inline-diff.php
php lanes/difftastic/examples/wordpress-git-rename-inline-diff.php
php lanes/difftastic/examples/wordpress-git-common-path-inline-diff.php
php lanes/difftastic/examples/wordpress-check-only-command.php
php lanes/difftastic/examples/wordpress-list-languages-command.php
php lanes/difftastic/examples/wordpress-plugin-toml-config-diff.php
php lanes/difftastic/examples/wordpress-plugin-release-matrix-toml-diff.php
php lanes/difftastic/examples/wordpress-language-override-directory-diff.php
php lanes/difftastic/examples/wordpress-env-language-overrides-command.php
php lanes/difftastic/examples/wordpress-binary-asset-inline-diff.php
php lanes/difftastic/examples/wordpress-binary-override-directory-diff.php
php lanes/difftastic/examples/wordpress-large-asset-manifest-side-by-side.php
php lanes/difftastic/examples/wordpress-minified-asset-map-side-by-side.php
php lanes/difftastic/examples/wordpress-html-diff.php
php lanes/difftastic/examples/wordpress-inline-style-html-diff.php
php lanes/difftastic/examples/wordpress-block-interactivity-script-diff.php
php lanes/difftastic/examples/wordpress-multi-asset-html-diff.php
php lanes/difftastic/examples/wordpress-view-script-js-diff.php
php lanes/difftastic/examples/wordpress-hook-registration-js-diff.php
php lanes/difftastic/examples/wordpress-block-registration-functions-js-diff.php
php lanes/difftastic/examples/wordpress-block-editor-syntax-error-js-diff.php
php lanes/difftastic/examples/wordpress-byte-limit-fallback-diff.php
php lanes/difftastic/examples/wordpress-generated-cpp-byte-limit-diff.php
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
php lanes/difftastic/examples/wordpress-plugin-directory-json-diff.php
php lanes/difftastic/examples/wordpress-env-display-options-command.php
php lanes/difftastic/examples/wordpress-env-unstable-json-command.php
php lanes/difftastic/examples/wordpress-env-display-controls-command.php
php lanes/difftastic/examples/wordpress-syntax-highlight-control-command.php
php lanes/difftastic/examples/wordpress-parser-error-ansi-command.php
php lanes/difftastic/examples/wordpress-env-ci-flags-command.php
php lanes/difftastic/examples/wordpress-env-resource-limits-command.php
php lanes/difftastic/examples/wordpress-tsx-tag-highlight-display.php
php lanes/difftastic/examples/wordpress-block-controller-highlight-display.php
php lanes/difftastic/examples/wordpress-block-registry-highlight-display.php
php lanes/difftastic/examples/wordpress-php-magic-constant-highlight-display.php
php lanes/difftastic/examples/wordpress-python-decorator-highlight-display.php
php lanes/difftastic/examples/wordpress-python-keyword-builtin-highlight-display.php
php lanes/difftastic/examples/wordpress-python-multiline-annotation-highlight-display.php
php lanes/difftastic/examples/wordpress-ruby-migration-highlight-display.php
php lanes/difftastic/examples/wordpress-elisp-maintenance-highlight-display.php
```

The WordPress PHP magic-constant display example now covers plugin bootstrap include-path review in `wp-content/plugins/acme-card/acme-card.php`. Native JSON display highlights `require_once`, `__DIR__`, and `__FILE__` as keyword-style upstream keyword/constant captures while keeping WordPress helper calls such as `plugin_dir_path(...)` and project class identifiers normal, so review UIs can distinguish PHP runtime constants from ordinary plugin APIs.

The WordPress Ruby migration helper now also exercises method-level Ruby block delimiter paths. The added `self.count` method in `wp-content/plugins/acme-migrator/tools/import_posts.rb` is emitted as a focused `def...end` insertion while the existing `records.each do ... end` body stays nested under its method path, making importer utility diffs reviewable without replacing the whole class.

The WordPress Python multiline annotation example now exercises migration helper signatures that wrap builtin type names across nested `dict[...]`, `list[...]`, `tuple[...]`, and PEP 604 `|` continuations. Type names inside the annotation are bold-highlighted while runtime locals named `list` stay normal, matching the upstream tree-sitter-python type-capture boundary without over-styling ordinary expressions.

The WordPress Python multiline annotation example now also covers `from __future__ import annotations` plus `typing` aliases used in migration helpers. `Optional`, `TypeAlias`, and stringized `dict[str, list[int]]` annotations are bold-highlighted only in annotation or likely type-alias contexts, while `label = "list"` remains a normal string and runtime locals named `list` remain unpromoted.

The same WordPress Python multiline annotation example now covers qualified `typing.Optional[Payload]` and `typing_extensions.TypeAlias` usage that commonly appears in migration scripts and importer helpers. Only the alias member names and stringized annotation bodies are promoted; `import typing`, `import typing_extensions`, runtime locals, and runtime strings remain normal/string highlighted.

The same WordPress Python multiline annotation example now covers quoted custom type names inside nested annotation regions. `list["Payload"]` and `typing.Optional["Payload"]` are promoted as type spans, while runtime strings such as `label = "Payload"` remain string-highlighted so migration scripts can use forward references without over-styling ordinary values.

Dependency closure: no new support component is needed for the PHP magic-constant slice. It reuses the existing bounded lane-local tokenizer, `SyntaxHighlightClassifier`, `AnsiSyntaxHighlighter`, and `JsonDiffRenderer`; a separate native PHP parser support component would only be justified behind an accepted gate for broader PHP AST or syntax-list parity beyond keyword/constant display captures.

The WordPress C native-module display example covers optional plugin support code under `wp-content/plugins/acme-card/native/block-support.c`. Native JSON display highlights `#include` and `#define` directive names as keyword-style spans and fixed-width primitive types such as `uint32_t` and `uint8_t` as type spans, while leaving ordinary helper identifiers such as `acme_block_flags` normal.

Dependency closure: no new support component is needed for the C/C++ preprocessor/type highlight slice. It reuses the existing bounded lane-local tokenizer, `SyntaxHighlightClassifier`, `AnsiSyntaxHighlighter`, and `JsonDiffRenderer`; a native C parser support component would only be proposed behind an accepted gate for broader C/C++ structural AST parity beyond keyword/type display captures.

The WordPress Emacs Lisp maintenance example covers plugin-local editor/script tooling under `wp-content/plugins/acme-card/tools/export.el`. Native JSON display highlights upstream special forms such as `defun` and `let` plus the `nil`/`t` constants as keyword-style spans, while ordinary helper symbols such as `message` and unmapped forms such as `when` remain normal.

Dependency closure: no new support component is needed for the Emacs Lisp special-form/constant highlight slice. It reuses the existing bounded lane-local tokenizer, `SyntaxHighlightClassifier`, `AnsiSyntaxHighlighter`, and `JsonDiffRenderer`; a native Emacs Lisp parser support component would only be proposed behind an accepted gate for broader Elisp syntax-list or macro/form semantics beyond keyword/constant display captures.

The WordPress SQL schema display example covers plugin install/upgrade SQL under `wp-content/plugins/acme-card/schema/install.sql`. Native JSON display highlights schema-review keywords such as `CREATE TABLE`, `PRIMARY KEY`, `SELECT`, `FROM`, and `WHERE`, highlights operators and builtin type names such as `BIGINT`, `VARCHAR`, `BOOLEAN`, and `NULL`, and leaves table names, column names, and boolean literals normal because upstream SQL `@field`, `@function.call`, and `@boolean` captures are not promoted into difftastic's display highlight enum.

Dependency closure: no new support component is needed for the SQL keyword/operator/type highlight slice. It reuses the existing bounded lane-local tokenizer, `SyntaxHighlightClassifier`, `AnsiSyntaxHighlighter`, and `JsonDiffRenderer`; a native SQL parser support component would only be proposed behind an accepted gate for broader SQL structural diffing beyond display-capture parity.

The WordPress Bash deploy display example covers plugin deploy/WP-CLI helper scripts under `wp-content/plugins/acme-card/bin/deploy.sh`. Native JSON display highlights shell control-flow words such as `export`, `if`, `then`, `else`, and `fi`, highlights `&&` and option flags such as `--path=wp` and `--activate`, and leaves `wp`, subcommands, and `WP_ENV` normal because upstream Bash command/function/property captures are not promoted into difftastic's display highlight enum.

Dependency closure: no new support component is needed for the Bash keyword/operator/flag highlight slice. It reuses the existing bounded lane-local tokenizer, `SyntaxHighlightClassifier`, `AnsiSyntaxHighlighter`, and `JsonDiffRenderer`; a native shell parser support component would only be proposed behind an accepted gate for broader Bash structural diffing beyond display-capture parity.

The WordPress Swift bridge display example covers plugin bridge/helper source under `wp-content/plugins/acme-card/tools/BlockBridge.swift`. Native JSON display highlights Swift control and declaration keywords such as `struct`, `let`, `func`, `for`, `in`, `if`, `return`, `false`, and `true`, highlights operators such as `:`, `->`, and `==`, highlights builtin types such as `String` and `Bool`, and leaves function names, parameters, struct names, and properties such as `isDynamic` normal because upstream function/field/property captures are not promoted into difftastic's display highlight enum.

Dependency closure: no new support component is needed for the Swift keyword/operator/type highlight slice. It reuses the existing bounded lane-local tokenizer, `SyntaxHighlightClassifier`, `AnsiSyntaxHighlighter`, and `JsonDiffRenderer`; a native Swift parser support component would only be proposed behind an accepted gate for broader Swift structural diffing beyond display-capture parity.

The WordPress Java build-helper display example covers plugin tooling source under `wp-content/plugins/acme-card/tools/BlockRegistry.java`. Native JSON display highlights Java control and declaration keywords such as `public`, `final`, `class`, `private`, `for`, `if`, `return`, `false`, and `true`, highlights operators such as `:`, `=`, and `==`, highlights primitive and type identifiers such as `boolean`, `BlockRegistry`, and `String`, and leaves method names, variables, and fields such as `register` and `dynamic` normal because upstream function/field/property captures are not promoted into difftastic's display highlight enum.

Dependency closure: no new support component is needed for the Java keyword/operator/type highlight slice. It reuses the existing bounded lane-local tokenizer, `SyntaxHighlightClassifier`, `AnsiSyntaxHighlighter`, and `JsonDiffRenderer`; a native Java parser support component would only be proposed behind an accepted gate for broader Java structural diffing beyond display-capture parity.

## Next Task

Expand the next upstream-query-backed syntax highlight boundary outside PHP magic constants, JavaScript variable.builtin, C/C++ preprocessor/type captures, SQL keyword/operator/type captures, Bash keyword/operator/flag captures, Swift keyword/operator/type captures, Java keyword/operator/type captures, Emacs Lisp special forms/constants, and the already mapped Python/Ruby clusters, while keeping unsupported function, field/property, and boolean captures normal unless the upstream display enum promotes them.
