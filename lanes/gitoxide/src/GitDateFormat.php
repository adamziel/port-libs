<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitDateFormat
{
    public const SHORT = 'short';
    public const UNIX = 'unix';
    public const RAW = 'raw';
    public const ISO8601 = 'iso8601';
    public const ISO8601_STRICT = 'iso8601-strict';
    public const RFC2822 = 'rfc2822';
    public const GIT_RFC2822 = 'git-rfc2822';
    public const GITOXIDE = 'gitoxide';
    public const DEFAULT = 'default';

    private function __construct()
    {
    }
}
