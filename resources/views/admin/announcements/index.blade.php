@extends('layouts.app')
@section('title', 'Announcements')
@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Announcements</h1>
    <p class="text-[#6E6E73] text-sm mt-1">Post an update to the customer app's Timeline. Every customer gets a push notification.</p>
</div>

<div class="max-w-3xl mx-auto">

@if(session('success'))
<div class="mb-5 px-4 py-3 rounded-xl text-sm font-medium" style="background:#F0FFF4; color:#15803D; border:1px solid #BBF7D0;">
    {{ session('success') }}
</div>
@endif

@if($errors->any())
<div class="mb-5 px-4 py-3 rounded-xl text-sm" style="background:#FFF0EF; color:#FF3B30; border:1px solid #FFCDD0;">
    <ul class="list-disc list-inside space-y-0.5">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
</div>
@endif

{{-- Compose — styled like a tweet composer --}}
<div class="card p-4 mb-5">
    <form id="announcement-form" method="POST" action="{{ route('announcements.store') }}"
          enctype="multipart/form-data"
          x-data="announcementComposer({
              presignUrl: '{{ route('announcements.audio.presign') }}',
              csrfToken: '{{ csrf_token() }}',
          })"
          class="flex gap-3">
        @csrf

        <div class="w-11 h-11 rounded-full bg-[#0071E3] flex items-center justify-center flex-shrink-0">
            <svg width="20" height="20" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="28" cy="28" r="18" stroke="white" stroke-width="1.5"/>
                <line x1="36" y1="18" x2="36" y2="46" stroke="white" stroke-width="1.5"/>
                <line x1="36" y1="46" x2="50" y2="46" stroke="white" stroke-width="1.5"/>
            </svg>
        </div>

        <div class="flex-1 min-w-0">
            <input type="text" name="title" value="{{ old('title') }}" required maxlength="255"
                class="w-full border-0 p-0 text-[17px] font-semibold text-[#1D1D1F] placeholder-[#AEAEB2] focus:ring-0 focus:outline-none"
                placeholder="Headline — e.g. New Catalogue: ISHQIA">
            @error('title')
                <p class="mt-1 text-[#FF3B30] text-xs">{{ $message }}</p>
            @enderror

            <textarea name="body" required rows="2"
                class="w-full border-0 p-0 mt-1.5 text-[15px] text-[#1D1D1F] placeholder-[#AEAEB2] resize-none focus:ring-0 focus:outline-none"
                placeholder="What's happening at Casual Lite?"
                oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'">{{ old('body') }}</textarea>
            @error('body')
                <p class="mt-1 text-[#FF3B30] text-xs">{{ $message }}</p>
            @enderror

            {{-- Image previews --}}
            <div x-show="previews.length" x-cloak class="relative mt-3">
                <div class="grid gap-2" :class="previews.length === 1 ? 'grid-cols-1' : 'grid-cols-2'">
                    <template x-for="(src, i) in previews" :key="i">
                        <img :src="src" alt="Preview"
                            class="w-full object-cover rounded-2xl border border-[#E8E8ED]"
                            :class="previews.length === 1 ? 'max-h-64' : 'h-40'">
                    </template>
                </div>
                <button type="button" @click="clearAll()"
                    class="absolute top-2 right-2 w-7 h-7 rounded-full bg-[#1D1D1F]/70 text-white flex items-center justify-center hover:bg-[#1D1D1F]">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            @error('images')
                <p class="mt-1 text-[#FF3B30] text-xs">{{ $message }}</p>
            @enderror
            @error('images.*')
                <p class="mt-1 text-[#FF3B30] text-xs">{{ $message }}</p>
            @enderror

            {{-- Voice note --}}
            <input type="hidden" name="audio_key" :value="audioKey">
            <input type="hidden" name="audio_original_filename" :value="audioOriginalFilename">

            <div x-show="audioStatus === 'uploading'" x-cloak class="mt-3 bg-[#F5F5F7] rounded-full px-4 py-2.5 flex items-center gap-3">
                <div class="flex-1 h-1.5 bg-[#E8E8ED] rounded-full overflow-hidden">
                    <div class="h-full bg-[#0071E3] rounded-full transition-all" :style="'width:' + audioProgress + '%'"></div>
                </div>
                <span class="text-[#6E6E73] text-xs flex-shrink-0" x-text="audioProgress + '%'"></span>
            </div>

            <div x-show="audioStatus === 'recording'" x-cloak class="mt-3 bg-[#FFF0EF] rounded-full pl-4 pr-2 py-2 flex items-center gap-3">
                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                      :class="recordingPaused ? 'bg-[#AEAEB2]' : 'bg-[#FF3B30] animate-pulse'"></span>

                {{-- Live waveform — bar heights track mic input volume in real time
                     (recordingLevels, sampled off an AnalyserNode), scrolling left as
                     new samples arrive on the right. Distinct from audioPeaks below,
                     which is decoded once from the finished file after upload. Sampling
                     freezes while paused, so the bars hold still rather than reacting
                     to ambient sound the recorder isn't actually capturing. --}}
                <div class="flex-1 min-w-0 h-7 flex items-center gap-x-px overflow-hidden">
                    <template x-for="(level, i) in recordingLevels" :key="i">
                        <div class="flex-1 min-w-0 max-w-[4px] rounded-full"
                             :class="recordingPaused ? 'bg-[#D2D2D7]' : 'bg-[#FF3B30]'"
                             :style="'height:' + Math.max(4, level * 28) + 'px'">
                        </div>
                    </template>
                </div>

                <span class="text-[#FF3B30] text-xs font-medium tabular-nums flex-shrink-0" x-text="formatAudioTime(recordingSeconds)"></span>
                <button type="button" @click="cancelRecording()" class="text-[#86868B] text-xs font-medium hover:text-[#1D1D1F] flex-shrink-0">Cancel</button>

                {{-- Pause/Resume — MediaRecorder.pause()/resume() append onto the
                     same chunk list, so stopping afterward produces one continuous
                     file with the paused gap simply skipped, not silence. --}}
                <button type="button" @click="togglePauseRecording()"
                        class="w-8 h-8 rounded-full border flex items-center justify-center flex-shrink-0 text-[#FF3B30] border-[#FF3B30]"
                        :title="recordingPaused ? 'Resume recording' : 'Pause recording'">
                    <svg x-show="!recordingPaused" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><rect x="5" y="4" width="3" height="12"/><rect x="12" y="4" width="3" height="12"/></svg>
                    <svg x-show="recordingPaused" x-cloak class="w-3.5 h-3.5 ml-0.5" fill="currentColor" viewBox="0 0 20 20"><path d="M6 4l12 6-12 6V4z"/></svg>
                </button>

                <button type="button" @click="stopRecording()" title="Stop recording"
                        class="w-8 h-8 rounded-full bg-[#FF3B30] text-white flex items-center justify-center flex-shrink-0">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><rect x="4" y="4" width="12" height="12" rx="2"/></svg>
                </button>
            </div>

            <p x-show="audioStatus === 'error'" x-cloak class="mt-2 text-[#FF3B30] text-xs" x-text="audioError"></p>
            @error('audio')
                <p class="mt-2 text-[#FF3B30] text-xs">{{ $message }}</p>
            @enderror

            <div x-show="audioStatus === 'ready'" x-cloak class="relative mt-3">
                <div class="flex items-center bg-[#F5F5F7] rounded-full px-3 py-2 pr-9">
                    <button type="button" @click="toggleAudioPreview()"
                            class="w-8 h-8 rounded-full bg-[#0071E3] text-white flex items-center justify-center flex-shrink-0 mr-3">
                        <svg x-show="!audioPlaying" class="w-3.5 h-3.5 ml-0.5" fill="currentColor" viewBox="0 0 20 20"><path d="M6 4l12 6-12 6V4z"/></svg>
                        <svg x-show="audioPlaying" x-cloak class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M6 4h3v12H6zM11 4h3v12h-3z"/></svg>
                    </button>

                    <div class="flex-1 min-w-0 h-7 flex items-center gap-x-px overflow-hidden cursor-pointer mr-3" @click="seekAudioPreview($event)">
                        <template x-for="(peak, i) in audioPeaks" :key="i">
                            {{-- flex-1 (not a fixed px width) so the bar row always exactly
                                 fills the available space instead of overflowing on narrow screens --}}
                            <div class="flex-1 min-w-0 max-w-[4px] rounded-full"
                                 :style="'height:' + Math.max(4, peak * 28) + 'px'"
                                 :class="(i / audioPeaks.length) * 100 <= audioProgressPct ? 'bg-[#0071E3]' : 'bg-[#D2D2D7]'">
                            </div>
                        </template>
                    </div>

                    <span class="text-[#86868B] text-xs tabular-nums flex-shrink-0" x-text="formatAudioTime(audioPlaying ? audioCurrentTime : audioDuration)"></span>
                </div>
                <button type="button" @click="removeAudio()"
                    class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-[#1D1D1F]/70 text-white flex items-center justify-center hover:bg-[#1D1D1F]">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="flex items-center justify-between flex-wrap gap-3 mt-3 pt-3 border-t border-[#F2F2F7]">
                <div class="flex items-center gap-1 flex-shrink-0">
                    <label class="w-9 h-9 rounded-full flex items-center justify-center text-[#0071E3] hover:bg-[#0071E3]/10 cursor-pointer transition-colors" title="Add images (optional, max 10MB each)">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6z" />
                            <circle cx="8.5" cy="8.5" r="1.5" />
                        </svg>
                        <input type="file" name="images[]" accept="image/*" multiple x-ref="imageInput" class="hidden"
                            @change="onFiles($event)">
                    </label>

                    <label class="w-9 h-9 rounded-full flex items-center justify-center text-[#0071E3] hover:bg-[#0071E3]/10 cursor-pointer transition-colors"
                           :class="(audioStatus === 'uploading' || audioStatus === 'recording') ? 'opacity-40 pointer-events-none' : ''"
                           title="Upload a voice note (optional)">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z" />
                        </svg>
                        {{--
                            No `accept` restriction on purpose. iOS's file picker turns
                            `accept="audio/*"` into a strict UTI filter applied before the
                            user ever taps a file — for some real-world audio exports
                            (confirmed with a WhatsApp-saved voice note) that filter silently
                            blocks selection even though the file is shown, not grayed out.
                            onAudioFile() below already validates the picked file is really
                            audio and rejects it with a clear message otherwise, and the
                            presign endpoint re-validates content_type server-side — so
                            dropping the native filter costs nothing and fixes the picker.
                        --}}
                        <input type="file" name="audio_file" x-ref="audioInput" class="hidden"
                            @change="onAudioFile($event)">
                    </label>

                    {{--
                        Records in-browser via MediaRecorder instead of requiring the
                        owner to save a WhatsApp voice note and pick it from Downloads.
                        On stop, the recorded Blob is wrapped in a File and handed to
                        the exact same uploadAudio() the file-picker above uses — same
                        presign, same S3 PUT, same waveform preview, same audio_key
                        field — so nothing downstream needs to know how the clip was
                        produced.
                    --}}
                    <button type="button" @click="startRecording()"
                            :disabled="audioStatus === 'uploading' || audioStatus === 'recording'"
                            class="w-9 h-9 rounded-full flex items-center justify-center text-[#0071E3] hover:bg-[#0071E3]/10 transition-colors"
                            :class="(audioStatus === 'uploading' || audioStatus === 'recording') ? 'opacity-40 pointer-events-none' : ''"
                            title="Record a voice note (optional)">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/></svg>
                    </button>
                </div>

                {{--
                    The Send button is type="button" and submits through the global
                    confirm modal rather than a plain type="submit" — this form (with
                    its title/body/image fields) is what actually gets submitted, so
                    formId points at it directly. Every other use of $store.confirm in
                    this codebase submits a separate hidden no-field form instead,
                    since proceed() just calls formId.submit() with no knowledge of
                    field contents — but this send genuinely needs the real form's
                    data, so pointing at it directly is correct here. Disabled while a
                    voice note is still uploading, since audio_key wouldn't be set yet.
                --}}
                <button type="button" class="btn-primary rounded-full px-5 py-2 text-sm w-full sm:w-auto text-center"
                        :disabled="audioStatus === 'uploading' || audioStatus === 'recording'"
                        :class="(audioStatus === 'uploading' || audioStatus === 'recording') ? 'opacity-50 cursor-not-allowed' : ''"
                        @click="$store.confirm.show({
                            title: 'Send Announcement',
                            message: 'Send this to every customer? This cannot be undone.',
                            formId: 'announcement-form',
                            confirmText: 'Send to All Customers'
                        })">
                    Post to Timeline
                </button>
            </div>
        </div>
    </form>
</div>

{{-- Timeline --}}
<div class="card divide-y divide-[#F2F2F7] overflow-hidden">
    @forelse($announcements as $announcement)
    <div class="p-4 flex gap-3 hover:bg-[#FAFAFA] transition-colors">
        <div class="w-11 h-11 rounded-full bg-[#0071E3] flex items-center justify-center flex-shrink-0">
            <svg width="20" height="20" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="28" cy="28" r="18" stroke="white" stroke-width="1.5"/>
                <line x1="36" y1="18" x2="36" y2="46" stroke="white" stroke-width="1.5"/>
                <line x1="36" y1="46" x2="50" y2="46" stroke="white" stroke-width="1.5"/>
            </svg>
        </div>

        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-1.5 text-[15px] flex-wrap">
                <span class="font-semibold text-[#1D1D1F]">Casual Lite</span>
                <span class="text-[#86868B]">·</span>
                <span class="text-[#86868B]" title="{{ $announcement->sent_at->format('M j, Y g:i A') }}">{{ $announcement->sent_at->diffForHumans() }}</span>
                <span class="text-[#86868B]">·</span>
                <span class="text-[#86868B] text-sm">by {{ $announcement->sentBy?->name ?? '—' }}</span>
            </div>

            <p class="font-semibold text-[#1D1D1F] text-[15px] leading-snug mt-0.5">{{ $announcement->title }}</p>
            <p class="text-[#1D1D1F] text-[15px] leading-relaxed mt-0.5 whitespace-pre-wrap">{{ $announcement->body }}</p>

            @if(!empty($announcement->image_paths))
            <div class="mt-3 grid gap-1 rounded-2xl overflow-hidden border border-[#E8E8ED] {{ count($announcement->image_paths) === 1 ? 'grid-cols-1' : 'grid-cols-2' }}">
                @foreach($announcement->image_paths as $path)
                <img src="{{ Storage::url($path) }}" alt=""
                     class="w-full object-cover {{ count($announcement->image_paths) === 1 ? 'max-h-96' : 'h-44' }}">
                @endforeach
            </div>
            @endif

            @if($announcement->audio_path)
            @include('admin.announcements._waveform-player', ['url' => Storage::url($announcement->audio_path)])
            @endif

            <div class="flex items-center gap-x-3 gap-y-1.5 mt-3 text-[#86868B] text-xs flex-wrap">
                <span class="flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1a4 4 0 100-8 4 4 0 000 8zm6 3.13a4 4 0 010 7.75M6 20.13a4 4 0 010-7.75" />
                    </svg>
                    {{ number_format($announcement->recipient_count) }} customers notified
                </span>
                <a href="{{ route('announcements.show', $announcement) }}" class="text-[#0066CC] font-medium hover:underline">
                    View Read Stats
                </a>
            </div>
        </div>
    </div>
    @empty
    <div class="text-center text-[#86868B] py-16 text-sm">No announcements sent yet.</div>
    @endforelse
</div>

<div class="mt-5">{{ $announcements->links() }}</div>

</div>

@endsection

@push('scripts')
<script>
/**
 * Downsamples a decoded AudioBuffer's first channel into `barCount` average-
 * amplitude peaks, normalized 0.12–1 (floor keeps silent bars visible as a
 * thin line rather than disappearing). Shared by the compose-form preview
 * (announcementComposer) and the sent-history player (waveformPlayer) so a
 * WhatsApp-style waveform never needs a server-side audio pipeline.
 */
function computeWaveformPeaks(audioBuffer, barCount) {
    const data = audioBuffer.getChannelData(0);
    const blockSize = Math.max(1, Math.floor(data.length / barCount));
    const peaks = [];
    for (let i = 0; i < barCount; i++) {
        let sum = 0;
        const start = i * blockSize;
        for (let j = 0; j < blockSize; j++) sum += Math.abs(data[start + j] || 0);
        peaks.push(sum / blockSize);
    }
    const max = Math.max(...peaks, 0.0001);
    return peaks.map(p => Math.max(0.12, p / max));
}

function formatAudioTime(sec) {
    if (!isFinite(sec) || sec < 0) return '0:00';
    const m = Math.floor(sec / 60);
    const s = Math.floor(sec % 60);
    return m + ':' + String(s).padStart(2, '0');
}

function announcementComposer({ presignUrl, csrfToken }) {
    return {
        previews: [],

        // idle | uploading | ready | error | recording
        audioStatus: 'idle',
        audioProgress: 0,
        audioError: '',
        audioKey: null,
        audioOriginalFilename: null,
        audioObjectUrl: null,
        audioEl: null,
        audioPlaying: false,
        audioCurrentTime: 0,
        audioDuration: 0,
        audioPeaks: [],

        recordingSeconds: 0,
        recordingPaused: false,
        mediaRecorder: null,
        mediaStream: null,
        recordedChunks: [],
        recordingCancelled: false,
        recordingTimerId: null,
        recordingLevels: [],
        recordingAudioCtx: null,
        recordingAnalyser: null,
        recordingLevelIntervalId: null,

        formatAudioTime,

        onFiles(e) {
            this.previews = Array.from(e.target.files).map(f => URL.createObjectURL(f));
        },
        clearAll() {
            this.previews = [];
            this.$refs.imageInput.value = '';
        },

        onAudioFile(e) {
            const file = e.target.files[0];
            if (!file) return;

            if (!file.type.startsWith('audio/')) {
                this.audioStatus = 'error';
                this.audioError = 'Please choose an audio file.';
                return;
            }

            this.uploadAudio(file);
        },

        async startRecording() {
            if (this.audioStatus === 'uploading' || this.audioStatus === 'recording') return;
            this.audioError = '';

            if (!navigator.mediaDevices || !window.MediaRecorder) {
                this.audioStatus = 'error';
                this.audioError = 'Voice recording is not supported in this browser.';
                return;
            }

            try {
                this.mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch (e) {
                this.audioStatus = 'error';
                this.audioError = 'Microphone access was denied.';
                return;
            }

            // audio/mp4 (AAC/M4A) is preferred over webm/ogg — iOS's AVFoundation
            // playback stack (used by the mobile app's voice-note player) can't
            // decode webm/Opus or ogg at all, so a voice note recorded in a
            // browser that supports mp4 recording (Chrome/Edge/Safari) needs to
            // stay in that format to play back on an iPhone. Only browsers with
            // no mp4 recording support (e.g. older Firefox) fall through to webm.
            const mimeType = ['audio/mp4', 'audio/webm', 'audio/ogg']
                .find(t => window.MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(t)) || '';

            this.recordedChunks = [];
            this.recordingCancelled = false;
            this.mediaRecorder = mimeType ? new MediaRecorder(this.mediaStream, { mimeType }) : new MediaRecorder(this.mediaStream);
            this.mediaRecorder.addEventListener('dataavailable', e => {
                if (e.data && e.data.size > 0) this.recordedChunks.push(e.data);
            });
            this.mediaRecorder.addEventListener('stop', () => {
                this.mediaStream.getTracks().forEach(t => t.stop());
                this.mediaStream = null;
                clearInterval(this.recordingTimerId);
                this.recordingTimerId = null;
                this.recordingSeconds = 0;
                this.recordingPaused = false;
                this.stopRecordingLevelMeter();

                if (this.recordingCancelled || !this.recordedChunks.length) {
                    this.audioStatus = 'idle';
                    return;
                }

                const blobType = this.mediaRecorder.mimeType || 'audio/webm';
                const extension = blobType.includes('mp4') ? 'm4a' : (blobType.includes('ogg') ? 'ogg' : 'webm');
                const blob = new Blob(this.recordedChunks, { type: blobType });
                const file = new File([blob], `voice-note-${Date.now()}.${extension}`, { type: blobType });
                this.uploadAudio(file);
            });

            this.mediaRecorder.start();
            this.audioStatus = 'recording';
            this.recordingSeconds = 0;
            this.recordingPaused = false;
            this.recordingTimerId = setInterval(() => {
                if (!this.recordingPaused) this.recordingSeconds++;
            }, 1000);
            this.startRecordingLevelMeter();
        },

        togglePauseRecording() {
            if (!this.mediaRecorder || this.mediaRecorder.state === 'inactive') return;
            if (this.recordingPaused) {
                this.mediaRecorder.resume();
                this.recordingPaused = false;
            } else {
                this.mediaRecorder.pause();
                this.recordingPaused = true;
            }
        },

        // Live waveform — taps the mic stream via an AnalyserNode (never
        // connected to the speakers, so there's no feedback loop) and
        // samples volume ~12x/sec into a scrolling bar row. Purely visual:
        // if AudioContext isn't available for any reason, recording still
        // works, it just shows flat bars.
        startRecordingLevelMeter() {
            this.recordingLevels = Array(40).fill(0.08);
            try {
                this.recordingAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const source = this.recordingAudioCtx.createMediaStreamSource(this.mediaStream);
                this.recordingAnalyser = this.recordingAudioCtx.createAnalyser();
                this.recordingAnalyser.fftSize = 256;
                this.recordingAnalyser.smoothingTimeConstant = 0.6;
                source.connect(this.recordingAnalyser);
            } catch (e) {
                this.recordingAnalyser = null;
                return;
            }

            const data = new Uint8Array(this.recordingAnalyser.frequencyBinCount);
            this.recordingLevelIntervalId = setInterval(() => {
                if (this.recordingPaused) return;
                this.recordingAnalyser.getByteFrequencyData(data);
                const avg = data.reduce((sum, v) => sum + v, 0) / data.length; // 0-255
                const level = Math.max(0.08, Math.min(1, avg / 90));
                this.recordingLevels = [...this.recordingLevels.slice(1), level];
            }, 80);
        },

        stopRecordingLevelMeter() {
            clearInterval(this.recordingLevelIntervalId);
            this.recordingLevelIntervalId = null;
            if (this.recordingAudioCtx) this.recordingAudioCtx.close();
            this.recordingAudioCtx = null;
            this.recordingAnalyser = null;
            this.recordingLevels = [];
        },

        stopRecording() {
            if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') this.mediaRecorder.stop();
        },

        cancelRecording() {
            this.recordingCancelled = true;
            this.stopRecording();
        },

        removeAudio() {
            if (this.audioEl) {
                this.audioEl.pause();
                this.audioEl = null;
            }
            if (this.audioObjectUrl) URL.revokeObjectURL(this.audioObjectUrl);

            this.audioStatus = 'idle';
            this.audioProgress = 0;
            this.audioError = '';
            this.audioKey = null;
            this.audioOriginalFilename = null;
            this.audioObjectUrl = null;
            this.audioPlaying = false;
            this.audioCurrentTime = 0;
            this.audioDuration = 0;
            this.audioPeaks = [];
            this.$refs.audioInput.value = '';
        },

        async uploadAudio(file) {
            this.audioStatus = 'uploading';
            this.audioProgress = 0;
            this.audioError = '';

            try {
                const uuid = crypto.randomUUID();
                const extension = (file.name.split('.').pop() || 'm4a').toLowerCase();
                const contentType = file.type || 'audio/mpeg';

                const presignRes = await fetch(presignUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ uuid, extension, content_type: contentType }),
                });
                if (!presignRes.ok) throw new Error('Could not get an upload URL.');
                const { url, headers, key } = await presignRes.json();

                await new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    xhr.open('PUT', url);
                    Object.entries(headers || {}).forEach(([k, v]) => xhr.setRequestHeader(k, v));
                    xhr.upload.onprogress = ev => {
                        if (ev.lengthComputable) this.audioProgress = Math.round((ev.loaded / ev.total) * 100);
                    };
                    xhr.onload = () => (xhr.status >= 200 && xhr.status < 300) ? resolve() : reject(new Error('Upload to storage failed.'));
                    xhr.onerror = () => reject(new Error('Upload to storage failed.'));
                    xhr.send(file);
                });

                this.audioKey = key;
                this.audioOriginalFilename = file.name;
                this.audioObjectUrl = URL.createObjectURL(file);

                this.audioEl = new Audio(this.audioObjectUrl);
                this.audioEl.addEventListener('loadedmetadata', () => { this.audioDuration = this.audioEl.duration; });
                this.audioEl.addEventListener('timeupdate', () => { this.audioCurrentTime = this.audioEl.currentTime; });
                this.audioEl.addEventListener('ended', () => { this.audioPlaying = false; this.audioCurrentTime = 0; });

                const buf = await file.arrayBuffer();
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const decoded = await ctx.decodeAudioData(buf);
                this.audioPeaks = computeWaveformPeaks(decoded, 40);
                if (!this.audioDuration) this.audioDuration = decoded.duration;
                ctx.close();

                this.audioStatus = 'ready';
            } catch (e) {
                this.audioStatus = 'error';
                this.audioError = e.message || 'Voice note upload failed.';
            }
        },

        toggleAudioPreview() {
            if (!this.audioEl) return;
            if (this.audioPlaying) {
                this.audioEl.pause();
                this.audioPlaying = false;
            } else {
                this.audioEl.play();
                this.audioPlaying = true;
            }
        },

        seekAudioPreview(e) {
            if (!this.audioEl || !this.audioDuration) return;
            const rect = e.currentTarget.getBoundingClientRect();
            const pct = Math.min(1, Math.max(0, (e.clientX - rect.left) / rect.width));
            this.audioEl.currentTime = pct * this.audioDuration;
        },

        get audioProgressPct() {
            return this.audioDuration ? (this.audioCurrentTime / this.audioDuration) * 100 : 0;
        },
    };
}

