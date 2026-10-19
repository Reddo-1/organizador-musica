<?php

namespace App\Console\Commands;

use App\Exceptions\MusicFolderNotFound;
use App\Services\LibraryScanner;
use Illuminate\Console\Command;

class ScanMusic extends Command
{
    protected $signature = 'music:scan {folder? : Carpeta a escanear; por defecto, la de Ajustes}';

    protected $description = 'Escanea la carpeta de música y da de alta los temas nuevos (solo lee los archivos)';

    public function handle(LibraryScanner $scanner): int
    {
        try {
            $result = $scanner->scan($this->argument('folder'));
        } catch (MusicFolderNotFound $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Escaneada {$result->folder}");

        $this->components->twoColumnDetail('Archivos en la carpeta', (string) $result->filesInFolder);
        $this->components->twoColumnDetail('Nuevos', (string) count($result->new));
        $this->components->twoColumnDetail('Reubicados', (string) count($result->relocated));
        $this->components->twoColumnDetail('Recuperados', (string) count($result->recovered));
        $this->components->twoColumnDetail('No encontrados', (string) count($result->missing));
        $this->components->twoColumnDetail('Errores', (string) count($result->errors));

        if ($result->relocated !== []) {
            $this->newLine();
            $this->line('  Reubicados (ruta vieja → ruta nueva):');
            foreach ($result->relocated as $move) {
                $this->line("  · {$move['from']}");
                $this->line("    → {$move['to']}");
            }
        }

        if ($result->missing !== []) {
            $this->newLine();
            $this->line('  No encontrados:');
            foreach ($result->missing as $path) {
                $this->line("  · {$path}");
            }
        }

        if ($result->errors !== []) {
            $this->newLine();
            $this->line('  Errores:');
            foreach ($result->errors as $error) {
                $this->line("  · {$error['path']}: {$error['message']}");
            }
        }

        return self::SUCCESS;
    }
}
