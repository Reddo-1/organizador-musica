# Organizador de música — Especificación para Claude Code

> Versión 2 en **Laravel**. Sustituye a la versión 1 (Python + Streamlit), cuya base de datos se puede importar (Fase 6).

---

## Cómo usar este documento

### 1. Prepara el entorno (una vez)
1. Instala **Laravel Herd para Windows** (herd.laravel.com). Trae PHP, Composer, Node y el instalador `laravel`. Pide permisos de administrador al instalarse.
2. Instala **Claude Code** (code.claude.com/docs/en/setup).
3. Comprueba en una terminal que todo responde:
   ```
   php -v            # 8.3 o superior
   composer -V
   node -v
   laravel --version
   ```

### 2. Crea el proyecto
```
cd C:\Users\Ricardo\Herd
laravel new organizador-musica
```
Respuestas del instalador:

| Pregunta | Opción A (Filament) | Opción B (Livewire + Flux) | Opción C (Inertia + Vue) |
|---|---|---|---|
| Starter kit | None | Livewire | Vue |
| Testing | Pest | Pest | Pest |
| Base de datos | SQLite | SQLite | SQLite |
| npm install | Sí | Sí | Sí |

Copia este archivo dentro del proyecto como **`SPEC.md`** y revisa la sección 0.

Dentro de `C:\Users\Ricardo\Herd`, Herd sirve la app en `http://organizador-musica.test`. En otra carpeta, usa `php artisan serve`.

### 3. Trabaja con Claude Code por fases
Abre una terminal en la carpeta del proyecto y ejecuta `claude`.

Lo primero es la **Fase previa**: Claude Code instala las skills y el MCP de Playwright. Cuando termine, cierra Claude Code (`/exit`) y vuelve a abrirlo con `claude`, porque las skills y el MCP nuevos solo se cargan al iniciar una sesión. Comprueba con `/mcp` que *playwright* aparece como conectado.

Después, en cada fase:

1. Activa el modo plan: `Shift+Tab` hasta ver *plan mode on*.
2. Pega el prompt de la fase (sección 9).
3. Revisa el plan. Puedes editarlo con `Ctrl+G` antes de aprobarlo.
4. Cuando termine, comprueba tú los puntos de **Hecho cuando** en el navegador.
5. Ejecuta `/clear` antes de empezar la siguiente fase.

---

## 0. Decisiones (marca con [x])

**Interfaz**
- [x] **A. Filament 5** (instala Livewire 4). Trae ya hechos tablas, filtros, formularios, etiquetas múltiples, acciones en bloque y modo oscuro. Es la opción con menos código y un diseño cuidado desde el principio, con aspecto de "panel de gestión". Las skills de diseño se notarán sobre todo en el tema y en las páginas propias (Clasificar, Generar).
- [ ] **B. Livewire 4 + Flux** (starter kit oficial). Da más libertad visual, pero las tablas, los filtros y la edición en bloque hay que construirlos. Saca más partido a las skills de diseño.
- [ ] **C. Inertia + Vue** (starter kit oficial). Es la opción con más libertad (una SPA) y aproximadamente el doble de trabajo. Es donde las skills de diseño y animación rinden más.

**Base de datos**
- [x] **SQLite**: un solo archivo, sin servidor y con copias de seguridad triviales.
- [ ] **PostgreSQL**: si quieres practicar con él. Las copias pasarían a hacerse con `pg_dump`.

> Claude Code: usa las opciones marcadas. Si algo de esta spec depende de la opción, aplica la variante correspondiente.

---

## 1. Qué es

App web **local**, de **un solo usuario** y **sin login**, para un DJ de drum and bass (con gusto principal en neuro, deep y techstep) con unos 150–300 temas en Windows. Sirve para catalogar los temas, filtrarlos con rangos y exportar playlists `.m3u8` para rekordbox.

Flujo de uso: **escanear carpeta → clasificar → filtrar y generar → exportar .m3u8 → importarlo en rekordbox** (este último paso es manual).

