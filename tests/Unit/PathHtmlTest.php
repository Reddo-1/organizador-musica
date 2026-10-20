<?php

use App\Support\PathHtml;

it('shows a path inside the base folder relative to it, ignoring case and the kind of slash', function () {
    expect(PathHtml::relative('C:\Users\Ricardo\Music\DNB\Neurofunk\AIFF\Señal.aiff', 'c:\users\ricardo\music\dnb\\'))
        ->toBe('Neurofunk\AIFF\Señal.aiff')
        ->and(PathHtml::relative('/mnt/c/Música/DNB/x.mp3', '/mnt/c/Música/DNB'))->toBe('x.mp3')
        ->and(PathHtml::relative('C:\Users\Ricardo\Music\DNB2\x.mp3', 'C:\Users\Ricardo\Music\DNB'))->toBe('C:\Users\Ricardo\Music\DNB2\x.mp3')
        ->and(PathHtml::relative('D:\Otra\x.mp3', 'C:\Users\Ricardo\Music\DNB'))->toBe('D:\Otra\x.mp3')
        ->and(PathHtml::relative('C:\Music\x.mp3', null))->toBe('C:\Music\x.mp3');
});

it('keeps the whole path in the tooltip when it shows it relative', function () {
    $html = (string) PathHtml::render('C:\Music\DNB\Deep\Bajo Cero.flac', base: 'C:\Music\DNB');

    expect($html)
        ->toContain('title="C:\Music\DNB\Deep\Bajo Cero.flac"')
        ->toContain('<span class="app-path-head">Deep\</span><span class="app-path-stem">Bajo Cero</span><span class="app-path-ext">.flac</span>');
});
