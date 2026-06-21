# pandoc Root Harness Gate - 2026-05-23

## HTML Reader Standalone Linebreak Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-native-html-standalone-linebreak-handoff.php`
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '623,710p'`
  - `git -C .upstream-cache/pandoc grep -n '<br\|LineBreak\|pLineBreak\|"br" -> pLineBreak' HEAD -- src/Text/Pandoc/Readers/HTML.hs test/command/2874.md test/command/3619.md`
  - Result: inspected the bounded `pPlain`/`pLineBreak` branch where top-level
    HTML `<br>` fragments become native `LineBreak` inlines; counted 7 focused
    source/fixture hits.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-native-html-standalone-linebreak-handoff.php`
  - Result: emitted WordPress paragraph line breaks for standalone `<br>`
    source fragments.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 4,405 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## HTML Reader Pre/Code Break Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-native-html-pre-code-breaks-handoff.php`
- Static native smoke inventory passed:
  - `NativeReader` parsed 252/252 upstream `.native` fixtures from
    `.upstream-cache/pandoc/test`
  - `WordPressBlockWriter`, `HtmlWriter`, and `MarkdownWriter` each rendered
    252/252 parsed upstream `.native` fixtures
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '650,676p' | rg -c 'pCodeBlock|matchTagOpen "pre"|matchTagOpen "code"|tagToText|TagText|TagOpen "br"|codeBlockWith'`
  - `git -C .upstream-cache/pandoc grep -n 'pCodeBlock\|tagToText\|TagOpen "br"' HEAD -- src/Text/Pandoc/Readers/HTML.hs test/Tests/Readers/HTML.hs`
  - Result: inspected the bounded `pCodeBlock` branch where `<br>` under
    `<pre>` contributes a newline and preformatted content becomes a native
    code block; counted 9 focused source branch lines.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-native-html-pre-code-breaks-handoff.php`
  - Result: emitted WordPress code blocks preserving `<br>` line breaks from
    pre/code and bare `<pre>` source exports.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 4,336 assertions, 0 failures
- Diff hygiene passed:
  - `git diff --check -- lanes/pandoc/src/MarkdownReader.php lanes/pandoc/tests/MarkdownReaderTest.php lanes/pandoc/fixtures/upstream-html-pre-code-br.html lanes/pandoc/examples/wordpress-native-html-pre-code-breaks-handoff.php lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json lanes/pandoc/notes/upstream-inventory.md lanes/pandoc/notes/root-harness-20260523.md lanes/pandoc/notes/wordpress-scenarios.md`
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## WordPress Writer DOCX Nested Link Label Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/WordPressBlockWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-native-docx-nested-links-handoff.php`
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:test/docx/nested_anchors_in_header.native | wc -l`
  - `git -C .upstream-cache/pandoc show HEAD:test/docx/nested_anchors_in_header.native | rg -o '\bLink\b' | wc -l`
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Writers/Shared.hs | sed -n '814,822p' | rg -c 'removeLinks|Link attr ils _|Span attr ils|walk go'`
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Writers/HTML.hs | sed -n '1581,1589p' | rg -c 'removeLinks|Link|mailto'`
  - `git -C .upstream-cache/pandoc grep -n 'nested_anchors_in_header\|removeLinks' HEAD -- test/Tests/Readers/Docx.hs test/Tests/Writers/Docx.hs src/Text/Pandoc/Writers/Shared.hs src/Text/Pandoc/Writers/HTML.hs`
  - Result: inspected the bounded DOCX native fixture and Pandoc writer rule
    that converts links inside link labels to spans before rendering HTML;
    counted 15 fixture lines, 10 `Link` constructors, 3 focused
    `removeLinks` helper lines, and 5 HTML writer link-call-site lines.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-native-docx-nested-links-handoff.php`
  - Result: emitted outer WordPress links whose inner DOCX page-number labels
    are spans instead of nested anchors.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 4,316 assertions, 0 failures
