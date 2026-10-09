<?php

declare(strict_types=1);

namespace Kaly\Forms\Render;

/** Rendering roles a theme can decorate, never markup structure. */
enum RenderPart
{
    case Form;
    /** Individual field wrapper (<div>). */
    case Field;
    case Label;
    case Control;
    case Help;
    case Errors;
    case Actions;
    case Group;
    /** Explicit fieldset or a group of radio/checkbox controls (<fieldset>). */
    case Fieldset;
    case Heading;
    case Text;
}
