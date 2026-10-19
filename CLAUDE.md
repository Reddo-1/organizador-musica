# Organizador de música

La especificación completa está en SPEC.md.

## Entorno: WSL, no Windows + Herd

Decidido con el usuario el 2026-10-19: la app corre en WSL (Ubuntu 24.04), no en Windows con Herd como asume la spec.

- PHP 8.4, Composer y `laravel` vienen de php.new (`~/.config/herd-lite/bin`); Node 24 LTS, de nvm. Si un shell no los encuentra: `export PATH="$HOME/.config/herd-lite/bin:$PATH"; . ~/.nvm/nvm.sh`.
- Sin nvm cargado, `npm` y `npx` son los de Windows (`/mnt/c/Program Files/nodejs`). En el proyecto usa siempre los de nvm.
- La app se sirve con `php artisan serve` en http://localhost:8000, accesible desde el navegador de Windows.
- Rutas: la BD y los ajustes guardan rutas de Windows (`C:\Users\Ricardo\Music\...`), como pide la spec, para que el .m3u8 funcione en rekordbox y encaje la BD de la versión Python. Solo se traducen a `/mnt/c/...` al tocar el disco, en un único sitio.
- La música está en `C:\Users\Ricardo\Music\Musikote\DNB` (`/mnt/c/Users/Ricardo/Music/Musikote/DNB`).
- En la terminal de WSL, `claude` es el Claude Code de Windows (npm). Para `claude mcp ...` usa el binario de la extensión de VS Code: `~/.vscode-server/extensions/anthropic.claude-code-*/resources/native-binary/claude`.
- Playwright MCP (ámbito local de este proyecto) corre con el Node de Windows y abre el Chrome de Windows, porque Chromium en WSL necesitaría librerías del sistema instaladas con sudo. Se lanza con `node.exe` + `npx-cli.js` y no con el script `npx`, que se traga el `initialize` del MCP. Las capturas se guardan solas en `.playwright-mcp/` (ignorada por git): no pases `filename`, porque se resolvería contra una ruta de Windows que no existe.
- `minimalist-ui` y `review-animations` solo se invocan a mano (`/minimalist-ui`, `/review-animations`); cuando la spec pida consultarlas, lee su `SKILL.md` en `.claude/skills/`.

La Fase 0 completa este archivo con los comandos del proyecto y las reglas de la sección 7 de SPEC.md.
