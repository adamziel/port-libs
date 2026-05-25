<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$source = <<<'HTML'
<html>
<head>
  <title>Captioned Embed Wrapper Import</title>
</head>
<body>
  <article>
    <h1>Captioned Embed Wrapper Import</h1>
    <p>Legacy WordPress migrations sometimes retain provider embeds inside publisher div wrappers with short captions.</p>
    <div class="video-embed caption">
      <iframe src="https://www.youtube.com/embed/wrapped-session"></iframe>
      <p>Archived session video.</p>
    </div>
    <section class="wp-block-embed provider-card">
      <div class="embed-responsive">
        <iframe src="https://www.youtube.com/embed/nested-session"></iframe>
      </div>
      <p>Nested provider session.</p>
    </section>
    <p>Inline context <iframe src="https://www.youtube.com/embed/inline-context"></iframe> should stay paragraph content.</p>
    <p>The wrapper and caption should stay together for manual review instead of being paragraph-wrapped.</p>
  </article>
</body>
</html>
HTML;

$extractor = new ArticleExtractor();
$article = $extractor->extract($source, 'https://example.test/posts/captioned-embed.html');
$blocks = $extractor->toWordPressBlocks($article);

echo 'Captioned embed HTML blocks: ' . substr_count($blocks, '<!-- wp:html -->') . PHP_EOL;
echo 'Embed wrapper retained: ' . (str_contains($blocks, '<div><iframe src="https://www.youtube.com/embed/wrapped-session"></iframe><p>Archived session video.</p></div>') ? 'yes' : 'no') . PHP_EOL;
echo 'Nested wrapper retained: ' . (str_contains($blocks, '<!-- wp:html -->' . "\n" . '<section>')
    && str_contains($blocks, 'https://www.youtube.com/embed/nested-session')
    && str_contains($blocks, 'Nested provider session.') ? 'yes' : 'no') . PHP_EOL;
echo 'Inline embed paragraph retained: ' . (str_contains($blocks, '<p>Inline context <iframe src="https://www.youtube.com/embed/inline-context"></iframe> should stay paragraph content.</p>') ? 'yes' : 'no') . PHP_EOL;
echo 'Paragraph-wrapped embed wrapper: ' . (str_contains($blocks, "<!-- wp:paragraph -->\n<div><iframe") ? 'yes' : 'no') . PHP_EOL;
echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . PHP_EOL;
