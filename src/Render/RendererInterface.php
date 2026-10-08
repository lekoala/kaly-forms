<?php

declare(strict_types=1);

namespace Kaly\Forms\Render;

use Kaly\Forms\Form;
use Kaly\Forms\Html;

interface RendererInterface
{
    public function render(Form $form): Html;
}
