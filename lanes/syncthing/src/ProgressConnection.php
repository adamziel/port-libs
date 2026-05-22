<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

interface ProgressConnection
{
    public function deviceId(): string;

    public function sendDownloadProgress(DownloadProgress $progress): void;
}