### Fuera de alcance
- **IMPORTANT: la app nunca modifica, mueve, renombra ni borra archivos de audio, ni escribe sus etiquetas. Solo los lee.**
- No toca la base de datos de rekordbox (`master.db`) ni genera XML de rekordbox.
- No hay usuarios, ni login, ni despliegue online.

---

## 2. Stack

- Laravel 13 (PHP 8.3+) y Pest para los tests.
- La interfaz según la sección 0. Con la opción A:
  ```
  composer require filament/filament:"~5.0"
  php artisan filament:install --panels
  ```
  En PowerShell hay que escribir `~` en vez de `^`, porque PowerShell se come el `^`.
- Lectura de etiquetas: `james-heinrich/getid3`.
- Opcional: **ffmpeg** para escuchar AIFF en el navegador. Ya está instalado en mi PC, pero fuera del PATH; su ruta se indica en `.env`.

### Herramientas de Claude Code (se instalan en la Fase previa)

| Herramienta | Para qué | Instalación |
|---|---|---|
| **taste-skill** (Leonxlnx) | Criterio de diseño de interfaces, para evitar el aspecto genérico de "hecho por IA" | `npx skills@latest add Leonxlnx/taste-skill -a claude-code --skill '*' -y` |
| **Skills de Emil Kowalski** | Animaciones y microinteracciones, revisión de animaciones y pruebas de estrés de la UI | `npx skills@latest add emilkowalski/skills -a claude-code --skill '*' -y` |
| **Playwright MCP** | Que Claude Code abra la app en un navegador, la use y haga capturas para verificar la UI | `claude mcp add playwright -- npx @playwright/mcp@latest` |

- Las skills se instalan a nivel de proyecto, en `.claude/skills/`, y se suben a git junto con el código.
- En Windows nativo, si `/mcp` muestra *playwright* como *Failed to connect*, hay que quitarlo con `claude mcp remove playwright` y volver a añadirlo con:
  ```
  claude mcp add playwright -- cmd /c npx @playwright/mcp@latest
  ```

---

## 3. Configuración

`config/music.php`, leyendo de `.env`:

```php
return [
    'default_folder'   => env('MUSIC_FOLDER', 'C:\Users\Ricardo\Music\Musikote\DNB'),
    'playlists_folder' => env('MUSIC_PLAYLISTS_FOLDER', storage_path('app/playlists')),
    'default_genre'    => 'Drum and Bass',
    'subfolder_as_subgenre' => true,
    'extensions'       => ['mp3', 'aiff', 'aif', 'wav', 'flac', 'm4a', 'ogg'],
    'browser_playable' => ['mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'flac' => 'audio/flac',
                           'm4a' => 'audio/mp4', 'ogg' => 'audio/ogg'],
    'ffmpeg'           => env('FFMPEG_BINARY'), // ruta completa a ffmpeg.exe
    'tag_categories'   => ['subgenre' => 'Subgénero', 'percussion' => 'Percusión'],
    'backups_to_keep'  => 10,
];
```

- **Rol en el set**: enum PHP `SetRole` con los valores `open` (Abrir), `body` (Cuerpo), `weight` (Peso) y `breather` (Respiro), cada uno con su etiqueta en español.
- **Ajustes editables desde la UI**: carpeta de música, género por defecto, subcarpeta como subgénero y carpeta de playlists. Se guardan en la tabla `settings`; los valores de `config` son solo el valor por defecto.
- Añadir una categoría de etiquetas (p. ej. `'vocal' => 'Vocal'`) solo requiere editar `config`, sin migraciones, y la categoría nueva aparece en todas las pantallas.

---

## 4. Modelo de datos

