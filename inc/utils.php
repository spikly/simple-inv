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

/**
 * A GET value, or false when it was not supplied.
 */
function queryParam(string $name)
{
    return $_GET[$name] ?? false;
}

/**
 * A GET value cast to an integer.
 */
function queryId(string $name): int
{
    return (int)($_GET[$name] ?? 0);
}

/**
 * A trimmed value from a submitted form, or null when it is blank.
 */
function textOrNull(array $source, string $name)
{
    $value = trim($source[$name] ?? '');

    return ($value === '') ? null : $value;
}

/**
 * A stored date or timestamp as something readable. Anything unparseable is
 * handed back untouched rather than turned into 1970.
 */
function formatDate($value, string $format = 'j M Y'): string
{
    $value = trim((string)$value);
    $time = $value === '' ? false : strtotime($value);

    return ($time === false) ? $value : date($format, $time);
}

function successMessage(string $message): array
{
    return ['status' => 'success', 'message' => $message];
}

function errorMessage(string $message): array
{
    return ['status' => 'error', 'message' => $message];
}

/**
 * A string safe to drop into JavaScript inside an HTML attribute, quotes and
 * all. Used for the confirm() prompts on delete buttons.
 */
function jsString(string $text): string
{
    return escapeHtml(json_encode($text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * Absolute URL of the folder the app is served from, used for QR labels.
 * Set 'site' => ['url' => '...'] in user.config.php when the guessed value is
 * wrong, for example behind a proxy.
 */
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
