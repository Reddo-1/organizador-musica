<?php

namespace App\Console\Commands;

use App\Services\LegacyImporter;
use Illuminate\Console\Command;
use RuntimeException;

class ImportLegacy extends Command
{
    protected $signature = 'music:import-legacy {path : Ruta al musica.db de la versión Python}';

    protected $description = 'Importa la biblioteca de la versión Python (abre su base de datos solo para leer)';

    public function handle(LegacyImporter $importer): int
    {
        try {
            $result = $importer->import($this->argument('path'));
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Importada la biblioteca de la versión Python');
        $this->components->twoColumnDetail('Temas nuevos', (string) $result['created']);
        $this->components->twoColumnDetail('Temas actualizados', (string) $result['updated']);
        $this->components->twoColumnDetail('Géneros', (string) $result['genres']);
        $this->components->twoColumnDetail('Etiquetas', (string) $result['tags']);
        $this->components->twoColumnDetail('Etiquetas puestas a temas', (string) $result['links']);
        $this->components->twoColumnDetail('Ajustes', $result['settings'] === [] ? '—' : implode(', ', $result['settings']));

        if ($result['unknownCategories'] !== []) {
            $this->components->warn('Categorías que esta app no muestra: '.implode(', ', $result['unknownCategories']).'. Añádelas a tag_categories en config/music.php para verlas.');
        }

        return self::SUCCESS;
    }
}