```
genres          id, name, timestamps
songs           id, path, path_key (unique), title, artist,
                genre_id (nullable, FK → genres, null on delete),
                song_year (smallint null), discovered_year (smallint null),
                energy (tinyint null, 1–5), set_role (string null, enum SetRole),
                bpm (decimal 5,2 null), musical_key (string null),
                duration (decimal null, segundos), notes (text null),
                missing (bool, default false), timestamps
tags            id, category (string), name, timestamps    unique(category, name)
song_tag        song_id, tag_id      PK compuesta, cascade on delete
playlists       id, name, filters (json null), timestamps
playlist_song   playlist_id, song_id, position   unique(playlist_id, song_id), cascade
filter_presets  id, name (unique), filters (json), timestamps
settings        key (PK), value
```

Reglas:
- Los nombres de géneros y etiquetas son **únicos sin distinguir mayúsculas**: "Halftime" y "halftime" son la misma etiqueta.
- `path_key` es la ruta normalizada para comparar: separadores `\` y todo en minúsculas (Windows no distingue mayúsculas). `path` guarda la ruta original tal cual.
- Percusión y subgénero son etiquetas múltiples: relación muchos a muchos `songs ↔ tags`, filtrada por `category`.

---

## 5. Funcionalidades

### 5.1 Escanear — servicio `LibraryScanner`
Se puede lanzar con el comando `php artisan music:scan` o con un botón en la UI.

1. Recorre la carpeta de forma recursiva. Ignora los archivos que empiezan por `._` y solo tiene en cuenta las extensiones de `config`.
2. Clasifica cada archivo y cada tema:
   - **nuevo**: su `path_key` no está en la base de datos;
   - **presente**;
   - **falta**: tema cuya ruta ya no existe, esté o no dentro de la carpeta escaneada;
   - **recuperado**: estaba marcado `missing` y vuelve a existir.
3. **Reubicación automática.** Se aplica cuando un tema que falta y *exactamente un* archivo nuevo tienen el mismo nombre sin extensión (sin distinguir mayúsculas), y ningún otro tema que falta comparte ese nombre. Se actualiza `path` y `path_key` y el tema conserva toda su clasificación. Casos típicos: conversión WAV→AIFF o mover el archivo a otra subcarpeta.
4. Los temas que siguen faltando pasan a `missing = true`; los recuperados vuelven a `false`.
5. Los archivos nuevos se dan de alta con los datos de la sección 5.2. Si un archivo falla al leerse, el escaneo no se para: el fallo se anota en el resumen.
6. Al terminar muestra un resumen con: archivos en la carpeta, nuevos, reubicados (lista ruta vieja → ruta nueva), no encontrados y errores.

### 5.2 Lectura de etiquetas — servicio `TagReader` (getID3)

| Campo | De dónde sale | Si no hay dato |
|---|---|---|
| title, artist | etiquetas | Del nombre de archivo: quitar el número de pista inicial (`^\d{1,3}[\s._-]+`) y separar por el primer `" - "` → *artista - título*. Si no hay `" - "`, todo el nombre es el título |
| song_year | date / year / TDRC / TYER / originaldate | Primer año entre 1950 y el año actual + 1; si no hay ninguno válido, null |
| bpm | TBPM / bpm | Solo se acepta si está entre 50 y 250 |
| musical_key | TKEY / initialkey / key | Buscar notación Camelot en los comentarios (COMM y TXXX:comment) con la expresión regular de debajo de la tabla. Mixed In Key escribe cosas como "8A - Energy 6" |
| duration | playtime_seconds | null |
| genre | Género por defecto de los ajustes | Si el ajuste está vacío, se usa el género de las etiquetas |
| discovered_year | Año de la fecha más antigua entre creación (`filectime`, que en Windows es la fecha de creación) y modificación (`filemtime`) | — |
| subgénero | Si la opción está activa, primer nivel de subcarpeta respecto a la carpeta escaneada (`DNB\JumpUp\x.mp3` → "JumpUp") | — |

Expresión regular para la key Camelot en comentarios (sin distinguir mayúsculas):
```
\b(1[0-2]|[1-9])\s?([AB])\b
```

### 5.3 Temas que faltan (reubicación manual)
- Lista de los temas con `missing = true`. Para cada uno, los 8 temas presentes con el nombre de archivo más parecido, ordenados de más a menos parecido y con el % de parecido.
- **Reubicar**: el tema que falta se queda con la ruta del tema elegido y ese duplicado se borra. Las etiquetas se unen, el género del duplicado solo se usa si el tema original no tenía, y la duración se toma del archivo nuevo.
- **Quitar**: borra el tema de la base de datos, nunca el archivo.

### 5.4 Clasificar (página)
- Barra de progreso: "X de Y temas con energía".
- Selector de vista: *Sin energía* (por defecto), *Sin percusión*, *Sin rol* o *Todas*, más un buscador por artista, título o ruta.
- Ficha del tema actual:
  - Reproductor y ruta del archivo.
  - Título y artista.
  - Energía (botones 1–5) y rol en el set (botones).
  - Género: selector con opción de crear uno nuevo.
  - Una selección múltiple por cada categoría de etiquetas, donde se crean etiquetas nuevas al escribirlas.
  - Año de la canción, año de descubrimiento, BPM, key y notas.
- Botones: **Guardar**, **Guardar y siguiente** (pasa al siguiente tema de la vista actual), **◀ Anterior** y **Siguiente ▶**.
- Atajos de teclado:
  - Teclas **1–5**: ponen la energía, siempre que el foco no esté en un campo de texto.
  - **Ctrl+Enter**: guardar y siguiente.

### 5.5 Reproductor
- La ruta `GET /songs/{song}/audio` sirve el archivo **a partir de la ruta guardada en la base de datos**; nunca acepta una ruta recibida por parámetro. Debe soportar peticiones Range (`response()->file()`) para poder avanzar en la barra.
- Formatos que el navegador no reproduce (AIFF):
  - Si `FFMPEG_BINARY` está configurado, se convierten a MP3 de 128 kbps la primera vez y se guarda en caché en `storage/app/previews/{id}-{mtime}.mp3`.
  - Si no, se muestra el aviso "Configura ffmpeg para escucharlo aquí".

### 5.6 Biblioteca
- Métricas arriba: total de temas, con energía, con percusión y archivos no encontrados.
- Tabla con: artista, título, género, etiquetas (badges por categoría), energía, rol, año, descubierta, BPM, key y falta. La ruta también, pero oculta por defecto.
- Búsqueda, filtros por columna y ordenación. La energía y el rol se editan directamente en la tabla.
- Acciones en bloque:
  - poner energía, rol o género;
  - añadir o quitar etiquetas;
  - quitar los temas de la app (no borra archivos).
- La edición completa de un tema usa el mismo formulario que Clasificar.

### 5.7 Generar playlist (página de inicio)

**Filtros**
- Género y rol, con selección múltiple en ambos.
- Por cada categoría de etiquetas:
  - **incluir** varias etiquetas, con un modo "Tiene alguna (OR)" o "Tiene todas (AND)";
  - **excluir** varias etiquetas (NOT).
- Rangos para energía, año de la canción, año de descubrimiento y BPM. **Un rango que abarca todo el mínimo–máximo de la biblioteca no filtra**, de modo que también entran los temas sin ese dato.
- "Incluir temas cuyo archivo falta": desactivado por defecto.
- Contador en vivo: "**N** de M temas cumplen los filtros".

**Generación**
- Nº de temas (0 = todos). Si cumplen más temas que N, se eligen al azar.
- Orden: Aleatorio, Energía ↑, Energía ↓, Año de la canción ↑, Año de descubrimiento ↑ o BPM ↑. Los empates quedan en orden aleatorio y los temas sin dato van al final.
- **Generar** crea una playlist en borrador con los temas ya ordenados. Volver a pulsarlo da otra combinación.
- En la playlist se puede:
  - quitar temas y reordenarlos arrastrando;
  - poner nombre (por defecto "Set AAAA-MM-DD");
  - ver la duración total y un **gráfico de la curva de energía** por posición.
- **Presets**: guardar el estado de los filtros con un nombre, cargarlo o borrarlo.
- **Historial**: lista de las playlists generadas, que se pueden reabrir, reexportar o borrar.

**Lógica — servicio `SongFilter`** (devuelve un query builder):
```
incluir + alguna:  whereHas('tags', categoría = c y nombre IN (...))
incluir + todas:   whereHas('tags', categoría = c y nombre IN (...), '=', nº de etiquetas elegidas)
excluir:           whereDoesntHave('tags', categoría = c y nombre IN (...))
rango:             whereBetween(campo, [min, max])   solo si NO es el rango completo
faltantes:         where('missing', false)          salvo que se pida incluirlos
```

### 5.8 Exportar M3U8 — servicio `M3u8Exporter`
Formato exacto:
```
#EXTM3U
#EXTINF:<segundos redondeados, o -1 si no se sabe>,<Artista> - <Título>
<ruta absoluta tal cual está en la base de datos>
```
- UTF-8 sin BOM y saltos de línea CRLF.
- Nombre de archivo saneado: se eliminan `<>:"/\|?*` y los caracteres de control.
- Botones:
  - **Guardar .m3u8**: lo guarda en la carpeta de playlists configurada y muestra la ruta final;
  - **Descargar .m3u8**.
