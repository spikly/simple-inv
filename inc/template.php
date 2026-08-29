<?php

/**
 * Rendering templates.
 *
 * Markup lives in templates/*.phtml rather than in echo statements, so a
 * change to how something looks is made in a file that reads like the page it
 * produces. The functions in inc/ work out what to show and hand the answer to
 * a template; the template does no fetching and no deciding beyond which of
 * the values it was given to draw.
 */

/**
 * Draw a template. $name is its path under templates/ without the extension,
 * eg 'form/row'. $data becomes the variables the template can see, and nothing
 * else does: a template that wants a value has to be handed it.
 */
function template(string $name, array $data = []): void
{
    // A static closure so the template cannot reach $this, and locals named
    // with underscores so extract() cannot overwrite the two it needs.
    (static function (string $__file, array $__data): void {
        extract($__data, EXTR_SKIP);

        require $__file;
    })(templatePath($name), $data);
}

/**
 * The same, returned rather than printed, for the few places that need the
 * markup as a value: a form control passed to another template, or a fragment
 * answered over AJAX.
 */
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
