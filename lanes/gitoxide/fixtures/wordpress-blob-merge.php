<?php

declare(strict_types=1);

return [
    'gitConfigConflictStyle' => 'zdiff3',
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
    'themeSharedDecision' => [
        'base' => "layout: content\ncolor: base\nspacing: normal\n",
        'ours' => "layout: wide\ncolor: blue\nspacing: fluid\n",
        'theirs' => "layout: wide\ncolor: green\nspacing: fluid\n",
        'expectedZealousDiff3' => "layout: wide\n<<<<<<< ours/theme.json\ncolor: blue\n||||||| base/theme.json\nlayout: content\ncolor: base\nspacing: normal\n=======\ncolor: green\n>>>>>>> theirs/theme.json\nspacing: fluid\n",
    ],
    'spacingAmbiguity' => [
        'base' => "<!-- wp:heading {\"level\":1} -->\n\n<!-- wp:separator -->\n<!-- wp:paragraph -->\n\n",
        'ours' => "\n\n<!-- wp:separator -->\n<!-- wp:paragraph -->\n",
        'theirs' => "<!-- wp:heading {\"level\":1} -->\n\n<!-- wp:paragraph -->\n\n",
        'expected' => "\n\n<!-- wp:paragraph -->\n",
    ],
    'mixedLineEndings' => [
        'base' => "<!-- wp:paragraph -->\r\n<!-- wp:group -->\r\n<!-- wp:separator -->",
        'ours' => "<!-- wp:paragraph -->\r\n<!-- wp:group -->\n<!-- wp:heading -->",
        'theirs' => "<!-- wp:paragraph -->\r\n<!-- wp:group -->\n<!-- wp:spacer -->",
        'expected' => "<!-- wp:paragraph -->\r\n<!-- wp:group -->\n<<<<<<< ours/post.html\n<!-- wp:heading -->\n=======\n<!-- wp:spacer -->\n>>>>>>> theirs/post.html\n",
    ],
    'sharedBlockRefactor' => [
        'base' => "<!-- wp:group -->\n<!-- wp:paragraph -->\nBase copy\n<!-- /wp:paragraph -->\n<!-- /wp:group -->\n",
        'ours' => "<!-- wp:group {\"className\":\"featured\"} -->\n<!-- wp:heading -->\nShared headline\n<!-- /wp:heading -->\n<!-- /wp:group -->\n",
        'theirs' => "<!-- wp:group {\"className\":\"featured\"} -->\n<!-- wp:paragraph -->\nShared headline\n<!-- /wp:heading -->\n<!-- /wp:group -->\n",
        'expected' => "<!-- wp:group {\"className\":\"featured\"} -->\n<<<<<<< ours/post.html\n<!-- wp:heading -->\n=======\n<!-- wp:paragraph -->\n>>>>>>> theirs/post.html\nShared headline\n<!-- /wp:heading -->\n<!-- /wp:group -->\n",
    ],
    'anonymousPreview' => [
        'base' => "<!-- wp:paragraph -->\nBase copy\n<!-- /wp:paragraph -->\n",
        'ours' => "<!-- wp:paragraph -->\nLocal editor copy\n<!-- /wp:paragraph -->\n",
        'theirs' => "<!-- wp:paragraph -->\nRemote editor copy\n<!-- /wp:paragraph -->\n",
        'expectedDiff3' => "<!-- wp:paragraph -->\n<<<<<<<\nLocal editor copy\n|||||||\nBase copy\n=======\nRemote editor copy\n>>>>>>>\n<!-- /wp:paragraph -->\n",
    ],
    'theme' => [
        'base' => "{\n  \"version\": 2,\n  \"settings\": {\n    \"color\": \"base\"\n  }\n}\n",
        'ours' => "{\n  \"version\": 2,\n  \"settings\": {\n    \"color\": \"blue\"\n  }\n}\n",
        'theirs' => "{\n  \"version\": 2,\n  \"settings\": {\n    \"color\": \"green\"\n  }\n}\n",
    ],
];