- Texto de ayuda en la UI: "Arrastra el .m3u8 a *Playlists* en rekordbox (o *Archivo → Importar → Importar playlist*)".

### 5.9 Etiquetas y géneros
- Listado por categoría con el nº de temas de cada etiqueta.
- Crear, renombrar y borrar.
- Renombrar una etiqueta a un nombre que ya existe en la misma categoría la **fusiona**: se mueven sus relaciones a la existente y se borra la vieja.
- Debe poder cambiarse solo las mayúsculas ("halftime" → "Halftime").

### 5.10 Copias de seguridad (solo con SQLite)
- Una vez al día, en la primera petición del día: `VACUUM INTO 'storage/app/backups/music_AAAA-MM-DD.sqlite'`. Se conservan las 10 últimas.
- Botón **Copia ahora** y la ruta de la carpeta de copias, visibles en la página de ajustes.

### 5.11 Importar la versión Python — `php artisan music:import-legacy {ruta a musica.db}`
Abre la base de datos vieja **en solo lectura** mediante una conexión SQLite extra en `config/database.php`.

| Python (`musica.db`) | Laravel |
|---|---|
| `canciones.ruta`, `titulo`, `artista` | `songs.path`, `title`, `artist` |
| `anio_cancion` / `anio_descubrimiento` | `song_year` / `discovered_year` |
| `energia` | `energy` |
| `funcion` (Abrir/Cuerpo/Peso/Respiro) | `set_role` (open/body/weight/breather) |
| `bpm`, `tonalidad`, `duracion`, `notas`, `falta` | `bpm`, `musical_key`, `duration`, `notes`, `missing` |
| `generos.nombre` + `canciones.genero_id` | `genres` + `songs.genre_id` |
| `etiquetas(categoria, nombre)` con `subgenero`/`percusion` | `tags(category, name)` con `subgenre`/`percussion` |
| `cancion_etiqueta` | `song_tag` |
| `ajustes`: `carpeta_musica`, `carpeta_playlists`, `genero_defecto`, `subcarpeta_subgenero` | `settings` |

