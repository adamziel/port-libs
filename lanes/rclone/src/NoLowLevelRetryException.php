<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class NoLowLevelRetryException extends \RuntimeException
{
    public function noLowLevelRetry(): bool
    {
        return true;
    }
}
