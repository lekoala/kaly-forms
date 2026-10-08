<?php

declare(strict_types=1);

namespace Kaly\Forms\Node;

/** Entry point for the standard layout intentions. Custom layouts implement Layout directly. */
final class Layouts
{
    private function __construct() {}

    public static function stack(): Layout
    {
        return new StackLayout();
    }

    public static function inline(): Layout
    {
        return new InlineLayout();
    }

    public static function columns(int $count): Layout
    {
        return new ColumnsLayout($count);
    }
}
