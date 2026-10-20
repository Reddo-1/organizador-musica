{{--
    Biblioteca's player: one song at a time, fixed at the bottom so the table stays usable while it
    plays. wire:ignore keeps the audio going when Livewire re-renders the table.
--}}
<div wire:ignore>
    <div
        class="app-dock"
        x-data="{
            id: null,
            label: '',
            retry: null,
            init() {
                Alpine.store('appDock', { id: null, playing: false })
            },
            play(song) {
                const audio = this.$refs.audio
                if (this.id === song.id) {
                    audio.paused ? audio.play() : audio.pause()
                    return
                }
                this.id = song.id
                this.label = song.label
                this.retry = song.retry
                Alpine.store('appDock').id = song.id
                audio.src = song.src
                audio.play()
            },
            failed() {
                {{-- A browser may know the container but not the codec (ALAC in .m4a): retry through ffmpeg. --}}
                if (this.retry) {
                    const retry = this.retry
                    this.retry = null
                    this.$refs.audio.src = retry
                    this.$refs.audio.play()
                }
            },
            close() {
                this.$refs.audio.pause()
                this.$refs.audio.removeAttribute('src')
                this.id = null
                Alpine.store('appDock').id = null
            },
        }"
        x-on:app-dock-play.window="play($event.detail)"
        x-show="id !== null"
        x-cloak
        x-transition:enter="app-dock-enter"
        x-transition:enter-start="app-dock-from"
        x-transition:leave="app-dock-leave"
        x-transition:leave-end="app-dock-from"
        role="region"
        aria-label="Reproductor"
    >
        <span class="app-dock-label" x-text="label"></span>
        <audio
            x-ref="audio"
            class="app-audio"
            controls
            preload="none"
            x-on:play="Alpine.store('appDock').playing = true"
            x-on:pause="Alpine.store('appDock').playing = false"
            x-on:ended="Alpine.store('appDock').playing = false"
            x-on:error="failed()"
        ></audio>
        <button type="button" class="app-dock-close" x-on:click="close()" aria-label="Cerrar el reproductor" title="Cerrar">
            <x-filament::icon icon="heroicon-m-x-mark" class="app-listen-icon" />
        </button>
    </div>
</div>