- Diff hygiene passed:
  - `git diff --check -- lanes/pandoc/src/WordPressBlockWriter.php lanes/pandoc/tests/MarkdownReaderTest.php lanes/pandoc/fixtures/upstream-native-docx-nested-anchors-header.native lanes/pandoc/examples/wordpress-native-docx-nested-links-handoff.php`
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## HTML Reader Orphan List Block Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-native-html-orphan-list-blocks-handoff.php`
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '348,378p'`
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '416,431p'`
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | rg -c 'orphans|#9187|pBulletList|pListItem|pOrderedList|mconcat xs'`
  - `git -C .upstream-cache/pandoc grep -n '#9187\|orphans <-\|mconcat xs' HEAD -- src/Text/Pandoc/Readers/HTML.hs`
  - Result: inspected the bounded orphan list-block branches where direct
    malformed block children under `ul`/`ol` become leading list items or
    append to the preceding list item; counted 8 branch lines and 21 source
    pattern hits in the cloned static inventory. No dedicated upstream command
    fixture for `#9187` was found.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-native-html-orphan-list-blocks-handoff.php`
  - Result: emitted a WordPress malformed-list handoff preserving a leading
    orphan paragraph, nested orphan list, and ordered-list continuation block.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 4,276 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## HTML Reader List Item ID Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-native-html-list-item-id-handoff.php`
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '362,372p'`
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '362,372p' | rg -c 'pListItem|addId ident|Plain|Span|B\.divWith|lookup "id"'`
  - `git -C .upstream-cache/pandoc show HEAD:test/command/3596.md | rg -c '<li id="id"|\[bar\]\{#id\}|::: \{#id\}|bar<ul'`
  - Result: inspected the bounded `pListItem` `addId` branch where tight
    list-item ids become `Span` anchors and block/loose list-item ids become
    `Div` anchors; counted 6 source branch lines and 6 command-fixture hits in
    the cloned static inventory.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-native-html-list-item-id-handoff.php`
  - Result: emitted a WordPress list handoff preserving tight source anchors as
    spans and loose/block source anchors as div wrappers.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 4,248 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## HTML Reader Generic Raw Inline Fallback Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-native-html-generic-raw-inline-handoff.php`
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '905,918p'`
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '905,918p' | rg -c 'pRawHtmlInline|tagComment|isInlineTag|Ext_raw_html|B\.rawInline "html"|ignore raw'`
  - `git -C .upstream-cache/pandoc grep -n '<blink>\|RawInline (Format "html") "<blink>"\|raw_html' HEAD -- test/command/parse-raw.md`
  - Result: inspected the bounded `pRawHtmlInline` fallback where raw
    inline tags/comments emit `RawInline (Format "html")` when raw HTML is
    enabled and are ignored when disabled; counted 7 source branch lines and
    4 `parse-raw.md` command fixture hits in the cloned static inventory.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-native-html-generic-raw-inline-handoff.php`
  - Result: emitted a WordPress paragraph preserving source `button`, `time`,
    and migration comment raw inline markup around parsed child content.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 4,227 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## HTML Reader MathML Annotation Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-native-html-mathml-annotation-handoff.php`
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '880,965p'`
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | rg -c 'MJX_Assistive_MathML|pMath|extractTeXAnnotation|annotation.*application/x-tex|mathMLToTeXMath|display.*block'`
  - `git -C .upstream-cache/pandoc grep -n 'MJX_Assistive_MathML\|application/x-tex\|<math' HEAD -- test/command test/Tests/Readers/HTML.hs src/Text/Pandoc/Readers/HTML.hs`
  - Result: inspected the bounded `pMath` embedded-TeX branch and assistive
    MathML span unwrapping; counted 13 source hits and three MathML-related
    command fixture hits in the cloned static inventory.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-native-html-mathml-annotation-handoff.php`
  - Result: emitted inline and display WordPress math spans plus a reviewable
    fallback span for MathML without embedded TeX.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 4,152 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## HTML Reader Span Strikeout Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-native-html-span-strikeout-handoff.php`
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '760,825p'`
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | rg -c '"s" -> pStrikeout|"strike" -> pStrikeout|"del" -> pStrikeout|"u" -> pUnderline|"ins" -> pUnderline|pStrikeout|pUnderline|class","strikeout"'`
  - `git -C .upstream-cache/pandoc grep -l '<span class="strikeout"\|<s>\|<strike>\|<del>\|<ins>\|Strikeout\|Underline' HEAD -- test/command test/Tests/Readers/HTML.hs | wc -l`
  - Result: inspected the bounded `pStrikeout` and `pUnderline` branches where
    exact `<span class="strikeout">` lowers to native `Strikeout`; counted 10
    source hits and 7 related command/reader fixture files in the cloned
    static inventory.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-native-html-span-strikeout-handoff.php`
  - Result: emitted a WordPress handoff with native `<del>` strikeout output
    and adjacent `<del>`/`<u>` edit review markup.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 4,089 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## HTML Reader Raw-HTML Disabled Skip Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-native-html-raw-disabled-handoff.php`
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '536,565p'`
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '900,930p'`
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '620,730p'`
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | rg -c 'Ext_raw_html|pRawHtmlBlock|pRawHtmlInline|ignore raw|SkippedContent|pHtmlBlock "script"|pHtmlBlock "style"|pHtmlBlock "textarea"'`
  - `git -C .upstream-cache/pandoc grep -l 'raw_html\|raw html\|<style\|<script\|<textarea' HEAD -- test/command test/Tests/Readers/HTML.hs | wc -l`
  - Result: inspected the bounded `Ext_raw_html` guard where raw block/inline
    HTML is emitted only when enabled, and otherwise skipped through `ignore`;
    counted 21 source hits and 45 related raw_html/style/script/textarea
    fixture files in the cloned static inventory.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-native-html-raw-disabled-handoff.php`
  - Result: emitted a WordPress handoff with no raw style/script/textarea tags
    while preserving display math from `script type="math/tex"`.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 4,055 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## HTML Reader Textarea Raw-Block Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-native-html-textarea-handoff.php`
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '215,255p'`
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '542,566p'`
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | rg -c '"textarea" -> pRawHtmlBlock|pRawHtmlBlock|pHtmlBlock "textarea"|RawBlock \(Format "html"\)'`
  - Result: inspected the bounded `pRawHtmlBlock` branch where block-level
    `<textarea>` emits `RawBlock (Format "html")`; counted 6 static source
    hits around dispatch and raw-block parsing.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-native-html-textarea-handoff.php`
  - Result: emitted a WordPress HTML block where a legacy textarea payload
    remains literal for review.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 3,983 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## HTML Reader Small Inline Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-native-html-small-inline-handoff.php`
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '692,786p'`
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | rg -c '"small" -> pSmall|pSmall|pInlinesInTags "small"|B\.spanWith \("",\["small"\],\[\]\)'`
  - Result: inspected the bounded `pSmall` branch where Pandoc lowers
    `<small>` to `Span ("",["small"],[])`; counted 3 static source hits
    around the inline dispatch and definition.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-native-html-small-inline-handoff.php`
  - Result: emitted a WordPress paragraph where source fine print remains
    reviewable as `<span class="small">...</span>`.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 3,894 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## HTML Writer Structural Block Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/HtmlWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-html-writer-figure-line-handoff.php`
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Writers/HTML.hs | sed -n '760,780p'`
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Writers/HTML.hs | sed -n '936,966p'`
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Writers/HTML.hs | sed -n '1080,1116p'`
  - `git -C .upstream-cache/pandoc show HEAD:test/testsuite.native | rg -c 'HorizontalRule'`
  - `git -C .upstream-cache/pandoc show HEAD:test/testsuite.native | rg -c '^  , Figure|^  \\[ Figure|Figure'`
  - Result: inspected the bounded `LineBlock`, `HorizontalRule`, and `Figure`
    branches where Pandoc emits `div.line-block`, `<hr />`, HTML5 `figure`,
    `figcaption`, and `aria-hidden` when a caption matches image alt text;
    counted 13 `HorizontalRule` occurrences and 1 `Figure` occurrence in
    `test/testsuite.native`.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-html-writer-figure-line-handoff.php`
  - Result: emitted HTML figure/line-block/separator preview plus matching
    WordPress image/paragraph/separator handoff markup.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 3,694 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## LaTeX Writer Highlighted Strikeout Inline-Code Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/LatexWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-latex-highlighted-strikeout-code-handoff.php`
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:test/Tests/Writers/LaTeX.hs | sed -n '55,95p'`
  - `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Writers/LaTeX.hs | sed -n '1000,1125p'`
  - Result: inspected the bounded inline-code group where Haskell code inside
    `Strikeout` emits `\VERB|\NormalTok{...}|` and the LaTeX writer protects
    `VERB` output with `\mbox{...}` inside soul commands.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-latex-highlighted-strikeout-code-handoff.php`
  - Result: emitted highlighted strikeout LaTeX output plus matching WordPress
    del/code handoff markup with source metadata.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 3,652 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## LaTeX Writer Listing Code-Block Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/LatexWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-latex-listing-code-handoff.php`
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:test/Tests/Writers/LaTeX.hs | sed -n '35,75p'`
  - Result: inspected the bounded code-block group where
    `writerHighlightMethod = IdiomaticHighlighting` emits `lstlisting` output
    with `[label=id]` for identified code blocks and no option bracket for
    anonymous code blocks.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-latex-listing-code-handoff.php`
  - Result: emitted LaTeX `lstlisting` reviewer output plus matching WordPress
    code-block handoff markup.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 3,648 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## LaTeX Writer Underline/Strikeout Inline-Note Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/LatexWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-latex-underline-strikeout-note-handoff.php`
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:test/Tests/Writers/LaTeX.hs | sed -n '80,170p'`
  - Result: inspected the bounded inline-note group where underline and
    strikeout multi-block notes split outside active style commands, plus the
    adjacent strikeout code-span mbox case.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-latex-underline-strikeout-note-handoff.php`
  - Result: emitted LaTeX underline/strikeout reviewer output plus matching
    WordPress insertion/deletion block handoff markup.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 3,642 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## LaTeX Writer Top-Level Division Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/LatexWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-latex-top-level-division-handoff.php`
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:test/Tests/Writers/LaTeX.hs | sed -n '120,230p'`
  - Result: inspected the bounded writer-options top-level division group
    where normal LaTeX output maps `TopLevelChapter`, `TopLevelPart`, and
    `TopLevelDefault` headings, plus the unnumbered part-level
    `\addcontentsline` branch.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-latex-top-level-division-handoff.php`
  - Result: emitted LaTeX chapter/section reviewer output plus matching
    WordPress heading block handoff markup.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 3,624 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## LaTeX Writer Unnumbered-Heading Note Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/LatexWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-latex-unnumbered-heading-note-handoff.php`
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:test/Tests/Writers/LaTeX.hs`
  - Result: inspected the bounded `headers` group unnumbered-heading case
    where a level-1 unnumbered heading with id `foo` and an inline note emits
    a starred section, `\texorpdfstring`, `\footnote`, `\label`, and
    `\addcontentsline`.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-latex-unnumbered-heading-note-handoff.php`
  - Result: emitted LaTeX reviewer starred heading/footnote output plus
    matching WordPress heading/endnote handoff markup.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 3,616 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## LaTeX Writer Figure-Placement Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/LatexWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-latex-figure-handoff.php`
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:test/Tests/Writers/LaTeX.hs | sed -n '1,340p'`
  - Result: inspected the bounded `figures` group placement case where a
    `latex-placement="htbp"` figure emits a LaTeX figure environment,
    `\centering`, bounded `\includegraphics` with alt text, and a caption.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-latex-figure-handoff.php`
  - Result: emitted LaTeX reviewer figure output plus matching WordPress image
    block handoff markup for an imported media frame.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 3,600 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## LaTeX Writer Heading Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/LatexWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-latex-heading-handoff.php`
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:test/Tests/Writers/LaTeX.hs | sed -n '55,160p'`
  - Result: inspected the bounded `headers` group list-item heading case and
    the writer-options default top-level heading output for
    section/subsection/subsubsection commands.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-latex-heading-handoff.php`
  - Result: emitted LaTeX reviewer section commands plus matching WordPress
    heading blocks for a migration-review outline.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 3,587 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## HTML Writer Highlighted-Code Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/HtmlWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-html-writer-highlighted-code-handoff.php`
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:test/Tests/Writers/HTML.hs | sed -n '1,140p'`
  - Result: inspected the bounded inline-code `haskell`/`nolanguage` cases
    and the `sample with style` / `variable with style` groups where
    highlighted Haskell code is wrapped in `samp` or `var`.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-html-writer-highlighted-code-handoff.php`
  - Result: emitted reviewer `sourceCode haskell` HTML with `span.op`
    operator markup plus `samp` and `var` semantic wrappers.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 3,557 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## HTML Writer Image/Heading Attribute Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/HtmlWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-html-writer-image-attrs-handoff.php`
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:test/Tests/Writers/HTML.hs | sed -n '55,78p'`
  - Result: inspected the bounded `images` alt-with-formatting case and the
    `blocks` heading-with-disallowed-attributes case where `invalid` is
    omitted and `lang` is retained.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-html-writer-image-attrs-handoff.php`
  - Result: emitted a `lang`-preserving heading and a media-review image with
    title, plain-stringified formatted alt text, and `data-source` handoff
    metadata.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 3,541 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## HTML Pre/Code Attribute Reader Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/src/WordPressBlockWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-native-html-codeblock-attrs-handoff.php`
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:test/Tests/Readers/HTML.hs | sed -n '124,150p'`
  - Result: inspected the bounded `code block` group where attributes in
    `pre > code` map to `CodeBlock ("a", ["python"], []) "\nprint('hi')"`
    and attributes on `pre` take precedence over nested `code` attributes.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-native-html-codeblock-attrs-handoff.php`
  - Result: emitted WordPress code blocks with `legacy-snippet`
    `data-source` metadata and `pre-wrapper-wins` precedence metadata.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 3,528 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## HTML Inline Code Alias Reader Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-native-html-inline-code-handoff.php`
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:test/Tests/Readers/HTML.hs | sed -n '112,128p'`
  - Result: inspected the bounded `code`, `tt`, `samp`, and `var` groups
    where `samp` maps to `Code` with class `sample` and `var` maps to
    `Code` with class `variable`.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-native-html-inline-code-handoff.php`
  - Result: emitted WordPress paragraph output with ordinary inline code for
    `code`/`tt` and classed inline code for `samp`/`var`.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 3,495 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## HTML Base Href Reader Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-native-html-base-media-handoff.php`
