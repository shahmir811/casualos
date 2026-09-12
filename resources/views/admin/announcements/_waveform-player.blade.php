{{--
    A previously-sent announcement's voice note. `$url` is resolved server-side
    (Storage::url()) since it's a fixed, already-public S3 object — no presign
    needed, same as the image gallery above. The `waveformPlayer()` Alpine
    factory (defined in index.blade.php's @push('scripts')) does the actual
    decode + playback.
--}}
<div class="mt-3" x-data="waveformPlayer(@js($url))" x-init="init()">
    <div class="flex items-center gap-3 bg-[#F5F5F7] rounded-full px-3 py-2">
        <button type="button" @click="toggle()" :disabled="loading || error"
                class="w-8 h-8 rounded-full bg-[#0071E3] text-white flex items-center justify-center flex-shrink-0 disabled:opacity-50">
            <svg x-show="!playing" class="w-3.5 h-3.5 ml-0.5" fill="currentColor" viewBox="0 0 20 20"><path d="M6 4l12 6-12 6V4z"/></svg>
            <svg x-show="playing" x-cloak class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M6 4h3v12H6zM11 4h3v12h-3z"/></svg>
        </button>

        <div class="flex-1 min-w-0 h-7 flex items-center" x-ref="bars" @click="seekFromClick($event)"
             :class="!loading && !error ? 'cursor-pointer gap-[2px]' : ''">
            <span x-show="loading" x-cloak class="text-[#86868B] text-xs">Loading voice note…</span>
            <span x-show="error" x-cloak class="text-[#FF3B30] text-xs">Couldn't load voice note.</span>
            <template x-if="!loading && !error">
                <template x-for="(peak, i) in peaks" :key="i">
                    <div class="w-[3px] rounded-full flex-shrink-0"
                         :style="'height:' + Math.max(4, peak * 28) + 'px'"
                         :class="(i / peaks.length) * 100 <= progressPct ? 'bg-[#0071E3]' : 'bg-[#D2D2D7]'">
                    </div>
                </template>
            </template>
        </div>

        <span class="text-[#86868B] text-xs tabular-nums flex-shrink-0" x-text="formatAudioTime(playing ? currentTime : duration)"></span>
    </div>
</div>
