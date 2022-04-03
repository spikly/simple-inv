<?php

function escapeHtml($string, $flag = ENT_QUOTES)
{
    $safeText = htmlspecialchars($string, $flag);
    return $safeText;
}

function slugify($string)
{
    $string = strtolower($string);
    $string = trim($string);
    $string = str_replace(' ', '-', $string);
    return $string;
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
    $string = strip_tags($string);
    return preg_replace('@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?)?)@', '<a href="$1" rel="nofollow" target="_blank">$1</a>', $string);
}