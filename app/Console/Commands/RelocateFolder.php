<?php

namespace App\Console\Commands;

use App\Services\LibraryRelocator;
use Illuminate\Console\Command;

class RelocateFolder extends Command
{
    protected $signature = 'music:relocate-folder
        {from : Carpeta en la que estaban los temas (p. ej. D:\Musica\DNB)}
        {to : Carpeta en la que están ahora (p. ej. C:\Users\Ricardo\Music\DNB)}
        {--force : No pedir confirmación}';

    protected $description = 'Cambia la carpeta base de las rutas de todos los temas, al mover la música a otro disco u otro PC (no toca archivos)';

    public function handle(LibraryRelocator $relocator): int
    {
        if (! $this->option('force') && ! $this->confirm("¿Cambiar «{$this->argument('from')}» por «{$this->argument('to')}» en las rutas de los temas?", true)) {
            return self::SUCCESS;
        }

        $changed = $relocator->relocate($this->argument('from'), $this->argument('to'));

        $this->components->info("{$changed} temas con la ruta cambiada. Escanea la carpeta para comprobar que están todos.");

        return self::SUCCESS;
    }
}
