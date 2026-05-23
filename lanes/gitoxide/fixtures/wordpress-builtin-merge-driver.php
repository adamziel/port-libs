<?php

declare(strict_types=1);

use PortLibs\Gitoxide\BuiltinDriver;

return [
    'blockNotes' => [
        'state' => BuiltinDriver::ATTRIBUTE_VALUE,
        'value' => BuiltinDriver::UNION,
        'base' => "stabilize/navigation\n",
        'ours' => "stabilize/navigation\nreview/block-bindings\n",
        'theirs' => "stabilize/navigation\nreview/site-logo\n",
        'expected' => "stabilize/navigation\nreview/block-bindings\nreview/site-logo\n",
    ],
    'media' => [
        'state' => BuiltinDriver::ATTRIBUTE_UNSET,
        'base' => "jpeg-base\0",
        'ours' => "jpeg-local\0",
        'theirs' => "jpeg-remote\0",
    ],
    'mediaAutoDetected' => [
        'state' => BuiltinDriver::ATTRIBUTE_SET,
        'base' => "avif-base\0",
        'ours' => "avif-local\0",
        'theirs' => "avif-remote\0",
    ],
    'themeJson' => [
        'state' => BuiltinDriver::ATTRIBUTE_SET,
        'markerSize' => '9',
        'base' => "{\"settings\":{\"layout\":\"base\"}}\n",
        'ours' => "{\"settings\":{\"layout\":\"wide\"}}\n",
        'theirs' => "{\"settings\":{\"layout\":\"boxed\"}}\n",
        'expected' => "<<<<<<<<< ours/theme.json\n{\"settings\":{\"layout\":\"wide\"}}\n=========\n{\"settings\":{\"layout\":\"boxed\"}}\n>>>>>>>>> theirs/theme.json\n",
    ],
    'unknownExternal' => [
        'state' => BuiltinDriver::ATTRIBUTE_VALUE,
        'value' => 'wordpress-json-normalizer',
    ],
];
