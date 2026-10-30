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

/**
 * Escapes plain $string, making each web address in it a link. Takes the text
 * unescaped, as an address matched in escaped text can swallow an entity.
 */
function text2link($string)
{
    $parts = preg_split('@(https?://[^\s<>"\']+)@i', (string)$string, -1, PREG_SPLIT_DELIM_CAPTURE);
    $html = '';

    foreach ($parts as $index => $part) {
        // Odd parts are the addresses.
        if ($index % 2 === 0) {
            $html .= escapeHtml($part);
            continue;
        }

        // Punctuation at the end usually belongs to the sentence.
        $trail = preg_match('/[.,;:!?)\]]+$/', $part, $match) ? $match[0] : '';
        $url = substr($part, 0, strlen($part) - strlen($trail));

        $html .= (isWebUrl($url)
                ? '<a href="' . escapeHtml($url) . '" rel="nofollow noopener" target="_blank">' . escapeHtml($url) . '</a>'
                : escapeHtml($url))
            . escapeHtml($trail);
    }

    return $html;
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

/** False when not supplied. An array, as from ?q[]=x, counts as not supplied. */
function queryParam(string $name)
{
    $value = $_GET[$name] ?? false;

    return is_array($value) ? false : $value;
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

/**
 * There is no login, so any page open on the network could otherwise post a
 * bulk delete here. Browsers name the page a POST came from in Origin (or at
 * least Referer); one naming another host is refused. A request naming none,
 * as from an old browser or a script, is let through.
 */
function rejectCrossSitePost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }

    $source = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');

    if ($source === '' || $source === 'null') {
        return;
    }

    $from = strtolower((string)parse_url($source, PHP_URL_HOST) . ':' . (parse_url($source, PHP_URL_PORT) ?? ''));
    $allowed = [];

    foreach ([baseUrl(), 'http://' . ($_SERVER['HTTP_HOST'] ?? '')] as $url) {
        $allowed[] = strtolower((string)parse_url($url, PHP_URL_HOST) . ':' . (parse_url($url, PHP_URL_PORT) ?? ''));
    }

    if (!in_array($from, $allowed, true)) {
        http_response_code(403);
        exit('This form was sent from another site, so it has been refused.');
    }
}

/** Cache buster. $path is relative to assets/. */
function assetVersion(string $path): string
{
    $file = __DIR__ . '/../assets/' . $path;

    return (string)(is_file($file) ? filemtime($file) : '');
}
