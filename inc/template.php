<?php

/** $name is the path under templates/ without the extension, eg 'form/row'. */
function template(string $name, array $data = []): void
{
    // Static so the template cannot reach $this; underscored locals so
    // extract() cannot overwrite them.
    (static function (string $__file, array $__data): void {
        extract($__data, EXTR_SKIP);

        require $__file;
    })(templatePath($name), $data);
}

function templateHtml(string $name, array $data = []): string
{
    ob_start();

    try {
        template($name, $data);
    } catch (\Throwable $e) {
        ob_end_clean();

        throw $e;
    }

    return ob_get_clean();
}

function templatePath(string $name): string
{
    return __DIR__ . '/../templates/' . $name . '.phtml';
}
