<?php

declare(strict_types=1);

namespace Kaly\Forms\Node;

/**
 * A layout intention: how children are preferably composed, never CSS.
 * Renderers interpret it (grid, row/col, plain div); the model stays decoupled.
 */
interface Layout {}
