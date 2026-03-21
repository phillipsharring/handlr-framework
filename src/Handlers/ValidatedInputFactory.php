<?php

namespace Handlr\Handlers;

use Handlr\Core\Request;
use Handlr\Validation\Validator;

class ValidatedInputFactory
{
    public function __construct(private Validator $validator) {}

    public function makeValidatedInput(
        Request $request,
        string $inputClass,
        ?string $validationMethod = null,
        array $additionalData = []
    ): array {
        // Merge route params, body, and additional data (additionalData wins —
        // used for server-set values like user_id from auth context)
        $data = array_merge(
            $request->getRouteParams(),
            $request->getParsedBody(),
            $additionalData
        );

        /** @var HandlerInput $input */
        $input = new $inputClass($data, $this->validator);

        $errors = [];

        if ($validationMethod) {
            $errors = $input->$validationMethod();
        }

        return [$input, $errors];
    }
}