/**
 * Playback for an already-sent voice note (Storage::url(), fetched fresh —
 * no presigning needed since announcement media is public, same as images).
 * Decodes the whole file client-side to render WhatsApp-style waveform bars;
 * fine for voice-note-length clips, which is the only use case here.
 */
function waveformPlayer(url) {
    return {
        url,
        peaks: [],
        duration: 0,
        currentTime: 0,
        playing: false,
        loading: true,
        error: false,
        audio: null,

        formatAudioTime,

        init() {
            this.audio = new Audio(this.url);
            this.audio.preload = 'metadata';
            this.audio.addEventListener('loadedmetadata', () => { this.duration = this.audio.duration; });
            this.audio.addEventListener('timeupdate', () => { this.currentTime = this.audio.currentTime; });
            this.audio.addEventListener('ended', () => { this.playing = false; this.currentTime = 0; });
            this.load();
        },

        async load() {
            try {
                const res = await fetch(this.url);
                const buf = await res.arrayBuffer();
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const decoded = await ctx.decodeAudioData(buf);
                this.peaks = computeWaveformPeaks(decoded, 48);
                if (!this.duration) this.duration = decoded.duration;
                ctx.close();
            } catch (e) {
                this.error = true;
            } finally {
                this.loading = false;
            }
        },

        toggle() {
            if (this.playing) {
                this.audio.pause();
                this.playing = false;
            } else {
                this.audio.play();
                this.playing = true;
            }
        },

        seekFromClick(e) {
            if (!this.duration || this.loading || this.error) return;
            const rect = this.$refs.bars.getBoundingClientRect();
            const pct = Math.min(1, Math.max(0, (e.clientX - rect.left) / rect.width));
            this.audio.currentTime = pct * this.duration;
        },

        get progressPct() {
            return this.duration ? (this.currentTime / this.duration) * 100 : 0;
        },
    };
}
</script>
@endpush
