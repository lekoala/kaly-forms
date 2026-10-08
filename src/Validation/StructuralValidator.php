<?php

declare(strict_types=1);

namespace Kaly\Forms\Validation;

use Kaly\Forms\Form;
use Kaly\Forms\FormState;

final class StructuralValidator
{
    /** @param array<string,mixed> $values */
    public function validate(Form $form, array $values): FormState
    {
        $violations = [];

        foreach ($form->fields() as $field) {
            if (!$field->isActiveFor($values)) {
                continue;
            }

            $value = $values[$field->name] ?? null;
            foreach ($field->rules as $rule) {
                $violation = $rule->validate($field->name, $value, $values);
                if ($violation !== null) {
                    $violations[] = $violation;
                }
            }
        }

        return FormState::from($values, $violations);
    }
}
