<?php

declare(strict_types=1);

namespace Kaly\Forms\Field;

/**
 * One labeled group inside a select. Single level only: HTML has no nested optgroups.
 */
final readonly class OptionGroup
{
    /** @param array<string|int,string> $choices */
    public function __construct(
        public string $label,
        public array $choices,
    ) {}

    /**
     * Turns the nested-array shorthand into OptionGroup objects. Single level only.
     *
     * @param array<string|int,string|OptionGroup|array<string|int,mixed>> $choices
     * @return array<string|int,string|OptionGroup>
     */
    public static function normalize(array $choices): array
    {
        $normalized = [];
        foreach ($choices as $value => $choice) {
            if ($choice instanceof self) {
                $normalized[$value] = $choice;
            } elseif (is_array($choice)) {
                $flat = [];
                foreach ($choice as $k => $text) {
                    if (!is_string($text)) {
                        throw new \InvalidArgumentException('Option groups support a single level only');
                    }
                    $flat[$k] = $text;
                }
                $normalized[$value] = new self((string) $value, $flat);
            } else {
                $normalized[$value] = $choice;
            }
        }
        return $normalized;
    }
}
