<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = <<<'EL'
(defun acme-card-export ()
  (let ((enabled nil))
    (message "legacy export")))
EL;

$after = <<<'EL'
(defun acme-card-export ()
  (let ((enabled t))
    (when enabled
      (message "modern export"))))
EL;

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/tools/export.el',
    'Emacs Lisp',
    ['language' => 'elisp'],
);
