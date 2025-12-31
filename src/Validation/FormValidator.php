<?php

declare(strict_types=1);

namespace Handlr\Validation;

use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;
use JsonException;

abstract class FormValidator implements Pipe, ValidationHandler
{
    abstract public function rules(): array;

    /**
     * @throws JsonException
     */
    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        $validator = new Validator();
        $isValid = $validator->validate($request->getParsedBody(), $this->rules());

        if (!$isValid) {
            return $response->withJson($validator->errors(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $next($request, $response, $args);
    }
}
