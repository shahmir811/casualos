{{-- Full-screen branded splash shown only when the portal is launched as an
     installed PWA (standalone display mode) — a plain browser tab never sees
     this. The OS-generated splash (small icon centered on background_color)
     can't be resized via the manifest; this custom overlay is what actually
     delivers a full-bleed launch screen, and covers the gap while the page
     itself finishes loading. Faded out on window 'load', with a floor so it
     never flashes for less than MIN_VISIBLE_MS even on a fast load. --}}
<style>
    #pwa-splash {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 9999;
        background: #000;
    }
    html.pwa-standalone #pwa-splash {
        display: block;
    }
    #pwa-splash img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    #pwa-splash.pwa-splash-hide {
        opacity: 0;
        transition: opacity 0.35s ease;
        pointer-events: none;
    }
</style>
<script>
    (function () {
        var isStandalone = window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;
        if (isStandalone) {
            document.documentElement.classList.add('pwa-standalone');
        }
    })();
</script>

<div id="pwa-splash">
    <img src="{{ asset('images/pwa/splash.png') }}" alt="Casualite">
</div>

<script>
    (function () {
        var MIN_VISIBLE_MS = 500;
        var shownAt = Date.now();

        function dismissSplash() {
            var el = document.getElementById('pwa-splash');
            if (!el) return;
            var elapsed = Date.now() - shownAt;
            var wait = Math.max(0, MIN_VISIBLE_MS - elapsed);
            setTimeout(function () {
                el.classList.add('pwa-splash-hide');
                setTimeout(function () { el.style.display = 'none'; }, 400);
            }, wait);
        }

        if (document.readyState === 'complete') {
            dismissSplash();
        } else {
            window.addEventListener('load', dismissSplash);
        }
    })();
</script>
