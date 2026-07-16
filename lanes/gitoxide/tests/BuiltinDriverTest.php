<?php

declare(strict_types=1);

use PortLibs\Gitoxide\BlobMerge;
use PortLibs\Gitoxide\BlobMergeResult;
use PortLibs\Gitoxide\BuiltinDriver;

return [
    'builtin driver names are ordered and case sensitive like upstream' => static function (TestRunner $t): void {
        $t->same([BuiltinDriver::TEXT, BuiltinDriver::BINARY, BuiltinDriver::UNION], BuiltinDriver::all());
        $t->same(BuiltinDriver::TEXT, BuiltinDriver::byName('text'));
        $t->same(BuiltinDriver::BINARY, BuiltinDriver::byName('binary'));
        $t->same(BuiltinDriver::UNION, BuiltinDriver::byName('union'));
        $t->same(null, BuiltinDriver::byName('Binary'));
        $t->same(null, BuiltinDriver::byName('merge'));
        $t->throws(\InvalidArgumentException::class, static fn () => BuiltinDriver::asString('Binary'));
    },
    'merge attribute driver selection follows gix platform builtin fallback' => static function (TestRunner $t): void {
        $t->same(BuiltinDriver::TEXT, BuiltinDriver::fromMergeAttribute(BuiltinDriver::ATTRIBUTE_SET));
        $t->same(BuiltinDriver::BINARY, BuiltinDriver::fromMergeAttribute(BuiltinDriver::ATTRIBUTE_UNSET));
        $t->same(BuiltinDriver::UNION, BuiltinDriver::fromMergeAttribute(BuiltinDriver::ATTRIBUTE_VALUE, 'union'));
        $t->same(BuiltinDriver::TEXT, BuiltinDriver::fromMergeAttribute(BuiltinDriver::ATTRIBUTE_VALUE, 'Binary'));
        $t->same(BuiltinDriver::TEXT, BuiltinDriver::fromMergeAttribute(BuiltinDriver::ATTRIBUTE_VALUE, 'custom-json-driver'));
        $t->same(BuiltinDriver::TEXT, BuiltinDriver::fromMergeAttribute(BuiltinDriver::ATTRIBUTE_UNSPECIFIED));
        $t->same(BuiltinDriver::BINARY, BuiltinDriver::fromMergeAttribute(BuiltinDriver::ATTRIBUTE_UNSPECIFIED, null, 'binary'));
        $t->same(BuiltinDriver::TEXT, BuiltinDriver::fromMergeAttribute(BuiltinDriver::ATTRIBUTE_UNSPECIFIED, null, 'Binary'));
        $t->throws(\InvalidArgumentException::class, static fn () => BuiltinDriver::fromMergeAttribute('bogus'));
    },
    'conflict marker size attributes parse as non-zero u8 values' => static function (TestRunner $t): void {
        $t->same(32, BuiltinDriver::markerSizeFromAttribute('32'));
        $t->same(7, BuiltinDriver::markerSizeFromAttribute('07'));
        $t->same(11, BuiltinDriver::markerSizeFromAttribute(null, 11));
        $t->same(11, BuiltinDriver::markerSizeFromAttribute('0', 11));
        $t->same(11, BuiltinDriver::markerSizeFromAttribute('256', 11));
        $t->same(11, BuiltinDriver::markerSizeFromAttribute(' 8', 11));
        $t->same(11, BuiltinDriver::markerSizeFromAttribute('8 ', 11));
    },
    'builtin driver wrapper applies text union and binary semantics' => static function (TestRunner $t): void {
        $text = BuiltinDriver::merge(
            BuiltinDriver::TEXT,
            "theme: base\n",
            "theme: ours\n",
            "theme: theirs\n",
            BlobMerge::STYLE_DIFF3,
            'ancestor',
            'ours',
            'theirs',
            3,
        );
        $union = BuiltinDriver::merge(BuiltinDriver::UNION, "base\n", "ours\n", "theirs\n");
        $binary = BuiltinDriver::merge(BuiltinDriver::BINARY, "base\0", "ours\0", "theirs\0");
        $binaryTheirs = BuiltinDriver::merge(
            BuiltinDriver::BINARY,
            "base\0",
            "ours\0",
            "theirs\0",
            BlobMerge::STYLE_MERGE,
            'base',
            'ours',
            'theirs',
            7,
            BlobMerge::PICK_THEIRS,
        );

        $t->same("<<< ours\ntheme: ours\n||| ancestor\ntheme: base\n===\ntheme: theirs\n>>> theirs\n", $text->content);
        $t->same(BlobMergeResult::RESOLUTION_AUTO_RESOLVED, $union->resolution);
        $t->same("ours\ntheirs\n", $union->content);
        $t->same(BlobMergeResult::RESOLUTION_CONFLICT, $binary->resolution);
        $t->same("ours\0", $binary->content);
        $t->same(BlobMergeResult::RESOLUTION_AUTO_RESOLVED, $binaryTheirs->resolution);
        $t->same("theirs\0", $binaryTheirs->content);
    },
    'text and union drivers fall back to binary for binary-like buffers' => static function (TestRunner $t): void {
        $textFallback = BuiltinDriver::merge(
            BuiltinDriver::TEXT,
            "base",
            "ours",
            "theirs\0",
        );
        $textResolved = BuiltinDriver::merge(
            BuiltinDriver::TEXT,
            "base",
            "ours",
            "theirs\0",
            binaryResolveWith: BlobMerge::PICK_THEIRS,
        );
        $sameBinary = BuiltinDriver::merge(
            BuiltinDriver::TEXT,
            "base",
            "same\0buffer",
            "same\0buffer",
        );
        $unionFallback = BuiltinDriver::merge(
            BuiltinDriver::UNION,
            "base",
            "ours\0",
            "theirs\0",
        );

        $t->same(BlobMergeResult::RESOLUTION_CONFLICT, $textFallback->resolution);
        $t->same("ours", $textFallback->content);
        $t->same(BlobMergeResult::RESOLUTION_AUTO_RESOLVED, $textResolved->resolution);
        $t->same("theirs\0", $textResolved->content);
        $t->same(BlobMergeResult::RESOLUTION_COMPLETE, $sameBinary->resolution);
        $t->same("same\0buffer", $sameBinary->content);
        $t->same(BlobMergeResult::RESOLUTION_CONFLICT, $unionFallback->resolution);
        $t->same("ours\0", $unionFallback->content);
    },
    'text driver falls back to binary for large buffers before diffing' => static function (TestRunner $t): void {
        $largeFallback = BuiltinDriver::merge(
            BuiltinDriver::TEXT,
            'base',
            'ours',
            'unspecified',
            largeFileThresholdBytes: 9,
        );

        $t->same(BlobMergeResult::RESOLUTION_CONFLICT, $largeFallback->resolution);
        $t->same('ours', $largeFallback->content);
        $t->throws(
            InvalidArgumentException::class,
            static fn () => BuiltinDriver::merge(BuiltinDriver::TEXT, '', '', '', largeFileThresholdBytes: -1),
        );
    },
    'wordpress builtin merge driver fixture maps attributes to native drivers' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-builtin-merge-driver.php';

        $notesDriver = BuiltinDriver::fromMergeAttribute($fixture['blockNotes']['state'], $fixture['blockNotes']['value']);
        $notes = BuiltinDriver::merge(
            $notesDriver,
            $fixture['blockNotes']['base'],
            $fixture['blockNotes']['ours'],
            $fixture['blockNotes']['theirs'],
        );
        $mediaDriver = BuiltinDriver::fromMergeAttribute($fixture['media']['state']);
        $media = BuiltinDriver::merge(
            $mediaDriver,
            $fixture['media']['base'],
            $fixture['media']['ours'],
            $fixture['media']['theirs'],
        );
        $autoMediaDriver = BuiltinDriver::fromMergeAttribute($fixture['mediaAutoDetected']['state']);
        $autoMedia = BuiltinDriver::merge(
            $autoMediaDriver,
            $fixture['mediaAutoDetected']['base'],
            $fixture['mediaAutoDetected']['ours'],
            $fixture['mediaAutoDetected']['theirs'],
        );
        $themeDriver = BuiltinDriver::fromMergeAttribute($fixture['themeJson']['state']);
        $theme = BuiltinDriver::merge(
            $themeDriver,
            $fixture['themeJson']['base'],
            $fixture['themeJson']['ours'],
            $fixture['themeJson']['theirs'],
            BlobMerge::STYLE_MERGE,
            'base/theme.json',
            'ours/theme.json',
            'theirs/theme.json',
            BuiltinDriver::markerSizeFromAttribute($fixture['themeJson']['markerSize']),
        );
        $unknownDriver = BuiltinDriver::fromMergeAttribute(
            $fixture['unknownExternal']['state'],
            $fixture['unknownExternal']['value'],
        );

        $t->same(BuiltinDriver::UNION, $notesDriver);
        $t->same(BlobMergeResult::RESOLUTION_AUTO_RESOLVED, $notes->resolution);
        $t->same($fixture['blockNotes']['expected'], $notes->content);
        $t->same(BuiltinDriver::BINARY, $mediaDriver);
        $t->same(BlobMergeResult::RESOLUTION_CONFLICT, $media->resolution);
        $t->same($fixture['media']['ours'], $media->content);
        $t->same(BuiltinDriver::TEXT, $autoMediaDriver);
        $t->same(BlobMergeResult::RESOLUTION_CONFLICT, $autoMedia->resolution);
        $t->same($fixture['mediaAutoDetected']['ours'], $autoMedia->content);
        $t->same(BuiltinDriver::TEXT, $themeDriver);
        $t->same($fixture['themeJson']['expected'], $theme->content);
        $t->same(BuiltinDriver::TEXT, $unknownDriver);
    },
];