- Targeted upstream source inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:test/Tests/Readers/HTML.hs`
  - Result: inspected the bounded `base tag` group covering file-like base
    paths, directory base paths, root-relative image paths, and already
    absolute image URLs.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-native-html-base-media-handoff.php`
  - Result: emitted WordPress image and paragraph blocks whose media/link URLs
    were absolute after resolving against the source HTML `<base href>`.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 3,411 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## Markdown Abbreviation Reader Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-abbrev-handoff.php`
- Upstream fixture parity passed:
  - `diff -u <(git -C .upstream-cache/pandoc show HEAD:test/command/md-abbrevs.md) lanes/pandoc/fixtures/upstream-command-md-abbrevs.md`
- Targeted upstream data evidence inspected:
  - `git -C .upstream-cache/pandoc show HEAD:data/abbreviations`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-abbrev-handoff.php`
  - Result: emitted WordPress paragraphs with nonbreaking abbreviation groups
    for `Mr. Bob`, `Dr. Rivera`, and `e.g. examples`, while escaped `Mr\. Bob`
    kept ordinary spacing.
- Focused lane tests passed after tightening the digit-following guard exposed
  by the first run:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 3,401 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## Native DOCX Table GridBefore Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/WordPressBlockWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-native-docx-table-gridbefore-handoff.php`
- Upstream fixture inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:test/docx/table_gridbefore.native`
  - Result: inspected the bounded Native table region used for
    `upstream-native-docx-table-gridbefore-slice.native`, including scientific
    `ColWidth` values, explicit empty cells, and `ColSpan 8`/`ColSpan 10`
    table packets.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-native-docx-table-gridbefore-handoff.php`
  - Result: emitted a WordPress table with source blank grid cells preserved
    and opt-in `data-pandoc-empty-cell` reviewer markers.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 3,360 assertions, 0 failures
- Whitespace check passed:
  - `git diff --check -- lanes/pandoc`
- Root harness was not assigned for this lane handoff, so no no-argument root
  run was started. Focused pandoc evidence is recorded here for the
  supervisor/integrator.

## Native ODT Multi-Header Table Slice

