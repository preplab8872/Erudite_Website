<?php

namespace RyanChandler\LaravelCloudflareTurnstile\View\Components;

use Illuminate\View\Component;

class Turnstile extends Component
{
    public function render()
    {
        // @phpstan-ignore argument.type
        return view('cloudflare-turnstile::components.turnstile');
    }
}
