<?php

declare(strict_types=1);

namespace Kaly\Forms\Render;

use Kaly\Forms\Field\CheckboxGroupField;
use Kaly\Forms\Field\Field;
use Kaly\Forms\Field\HiddenField;
use Kaly\Forms\Field\RadioGroupField;
use Kaly\Forms\Node\ContainerNode;
use Kaly\Forms\Node\FormNode;

/**
 * Default id scheme of the built-in renderers, and its uniqueness check.
 *
 * Ids are form-scoped (kf-<form>-<field>), a custom attributes['id'] wins.
 * Derived ids: <id>-help, <id>-label, <id>-error-<n>, and <id>-<n> for the
 * boxes of a radio/checkbox group with a custom id. Uniqueness is only
 * guaranteed within one form: other forms of the page are unknown here.
 */
final class ControlIds
{
    public static function defaultId(string $formName, string $fieldName): string
    {
        $slug = self::slug($fieldName);
        return $formName !== '' ? 'kf-' . self::slug($formName) . '-' . $slug : 'field-' . $slug;
    }

    public static function controlId(string $formName, Field $field): string
    {
        $custom = self::customId($field);
        return $custom ?? self::defaultId($formName, $field->name);
    }

    /**
     * @param list<FormNode> $nodes
     * @throws \LogicException when two fields would render the same id
     */
    public static function assertUnique(string $formName, array $nodes): void
    {
        /** @var array<string,string> $owners id => field name */
        $owners = [];
        /** @var array<string,string> $errorPrefixes "<id>-error-" => field name */
        $errorPrefixes = [];
        foreach (self::fields($nodes) as $field) {
            if ($field instanceof HiddenField && self::customId($field) === null) {
                continue; // no id, no label, no messages
            }
            $base = self::controlId($formName, $field);
            $ids = [$base, $base . '-help', $base . '-label'];
            if (($field instanceof RadioGroupField || $field instanceof CheckboxGroupField) && self::customId($field) !== null) {
                foreach (array_keys(array_values($field->choices)) as $index) {
                    $ids[] = $base . '-' . $index;
                }
            }
            foreach ($ids as $id) {
                if (isset($owners[$id])) {
                    self::collision($id, $owners[$id], $field->name);
                }
                $owners[$id] = $field->name;
            }
            $errorPrefixes[$base . '-error-'] = $field->name;
        }
        foreach ($owners as $id => $owner) {
            if (preg_match('/^(.*-error-)\d+$/', $id, $m) === 1 && isset($errorPrefixes[$m[1]]) && $errorPrefixes[$m[1]] !== $owner) {
                self::collision($id, $errorPrefixes[$m[1]], $owner);
            }
        }
    }

    private static function collision(string $id, string $first, string $second): never
    {
        throw new \LogicException(sprintf(
            $first === $second
                ? 'Field "%2$s" appears twice in the form: id "%1$s" would be duplicated'
                : 'Fields "%2$s" and "%3$s" would both render id "%1$s": rename one or set attributes[\'id\']',
            $id,
            $first,
            $second,
        ));
    }

    private static function customId(Field $field): ?string
    {
        $custom = $field->attributes['id'] ?? null;
        return is_string($custom) && $custom !== '' ? $custom : null;
    }

    private static function slug(string $value): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $value);
    }

    /**
     * @param list<FormNode> $nodes
     * @return iterable<Field>
     */
    private static function fields(array $nodes): iterable
    {
        foreach ($nodes as $node) {
            if ($node instanceof Field) {
                yield $node;
            } elseif ($node instanceof ContainerNode) {
                yield from self::fields($node->children());
            }
        }
    }
}
