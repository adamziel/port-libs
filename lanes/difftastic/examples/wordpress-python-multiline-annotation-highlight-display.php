<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\AnsiSyntaxHighlighter;

$source = "from __future__ import annotations\n"
    . "import typing\n"
    . "import typing_extensions\n"
    . "from typing import Optional, TypeAlias\n"
    . "\n"
    . "Payload: TypeAlias = \"dict[str, list[int]]\"\n"
    . "FuturePayload: typing_extensions.TypeAlias = \"typing.Optional[Payload]\"\n"
    . "label = \"list\"\n"
    . "\n"
    . "def normalize_posts(\n"
    . "    posts: list[\n"
    . "        dict[str | bytes, int | list[str]],\n"
    . "    ],\n"
    . ") -> tuple[\n"
    . "    int,\n"
    . "    list[str],\n"
    . "]:\n"
    . "    parent: Optional[Payload] = None\n"
    . "    future_parent: typing.Optional[Payload] = None\n"
    . "    quoted_parent: list[\"Payload\"] = []\n"
    . "    quoted_future_parent: typing.Optional[\"Payload\"] = None\n"
    . "    encoded: \"dict[str, list[int]]\" = {}\n"
    . "    list = []\n"
    . "    label = \"Payload\"\n"
    . "    return (len(posts), list)\n";

$highlighter = new AnsiSyntaxHighlighter();
$lines = [];
$offset = 0;
foreach (explode("\n", rtrim($source, "\n")) as $line) {
    $lines[] = $highlighter->highlightLine($line, 8, [
        'language' => 'python',
        'source' => $source,
        'lineStartOffset' => $offset,
    ]);
    $offset += strlen($line) + 1;
}

echo json_encode([
    'path' => 'wp-content/plugins/acme-migrator/tools/normalize_posts.py',
    'language' => 'Python',
    'lines' => $lines,
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
