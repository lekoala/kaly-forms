<?php

declare(strict_types=1);

namespace Kaly\Forms\Validation;

use Kaly\Forms\Field\Field;
use Kaly\Forms\Form;
use Kaly\Forms\FormState;
use Kaly\Forms\Node\ContainerNode;
use Kaly\Forms\Node\Fieldset;
use Kaly\Forms\Node\FormNode;
use Kaly\Forms\Node\Group;
use Kaly\Forms\Violation;

final class StructuralValidator
{
    /** @param array<string,mixed> $values */
    public function validate(Form $form, array $values): FormState
    {
        $violations = [];
        $this->collect($form->children(), true, $values, $violations);
        return FormState::from($values, $violations);
    }

    /**
     * Inactive subtrees skip structural rules, but submitted values are kept as-is:
     * visibility is presentation state, never an acceptance policy.
     *
     * @param list<FormNode> $nodes
     * @param array<string,mixed> $values
     * @param list<Violation> $violations
     */
    private function collect(array $nodes, bool $active, array $values, array &$violations): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof Field) {
                if (!$active || !$node->isActiveFor($values)) {
                    continue;
                }
                $value = $values[$node->name] ?? null;
                foreach ($node->rules as $rule) {
                    $violation = $rule->validate($node->name, $value, $values);
                    if ($violation !== null) {
                        $violations[] = $violation;
                    }
                }
            } elseif ($node instanceof ContainerNode) {
                $childActive = $active;
                if ($node instanceof Group || $node instanceof Fieldset) {
                    $childActive = $active && $node->isActiveFor($values);
                }
                $this->collect($node->children(), $childActive, $values, $violations);
            }
        }
    }
}
