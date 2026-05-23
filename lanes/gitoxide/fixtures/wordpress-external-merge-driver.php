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
    'wordpressUse' => 'A PHP deployment tool can prepare a configured WordPress JSON merge driver command and tempfiles for review without executing the external driver in shared-hosting or Playground contexts.',
];
