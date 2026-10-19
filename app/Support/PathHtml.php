<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * A file path in monospace, truncated in the middle (SPEC §6): the folders shrink first, then the
 * file name, and the extension always stays, "C:\Users\Ri…\Kanine - Sund….aiff" (a WAV that became
 * an AIFF must still read as such). The whole path is in the tooltip.
 */
final class PathHtml
{
    public static function render(?string $path, string $class = ''): HtmlString
    {
        if (blank($path)) {
            return new HtmlString('<span class="app-dash">—</span>');
        }

        $cut = max((int) strrpos($path, '\\'), (int) strrpos($path, '/'));
        $head = $cut > 0 ? substr($path, 0, $cut + 1) : '';
        $name = $cut > 0 ? substr($path, $cut + 1) : $path;
        $dot = strrpos($name, '.');
        $stem = $dot > 0 ? substr($name, 0, $dot) : $name;
        $extension = $dot > 0 ? substr($name, $dot) : '';

        return new HtmlString(sprintf(
            '<span class="app-path %s" title="%s"><span class="app-path-head">%s</span><span class="app-path-stem">%s</span><span class="app-path-ext">%s</span></span>',
            e($class),
            e($path),
            e($head),
            e($stem),
            e($extension),
        ));
    }
}
