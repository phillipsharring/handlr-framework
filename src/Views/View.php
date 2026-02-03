<?php

declare(strict_types=1);

namespace Handlr\Views;

/**
 * Simple PHP template renderer.
 *
 * Templates are PHP files in `resources/views/`. Data is extracted into the
 * template's scope, so `['name' => 'John']` becomes `$name` in the template.
 *
 * ## Usage
 *
 * ```php
 * // Render resources/views/emails/welcome.php with data
 * $view = new View('emails/welcome', ['name' => 'John', 'email' => $email]);
 * $html = $view->render();
 *
 * // In a Handler, return a view response
 * return new HtmlResponse((new View('pages/dashboard', $data))->render());
 * ```
 *
 * ## Template file (resources/views/emails/welcome.php)
 *
 * ```php
 * <h1>Welcome, <?= $name ?></h1>
 * <p>Your email: <?= $email ?></p>
 * ```
 */
class View
{
    /** Base path for view templates */
    protected string $basePath = HANDLR_APP_ROOT . '/resources/views';

    /**
     * @param string $templatePath Path relative to views dir (no .php extension)
     * @param array  $data         Variables to extract into template scope
     */
    public function __construct(private readonly string $templatePath, private ?array $data = []) {}

    /**
     * Render the template and return the output as a string.
     */
    public function render(): string
    {
        extract($this->data);
        ob_start();
        require_once "$this->basePath/$this->templatePath.php"; // NOSONAR
        return ob_get_clean();
    }
}
