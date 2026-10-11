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
    /** Actions zone wrapper (<div>). */
    case Actions;
    /** One submit button inside the actions zone (theme gets a null node). */
    case Action;
    case Group;
    /** Explicit fieldset or a group of radio/checkbox controls (<fieldset>). */
    case Fieldset;
    /** Legend of a fieldset or of a radio/checkbox group. */
    case Legend;
    case Heading;
    case Text;
}
