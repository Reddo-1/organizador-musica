# Estado del proyecto

Traspaso para la próxima sesión de Claude Code, escrito el 2026-10-19. CLAUDE.md tiene las reglas y el entorno; aquí va lo que se ha hecho, lo que falta y cómo usar Playwright MCP.

## Primero: abre la sesión en la carpeta del proyecto

Playwright MCP está registrado en el ámbito *local* de `/home/ricardo/proyectos_varios/organizador-musica`. Las sesiones anteriores se abrieron en `~/proyectos_varios`, la carpeta padre, y por eso no tenían las herramientas `mcp__playwright__*`. Comprobado el 2026-10-19: `claude mcp list` desde el proyecto da `playwright … ✔ Connected` y desde la carpeta padre no aparece.

- Si las herramientas `mcp__playwright__browser_*` no están, la sesión no está abierta en esta carpeta. Comprueba con `/mcp`, o con `~/.vscode-server/extensions/anthropic.claude-code-*/resources/native-binary/claude mcp list` desde la carpeta (el `claude` del PATH es el de Windows y mira otra configuración).
- La memoria automática de las sesiones anteriores está guardada con la clave de la carpeta padre y no se carga aquí. Lo que importa de ella está en este archivo y en CLAUDE.md.
- El usuario es Ricardo: DJ de drum and bass (neuro, deep y techstep) que usa rekordbox en Windows. Escribe en español y trabaja por fases con la spec, comprobando él los «Hecho cuando».

## Qué está hecho

Todas las fases de SPEC.md están terminadas, cada una con su commit. `php artisan test` da 202 tests y 720 aserciones en verde.

| Fase | Commit | Contenido |
|---|---|---|
| Previa | `bbc898f` | Skills de diseño, grill-me y Playwright MCP |
| 0 | `b7242d5` | Panel Filament 5 sin login, tema oscuro y navegación |
| 1 | `d89824c` | Modelo de datos, lectura de etiquetas (getID3), escaneo y exportación .m3u8 |
| 2 | `c0ffc01` | Escanear, Etiquetas y géneros (renombrar y fusionar), Ajustes |
| 3 | `0d61945` | Biblioteca, edición en bloque, reproductor con Range y AIFF vía ffmpeg |
| 4 | `2304443` | Clasificar: cola, ficha, 1–5 para la energía, Ctrl+Enter |
| 5 | `dd48c35` | Generar playlist (página de inicio), presets, setlist ordenable, exportar e historial |
| 6 | `a8b37a9` | Copias diarias y «Copia ahora», `music:import-legacy`, mover la biblioteca |
| Docs | `575ab55` | README.md para instalar en otro PC |

Sin comprobar, porque en este PC no se puede:
- Que rekordbox importe el `.m3u8` (Fase 5). El archivo sí está verificado: CRLF, UTF-8, orden y rutas de Windows.
- El `musica.db` real de la versión Python (Fase 6). La importación está probada con una base de datos que sigue el esquema de SPEC §5.11. Si la real tiene otras columnas, el comando dice cuál falta.

Las capturas de cada fase están en `.playwright-mcp/capturas/{fase-0,fase-2,…,fase-6,break-ui}/`.

## Decisiones que no están en la spec

- **Desarrollo en WSL, no en Windows con Herd.** El PC con la música es otro, Windows con Herd. Todo lo propio de un PC va en `.env` o en Ajustes. README.md explica la instalación allí.
- **Rutas.** La BD guarda rutas de Windows (`C:\…`). `App\Support\LocalPath` es el único sitio que las traduce a `/mnt/c/…` en WSL. Se detecta solo; `MUSIC_WSL` en `.env` lo fuerza.
- **Añadidos para poder llevar la app a otro PC**: `music:relocate-folder {antes} {ahora}` y la sección de Ajustes «Mover la biblioteca» (`LibraryRelocator`). El usuario pidió que la app fuera «escalable/transferible».
- **Inicio.** «Generar playlist» se sirve en `/`.
- **Diseño.** Estilo «true-black flat» (`.taste-skill/direction.json`): fondo #000, tarjetas #111 y paneles anidados #2a2a2a. El violeta queda para las acciones principales. Los colores de energía, roles y categorías están en `app/Support/Palette.php`.

## Playwright MCP en este proyecto

Configuración en `~/.claude.json`, en el proyecto: `"/mnt/c/Program Files/nodejs/node.exe"` con `C:\Program Files\nodejs\node_modules\npm\bin\npx-cli.js @playwright/mcp@latest --output-dir \\wsl.localhost\Ubuntu\home\ricardo\proyectos_varios\organizador-musica\.playwright-mcp`.

