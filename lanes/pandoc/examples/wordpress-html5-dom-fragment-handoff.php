<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\Html5Dom;

$source = <<<'HTML'
<article id="legacy-post-42" data-source="html-export">
  <h1>Imported source packet</h1>
  <p>AT&amp;T &lt;review&gt; text<br>keeps its line break.</p>
  <figure><img src="cover.png" alt="Cover"><figcaption>Cover image</figcaption></figure>
</article>
HTML;

$body = Html5Dom::parseHtmlFragment($source);
$article = Html5Dom::firstChildElement($body, 'article');
if (!$article instanceof DOMElement) {
    throw new RuntimeException('Expected article fragment root');
}

$normalized = Html5Dom::serializeHtmlChildren($body);

if (($argv[1] ?? '') === '--self-test') {
    if (Html5Dom::normalizedText($article) !== 'Imported source packet AT&T <review> text keeps its line break. Cover image') {
        throw new RuntimeException('HTML5 DOM fragment self-test missing normalized reviewer text');
    }
    if ((Html5Dom::attributes($article)['data-source'] ?? '') !== 'html-export') {
        throw new RuntimeException('HTML5 DOM fragment self-test missing source attribute');
    }
    if (!str_contains($normalized, '<br>keeps its line break.')) {
        throw new RuntimeException('HTML5 DOM fragment self-test missing serialized line break');
    }

    echo "html5 dom fragment handoff self-test ok\n";
    exit(0);
}

echo "HTML5 DOM fragment handoff for WordPress review:\n";
echo 'text=' . Html5Dom::normalizedText($article) . "\n";
echo 'attrs=' . json_encode(Html5Dom::attributes($article), JSON_UNESCAPED_SLASHES) . "\n";
echo "html:\n" . $normalized . "\n";