- Focused lane JSON validation passed:
  - `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- Focused lane lint passed:
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-native-odt-multi-header-table-handoff.php`
- Upstream fixture parity passed:
  - `diff -u <(git -C .upstream-cache/pandoc show HEAD:test/odt/native/simpleTableWithMultipleHeaderRows.native) lanes/pandoc/fixtures/upstream-native-odt-simple-table-multiple-header-rows.native`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-native-odt-multi-header-table-handoff.php`
  - Result: emitted a WordPress table with two `<thead>` rows, three body
    rows, preserved empty cells, no invented `<colgroup>`, and no trailing
    empty paragraph.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 3,321 assertions, 0 failures
- Whitespace check passed:
  - `git diff --check -- lanes/pandoc`
- Root harness was not assigned for this lane handoff, so no no-argument root
  run was started. Focused pandoc evidence is recorded here for the
  supervisor/integrator.

## Markdown Nested Spanlike Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/WordPressBlockWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-spanlike-handoff.php`
- Upstream command fixture inspection passed:
  - `git -C .upstream-cache/pandoc show HEAD:test/command/nested-spanlike.md`
  - Result: expected `<kbd id="bar"><u><span class="smallcaps">test</span></u></kbd>`
    wrapper for `[test]{.foo .underline #bar .smallcaps .kbd}`.
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-spanlike-handoff.php`
  - Result: emitted the mapped WordPress paragraph with `<kbd>`, `<u>`, and
    smallcaps wrappers and no consumed marker-class leakage.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 3,274 assertions, 0 failures
- Root harness was not assigned for this lane handoff, so no no-argument root
  run was started. Focused pandoc evidence is recorded here for the
  supervisor/integrator.

## Native DOCX Paragraph Split Decision Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-native-docx-paragraph-change-decision-handoff.php`
- Upstream fixture parity passed:
  - `diff -u <(git -C .upstream-cache/pandoc show HEAD:test/docx/paragraph_insertion_deletion_accept.native) lanes/pandoc/fixtures/upstream-native-docx-paragraph-insertion-deletion-accept.native`
  - `diff -u <(git -C .upstream-cache/pandoc show HEAD:test/docx/paragraph_insertion_deletion_reject.native) lanes/pandoc/fixtures/upstream-native-docx-paragraph-insertion-deletion-reject.native`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-native-docx-paragraph-change-decision-handoff.php`
  - Result: emitted accepted and rejected paragraph split sections as plain
    WordPress paragraphs with no residual paragraph-change metadata.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 3,268 assertions, 0 failures
- Root harness was not assigned for this lane handoff, so no no-argument root
  run was started. Focused pandoc evidence is recorded here for the
  supervisor/integrator.

## Native DOCX Scrubbed Metadata Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/WordPressBlockWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-native-docx-scrubbed-metadata-handoff.php`
- Upstream fixture parity passed:
  - `diff -u <(git -C .upstream-cache/pandoc show HEAD:test/docx/track_changes_scrubbed_metadata.native) lanes/pandoc/fixtures/upstream-native-docx-track-changes-scrubbed-metadata.native`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-native-docx-scrubbed-metadata-handoff.php`
  - Result: emitted author-only `<ins>`, `<del>`, and comment review spans
    with explicit missing-date status and no fake `datetime`.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 3,225 assertions, 0 failures
- Root harness was not assigned for this lane handoff, so no no-argument root
  run was started. Focused pandoc evidence is recorded here for the
  supervisor/integrator.

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,167 assertions, 0 failures
- Root harness was not started because the required duplicate-root gate found
  active root harness processes:
  - PID `2552688`, user `claude`, PPID `2539412`, elapsed `00:26`, state `Rs`,
    command `php tools/run-tests.php`
  - PID `2552780`, user `claude`, PPID `2509052`, elapsed `00:25`, state `Ss`,
    command `php tools/run-tests.php`

- A later duplicate-root gate was clear, so this lane worker ran the root
  harness:
  - `php tools/run-tests.php`
  - Result: 200 test files, 22,731 assertions, 0 failures

## Markdown Writer Mark-Span Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
  - Result: emitted `==verify source caption==` and escaped literal
    `==audit tokens==` in the native reviewer handoff packet.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,269 assertions, 0 failures
- Required duplicate-root gate was clear:
  - `pgrep -af '^php tools/run-tests\.php( |$)'`
  - Result: no active root harness processes
- Root harness was run once without a lock-wait message:
  - `php tools/run-tests.php`
  - Result: 226 test files, 25,266 assertions, 204 failures
  - Pandoc tests passed inside the root run.
  - Visible non-pandoc failures were in
    `lanes/difftastic/tests/TokenDifferTest.php`, all reporting
    `Call to undefined method PortLibs\Difftastic\TokenDiffer::isNixLanguage()`.

## Nested-Dollar Inline Math Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,176 assertions, 0 failures
- Root harness was not started because the required duplicate-root gate found
  an active root harness process:
  - PID `2613382`, user `claude`, PPID `2613380`, elapsed `00:19`, state `R`,
    command `php tools/run-tests.php`

## Raw HTML Before Header And Commented List Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,199 assertions, 0 failures
- Required duplicate-root gate was clear:
  - `pgrep -af '^php tools/run-tests\.php( |$)'`
  - Result: no active root harness processes
- Root harness was run once and failed outside this lane:
  - `php tools/run-tests.php`
  - Result: 202 test files, 23,114 assertions, 2 failures
  - Visible non-pandoc failure: `lanes/readability/tests/ArticleExtractorTest.php`
    test `maps Mozilla firefox-nightly-blog fixture with article-header rel
    author byline` expected `Mike Conley` and got `NULL`.
  - Pandoc tests passed inside the root run.
- Post-run duplicate-root sample found another active root harness:
  - PID `2656523`, user `claude`, PPID `2627884`, elapsed `00:50`, state `R`,
    command `php tools/run-tests.php`
  - No additional root run was started.

## Case-Insensitive Reference, Curly Quote, And Consecutive List Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,232 assertions, 0 failures
- Root harness was not started because the required duplicate-root gate found
  an active root harness process:
  - PID `2694170`, user `claude`, PPID `2667629`, elapsed `00:12`, state `Rs`,
    command `php tools/run-tests.php`

## Task-List Markdown And LaTeX Writer Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/src/WordPressBlockWriter.php`
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/src/LatexWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,234 assertions, 0 failures
- Required duplicate-root gate was clear:
  - `pgrep -af '^php tools/run-tests\.php( |$)'`
  - Result: no active root harness processes
- Final root harness run passed:
  - `php tools/run-tests.php`
  - Result: 204 test files, 23,553 assertions, 0 failures

## Markdown Writer Fancy Ordered-List Marker Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,238 assertions, 0 failures
- Required duplicate-root gate:
  - `pgrep -af '^php tools/run-tests\.php( |$)'`
  - First sample found focused lane PID `2835754`, command `php tools/run-tests.php lanes/syncthing/tests`.
  - Immediate exact-root sample found PID `2836374`, owner `claude`, PPID `2836373`, elapsed `00:07`, state `R`, command `php tools/run-tests.php`.
  - Later sample found exact-root PID `2858105`, owner `claude`, PPID `2833947`, elapsed `00:24`, state `R`, command `php tools/run-tests.php`.
- This lane did not start a duplicate root harness while those processes were active.
- A final duplicate-root gate was clear, so this lane worker ran the root harness once:
  - `php tools/run-tests.php`
  - Result: 205 test files, 23,807 assertions, 0 failures

## Markdown Writer Note/Reference Location Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
  - Result: emitted setext-heading handoff Markdown with block-local footnotes
    and shortcut reference definitions.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,242 assertions, 0 failures
- Required duplicate-root gate found an active exact root harness, so this lane
  did not start a duplicate root run:
  - PID `2966490`, user `claude`, PPID `2966489`, elapsed `00:08`, state `R`,
    command `php tools/run-tests.php`
- A later exact-root sample briefly found PID `2969564`, but it exited before
  owner sampling. When this lane attempted a root run after a clear sample, the
  root runner immediately reported that another root run held
  `.upstream-cache/run-tests.lock`; process sampling showed active root PID
  `2970899` owned by `claude` and this lane's queued `php tools/run-tests.php`
  process `2970907`. The queued lane process was terminated instead of waiting
  to run behind the active root.
- A final exact-root sample was clear, so this lane ran the root harness once:
  - `php tools/run-tests.php`
  - Result: 209 test files, 24,067 assertions, 0 failures

## Markdown Writer Shortcut Reference Boundary Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
  - Result: emitted duplicate adjacent reviewer source links with numbered
    reference definitions, escaped bracketed reviewer text, and
    citation-adjacent reference syntax.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,254 assertions, 0 failures
- Required duplicate-root gate found an active exact root harness, so this lane
  did not start a duplicate root run:
  - PID `2994382`, user `claude`, PPID `2994380`, elapsed `00:07`, state `R`,
    command `php tools/run-tests.php`

## Markdown Writer Top-Level Cases Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
  - Result: emitted the native Markdown reviewer handoff packet with
    block-local notes and adjacent shortcut reference definitions.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,257 assertions, 0 failures
- Required duplicate-root gate found an active exact root harness, so this lane
  did not start a duplicate root run:
  - PID `3087737`, user `claude`, PPID `3087673`, elapsed `00:18`, state `R`,
    command `php tools/run-tests.php`

