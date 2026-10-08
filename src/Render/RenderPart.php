<?php

declare(strict_types=1);

namespace Kaly\Forms\Render;

/** Rendering roles a theme can decorate, never markup structure. */
enum RenderPart
{
    case Form;
    case Field;
    case Label;
    case Control;
    case Help;
    case Errors;
    case Actions;
    case Group;
    case Fieldset;
    case Heading;
    case Text;
}
