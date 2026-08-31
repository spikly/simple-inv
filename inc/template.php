<?php

/**
 * Markup lives in templates/*.phtml. The functions in inc/ work out what to
 * show and hand it over; a template does no fetching and no deciding.
 */

/**
 * $name is the path under templates/ without the extension, eg 'form/row'.
 * $data is all a template can see: one wanting a value has to be handed it.
 */
function template(string $name, array $data = []): void
{
    // Static so the template cannot reach $this; underscored locals so
    // extract() cannot overwrite the two it needs.
    (static function (string $__file, array $__data): void {
        extract($__data, EXTR_SKIP);

        require $__file;
    })(templatePath($name), $data);
}

/** The same, returned rather than printed, for markup passed on as a value. */
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

/** Where a template lives. Kept in one place so the layout can move. */
function templatePath(string $name): string
{
    return __DIR__ . '/../templates/' . $name . '.phtml';
}
