<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = "puts 'legacy import'\n";
$after = "class ImportRunner\n"
    . "  DEFAULT_LIMIT = nil\n"
    . "  def self.call(records)\n"
    . "    records.each do |record|\n"
    . "      next unless record[:post_type]\n"
    . "    end\n"
    . "  rescue StandardError\n"
    . "    require 'json'\n"
    . "  end\n"
    . "\n"
    . "  def self.count(records)\n"
    . "    records.length\n"
    . "  end\n"
    . "end\n";

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-migrator/tools/import_posts.rb',
    'Ruby',
    ['language' => 'ruby'],
);