- La importación es idempotente: si un `path_key` ya existe, se actualiza en lugar de duplicarse.
- Los presets no se importan.

---

## 6. Diseño

- Modo **oscuro por defecto**, con opción de modo claro, y color principal **violeta**. En Filament: `->colors(['primary' => Color::Violet])` y `->defaultThemeMode(ThemeMode::Dark)`.
- **Energía** como badge con una escala de color 1→5 (azul, verde, amarillo, naranja, rojo). Es la misma escala en tablas, fichas y en el gráfico.
- **Rol en el set**: un color fijo por rol. **Etiquetas**: badges con un color por categoría.
- Tablas compactas, números tabulares y la ruta en tipografía monoespaciada, truncada por el centro.
- Navegación lateral en este orden: **Generar playlist** (inicio), Clasificar, Biblioteca, Playlists, Escanear, Etiquetas y Ajustes.
- La app se sirve en la raíz (`/`, en Filament `->path('')`) y sin login: se quitan `->login()` y la autenticación del panel. Si eso da problemas, se crea un único usuario con la sesión recordada.
- Todos los textos de la interfaz en **español**.
- Estados vacíos con indicación clara, p. ej. "Aún no hay temas → Escanear carpeta".

---

## 7. Reglas para Claude Code

- **IMPORTANT: los archivos de audio son de solo lectura** (ver la sección 1).
- Código, clases, tablas y columnas en inglés; textos de la interfaz en español.
- La lógica va en servicios (`app/Services`), independientes de la UI, que se limita a llamarlos.
- Cada servicio tiene tests Pest. Para las pruebas, genera audios de 1 s con etiquetas (con ffmpeg) y guárdalos en `tests/Fixtures`.
- Al terminar cada fase: ejecutar `php artisan test`, enseñarme la salida y hacer un commit con un mensaje descriptivo.
- Entorno Windows:
  - rutas con `\` y comparaciones sin distinguir mayúsculas;
  - nombres de archivo con tildes y ñ (UTF-8);
  - en Composer desde PowerShell, `"~X.Y"` en vez de `^`.
- No añadir más dependencias que las de la sección 2 sin preguntarme.
- Si algo de esta spec es ambiguo o choca con Laravel 13 o con la opción elegida, pregunta antes de improvisar.

**Diseño y verificación con las herramientas de la Fase previa**
- Al construir o cambiar pantallas, consulta **taste-skill** (`design-taste-frontend`, o `minimalist-ui` para un estilo limpio) junto con la sección 6.
- Para transiciones y microinteracciones, usa las **skills de Emil** (`emil-design-eng`, `animate`, `review-animations`). Es una herramienta de trabajo, así que las animaciones deben ser cortas y sutiles y respetar `prefers-reduced-motion`.
- Al cerrar cada fase con UI, usa **`break-ui`** para probar las pantallas con datos extremos: nombres muy largos, listas vacías, 500 temas.
- **El stack de la sección 0 manda sobre las skills.** No cambies de framework ni añadas librerías de UI aunque una skill lo sugiera (p. ej. `pick-ui-library`). Con Filament, aplica el diseño mediante su tema personalizado y las vistas de las páginas propias, sin sustituir sus componentes.
- Ignora las skills que no aplican a este proyecto (Swift, Expo/React Native, generación de imágenes, Stitch).
- **Verificación visual con Playwright MCP.** En cada fase con UI, abre la app en el navegador y recorre el flujo de los puntos "Hecho cuando". Haz capturas a 1440 px de ancho en modo oscuro y en modo claro, revísalas contra la sección 6, corrige lo que no cuadre y enséñame las capturas finales.
- La Fase 0 crea un `CLAUDE.md` **corto**: comandos del proyecto, estas reglas y la frase "La especificación completa está en SPEC.md".

---

## 8. Fases

**Fase previa — Herramientas de Claude Code**
Instala las dos colecciones de skills y el MCP de Playwright con los comandos de la sección 2. Comprueba que existen las carpetas de las skills en `.claude/skills/` y que `claude mcp list` muestra *playwright*. Haz commit de `.claude/skills/`.
*Hecho cuando:* tras reiniciar Claude Code, `/mcp` muestra *playwright* conectado y Claude Code sabe decirme qué skills de diseño tiene disponibles.

**Fase 0 — Base**
Panel instalado según la opción elegida, sin login y en la raíz, con el tema de la sección 6. Además: `config/music.php`, enum `SetRole`, `CLAUDE.md`, `git init` y primer commit.
*Hecho cuando:* abro la app, veo el panel vacío en modo oscuro con la navegación, y `php artisan test` pasa.

**Fase 1 — Datos y servicios**
Migraciones, modelos, relaciones y factories. Servicios `TagReader`, `LibraryScanner` y `M3u8Exporter`, todavía sin UI. Comando `music:scan`.
*Hecho cuando:* pasan los tests de:
- parseo de "01 - Artista - Título";
- Camelot sacado del comentario "5A - Energy 7";
- año fuera de rango ignorado;
- reubicación por mismo nombre (mp3 → aiff);
- subcarpeta → subgénero;
- formato exacto del M3U8 con tildes.

Además, `php artisan music:scan` sobre mi carpeta da un resumen correcto.

**Fase 2 — Escanear, etiquetas y ajustes**
Página Escanear (ajustes, botón, resumen y temas que faltan con reubicación manual), gestión de etiquetas y géneros con renombrar y fusionar, y página de Ajustes.
*Hecho cuando:*
- escaneo desde la UI;
- renombro "Halftime" a "Half-time" y luego la fusiono con otra;
- reubico a mano un archivo que renombré.

**Fase 3 — Biblioteca y reproductor**
Tabla, edición, acciones en bloque, ruta de audio con Range y preview de AIFF con ffmpeg.
*Hecho cuando:*
- edito la energía directamente en la tabla;
- añado una etiqueta a 10 temas a la vez;
- escucho un MP3 y salto a mitad del tema;
- escucho un AIFF con ffmpeg configurado.

**Fase 4 — Clasificar**
Página con la cola de temas, la ficha y los atajos.
*Hecho cuando:* clasifico 5 temas seguidos solo con el teclado (1–5 + Ctrl+Enter) y la barra de progreso avanza.

**Fase 5 — Generar y exportar**
`SongFilter`, página Generar, playlists, presets, exportación, historial y gráfico.
*Hecho cuando:*
- pasan los tests de `SongFilter`: OR, AND, NOT, rangos, que el rango completo no filtre y que se excluyan los temas que faltan;
- genero 20 temas ordenados por energía, quito uno, reordeno y guardo el `.m3u8`;
- rekordbox importa ese `.m3u8` con las rutas correctas.

**Fase 6 — Copias e importación**
Copias diarias, botón Copia ahora y comando `music:import-legacy`.
*Hecho cuando:* existe la copia del día, e importo el `musica.db` de la versión Python y veo mis clasificaciones.

---

## 9. Prompts para pegar en Claude Code

**Primera sesión: Fase previa** (modo normal, no hace falta el modo plan):
```
Lee @SPEC.md entero y haz la Fase previa: instala las skills y el MCP de Playwright
con los comandos de la sección 2. Si un comando pide confirmación o falla, dime qué pasa.
Cuando acabes, recuérdame que reinicie Claude Code.
```
Después: `/exit`, vuelve a abrir con `claude` y comprueba `/mcp`.

**Segunda sesión: Fase 0** (en modo plan):
```
Lee @SPEC.md entero y usa las opciones marcadas en la sección 0.
Antes de tocar nada, dime si hay algo ambiguo o que choque con Laravel 13 o Filament 5.
Después planifica y ejecuta la Fase 0.
```

**Cada fase siguiente** (tras `/clear`, en modo plan):
```
Lee @SPEC.md y haz la Fase N. Cumple las reglas de la sección 7, incluidas las de diseño.
Al terminar ejecuta los tests y enséñame la salida. Si la fase tiene UI, verifícala con
Playwright y enséñame las capturas. Comprueba uno a uno los puntos de "Hecho cuando".
```

**Revisión al cerrar cada fase** (opcional):
```
/code-review
```

---

## Ideas para más adelante (no implementar ahora)
- Importar BPM, key y cues desde el XML de colección de rekordbox.
- Detectar duplicados y posibles transcodes (MP3 de baja calidad real).
- Sugerir el siguiente tema por key Camelot compatible y BPM cercano.
- Estadísticas de la biblioteca: distribución de energía, de años, etc.
