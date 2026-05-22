<?php

declare(strict_types=1);

return [
    'metadata' => [
        'base' => "title: Demo\nslug: demo\nstatus: draft\n",
        'ours' => "title: Demo Import\nslug: demo\nstatus: draft\n",
        'theirs' => "title: Demo\nslug: demo\nstatus: publish\n",
        'expected' => "title: Demo Import\nslug: demo\nstatus: publish\n",
    ],
    'theme' => [
        'base' => "{\n  \"version\": 2,\n  \"settings\": {\n    \"color\": \"base\"\n  }\n}\n",
        'ours' => "{\n  \"version\": 2,\n  \"settings\": {\n    \"color\": \"blue\"\n  }\n}\n",
        'theirs' => "{\n  \"version\": 2,\n  \"settings\": {\n    \"color\": \"green\"\n  }\n}\n",
    ],
];
