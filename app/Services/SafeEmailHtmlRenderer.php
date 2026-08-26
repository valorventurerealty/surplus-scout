<?php

namespace App\Services;

use Illuminate\Support\Str;
use League\CommonMark\Extension\Autolink\AutolinkExtension;

class SafeEmailHtmlRenderer
{
    public function render(string $text): string
    {
        return Str::markdown($text, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 10,
            'renderer' => ['soft_break' => "<br>\n"],
        ], [new AutolinkExtension]);
    }
}
