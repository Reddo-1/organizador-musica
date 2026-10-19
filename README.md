# Organizador de música

App local para clasificar una biblioteca de drum and bass y sacar de ella playlists `.m3u8` que se arrastran a rekordbox. Laravel 13 + Filament 5 + SQLite, sin usuarios ni login. La especificación completa está en [SPEC.md](SPEC.md).

La app **solo lee** los archivos de audio: nunca los modifica, mueve, renombra ni borra, ni escribe sus etiquetas. Tampoco toca la base de datos de rekordbox.

## Instalar en el PC con la música (Windows + Herd)

1. Instala [Laravel Herd para Windows](https://herd.laravel.com). Trae PHP, Composer y Node.
2. Pon el proyecto en `C:\Users\Ricardo\Herd\organizador-musica`. Puedes clonar el repositorio o copiar la carpeta, sin `vendor`, `node_modules` ni `.env`. Para sacar una copia limpia desde otro PC: `git archive -o organizador-musica.zip HEAD`.
3. En una terminal, dentro de esa carpeta:
   ```
   composer setup
   ```
   Instala las dependencias, crea `.env` y la clave, crea la base de datos `database\database.sqlite` y compila el tema.
4. Abre `.env` y ajusta lo de este PC (ver [Configuración](#configuración)). Como mínimo `FFMPEG_BINARY`, si quieres escuchar los AIFF.
5. Abre http://organizador-musica.test. Herd sirve la carpeta solo; no hace falta `php artisan serve`.

Sin Herd vale cualquier PHP 8.3+ con Composer y Node: `composer setup`, y después `php artisan serve` para abrirla en http://localhost:8000.

## Traer tu biblioteca

Hay tres casos; puedes combinarlos.

**Desde la versión Python.** Importa su `musica.db`:
```
php artisan music:import-legacy "C:\ruta\a\musica.db"
```
Trae los temas con su energía, rol, años, BPM, key y notas, los géneros, las etiquetas (subgénero y percusión) y los ajustes. El archivo viejo se abre en solo lectura. Se puede repetir sin duplicar nada: los temas se reconocen por su ruta, sin distinguir mayúsculas. Después pulsa **Escanear** para dar de alta los temas nuevos y marcar los que falten.

**Desde otro PC con esta app.** Copia su base de datos (`database\database.sqlite`, o la copia más reciente de `storage\app\backups`) como `database\database.sqlite` en el PC nuevo y ejecuta `php artisan migrate`.

**Si la música está en otra carpeta o en otro disco.** Las rutas se guardan completas (`C:\Users\...\DNB\tema.mp3`). Cámbialas todas de una vez en **Ajustes → Mover la biblioteca**, o con:
```
php artisan music:relocate-folder "D:\Musica\DNB" "C:\Users\Ricardo\Music\Musikote\DNB"
```
Solo cambia las rutas guardadas; los archivos no se tocan. Después, **Escanear**.

## Configuración

Lo propio de cada PC va en `.env` o en **Ajustes**, nunca en el código.

| Dónde | Qué | Ejemplo |
|---|---|---|
| `.env` `APP_URL` | Dirección de la app | `http://organizador-musica.test` |
| `.env` `MUSIC_FOLDER` | Carpeta de música inicial. Se cambia luego en Ajustes | `'C:\Users\Ricardo\Music\Musikote\DNB'` |
| `.env` `MUSIC_PLAYLISTS_FOLDER` | Carpeta de los `.m3u8`. Sin definir: `storage\app\playlists` | `'C:\Users\Ricardo\Music\Playlists'` |
| `.env` `FFMPEG_BINARY` | Ruta completa a `ffmpeg.exe`, para escuchar AIFF en el navegador | `'C:\ffmpeg\bin\ffmpeg.exe'` |
| Ajustes | Carpeta de música, género por defecto, subcarpeta como subgénero y carpeta de playlists | |

Escribe las rutas de Windows entre comillas simples en `.env`, para que las `\` lleguen tal cual.

## Copias de seguridad

La primera visita de cada día copia la base de datos en `storage\app\backups\music_AAAA-MM-DD.sqlite`. Se guardan las 10 últimas. En **Ajustes** están la carpeta, las últimas copias y el botón **Copia ahora**.

Para recuperar una copia, ponla en lugar de `database\database.sqlite` (guarda antes el actual por si acaso) y recarga la página.

## Uso

1. **Escanear**: da de alta los temas de la carpeta de música y marca los que ya no están.
2. **Clasificar**: tema a tema, con 1–5 para la energía y Ctrl+Enter para guardar y pasar al siguiente.
3. **Biblioteca**: la tabla completa, con filtros, edición en bloque y reproductor.
4. **Generar playlist**: filtra, genera, ordena arrastrando y guarda el `.m3u8`. En rekordbox, arrástralo a *Playlists* (o *Archivo → Importar → Importar playlist*).

## Comandos

| Comando | Qué hace |
|---|---|
| `composer setup` | Deja la app lista en un PC nuevo |
| `php artisan music:scan` | Escanea la carpeta de música, como el botón Escanear |
| `php artisan music:import-legacy {ruta}` | Importa el `musica.db` de la versión Python |
| `php artisan music:relocate-folder {antes} {ahora}` | Cambia el principio de las rutas de todos los temas |
| `npm run build` | Recompila el tema tras tocar CSS o vistas |
| `php artisan test` | Tests (Pest) |

## Desarrollo en WSL

También corre en WSL. La base de datos guarda igualmente rutas de Windows (`C:\...`) y la app las traduce a `/mnt/c/...` al leer el disco; se detecta sola y `MUSIC_WSL=true` o `false` en `.env` lo fuerza. Las notas del entorno de desarrollo están en [CLAUDE.md](CLAUDE.md).