## Markdown Writer Plain/Para Marker-Escaping Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-plain-marker-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-plain-marker-handoff.php`
  - Result: emitted escaped literal reviewer source markers including `1\.`,
    `\(2\)`, `\-`, and `\%`, plus a nested escaped list-item paragraph marker.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,431 assertions, 0 failures
- Root harness was not assigned for this lane handoff, so no no-argument root
  run was started. Focused pandoc evidence is recorded here for the
  supervisor/integrator.

## NativeWriter Common-AST Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/NativeWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-native-review-packet-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-native-review-packet-handoff.php`
  - Result: emitted a standalone Pandoc Native reviewer packet with `Meta
    { unMeta = fromList ... }`, `Header`, `Para`, `BulletList`, and
    `CodeBlock` constructors plus escaped code-block newlines.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,663 assertions, 0 failures
- Root harness was not assigned for this lane handoff, so no no-argument root
  run was started. Focused pandoc evidence is recorded here for the
  supervisor/integrator.

## PlainText Template Include-Variable Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-plain-template-include-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-plain-template-include-handoff.php`
  - Result: emitted a WordPress import audit packet with header-includes, two
    include-before entries, body text, and a metadata-derived include-after
    footer with admin URLs and code ticks stripped from metadata-rendered text.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,513 assertions, 0 failures
- Root harness was not assigned for this lane handoff, so no no-argument root
  run was started. Focused pandoc evidence is recorded here for the
  supervisor/integrator.

## Markdown Writer Table Fallback Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php`
  - Result: emitted a source-review `<table>` with `data-source`, caption,
    colgroup, colspan, and alignment metadata.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,329 assertions, 0 failures
- Root harness was not assigned for this lane handoff, so no no-argument root
  run was started. Focused pandoc evidence is recorded here for the
  supervisor/integrator.

## Markdown Writer Header Attribute Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-heading-anchors-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-heading-anchors-handoff.php`
  - Result: emitted custom Pandoc heading attributes for WordPress review
    anchors and elided duplicate imported auto ids.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,400 assertions, 0 failures
- Root harness was not assigned for this lane handoff, so no no-argument root
  run was started. Focused pandoc evidence is recorded here for the
  supervisor/integrator.

## Markdown Writer Inline Escaping And Reference Labels Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
  - Result: emitted the native Markdown reviewer handoff packet with
    block-local notes, adjacent shortcut reference definitions, and escaped
    literal audit tokens.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,258 assertions, 0 failures
- First duplicate-root gate found an active exact root harness, so this lane
  did not start a duplicate root run at that point:
  - PID `3110747`, user `claude`, PPID `3096285`, elapsed `00:18`, state `Rs`,
    command `php tools/run-tests.php`
- A later duplicate-root gate was clear, so this lane ran the root harness once:
  - `php tools/run-tests.php`
  - Result: 214 test files, 24,638 assertions, 1 failure
  - Pandoc tests passed inside the root run. The retained tool-output chunks did
    not include the failing `FAIL ...` line, so the failing non-pandoc test name
    is not known from this lane run.
- Post-run duplicate-root sample found another active exact root harness, so no
  second root run was started:
  - PID `3168962`, user `claude`, PPID `3093040`, elapsed `00:13`, state `Rs`,
    command `php tools/run-tests.php`
- Final duplicate-root sample still found an active exact root harness:
  - PID `3174787`, user `claude`, PPID `3105286`, elapsed `00:27`, state `Rs`,
    command `php tools/run-tests.php`
- A final filtered root capture was run after the exact-root gate cleared:
  - `php tools/run-tests.php`
  - Result: 214 test files, 24,677 assertions, 0 failures

## Markdown Writer URI/E-Mail Autolink And Link Attribute Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
  - Result: emitted angle-bracket URI/e-mail autolinks plus an attributed
    reviewer packet reference definition.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,260 assertions, 0 failures
- Required duplicate-root gate was clear:
  - `pgrep -af '^php tools/run-tests\.php( |$)'`
  - Result: no active exact root harness processes
- Root harness passed:
  - `php tools/run-tests.php`
  - Result: 216 test files, 24,927 assertions, 0 failures

## Markdown Writer Image Emission Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
  - Result: emitted a reference-style reviewer image definition with
    id/class/alt/data-source metadata.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,262 assertions, 0 failures
- Required duplicate-root gate was clear:
  - `pgrep -af '^php tools/run-tests\.php( |$)'`
  - Result: no active exact root harness processes
- Root harness passed:
  - `php tools/run-tests.php`
  - Result: 223 test files, 25,545 assertions, 0 failures

## Markdown Writer Code/Span Emission Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
  - Result: emitted a bracketed reviewer span with id/class/data/title
    metadata, an attributed inline code token, and an emoji alias.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,264 assertions, 0 failures
- Required duplicate-root gate was clear:
  - `pgrep -af '^php tools/run-tests\.php( |$)'`
  - Result: no active exact root harness process; concurrent lane-focused
    commands were visible for readability and syncthing and were not treated
    as duplicate root runs.
- Root harness was run once and failed outside this lane:
  - `php tools/run-tests.php`
  - Result: 224 test files, 25,731 assertions, 1 failure
  - Pandoc tests passed inside the root run.
  - Non-pandoc failure: `lanes/libsqlite/tests/SQLiteHeaderTest.php` test
    `plans wordpress replacement by merging a non-root composite index parent
    under a multi-child root` expected IDs `[1, 2, 3, 4, 6, 7]` and got
    `[1, 2, 3, 4, 5, 6, 7]`.

## Markdown Writer Strike/Script/Math/Raw Inline Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
  - Result: emitted reviewer handoff Markdown with subscript, superscript,
    strikeout, inline math followed by Pandoc's `<!-- -->` digit guard, raw
    TeX, and raw-attribute fallback output.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,265 assertions, 0 failures
- Required duplicate-root gate was clear:
  - `pgrep -af '^php tools/run-tests\.php( |$)'`
  - Result: no active exact root harness process
- Root harness passed:
  - `php tools/run-tests.php`
  - Result: 224 test files, 25,874 assertions, 0 failures

## Markdown Writer Quoted/Underline/Small-Caps Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
  - Result: emitted reviewer quote/style handoff Markdown with smart quote
    delimiters plus bracketed underline and small-caps spans.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,266 assertions, 0 failures
- Required duplicate-root gate was clear:
  - `pgrep -af '^php tools/run-tests\.php( |$)'`
  - Result: no active exact root harness process
- Root harness passed:
  - `php tools/run-tests.php`
  - Result: 226 test files, 26,113 assertions, 0 failures

## Markdown Writer Citation Rendering Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
  - Result: emitted `@migration-audit [p. 12; see @source-log ch. 4]`
    and `[-@{legacy key}, appendix]` in the native reviewer handoff packet.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,268 assertions, 0 failures
- Required duplicate-root gate was clear:
  - `pgrep -af '^php tools/run-tests\.php( |$)'`
  - Result: no active exact root harness process
- Root harness passed:
  - `php tools/run-tests.php`
  - Result: 226 test files, 26,288 assertions, 0 failures

