<?php

declare(strict_types=1);

namespace Handlr\Pipes;

use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Views\View;

/**
 * Template rendering pipe.
 *
 * Renders a view template and returns it as an HTML response.
 * This is a terminal pipe - it does NOT call `$next()`.
 *
 * ## Basic usage
 *
 * ```php
 * // In route definition
 * $router->get('/about', new ViewPipe('pages/about'));
 * $router->get('/contact', new ViewPipe('pages/contact'));
 * ```
 *
 * ## Passing data to templates
 *
 * ```php
 * $router->get('/welcome', new ViewPipe('pages/welcome', [
 *     'title' => 'Welcome to Our Site',
 *     'user' => $currentUser,
 * ]));
 * ```
 *
 * ## Template file location
 *
 * Templates are resolved relative to your views directory:
 *
 * ```
 * views/
 * ├── pages/
 * │   ├── about.php
 * │   ├── contact.php
 * │   └── welcome.php
 * └── layouts/
 *     └── main.php
 * ```
 *
 * ## Dynamic data from handlers
 *
 * For dynamic data, create a custom pipe that builds the data first:
 *
 * ```php
 * class UserProfilePipe implements Pipe
 * {
 *     public function __construct(private UsersTable $users) {}
 *
 *     public function handle(Request $request, Response $response, array $args, callable $next): Response
 *     {
 *         $user = $this->users->findById($args['id']);
 *
 *         if (!$user) {
 *             return $response->withStatus(404)->withBody('User not found');
 *         }
 *
 *         $view = new View('users/profile', ['user' => $user]);
 *         return $response->withHtml($view->render());
 *     }
 * }
 * ```
 *
 * ## Note
 *
 * This pipe is terminal - it renders the view and returns immediately.
 * It does not call `$next()`, so any pipes after it in the chain will not run.
 */
class ViewPipe implements Pipe
{
    /**
     * @param string     $templatePath Path to the template file (relative to views directory)
     * @param array|null $data         Data to pass to the template
     */
    public function __construct(public string $templatePath, public ?array $data = []) {}

    /**
     * Render the template and return an HTML response.
     *
     * @param Request  $request  The incoming HTTP request (unused)
     * @param Response $response The response object to build upon
     * @param array    $args     Route parameters (unused)
     * @param callable $next     The next pipe (NOT called - this is terminal)
     *
     * @return Response HTML response with rendered template
     */
    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        return $response->withHtml(new View($this->templatePath, $this->data)->render());
    }
}
