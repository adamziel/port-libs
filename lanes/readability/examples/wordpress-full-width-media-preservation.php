<?php

declare(strict_types=1);

use PortLibs\Readability\ArticleExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$html = <<<'HTML'
<html>
    <head>
        <meta property="og:title" content="Editorial Media Import">
    </head>
    <body>
        <article>
            <h1>Editorial Media Import</h1>
            <p>Migration reviewers need real editorial copy around imported media before cleanup decisions are made. This paragraph keeps the article substantial enough to select.</p>
            <div class="legacy-media-shell">
                <figure class="graf--figure postField--fillWidthImage">
                    <div><img src="/uploads/editorial-full-width.jpg" alt="Editorial lab photo"></div>
                    <figcaption>Editorial lab photo by source author</figcaption>
                </figure>
            </div>
            <p>The source page can also include layout crops that should not become WordPress media blocks. The native cleanup keeps the editorial figure and drops decorative wrappers.</p>
            <div class="theme-wide-crop">
                <figure>
                    <img src="/uploads/decorative-crop.jpg" alt="Decorative crop">
                    <figcaption>Decorative crop</figcaption>
                </figure>
            </div>
        </article>
    </body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($html);

echo "Title: {$article->title}\n\n";
echo $extractor->toWordPressBlocks($article) . "\n";