## Markdown Writer Raw-HTML Fallback Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown/Inline.hs`
  - `src/Text/Pandoc/Writers/HTML.hs`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php`
  - Result: emitted an attributed reviewer edit link and media image as raw
    HTML with escaped attributes and `data-source` metadata.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,272 assertions, 0 failures
- Required duplicate-root gate was clear:
  - `pgrep -af '^php tools/run-tests\.php( |$)'`
  - Result: no active exact root harness process
- Root harness passed:
  - `php tools/run-tests.php`
  - Result: 226 test files, 26,723 assertions, 0 failures

## Markdown Writer Generic Span Fallback Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown/Inline.hs`
  - `src/Text/Pandoc/Writers/HTML.hs`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php`
  - Result: emitted an attributed reviewer edit link, scoped span, and media
    image as raw HTML with escaped attributes and `data-source` metadata.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,276 assertions, 0 failures
- Required duplicate-root gate was clear:
  - `pgrep -af '^php tools/run-tests\.php( |$)'`
  - Result: no active exact root harness process
- Root harness left pending:
  - Active broad upstream runners were present: Dolt BATS PID `237222` and
    SQLite `testrunner.tcl --jobs` PIDs `3854382`/`3854383`.
  - Per lane instructions, no additional no-argument root harness was started.

## Markdown Writer Underline/Small-Caps Fallback Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown/Inline.hs`
  - `src/Text/Pandoc/Writers/HTML.hs`
  - `src/Text/Pandoc/Shared.hs`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php`
  - Result: emitted an attributed reviewer edit link, scoped span, raw `<u>`
    underline, smallcaps span, and media image with escaped attributes and
    `data-source` metadata.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,280 assertions, 0 failures
- Required duplicate-root gate was clear:
  - `pgrep -af '^php tools/run-tests\.php( |$)'`
  - Result: no active exact root harness process
- Root harness left pending:
  - Active broad upstream runners were present: Dolt BATS PID `237222` with
    child BATS processes and SQLite `testrunner.tcl --jobs` PIDs
    `3854382`/`3854383`.
  - Per lane instructions, no additional no-argument root harness was started.

## Markdown Writer Nested/Empty Emphasis Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown/Inline.hs`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
  - Result: emitted `Reviewer emphasis normalization: source flag and empty
    source marks drop before handoff.`
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,283 assertions, 0 failures
- Required duplicate-root gate:
  - First sample found focused lane PID `497483`, command
    `php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests`; it
    exited before owner sampling.
  - Later exact-root sample returned no active root harness process.
- Root harness left pending:
  - Active broad upstream runners were present after the later exact-root
    sample: SQLite `testrunner.tcl --jobs` PIDs `3854382` and `3854383`, owned
    by `claude`.
  - Per lane instructions, no additional no-argument root harness was started.

## Markdown Writer Strikeout Disabled-Extension Fallback Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown/Inline.hs`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php`
  - Result: emitted deleted source `<s>legacy caption</s>` in the native
    reviewer fallback packet.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,286 assertions, 0 failures
- Required duplicate-root gate was clear:
  - `pgrep -af '^php tools/run-tests\.php( |$)'`
  - Result: no active exact root harness process
- Root harness left pending:
  - Active broad upstream runners were present: Dolt BATS PIDs `575005`,
    `575036`, and `575043`, plus SQLite `testrunner.tcl --jobs` PIDs
    `3854382` and `3854383`, all owned by `claude`.
  - Per lane instructions, no additional no-argument root harness was started
    because focused pandoc tests were already green.

## Markdown Writer Superscript/Subscript Disabled-Extension Fallback Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown/Inline.hs`
  - `src/Text/Pandoc/Writers/Shared.hs`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php`
  - Result: emitted raw script fallback markers `H<sub>2</sub>` and
    `x<sup>2</sup>` in the native reviewer fallback packet.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,289 assertions, 0 failures
- Required duplicate-root gate was clear:
  - `pgrep -af '^php tools/run-tests\.php( |$)'`
  - Result: no active exact root harness process
- Root harness left pending:
  - Active broad upstream runners were present: Dolt BATS PIDs `575005`,
    `575036`, and `575043`; Syncthing Go test PIDs `744251` and `744254`;
    and Gitoxide Cargo test PIDs `744335` and `744338`, all owned by
    `claude`.
  - Per lane instructions, no additional no-argument root harness was started
    because focused pandoc tests were already green.

## Markdown Writer Quoted Smart-Disabled Fallback Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown/Inline.hs`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php`
  - Result: emitted smart-disabled quote entity fallbacks
    `&lsquo;legacy reviewer quote&rsquo;` and
    `&ldquo;migration excerpt&rdquo;` in the native reviewer fallback packet.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,293 assertions, 0 failures
- Required duplicate-root gate found an active exact root harness, so this lane
  did not start a duplicate root run:
  - PID `938813`, user `claude`, PPID `934496`, elapsed `00:18`, state `R`,
    command `php tools/run-tests.php`

## Markdown Writer Str PreferAscii Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown/Inline.hs`
  - `src/Text/Pandoc/XML.hs`
  - Hackage `tagsoup-0.14.8` `Text.HTML.TagSoup.Entity` table for the
    `toHtml5Entities` name-selection behavior used by Pandoc.
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php`
  - Result: emitted non-ASCII reviewer text as `R&eacute;sum&eacute;`,
    `&COPY;`, `&in;`, `&#128512;`,
    `&ldquo;curly excerpt&rdquo;`, and `&mldr;`.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,295 assertions, 0 failures
- Required duplicate-root gate found an active exact root harness, so this lane
  did not start a duplicate root run:
  - PID `1035563`, user `claude`, PPID `1019446`, elapsed `00:09`, state `Rs`,
    command `php tools/run-tests.php`
- Additional broad runner sample:
  - Dolt Go test PIDs `1035352`/`1035381` and
    `1035887`/`1035940` were active.

## Markdown Writer LineBreak Option Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown/Inline.hs`
  - `src/Text/Pandoc/Extensions.hs`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
  - Result: emitted the reviewer line-break paragraph with Pandoc's default
    escaped-line-break backslash.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,298 assertions, 0 failures
- Required duplicate-root gate was clear:
  - `pgrep -af '^php tools/run-tests\.php( |$)'`
  - Result: no active exact root harness process
- Root harness left pending:
  - Active broad upstream runners were present and owned by `claude`: Dolt BATS
    PIDs `1036684`, `1036713`, `1036720`, `1036721`, and `1036722`; SQLite
    `testrunner.tcl --jobs` PIDs `1057838` and `1057839`.
  - Per lane instructions, no additional no-argument root harness was started
    because focused pandoc tests were already green.

## Markdown Writer RawInline Extension Fallback Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown/Inline.hs`
  - `src/Text/Pandoc/Extensions.hs`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
  - Result: emitted raw TeX, OPML, and HTML reviewer markers as Pandoc
    raw-attribute Markdown by default.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,307 assertions, 0 failures
- Required duplicate-root gate was clear:
  - `pgrep -af '^php tools/run-tests\.php( |$)'`
  - Result: no active exact root harness process
- Root harness left pending:
  - Active broad upstream Dolt BATS runners were present and owned by
    `claude`: PIDs `1036684`, `1036713`, `1036720`, `1036721`, `1036722`, and
    `1434434`.
  - Per lane instructions, no additional no-argument root harness was started
    because focused pandoc tests were already green.

## Markdown Writer RawBlock/Div Fallback Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown.hs`
  - `src/Text/Pandoc/Extensions.hs`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
  - Result: emitted a fenced WordPress review Div, OPML fenced raw block, and
    literal TeX review environment in the native reviewer handoff packet.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,319 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## PlainText Template Compile-Diagnostics Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-plain-template-diagnostics-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-plain-template-diagnostics-handoff.php`
  - Result: emitted a Pandoc-style template diagnostic for
    `templates/reviewer-card.txt` line 2, column 7, and did not render the
    source admin URL body.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,632 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## PlainText Custom Template Pipe Slice

