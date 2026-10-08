<?php

declare(strict_types=1);

namespace Kaly\Forms\Validation;

use Kaly\Forms\Field\Field;
use Kaly\Forms\Field\FileField;
use Kaly\Forms\Form;
use Kaly\Forms\FormError;
use Kaly\Forms\FormState;
use Kaly\Forms\Node\ContainerNode;
use Kaly\Forms\Node\Fieldset;
use Kaly\Forms\Node\FormNode;
use Kaly\Forms\Node\Group;

/**
 * Optional standalone validator for the small structural rules shipped with
 * kaly-forms (required/length/email/numeric/checked).
 *
 * Applications using an external validation layer (Kaly, Symfony Validator, ...)
 * should use that layer as the server authority and pass the resulting errors
 * into FormState. Do not run both validators for the same submission unless
 * this is intentional: rich server validation and HTML constraints necessarily
 * overlap, but they are not required to stay synchronized automatically.
 *
 * The field rules are also projectable to native HTML attributes; the validator
 * is the optional server-side counterpart of that projection, never a substitute
 * for application/business validation.
 */
final class StructuralValidator
{
    public function __construct(
        private readonly ?FilePresence $files = null,
    ) {}

    /** @param array<string,mixed> $values */
    public function validate(Form $form, array $values): FormState
    {
        $errors = [];
        $this->collect($form->children(), true, $values, $errors);
        return FormState::from($values, $errors);
    }

    /**
     * Inactive subtrees skip structural rules, but submitted values are kept as-is:
     * visibility is presentation state, never an acceptance policy.
     *
     * FileField presence is answered by FilePresence (uploads live outside
     * scalar values). Without an adapter, Required on a FileField is skipped
     * and the application must validate the upload itself.
     *
     * @param list<FormNode> $nodes
     * @param array<string,mixed> $values
     * @param list<FormError> $errors
     */
    private function collect(array $nodes, bool $active, array $values, array &$errors): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof Field) {
                if (!$active || !$node->isActiveFor($values)) {
                    continue;
                }
                $value = $values[$node->name] ?? null;
                foreach ($node->rules as $rule) {
                    if ($node instanceof FileField && $rule instanceof Required) {
                        if ($this->files === null) {
                            continue;
                        }
                        if (!$this->files->has($node->name)) {
                            $errors[] = new FormError('This file is required', $node->name, 'required');
                        }
                        continue;
                    }
                    $error = $rule->validate($node->name, $value, $values);
                    if ($error !== null) {
                        $errors[] = $error;
                    }
                }
            } elseif ($node instanceof ContainerNode) {
                $childActive = $active;
                if ($node instanceof Group || $node instanceof Fieldset) {
                    $childActive = $active && $node->isActiveFor($values);
                }
                $this->collect($node->children(), $childActive, $values, $errors);
            }
        }
    }
}
