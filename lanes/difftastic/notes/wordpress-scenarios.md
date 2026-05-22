# difftastic WordPress Scenario

Readable diffs for blocks, render callbacks, templates, theme.json, code snippets, and structured documents.

## Current Native Slice

Native token-level differ that avoids raw line-only comparison, classifies comments separately, carries delimiter open/close anchors, filters comments on request, normalizes trailing commas before closing delimiters, reports recursive changes inside bracketed syntax lists, applies upstream-style word/subword splitting for punctuation, Unicode words, and number boundaries, and renders escaped HTML for token, word/subword, and syntax-list changes.

Rust mode now adds a targeted upstream `slider_*.rs` mapping for method and statement sliders. It splits block items at method boundaries and semicolon-terminated statements so code-review excerpts keep retained methods and follow-up statements stable while reporting inserted setup calls and fields as focused additions.

HTML and XML modes add upstream-style angle-bracket delimiters without changing default code tokenization, where `<` and `>` remain punctuation/operators. This lets block markup, saved post content, and XML export diffs report tag-list changes such as class mutations, inserted `id` attributes, newly inserted inline tags, and namespaced metadata tags while still escaping the rendered review HTML.

JSON mode aligns object items by their string property key before comparing values. This maps the upstream `sample_files/json_*.json` pair and keeps WordPress `block.json`/`theme.json` metadata reviews focused on changed values and nested arrays instead of whole-object churn.

Emacs Lisp mode now keeps reader quotes and semicolon comments distinct from strings, and splits flat quoted string/comment lists as individual items. This maps a targeted upstream `strings_*.el` excerpt so large keyword-list changes stay item-focused while preserving the existing Lisp outer-wrapper behavior used to validate WordPress template-wrapper diffs.

The current WordPress fixture compares a block render callback where a PHP comment changes at the same time as the escaping API changes from `esc_html` to `wp_kses_post`. With `ignoreComments`, the diff hides the comment-only churn but still reports the security-relevant API change.

The recursive list fixture compares nested `register_block_type` arrays so block support changes such as `html => false` becoming `html => true` and new alignment support show up at the nested array path instead of as a single flattened line replacement.

The subword fixture compares a `register_block_style` slug change from `legacy-cta-v2` to `modern-cta-v3`. The number-aware word diff reports the changed slug words and version number separately, matching the upstream `src/words.rs` behavior used for readable inline changes.

The HTML fixture combines a block-style subword diff with a `theme.json` palette change. It emits escaped `<span>`, `<del>`, and `<ins>` markers with operation and syntax-list path metadata so a WordPress UI can embed reviewable diffs without trusting source text as HTML.

The block-markup fixture compares saved block HTML where a group block gains an `is-style-card` class, a heading gains an `id`, and the paragraph text is wrapped in `<strong>`. The syntax-list renderer reports the tag-level changes and escapes source tags as text for safe embedding in review UIs.

The WXR XML fixture compares namespaced `wp:postmeta` tags in a migration export. XML mode now maps the upstream `sample_files/xml_*.xml` tag insertion shape and renders changed/inserted postmeta tags as escaped text, so a browser-based migration review surface does not execute or trust source XML.

The block.json fixture compares block metadata where the title changes, a `viewScriptModule` is added, HTML support changes, and `full` alignment is added. JSON key alignment keeps the `supports` object matched while reporting nested changes.

The JSON display fixture emits compact machine-readable review data for that same `block.json` path. It mirrors upstream `src/display/json.rs` by returning the language, path, lowercase status, aligned line pairs, and chunks with per-side line numbers and highlighted novel token spans. This gives a WordPress code review or migration UI data it can render itself without trusting source text as HTML.

The block-copy JSON display fixture compares a block description string where only `legacy` changes to `modern`. It applies upstream `ReplacedString` word splitting from `src/parse/syntax.rs`, so machine-readable review data reports the changed words inside the string rather than replacing the whole description.

The multiline render doc-comment fixture compares PHP block comments beside a WordPress render callback where `legacy` changes to `modern`. Token byte spans let the JSON renderer project paired multiline comment word diffs back onto line/column spans, so browser review data keeps those words comment-highlighted instead of treating them as normal line text.

The plugin workflow YAML fixture compares a GitHub Actions release step for a block plugin. YAML mode treats `run: |` command bodies as block-scalar string atoms, so WP-CLI command changes such as `make-json` to `make-pot` stay string-highlighted in machine-readable JSON review data instead of falling back to normal per-line token highlighting.

The upstream `trailling_newline_*.yaml` mapping covers the same GitHub Actions review surface when a YAML block scalar changes from an expression such as `${{ BAR }}` to literal command text. The JSON display keeps the expression braces string-highlighted instead of treating them as executable code delimiters.

The plugin workflow step YAML fixture compares a release workflow where a WordPress test environment setup step is inserted and the translation generation step is renamed. The YAML syntax-list renderer aligns nested `jobs.release.steps` block-sequence items so stable checkout/build steps stay out of the change stream while inserted/deleted release steps are shown with `$yaml.jobs.release.steps[...]` paths.

The theme-variation fixture applies the upstream `slider_at_end` JSON list-deletion shape to `theme.json`. Deprecated button variations are reported as focused deletions while retained variations stay out of the rendered change stream.

The template-wrapper fixture applies upstream `nested_slider` wrapper correction to a WordPress block template helper call. When `coreParagraph('Hero introduction')` is wrapped in `coreGroup(...)`, the retained paragraph call stays out of the deletion stream and the diff reports the wrapper as a focused syntactic insertion.

The upstream Emacs Lisp `nested_slider` fixture now exercises the opposite outer-delimiter preference used for Lisp-family languages. This keeps the existing WordPress template-wrapper inner-delimiter behavior honest by proving the implementation can choose the delimiter direction from the mapped language instead of using one wrapper strategy for every syntax.

The upstream Emacs Lisp `change_outer` fixture now exercises changed outer delimiters. The WordPress block allow-list fixture applies the same behavior to PHP array syntax modernization, where `array('core/paragraph', 'core/image')` becomes `['core/paragraph', 'core/image']` without rendering the retained block names as changed.

Run:

```sh
php lanes/difftastic/examples/wordpress-render-callback-diff.php
php lanes/difftastic/examples/wordpress-subword-diff.php
php lanes/difftastic/examples/wordpress-html-diff.php
php lanes/difftastic/examples/wordpress-block-markup-html-diff.php
php lanes/difftastic/examples/wordpress-block-json-diff.php
php lanes/difftastic/examples/wordpress-block-json-display.php
php lanes/difftastic/examples/wordpress-block-copy-display.php
php lanes/difftastic/examples/wordpress-multiline-comment-display.php
php lanes/difftastic/examples/wordpress-plugin-workflow-yaml-display.php
php lanes/difftastic/examples/wordpress-plugin-workflow-steps-yaml-diff.php
php lanes/difftastic/examples/wordpress-theme-variation-json-diff.php
php lanes/difftastic/examples/wordpress-template-wrapper-diff.php
php lanes/difftastic/examples/wordpress-block-array-syntax-diff.php
php lanes/difftastic/examples/wordpress-wxr-xml-diff.php
```

## Next Task

Add more display parity for Lisp literal-list changes, including expected JSON/HTML output shape, or map another untouched upstream sample_files display pair.
