<?php

use App\Support\LocalPath;

describe('inside WSL', function () {
    beforeEach(fn () => config(['music.wsl' => true]));

    it('reads Windows paths from /mnt', function () {
        expect(LocalPath::toLocal('C:\Users\Ricardo\Music\Musikote\DNB\Kanine - Sundown.mp3'))
            ->toBe('/mnt/c/Users/Ricardo/Music/Musikote/DNB/Kanine - Sundown.mp3')
            ->and(LocalPath::toLocal('D:/Música/Ñandú.aiff'))->toBe('/mnt/d/Música/Ñandú.aiff')
            ->and(LocalPath::toLocal('C:\\'))->toBe('/mnt/c');
    });

    it('stores what it finds under /mnt as Windows paths', function () {
        expect(LocalPath::toStored('/mnt/c/Users/Ricardo/Music/Musikote/DNB/JumpUp/x.mp3'))
            ->toBe('C:\Users\Ricardo\Music\Musikote\DNB\JumpUp\x.mp3');
    });

    it('treats the WSL disk as seen from Windows as a Linux path', function () {
        expect(LocalPath::toLocal('\\\\wsl.localhost\Ubuntu\home\ricardo\playlists'))->toBe('/home/ricardo/playlists')
            ->and(LocalPath::toLocal('\\\\wsl$\Ubuntu\home'))->toBe('/home');
    });

    it('leaves Linux paths alone', function () {
        expect(LocalPath::toLocal('/home/ricardo/x.mp3'))->toBe('/home/ricardo/x.mp3')
            ->and(LocalPath::toStored('/home/ricardo/x.mp3'))->toBe('/home/ricardo/x.mp3');
    });
});

describe('on Windows', function () {
    beforeEach(fn () => config(['music.wsl' => false]));

    it('uses stored paths as they are', function () {
        expect(LocalPath::toLocal('C:\Users\Ricardo\Music\x.mp3'))->toBe('C:\Users\Ricardo\Music\x.mp3');
    });

    it('stores drive paths with backslashes', function () {
        expect(LocalPath::toStored('C:/Users/Ricardo/Music/DNB\JumpUp\x.mp3'))->toBe('C:\Users\Ricardo\Music\DNB\JumpUp\x.mp3');
    });
});

it('compares paths with backslashes and ignoring case, accents included', function () {
    expect(LocalPath::key('C:/Users/Ricardo/MÚSICA/ÑANDÚ - Canción.MP3'))
        ->toBe('c:\users\ricardo\música\ñandú - canción.mp3');
});

it('takes the file name apart with either separator', function (string $path) {
    expect(LocalPath::fileName($path))->toBe('Kanine - Sundown.MP3')
        ->and(LocalPath::stem($path))->toBe('Kanine - Sundown')
        ->and(LocalPath::extension($path))->toBe('mp3');
})->with(['C:\DNB\Kanine\Kanine - Sundown.MP3', '/mnt/c/DNB/Kanine/Kanine - Sundown.MP3']);
