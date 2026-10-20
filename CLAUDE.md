# Organizador de música

La especificación completa está en SPEC.md. Estado del trabajo, pendientes y cómo usar Playwright MCP aquí: @ESTADO.md

## Comandos

- `composer setup`: deja la app lista en un PC nuevo (dependencias, `.env`, clave, base de datos y assets).
- `php artisan serve`: sirve la app en http://localhost:8000. Con Herd en Windows no hace falta.
- `npm run build`: recompila el tema tras tocar CSS o vistas (`npm run dev` para recarga en caliente).
- `php artisan test`: tests Pest. `vendor/bin/pint --dirty`: formato del PHP.
- `php artisan music:scan`, `music:import-legacy {musica.db}`, `music:relocate-folder {antes} {ahora}`: escanear, importar la versión Python y cambiar la carpeta base de las rutas. Cómo llevar la app a otro PC: README.md.
- Instancias de demostración para Playwright, con su propia BD, música y storage (nunca la del usuario, que suele usar el puerto 8000/8001): `.playwright-mcp/tools/serve-demo.sh` (8765) y `serve-worst.sh` (8766, 500 temas de `WorstCaseLibrarySeeder`). `node .playwright-mcp/tools/pw.mjs steps.json salida/` maneja el MCP de Playwright por pasos; cada ejecución es un navegador nuevo. `.playwright-mcp/` está en `.gitignore`: estas herramientas solo existen en el PC de desarrollo.

## Reglas (SPEC §7)

- IMPORTANT: los archivos de audio son de solo lectura. La app nunca los modifica, mueve, renombra ni borra, ni escribe sus etiquetas.
- Código, clases, tablas y columnas en inglés; textos de la interfaz en español.
- La lógica va en servicios (`app/Services`), independientes de la UI, que solo los llama. Cada servicio tiene tests Pest; los audios de prueba (1 s, con etiquetas, generados con ffmpeg) van en `tests/Fixtures`.
- Al terminar cada fase: `php artisan test`, enseñar la salida y hacer un commit descriptivo.
- Los commits y las PR no llevan `Co-Authored-By: Claude` ni ninguna otra atribución a Claude: el repo es público y Claude no debe aparecer como colaborador.
- Windows: rutas con `\`, comparaciones sin distinguir mayúsculas, nombres con tildes y ñ (UTF-8). En Composer desde PowerShell, `"~X.Y"` en vez de `^`.
- No añadir dependencias fuera de la sección 2 de la spec sin preguntar. Si algo es ambiguo o choca con Laravel 13 o Filament 5, preguntar antes de improvisar.
- La app tiene que poder llevarse a otro PC (Windows con Herd, o WSL) sin tocar código: lo propio de cada PC va en `.env` o en Ajustes, nunca en el código.
- Diseño: taste-skill (`design-taste-frontend`, en concreto `references/app-screens.md`; el estilo elegido, "true-black flat", está en `.taste-skill/direction.json`) y las skills de Emil para microinteracciones, cortas y respetando `prefers-reduced-motion`. `break-ui` al cerrar cada fase con UI. El stack manda sobre las skills: con Filament, el diseño va en `resources/css/filament/app/theme.css` y en las vistas de las páginas propias, sin sustituir componentes. Los colores de datos (energía, roles, categorías) están en `app/Support/Palette.php`.
- Verificación visual con Playwright MCP en cada fase con UI: recorrer los puntos de "Hecho cuando" y capturas a 1440 px en oscuro y en claro. El modo se cambia con `localStorage['filament:theme:mode']`.

## Entorno: WSL, no Windows + Herd

Decidido con el usuario el 2026-10-19: aquí la app corre en WSL (Ubuntu 24.04), no en Windows con Herd como asume la spec. El PC con la música es otro, así que todo debe funcionar igual en Windows nativo.

- PHP 8.4, Composer y `laravel` vienen de php.new (`~/.config/herd-lite/bin`); Node 24 LTS, de nvm; ffmpeg 8.1 estático en `~/.local/bin`. Si un shell no los encuentra: `export PATH="$HOME/.config/herd-lite/bin:$PATH"; . ~/.nvm/nvm.sh`.
- Sin nvm cargado, `npm` y `npx` son los de Windows (`/mnt/c/Program Files/nodejs`). En el proyecto usa siempre los de nvm.
- La app se sirve con `php artisan serve` en http://localhost:8000, accesible desde el navegador de Windows.
- Rutas: la BD y los ajustes guardan rutas de Windows (`C:\Users\Ricardo\Music\...`), como pide la spec, para que el .m3u8 funcione en rekordbox y encaje la BD de la versión Python. Solo se traducen a `/mnt/c/...` al tocar el disco, en un único sitio.
- En este PC hay una carpeta de prueba en `C:\Users\Ricardo\Music\Musikote\DNB` (`/mnt/c/Users/Ricardo/Music/Musikote/DNB`, 57 archivos mp3 y m4a).
- En la terminal de WSL, `claude` es el Claude Code de Windows (npm). Para `claude mcp ...` usa el binario de la extensión de VS Code: `~/.vscode-server/extensions/anthropic.claude-code-*/resources/native-binary/claude`.
- Playwright MCP (ámbito local de este proyecto) corre con el Node de Windows y abre el Chrome de Windows, porque Chromium en WSL necesitaría librerías del sistema instaladas con sudo. Se lanza con `node.exe` + `npx-cli.js` y no con el script `npx`, que se traga el `initialize` del MCP. Las capturas se guardan solas en `.playwright-mcp/` (ignorada por git): no pases `filename`, porque se resolvería contra una ruta de Windows que no existe.
- `minimalist-ui` y `review-animations` solo se invocan a mano (`/minimalist-ui`, `/review-animations`); cuando la spec pida consultarlas, lee su `SKILL.md` en `.claude/skills/`.
