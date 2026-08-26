<?php

namespace App\View\Components\Layouts;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class App extends Component
{
    public function __construct(
        public ?string $title = null,
        public ?string $heading = null,
        public ?string $subheading = null,
    ) {}

    public function render(): View
    {
        return view('layouts.app');
    }
}
