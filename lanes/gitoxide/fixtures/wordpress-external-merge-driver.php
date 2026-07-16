<?php

declare(strict_types=1);

use PortLibs\Gitoxide\ExternalMergeDriver;

return [
    'drivers' => [
        new ExternalMergeDriver(
            'wordpress-json-normalizer',
            'wp-merge-json --base=%O --ours=%A --theirs=%B --marker=%L --path=%P --labels=%S:%X:%Y --unknown=%F',
        ),
    ],
    'attributeValue' => 'wordpress-json-normalizer',
    'relativePath' => 'wp-content/themes/acme/theme.json',
    'ancestorLabel' => 'base/theme.json',
    'currentLabel' => 'ours/theme.json',
    'otherLabel' => 'theirs/theme.json',
    'markerSize' => 11,
    'ancestor' => "{\"settings\":{\"layout\":\"base\"}}\n",
    'current' => "{\"settings\":{\"layout\":\"wide\"}}\n",
    'other' => "{\"settings\":{\"layout\":\"boxed\"}}\n",
    'expectedMerged' => "{\"settings\":{\"layout\":\"wide\",\"contentSize\":\"840px\"}}\n",
    'deletedBase' => [
        'ancestor' => null,
        'current' => "{\"version\":2,\"settings\":{}}\n",
        'other' => "{\"version\":2,\"settings\":{\"layout\":{\"contentSize\":\"840px\"}}}\n",
    ],
    'tooLargeMedia' => [
        'ancestor' => "avif-base\n",
        'current' => "avif-current\n",
        'other' => "oversized-avif-binary-placeholder",
        'threshold' => 13,
    ],
    'wordpressUse' => 'A PHP deployment tool can prepare a configured WordPress JSON merge driver command, treat deleted theme.json bases as empty driver tempfiles, reject too-large media-like resources before any approved runner is called, let a caller-injected approved runner overwrite the current tempfile, and read the merged theme.json buffer back without invoking Git or launching a shell.',
];
