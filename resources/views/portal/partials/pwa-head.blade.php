{{-- Shared PWA meta tags + service worker registration for the customer portal.
     Included on both show.blade.php and dashboard.blade.php since either can be
     the first-paint document for /portal/{token} depending on login state, and
     both need identical PWA identity so installability doesn't depend on which
     page happened to be open when the customer installed. --}}
<link rel="manifest" href="{{ route('portal.manifest', $customer->portal_token) }}">
<meta name="theme-color" content="#1D1D1F">
<link rel="apple-touch-icon" href="{{ asset('images/pwa/apple-touch-icon-180.png') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Casualite">
<meta name="vapid-public-key" content="{{ config('services.vapid.public_key') }}">
<meta name="csrf-token" content="{{ csrf_token() }}">

<script>
    if ('serviceWorker' in navigator) {
        // Exposed on window so pushOptIn() (dashboard.blade.php) can await the
        // real registration outcome instead of only navigator.serviceWorker.ready,
        // which just reports "no active worker" with no reason why — this surfaces
        // the actual browser-thrown error (bad response, security error, etc.).
        window.swRegistration = new Promise((resolve, reject) => {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js', {
                    scope: '{{ route('portal.show', $customer->portal_token) }}',
                }).then(resolve).catch((err) => {
                    console.error('SW registration failed:', err);
                    reject(err);
                });
            });
        });
    }
</script>
