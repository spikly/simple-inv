<?php

/** Dot notation, eg config('db.host'). */
function config(string $key, $default = null)
{
    static $settings = null;

    if ($settings === null) {
        $settings = require __DIR__ . '/../config/user.config.php';
    }

    $value = $settings;

    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

function siteTitle(): string
{
    return (string)config('site.title', 'Inventory Tracker');
}
