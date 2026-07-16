<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$packet = <<<'JSON'
{
  "pandoc-api-version": [1, 23, 1],
  "meta": {
    "title": {"t":"MetaInlines","c":[{"t":"Str","c":"Imported"},{"t":"Space"},{"t":"Str","c":"JSON"}]},
    "source": {"t":"MetaString","c":"batch-42"}
  },
  "blocks": [
    {"t":"Header","c":[2,["json-review",["wp-import"],[["data-source","json-filter"]]], [{"t":"Str","c":"JSON"},{"t":"Space"},{"t":"Str","c":"Review"}]]},
    {"t":"Para","c":[
      {"t":"Str","c":"Review"},
      {"t":"Space"},
      {"t":"Link","c":[["",["source-link"],[["data-source","archive"]]], [{"t":"Str","c":"source packet"}], ["https://example.test/import packets/source one(archived).html", "Source packet"]]},
      {"t":"Space"},
      {"t":"Code","c":[["",["php"],[["data-source","json-filter"]]],"wp_insert_post"]},
      {"t":"Space"},
      {"t":"Note","c":[{"t":"Para","c":[{"t":"Str","c":"Keep"},{"t":"Space"},{"t":"Str","c":"the"},{"t":"Space"},{"t":"Str","c":"archive"},{"t":"Space"},{"t":"Str","c":"URL"}]}]}
    ]}
  ]
}
JSON;

$reader = new PandocJsonReader();
$document = $reader->read($packet);
$filtered = new AstNode('document', $document->attrs, [
    ...$document->children,
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Native PHP JSON filter added this WordPress review marker.']),
    ]),
]);

$blocks = (new WordPressBlockWriter())->write($filtered);
$json = (new PandocJsonWriter())->write($filtered);

if (in_array('--self-test', $argv, true)) {
    if (
        !str_contains($blocks, '<h2 id="json-review" class="wp-import">JSON Review</h2>')
        || !str_contains($blocks, 'Native PHP JSON filter added this WordPress review marker.')
        || !str_contains($json, '"pandoc-api-version"')
        || !str_contains($json, '"MetaInlines"')
    ) {
        fwrite(STDERR, "pandoc json native handoff failed\n");
        exit(1);
    }

    echo "pandoc json native handoff ok\n";
    exit(0);
}

echo $blocks . "\n\n";
echo $json;
