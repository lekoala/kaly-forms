<?php

declare(strict_types=1);

namespace Kaly\Forms\Field;

use Kaly\Forms\Interaction\Condition;

/**
 * Renders a custom element and, by default, a hidden native input carrying the submitted value.
 * This keeps custom JS UI separate from the server-side submission contract.
 */
final class CustomElementField extends Field
{
    public function __construct(
        string $name,
        public readonly string $tag,
        ?string $label = null,
        ?string $help = null,
        public readonly bool $mirrorHiddenInput = true,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ) {
        if (!self::isValidTag($tag)) {
            throw new \InvalidArgumentException(sprintf('Invalid custom element tag "%s"', $tag));
        }

        parent::__construct($name, $label, $help, $attributes, [], $visibleWhen);
    }

    /**
     * Valid custom element name (ASCII subset of the HTML grammar): lowercase,
     * starts with a letter, contains a hyphen, not one of the reserved names.
     * The tag is written raw into markup, so this is the only thing standing
     * between a misconfigured tag and injected attributes.
     */
    public static function isValidTag(string $tag): bool
    {
        if (preg_match('/^[a-z][a-z0-9._]*-[a-z0-9._-]*$/', $tag) !== 1) {
            return false;
        }
        return !in_array(
            $tag,
            [
                'annotation-xml',
                'color-profile',
                'font-face',
                'font-face-src',
                'font-face-uri',
                'font-face-format',
                'font-face-name',
                'missing-glyph',
            ],
            true,
        );
    }
}