- Abre el Chrome de Windows **con ventana** (sin `--headless`) y con perfil persistente: el usuario lo ve. Si da «browser is already in use», hay otra ventana de Chrome del MCP abierta; ciérrala o llama a `browser_close`.
- Chrome en Windows llega a los servidores de WSL por `http://localhost:PUERTO`, aunque estén escuchando en 127.0.0.1.
- **Nunca uses la instancia del usuario** (`php artisan serve` desde su terminal, puertos 8000 u 8001, con `database/database.sqlite`) para nada que escriba. En una fase anterior un clic de prueba en «Escanear» escribió ajustes y un escaneo en su BD. Usa las instancias de demostración. Arráncalas con Bash y `run_in_background`; cada una tiene su propia BD, música y storage:
  - `.playwright-mcp/tools/serve-demo.sh`: http://localhost:8765, con 13 temas copiados de `tests/Fixtures/audio`. Para rehacerla: `.playwright-mcp/tools/demo.sh`, y después escanea `.playwright-mcp/demo/music` desde la UI o con `DB_DATABASE=… php artisan music:scan …`.
  - `.playwright-mcp/tools/serve-worst.sh`: http://localhost:8766, con 500 temas de casos límite. Para rehacerla: `DB_DATABASE=.playwright-mcp/worst.sqlite php artisan migrate:fresh --seeder=WorstCaseLibrarySeeder`.
  - `LARAVEL_STORAGE_PATH` separa el storage de cada instancia. Sin él, las copias diarias y las previews de las demos acaban en el `storage/` del proyecto.
- **Capturas.** No pases `filename` a `browser_take_screenshot`: se resolvería contra una ruta de Windows que no existe. Se guardan solas como `.playwright-mcp/page-<fecha>.png` y también vuelven en la respuesta. Muévelas a `.playwright-mcp/capturas/fase-N/` con un nombre claro.
- **Comprobación de cada fase con UI:** `browser_resize` a 1440×900 y capturas en oscuro y en claro. El tema se cambia con `localStorage.setItem('filament:theme:mode', 'light' | 'dark')` y recargando. Para break-ui, mira también 390 px y que `document.documentElement.scrollWidth` no pase del ancho de la ventana.
- **Selectores útiles:** notificaciones `.fi-no-notification` (filtra por texto), modales `.fi-modal-window`, filas `.fi-ta-row`, setlist `.app-setlist-item`, `.app-setlist-handle` y `.app-setlist-remove`.
- **Arrastrar en la setlist.** Usa SortableJS en modo `forceFallback`, así que el drag de HTML5 (`browser_drag`) no funciona. Hazlo con `browser_run_code_unsafe` y `page.mouse.move` / `down` / `move` / `up` sobre `.app-setlist-handle`. Hay un ejemplo en `.playwright-mcp/tools/steps/f5ab.json`.
- **Alternativa sin MCP en la sesión:** `. ~/.nvm/nvm.sh && node .playwright-mcp/tools/pw.mjs pasos.json carpeta-salida/`. Lanza el mismo servidor MCP sin ventana y aislado, y ejecuta una lista de pasos `{tool, args, save, print, max}`; hay ejemplos en `tools/steps/`. Cada ejecución es un navegador nuevo, así que el estado de sesión (`#[Session]`, el borrador de Generar) no pasa de una a otra.
- `.playwright-mcp/` está en `.gitignore`: las herramientas, las demos y las capturas solo existen en este PC.

## Entorno de este PC (resumen; el detalle está en CLAUDE.md)

- En cada shell: `export PATH="$HOME/.config/herd-lite/bin:$PATH"; . ~/.nvm/nvm.sh`.
- Tests con salida legible: `env -u CLAUDECODE -u AI_AGENT php artisan test --colors=never`. Dentro de Claude Code, Pest detecta el agente y escribe JSON.
- El `.env` de aquí tiene `FFMPEG_BINARY=/home/ricardo/.local/bin/ffmpeg`. Sin él, el test del AIFF se salta.
- Tras tocar CSS o vistas con clases nuevas: `npm run build`.

## Trampas de Filament 5 y Livewire 4 que ya costaron tiempo

- Los datos de `View::make()->viewData()` se cachean con el schema: pasa un closure y, si el registro cambia, `unset($this->cachedSchemas['content'])`.
- Una acción pintada a mano en Blade no comprueba su visibilidad: envuélvela en `@if ($action->isVisible())`.
- `Actions::make()->alignEnd()` usa row-reverse: la primera acción del array queda a la derecha.
- En una página, `$view` choca con la vista de Filament (por eso existe `$queueView` en Clasificar).
- `ToggleButtons` no deja deseleccionar; los `Slider` en dos columnas se solapan, así que van en una y con `markAsRequired(false)`.
- Para testear acciones dentro del schema `content`: `TestAction::make('x')->schemaComponent('clave', schema: 'content')`, y dale `->key('clave')` al `Actions`.
- Los tests que cambian la conexión por defecto tienen que devolverla a `sqlite` antes de terminar, porque RefreshDatabase hace rollback sobre la conexión por defecto (ver `tests/Feature/BackupTest.php`).
- En PHP, el spread `[...$a, 800 => …]` renumera las claves enteras: usa `array_replace`.
- No uses `pkill -f` con un patrón que también aparezca en tu propio comando: mata tu propio shell. Mata por PID.

## Pendiente

1. **En el PC con la música:** instalar Herd, copiar el proyecto (no hay remoto; `git archive -o organizador-musica.zip HEAD`), `composer setup`, poner `FFMPEG_BINARY` en `.env` con la ruta a su `ffmpeg.exe`, `php artisan music:import-legacy "C:\…\musica.db"`, Escanear y elegir en Ajustes la carpeta de playlists. Luego comprobar que rekordbox importa un `.m3u8`.
2. **GitHub:** el repo no tiene remoto. Pregunta antes de crear uno o de hacer push.
3. **Ideas futuras de la spec**, sin empezar: importar BPM, key y cues del XML de colección de rekordbox.
