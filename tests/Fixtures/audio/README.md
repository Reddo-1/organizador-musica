# Audios de prueba

Tonos de 1 s a 440 Hz, mono, generados con ffmpeg 8.1. Los tests copian estos archivos a una
carpeta temporal y trabajan sobre las copias.

```sh
FF="ffmpeg -y -f lavfi -i sine=frequency=440:duration=1 -ac 1 -ar 22050 -fflags +bitexact -flags:a +bitexact"
$FF -c:a libmp3lame -b:a 32k -id3v2_version 4 -metadata title="Sombra Eléctrica" -metadata artist="Kanine" -metadata date="2019-03-01" -metadata TBPM=174 -metadata TKEY=8A -metadata genre="Neurofunk" -metadata comment="Mezcla para cerrar" tagged-v24.mp3
$FF -c:a libmp3lame -b:a 32k -id3v2_version 3 -metadata title="Ruido de Fondo" -metadata artist="Phibes" -metadata date="2016" -metadata TBPM=172 -metadata TKEY=11B -metadata genre="Techstep" tagged-v23.mp3
$FF -c:a libmp3lame -b:a 32k -id3v2_version 0 -write_id3v1 0 untagged.mp3
$FF -c:a libmp3lame -b:a 32k -id3v2_version 3 -metadata title="Sin Key" -metadata artist="Rova" -metadata comment="5A - Energy 7" camelot-comment.mp3
$FF -c:a libmp3lame -b:a 32k -id3v2_version 3 -metadata title="Año Raro" -metadata artist="Gladde Paling" -metadata date="1890" -metadata TBPM=400 old-year.mp3
$FF -c:a pcm_s16be -write_id3v2 1 -id3v2_version 3 -metadata title="Sombra Eléctrica" -metadata artist="Kanine" -metadata date="2019" -metadata TBPM=174 -metadata TKEY=8A tagged.aiff
$FF -c:a flac -metadata TITLE="Bajo Cero" -metadata ARTIST="MaySev" -metadata DATE="2021" -metadata BPM=170 -metadata INITIALKEY=4A -metadata GENRE="Deep" tagged.flac
$FF -c:a aac -b:a 32k -movflags +use_metadata_tags -metadata title="Túnel" -metadata artist="Frannabik" -metadata date="2018" -metadata BPM=174 -metadata initialkey=6A tagged.m4a
$FF -c:a libvorbis -q:a 0 -metadata TITLE="Niebla" -metadata ARTIST="LSB" -metadata DATE="2015" -metadata BPM=175 -metadata INITIALKEY=9B tagged.ogg
$FF -c:a pcm_s16le -metadata title="Pulso" -metadata artist="AirGlo" -metadata date="2022" -metadata comment="2B" tagged.wav
```

ffmpeg escribe el comentario de los MP3 como `TXXX:comment` (lo usa `camelot-comment.mp3`).
Mixed In Key escribe un `COMM`, que ffmpeg no sabe escribir, así que `camelot-comm.mp3` es una copia
de `untagged.mp3` con una etiqueta ID3v2.3 escrita una sola vez con el escritor de getID3: título
"Bajo Tierra", artista "Ekko & Sidetrack" y `COMM` "8A - Energy 6". La app nunca escribe etiquetas.
