<?php

function escapeHtml($string, $flag = ENT_QUOTES)
{
    return is_null($string) ? null : htmlspecialchars($string, $flag);
}

function slugify($string)
{
    return str_replace(' ', '-', trim(strtolower($string)));
}

function nl2p($string)
{
    $p = '';

    foreach (explode("\n", $string) as $line) {
        if (trim($line)) {
            $p .= '<p>' . $line . '</p>';
        }
    }

    return $p;
}

function text2link($string)
{
    return preg_replace(
        '@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?)?)@',
        '<a href="$1" rel="nofollow" target="_blank">$1</a>',
        strip_tags($string)
    );
}

/** Null passes. The scheme is checked because escaping can't stop javascript: URLs. */
function isWebUrl(?string $url): bool
{
    if ($url === null) {
        return true;
    }

    return filter_var($url, FILTER_VALIDATE_URL) !== false
        && in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
}

function calculatePercentage($number1, $number2)
{
    if ($number1 == 0) {
        return 0;
    }

    return round(($number2 / $number1) * 100, 2);
}

function utilisationBg($utilisation = 0)
{
    if ($utilisation >= 75) {
        return 'red';
    }

    if ($utilisation >= 50) {
        return 'amber';
    }

    return 'green';
}

/** False when not supplied. */
function queryParam(string $name)
{
    return $_GET[$name] ?? false;
}

function queryId(string $name): int
{
    return (int)($_GET[$name] ?? 0);
}

/** Trimmed, or null when blank. */
function textOrNull(array $source, string $name)
{
    $value = trim($source[$name] ?? '');

    return ($value === '') ? null : $value;
}

/** Unparseable values are returned untouched rather than turned into 1970. */
function formatDate($value, string $format = 'j M Y'): string
{
    $value = trim((string)$value);
    $time = $value === '' ? false : strtotime($value);

    return ($time === false) ? $value : date($format, $time);
}

function successMessage(string $message): array
{
    return ['status' => 'success', 'messages' => [$message]];
}

/** $messages may be keyed by field, which lets formRow() highlight that control. */
function errorMessage($messages): array
{
    return ['status' => 'error', 'messages' => is_array($messages) ? $messages : [$messages]];
}

/** Safe as a JavaScript string inside an HTML attribute. */
function jsString(string $text): string
{
    return escapeHtml(json_encode($text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/** Set site.url in user.config.php when the guess is wrong, eg behind a proxy. */
function baseUrl(): string
{
    $configured = config('site.url');

    if ($configured) {
        return rtrim($configured, '/') . '/';
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') === '443';
    $folder = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

    return ($secure ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $folder . '/';
}

/** Cache buster. $path is relative to assets/. */
function assetVersion(string $path): string
{
    $file = __DIR__ . '/../assets/' . $path;

    return (string)(is_file($file) ? filemtime($file) : '');
}
