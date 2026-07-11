<?php
declare(strict_types=1);

// Schlanker .env-Parser ohne Composer-Abhängigkeit. Echte Server-Umgebungsvariablen
// (getenv()) haben immer Vorrang, damit Produktivserver ohne Dateiänderung überschreiben können.
function env(string $key, ?string $default = null): ?string
{
    static $vars = null;

    if ($vars === null) {
        $vars = [];
        $path = __DIR__ . '/../.env';
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                    continue;
                }
                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                $isQuoted = strlen($value) >= 2
                    && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")));
                if ($isQuoted) {
                    $value = substr($value, 1, -1);
                }
                $vars[$name] = $value;
            }
        }
    }

    $serverValue = getenv($key);
    if ($serverValue !== false) {
        return $serverValue;
    }

    return $vars[$key] ?? $default;
}