- Targeted upstream evidence inspected:
  - `MANUAL.txt` Template syntax, especially the Pipes section.
  - `src/Text/Pandoc/Templates.hs`
  - `data/templates/default.plain`
  - Revalidated static inventory status from the lane manifest: 2,276 upstream
    test/data/benchmark artifacts, including 1,974 `test/`, 54
    `pandoc-lua-engine/test/`, 247 `data/`, and 1 `benchmark/` artifact.
  - Full upstream runner was not executed: `test-pandoc` and
    `test-pandoc-lua-engine` require building Haskell Tasty executables from a
    broad checkout and dependency graph. Focused evidence remains cloned static
    inventory plus targeted source/manual inspection.
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-plain-template-pipes-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-plain-template-pipes-handoff.php`
  - Result: emitted a custom WordPress PlainText packet with lowercase status,
    uppercase queue, label count/first-last/reverse pipe output, alphabetic
    reviewer labels from `pairs/alpha/uppercase`, and reviewer-safe body text.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,573 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## PlainText Template Object Loop And Partial Depth Slice

- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-plain-template-object-loop-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-plain-template-object-loop-handoff.php`
  - Result: emitted a WordPress object-loop reviewer packet with nested reviewer
    names/queues and PlainText-rendered body output without the source admin URL.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,626 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## PlainText Table Cell/Caption Fallback Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown.hs`
  - `src/Text/Pandoc/Writers/Markdown/Inline.hs`
  - `src/Text/Pandoc/Writers/Markdown/Table.hs`
  - `src/Text/Pandoc/Extensions.hs`
  - Revalidated static inventory with `git ls-tree`: 2,276 upstream
    test/data/benchmark artifacts, including 1,974 `test/`, 54
    `pandoc-lua-engine/test/`, 247 `data/`, and 1 `benchmark/` artifacts.
  - Inspected the PlainText table caption/cell path: captions and cells render
    through `inlineListToMarkdown` under `PlainText`, while attrs still use
    `attrsToMarkdown`.
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-plain-table-fallback-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-plain-table-fallback-handoff.php`
  - Result: emitted a plain paragraph, `[TABLE]`, and
    `: Migration source edit for wp_posts {.wp-review source="batch-42"}` with
    no Markdown link or code syntax.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,501 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## Markdown Writer Pipe Default-Width Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown/Table.hs`
  - Revalidated static inventory with `git ls-tree`: 2,276 upstream
    test/data/benchmark artifacts, including 1,974 `test/`, 54
    `pandoc-lua-engine/test/`, 247 `data/`, and 1 `benchmark` artifact.
  - Inspected the 41-line `pipeTable` source boundary where `pipeWidths`
    checks `not (all (== 0) widths)` and maps the original positional
    `widths` list into delimiter widths instead of compacting default-width
    columns.
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-pipe-default-width-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-pipe-default-width-handoff.php`
  - Result: emitted a narrow pipe table where the first default-width column
    kept a minimal `--` delimiter while the second and third columns kept their
    25 percent and 75 percent relative delimiter widths.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,444 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## Markdown Writer Figure Fallback Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown.hs`
  - `src/Text/Pandoc/Shared.hs`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php`
  - Result: emitted an attributed `<figure>` with `data-source`, image
    title/alt metadata, and `figcaption` fallback output.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,324 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## Markdown Writer Grid-Table Branch Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown.hs`
  - `src/Text/Pandoc/Writers/Markdown/Table.hs`
  - `src/Text/Pandoc/Writers/Shared.hs`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-grid-table-handoff.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php`
- Focused examples passed:
  - `php lanes/pandoc/examples/wordpress-markdown-grid-table-handoff.php`
  - Result: emitted a Pandoc-style grid table with block-level cell content,
    footer totals, width-driven columns, alignment markers, and caption/source
    attrs.
  - `php lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php`
  - Result: still emitted a source-review raw HTML table for a spanned-table
    fallback.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,339 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## Markdown Writer Multiline-Table Branch Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown.hs`
  - `src/Text/Pandoc/Writers/Markdown/Table.hs`
  - `test/tables.markdown`
  - `test/tables.native`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-multiline-table-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-multiline-table-handoff.php`
  - Result: emitted a Pandoc-style multiline table with wrapped WordPress
    reviewer notes, width-derived columns, caption attrs, and no raw HTML
    fallback.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,352 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## Markdown Writer SoftBreak Wrap-Option Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown/Inline.hs`
  - `src/Text/Pandoc/Writers/Markdown.hs`
  - `src/Text/Pandoc/Options.hs`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-wrap-preserve-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-wrap-preserve-handoff.php`
  - Result: emitted a setext-heading reviewer handoff with paragraph source
    line boundaries preserved under `wrap-preserve`.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,396 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## Markdown Writer DefinitionList Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown.hs`
  - `test/testsuite.txt`
  - `test/testsuite.native`
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-definition-list-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-definition-list-handoff.php`
  - Result: emitted a Pandoc-style WordPress import glossary/checklist with
    repeated definition markers, a loose nested shortcode snippet body, and
    source attrs.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,409 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## Markdown Writer Adjacent Same-Type List Separator Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown.hs`
  - Revalidated static inventory with `git ls-tree`: 2,276 upstream
    test/data/benchmark artifacts, including 1,974 `test/`, 54
    `pandoc-lua-engine/test/`, 247 `data/`, and 1 `benchmark/` artifacts.
  - Counted the 53-line `blockListToMarkdown` `fixBlocks` source boundary,
    including three same-type list separator guards for `BulletList`,
    `OrderedList`, and `DefinitionList`.
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-adjacent-list-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-adjacent-list-handoff.php`
  - Result: emitted separate WordPress reviewer bullet and ordered queues with
    Pandoc neutral `<!-- -->` separators between same-type list blocks.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,415 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## Markdown Writer Raw/Plain FixBlocks Boundary Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown.hs`
  - Revalidated static inventory with `git ls-tree`: 2,276 upstream
    test/data/benchmark artifacts, including 1,974 `test/`, 54
    `pandoc-lua-engine/test/`, 247 `data/`, and 1 `benchmark/` artifacts.
  - Counted the 42-line `blockListToMarkdown` `fixBlocks` source boundary,
    including 15 Plain/RawBlock/Div/comment/code guard mentions.
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-raw-boundary-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-raw-boundary-handoff.php`
  - Result: emitted adjacent plain/raw/plain WordPress reviewer handoff content
    and kept the following Markdown heading separated.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,419 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## Markdown Writer PandocTable Display-Width NumChars Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown/Table.hs`
  - Revalidated static inventory with `git ls-tree`: 2,276 upstream
    test/data/benchmark artifacts, including 1,974 `test/`, 54
    `pandoc-lua-engine/test/`, 247 `data/`, and 1 `benchmark/` artifacts.
  - Inspected the `pandocTable` `numChars`/`minNumChars` source boundary,
    including widthless simple-table `offset + 2`, width-bearing `WrapAuto`
    `minOffset`, and no-wrap `offset` behavior.
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-unicode-table-width-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-unicode-table-width-handoff.php`
  - Result: emitted a native Pandoc simple table with CJK source labels and
    zero-width import tokens aligned by display width.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,436 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## Markdown Writer Pipe Caption-Wrap Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown.hs`
  - `src/Text/Pandoc/Options.hs`
  - `src/Text/Pandoc/Writers/Markdown/Table.hs`
  - Revalidated static inventory with `git ls-tree`: 2,276 upstream
    test/data/benchmark artifacts, including 1,974 `test/`, 54
    `pandoc-lua-engine/test/`, 247 `data/`, and 1 `benchmark/` artifacts.
  - Inspected `pandocToMarkdown` `WrapAuto` render-width setup, table
    `caption'''` rendering, and simple/pipe/multiline/grid caption placement.
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-pipe-caption-wrap-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-pipe-caption-wrap-handoff.php`
  - Result: emitted a narrow WordPress reviewer pipe table with a wrapped
    caption and retained caption attribute tuple.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,450 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## Markdown Writer Disabled Table-Caption Marker Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown.hs`
  - `src/Text/Pandoc/Writers/Markdown/Table.hs`
  - `src/Text/Pandoc/Extensions.hs`
  - `src/Text/Pandoc/Options.hs`
  - Revalidated static inventory with `git ls-tree`: 2,276 upstream
    test/data/benchmark artifacts, including 1,974 `test/`, 54
    `pandoc-lua-engine/test/`, 247 `data/`, and 1 `benchmark/` artifacts.
  - Inspected the `caption'''` branch where disabled `Ext_table_captions`
    drops only the Pandoc `: ` marker while preserving caption text and attrs,
    plus the CommonMark extension defaults where table captions are absent.
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-commonmark-caption-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-commonmark-caption-handoff.php`
  - Result: emitted a CommonMark-flavored WordPress reviewer pipe table whose
    caption text remained visible without a Pandoc colon marker and retained
    caption attrs.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,456 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## Markdown Writer CommonMark Raw Inline And Linebreak Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown/Inline.hs`
  - `src/Text/Pandoc/Writers/Markdown.hs`
  - `src/Text/Pandoc/Extensions.hs`
  - Revalidated static inventory with `git ls-tree`: 2,276 upstream
    test/data/benchmark artifacts, including 1,974 `test/`, 54
    `pandoc-lua-engine/test/`, 247 `data/`, and 1 `benchmark/` artifacts.
  - Inspected the `Commonmark` `RawInline` branch and `LineBreak` branch,
    including the CommonMark raw-format allow list, raw HTML/raw TeX/raw
    attribute fallbacks, and the forced backslash hard-break behavior.
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-commonmark-raw-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-commonmark-raw-handoff.php`
  - Result: emitted raw CommonMark/HTML reviewer spans and a backslash hard
    break despite `escapedLineBreaks=false`.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,465 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## Markdown Writer LineBlock Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown.hs`
  - `src/Text/Pandoc/Shared.hs`
  - `src/Text/Pandoc/Extensions.hs`
  - Revalidated static inventory with `git ls-tree`: 2,276 upstream
    test/data/benchmark artifacts, including 1,974 `test/`, 54
    `pandoc-lua-engine/test/`, 247 `data/`, and 1 `benchmark/` artifacts.
  - Inspected the `LineBlock` branch, its `Ext_line_blocks` gate, the
    `linesToPara` fallback, and CommonMark/default-extension behavior.
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-markdown-line-block-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-markdown-line-block-handoff.php`
  - Result: emitted a pipe-prefixed WordPress reviewer stanza with nonbreaking
    source indentation and an empty line entry.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,479 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## PlainText Block Writer Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown.hs`
  - Revalidated static inventory with `git ls-tree`: 2,276 upstream
    test/data/benchmark artifacts, including 1,974 `test/`, 54
    `pandoc-lua-engine/test/`, 247 `data/`, and 1 `benchmark/` artifacts.
  - Inspected the PlainText branches for `Plain`, `RawBlock`,
    `HorizontalRule`, `Header`, `BlockQuote`, and same-type list separators.
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-plain-review-blocks-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-plain-review-blocks-handoff.php`
  - Result: emitted unmarked plain headings, source paragraph label text
    without Markdown link markup, a two-space-indented quote note, a literal
    plain raw review packet, and a writer-column dash separator.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,482 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## PlainText Image And Note Writer Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown/Inline.hs`
  - `src/Text/Pandoc/Writers/Markdown.hs`
  - `test/writer.plain`
  - Revalidated static inventory with `git ls-tree`: 2,276 upstream
    test/data/benchmark artifacts, including 1,974 `test/`, 54
    `pandoc-lua-engine/test/`, 247 `data/`, and 1 `benchmark/` artifacts.
  - Inspected the PlainText `Image`, `Link`, `Note`, note-definition, and
    implicit-figure branches: images render as bracketed plain link text,
    source-equal image labels collapse to `[]`, note references use numeric
    bracket labels, and note definitions avoid Markdown `[^n]:` syntax.
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-plain-media-note-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-plain-media-note-handoff.php`
  - Result: emitted an unmarked plain heading, bracketed image caption,
    numeric note reference, stripped source edit link text, and an indented
    code note body.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,488 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## PlainText Gutenberg Inline Writer Slice

- Targeted upstream evidence inspected:
  - `test/Tests/Writers/Plain.hs`
  - `src/Text/Pandoc/Writers/Markdown/Inline.hs`
  - `src/Text/Pandoc/Shared.hs`
  - Revalidated static inventory with `git ls-tree`: 2,276 upstream
    test/data/benchmark artifacts, including 1,974 `test/`, 54
    `pandoc-lua-engine/test/`, 247 `data/`, and 1 `benchmark/` artifacts.
  - Inspected the `writePlain` Gutenberg strong-uppercase test plus the
    PlainText `Emph`/`Strong` branches and `capitalize`.
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-plain-gutenberg-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-plain-gutenberg-handoff.php`
  - Result: emitted an unmarked plain heading, uppercase `MEDIA PRÜFUNG` and
    `SOURCE EDITOR VIA` text, preserved `wp_update_post`, and underscore
    emphasis.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,493 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## PlainText Template Titleblock Writer Slice

