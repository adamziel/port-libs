<?php

declare(strict_types=1);

return [
    'metadata' => [
        'base' => "title: Demo\nslug: demo\nstatus: draft\n",
        'ours' => "title: Demo Import\nslug: demo\nstatus: draft\n",
        'theirs' => "title: Demo\nslug: demo\nstatus: publish\n",
        'expected' => "title: Demo Import\nslug: demo\nstatus: publish\n",
    ],
    'themeDecision' => [
        'base' => "{\"version\":2,\"settings\":{\"layout\":\"content\"}}\n",
        'ours' => "{\"version\":2,\"settings\":{\"layout\":\"wide\"}}\n",
        'theirs' => "{\"version\":2,\"settings\":{\"layout\":\"boxed\"}}\n",
    ],
    'blockNotes' => [
        'base' => 'stabilize/navigation',
        'ours' => 'stabilize/navigation + wp_search',
        'theirs' => 'stabilize/navigation + site_logo',
        'expectedUnion' => "stabilize/navigation + wp_search\nstabilize/navigation + site_logo",
    ],
    'theme' => [
        'base' => "{\n  \"version\": 2,\n  \"settings\": {\n    \"color\": \"base\"\n  }\n}\n",
        'ours' => "{\n  \"version\": 2,\n  \"settings\": {\n    \"color\": \"blue\"\n  }\n}\n",
        'theirs' => "{\n  \"version\": 2,\n  \"settings\": {\n    \"color\": \"green\"\n  }\n}\n",
    ],
];
