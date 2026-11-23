<?php

namespace Amea\Config;

use Amea\Core\Runtime;

final class Environment
{
    public static function load(string $projectRoot): void
    {
        if (Runtime::configFilePath() !== null) {
            Runtime::loadEnv($projectRoot);
            return;
        }

        $file = $projectRoot . '/.env';
        if (!is_file($file)) {
            return;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_contains($line, '=') && !str_starts_with(ltrim($line), '#')) {
                [$key, $value] = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value);
            }
        }
    }
}