- Targeted upstream evidence inspected:
  - `src/Text/Pandoc/Writers/Markdown.hs`
  - `data/templates/default.plain`
  - `test/writer.plain`
  - Revalidated static inventory with `git ls-tree`: 2,276 upstream
    test/data/benchmark artifacts, including 1,974 `test/`, 54
    `pandoc-lua-engine/test/`, 247 `data/`, and 1 `benchmark/` artifact.
  - Inspected the `pandocToMarkdown` titleblock construction, the
    `plainTitleBlock` helper, and the default plain template placement before
    body output.
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-plain-titleblock-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-plain-titleblock-handoff.php`
  - Result: emitted a plain title/author/date metadata header, then body text,
    while stripping Markdown link/code syntax and admin URLs.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,507 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.

## PlainText Custom Template Nesting Slice

- Targeted upstream evidence inspected:
  - `MANUAL.txt` Template syntax section for `$^$` nesting and automatic
    nesting of multiline variables alone on indented lines.
  - `src/Text/Pandoc/Templates.hs`, confirming template rendering is delegated
    to `doctemplates`.
  - Revalidated static inventory remains 2,276 upstream test/data/benchmark
    artifacts, including 1,974 `test/`, 54 `pandoc-lua-engine/test/`, 247
    `data/`, and 1 `benchmark/` artifact.
- Focused lane lint passed:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-plain-template-nesting-handoff.php`
- Focused example passed:
  - `php lanes/pandoc/examples/wordpress-plain-template-nesting-handoff.php`
  - Result: emitted a nested multiline review description, aligned owner
    continuation, automatically nested summary variable, nested legal-hold
    partial, and PlainText-rendered body text.
- Focused lane tests passed:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: 1 test file, 2,593 assertions, 0 failures
- Root harness left pending:
  - This lane was not assigned no-argument root verification. Per lane
    instructions, no root harness was started and focused pandoc evidence was
    recorded for supervisor/integrator handoff.
