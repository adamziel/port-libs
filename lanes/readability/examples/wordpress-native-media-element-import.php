<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <title>Native Media Import - Legacy Publisher</title>
</head>
<body>
  <article>
    <h1>Native Media Import</h1>
    <p>Legacy WordPress migrations sometimes contain native video and audio elements instead of iframe embeds.</p>
    <video controls poster="/media/poster.jpg">
      <source src="/media/session.mp4" type="video/mp4">
    </video>
    <figure>
      <iframe src="https://www.youtube.com/embed/archive-session"></iframe>
      <figcaption>Archived conference video.</figcaption>
    </figure>
    <audio controls src="/media/interview.mp3"></audio>
    <p>The retained media stays reviewable as HTML blocks before the importer decides whether to convert it.</p>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'https://example.test/posts/native-media.html');
$blocks = $extractor->toWordPressBlocks($article);

echo 'HTML media blocks: ' . substr_count($blocks, '<!-- wp:html -->') . "\n";
echo 'Video retained: ' . (str_contains($blocks, 'https://example.test/media/session.mp4') ? 'yes' : 'no') . "\n";
echo 'Media figure retained: ' . (str_contains($blocks, 'https://www.youtube.com/embed/archive-session') ? 'yes' : 'no') . "\n";
echo 'Audio retained: ' . (str_contains($blocks, 'https://example.test/media/interview.mp3') ? 'yes' : 'no') . "\n";
echo 'Paragraph-wrapped media: ' . (
    str_contains($blocks, "<!-- wp:paragraph -->\n<video")
    || str_contains($blocks, "<!-- wp:paragraph -->\n<figure><iframe")
    || str_contains($blocks, "<!-- wp:paragraph -->\n<audio")
        ? 'yes'
        : 'no'
) . "\n";
