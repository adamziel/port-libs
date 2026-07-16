<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html inline code language provenance for reviewer handoff' => static function (TestRunner $t): void {
        $inlineText = 'SELECT <id>';
        $dataLanguageText = "graph LR\nA-->B";
        $blockText = "echo 1;\n";
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p>Use <code id="query" class="language-SQL reviewer-token">' . htmlspecialchars($inlineText, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</code>'
                . ' and <code id="diagram" data-language="Mermaid">' . htmlspecialchars($dataLanguageText, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</code>'
                . ' plus <code id="plain" class="review-token">plain</code>.</p>'
                . '<pre id="snippet"><code class="language-php">' . htmlspecialchars($blockText, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</code></pre>',
            'inline code language review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/inline-code-language-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $summary[0];
        $query = $paragraph['children'][1];
        $diagram = $paragraph['children'][3];
        $plain = $paragraph['children'][5];
        $pre = $summary[1];
        $blockCode = $pre['children'][0];

        $t->same('code', $query['name']);
        $t->same('html-code-language-provenance-review', $query['codeElementReviewPolicy']);
        $t->same('inline-code', $query['codeContext']);
        $t->same(false, $query['codeInPreformattedBlock']);
        $t->same($inlineText, $query['codeTextRaw']);
        $t->same($inlineText, $query['codeText']);
        $t->same(strlen($inlineText), $query['codeTextLength']);
        $t->same(hash('sha256', $inlineText), $query['codeTextSha256']);
        $t->same(1, $query['codeLineCount']);
        $t->same(false, $query['codeContainsNewline']);
        $t->same('sql', $query['codeLanguage']);
        $t->same('class-token', $query['codeLanguageSource']);
        $t->same('language-SQL', $query['codeLanguageToken']);
        $t->same(['language-SQL', 'reviewer-token'], $query['codeClassTokens']);

        $t->same('diagram', $diagram['elementId']);
        $t->same($dataLanguageText, $diagram['codeTextRaw']);
        $t->same('graph LR A-->B', $diagram['codeText']);
        $t->same(2, $diagram['codeLineCount']);
        $t->same(true, $diagram['codeContainsNewline']);
        $t->same('mermaid', $diagram['codeLanguage']);
        $t->same('data-language', $diagram['codeLanguageSource']);
        $t->same(null, $diagram['codeLanguageToken']);
        $t->same([], $diagram['codeClassTokens']);

        $t->same('plain', $plain['elementId']);
        $t->same('missing', $plain['codeLanguageSource']);
        $t->same(null, $plain['codeLanguage']);
        $t->same(['review-token'], $plain['codeClassTokens']);

        $t->same('preformatted-code', $blockCode['codeContext']);
        $t->same(true, $blockCode['codeInPreformattedBlock']);
        $t->same($blockText, $blockCode['codeTextRaw']);
        $t->same(1, $blockCode['codeLineCount']);
        $t->same(true, $blockCode['codeContainsNewline']);
        $t->same('php', $blockCode['codeLanguage']);
        $t->same('php', $pre['codeLanguage']);

        $t->same(
            '<p>Use <code class="language-SQL reviewer-token" id="query">SELECT &lt;id&gt;</code>'
                . ' and <code data-language="Mermaid" id="diagram">graph LR' . "\n" . 'A--&gt;B</code>'
                . ' plus <code class="review-token" id="plain">plain</code>.</p>'
                . '<pre id="snippet"><code class="language-php">echo 1;' . "\n" . '</code></pre>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/inline-code-language-review.html', $document->children[0]->attr('part'));
    },
];
