<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $catalogue->name }} — Casualite Booking Form</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">

    {{-- Open Graph tags for WhatsApp / social link previews --}}
    <meta property="og:type"        content="website">
    <meta property="og:url"         content="{{ url()->current() }}">
    <meta property="og:title"       content="{{ $catalogue->name }} — Casualite Booking Form">
    <meta property="og:description" content="Place your order for the {{ $catalogue->name }} catalogue.">
    @if ($catalogue->cover_photo_og ?? $catalogue->cover_photo)
    <meta property="og:image"        content="{{ Storage::url($catalogue->cover_photo_og ?? $catalogue->cover_photo) }}">
    <meta property="og:image:width"  content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:type"   content="image/jpeg">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body {
            font-family: 'SF Pro Text', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            background: #F2F2F7;
        }

        .section-card {
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }

        .field-label {
            font-size: 0.8125rem;
            font-weight: 600;
            color: #1D1D1F;
            margin-bottom: 0.5rem;
            display: block;
        }
        .field-required { color: #FF3B30; font-weight: 400; }

        .form-input {
            width: 100%;
            background: #F5F5F7;
            border: 1.5px solid transparent;
            border-radius: 10px;
            color: #1D1D1F;
            font-size: 0.9375rem;
            padding: 0.75rem 1rem;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
        }
        .form-input:focus {
            background: #fff;
            border-color: #0071E3;
            box-shadow: 0 0 0 3px rgba(0,113,227,0.12);
        }
        .form-input::placeholder { color: #AEAEB2; }

        /* Size row */
        .size-row {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.25rem;
            border-bottom: 1px solid #F2F2F7;
        }
        .size-row:last-child { border-bottom: none; }

        .size-label {
            flex: 1;
            font-size: 0.9375rem;
            color: #1D1D1F;
        }
        .size-label span {
            font-size: 0.75rem;
            color: #86868B;
            margin-left: 0.4rem;
        }

        .qty-wrap {
            display: flex;
            align-items: center;
            background: #F5F5F7;
            border: 1.5px solid transparent;
            border-radius: 10px;
            overflow: hidden;
            transition: border-color 0.15s, background 0.15s;
        }
        .qty-wrap:focus-within {
            background: #fff;
            border-color: #0071E3;
        }
        .qty-btn {
            width: 40px;
            height: 44px;
            flex-shrink: 0;
            font-size: 1.25rem;
            line-height: 1;
            color: #0071E3;
            background: transparent;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.1s;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }
        .qty-btn:hover { background: rgba(0,113,227,0.08); }
        .qty-btn:active { background: rgba(0,113,227,0.15); }
        .qty-field {
            width: 52px;
            text-align: center;
            border: none;
            outline: none;
            font-size: 1rem;
            font-weight: 700;
            color: #1D1D1F;
            background: transparent;
            padding: 0.5rem 0;
        }
        .qty-field::-webkit-inner-spin-button,
        .qty-field::-webkit-outer-spin-button { -webkit-appearance: none; }
        .qty-field { -moz-appearance: textfield; }

        /* Subtotal chip on size row */
        .size-subtotal {
            font-size: 0.8125rem;
            font-weight: 600;
            color: #3C3C43;
            min-width: 90px;
            text-align: right;
            padding-left: 0.75rem;
        }

        /* Submit */
        .submit-btn {
            width: 100%;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            padding: 1.05rem 1rem;
            cursor: pointer;
            transition: background 0.15s, opacity 0.15s, transform 0.1s;
            background: #1D1D1F;
            color: #fff;
        }
        .submit-btn:not(:disabled):hover { background: #3A3A3C; }
        .submit-btn:not(:disabled):active { transform: scale(0.985); }
        .submit-btn:disabled { opacity: 0.38; cursor: not-allowed; }

        /* Sticky total */
        .sticky-bar {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            background: rgba(255,255,255,0.88);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-top: 1px solid rgba(0,0,0,0.08);
            z-index: 50;
            padding: 0.85rem 1.25rem;
            transition: opacity 0.2s, transform 0.2s;
        }
    </style>
</head>
<body>

@php
    // Use catalogue cover photo, or fall back to the first design with a photo
    $coverPhoto = $catalogue->cover_photo
        ?? $catalogue->designs->firstWhere('photo', '!=', null)?->photo;
@endphp

{{-- ============================================================ --}}
{{-- SOLD-OUT SCREEN                                              --}}
{{-- ============================================================ --}}
@if($soldOut)

<div class="min-h-screen flex flex-col">

    {{-- Cover --}}
    @if($coverPhoto)
    <div style="width:100%;height:300px;overflow:hidden;background:#E5E5EA;">
        <img src="{{ Storage::url($coverPhoto) }}"
             alt="{{ $catalogue->name }}"
             style="width:100%;height:100%;object-fit:cover;object-position:top;display:block;filter:brightness(0.75);">
    </div>
    @else
    <div style="width:100%;height:200px;background:#1D1D1F;display:flex;align-items:center;justify-content:center;">
        <p style="color:#fff;font-size:1.5rem;font-weight:700;letter-spacing:0.05em;">{{ $catalogue->name }}</p>
    </div>
    @endif

    <div class="max-w-lg mx-auto px-4 w-full flex-1 flex flex-col items-center justify-start pt-6">

        {{-- Sold-out badge --}}
        <div style="display:inline-flex;align-items:center;gap:0.45rem;
                    background:#FFF0EF;border:1.5px solid #FFCDD0;
                    border-radius:999px;padding:0.4rem 1rem;margin-bottom:1.25rem;">
            <svg style="width:15px;height:15px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="#FF3B30" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            <span style="font-size:0.8125rem;font-weight:700;color:#FF3B30;letter-spacing:0.04em;text-transform:uppercase;">Sold Out</span>
        </div>

        {{-- Card --}}
        <div class="section-card px-6 py-6 w-full text-center mb-4">
            <h1 class="text-2xl font-bold tracking-tight text-[#1D1D1F] mb-2">{{ $catalogue->name }}</h1>
            <p class="text-[#6E6E73] text-sm leading-relaxed">
                This catalogue is no longer accepting orders.<br>
                All available pieces have been reserved, or the ordering period has ended.
            </p>
        </div>

        {{-- Help card --}}
        <div class="section-card px-5 py-4 w-full">
            <div style="display:flex;align-items:flex-start;gap:0.875rem;">
                <div style="width:38px;height:38px;flex-shrink:0;background:#F5F5F7;border-radius:10px;
                            display:flex;align-items:center;justify-content:center;margin-top:1px;">
                    <svg style="width:18px;height:18px;" fill="none" viewBox="0 0 24 24" stroke="#1D1D1F" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-[#1D1D1F] mb-0.5">Need help?</p>
                    <p class="text-xs text-[#6E6E73] leading-relaxed">
                        Contact the Casualite team directly if you believe this is an error or would like to be notified about the next collection.
                    </p>
                </div>
            </div>
        </div>

    </div>
</div>

@else
{{-- ============================================================ --}}
{{-- ORDER FORM                                                   --}}
{{-- ============================================================ --}}

{{-- Pass design data to Alpine --}}
@php
    $designsJson = $catalogue->designs->map(fn($d) => [
        'id'             => $d->id,
        'name'           => $d->name,
        'selling_price'   => (int) round((float) $d->selling_price),
        'discount_price' => $d->discount_price !== null
            ? (int) round((float) $d->discount_price)
            : (int) round((float) $d->selling_price),
    ])->values()->toJson();
    $numDesigns  = $catalogue->designs->count();
    $benchmark   = $catalogue->quantity_benchmark ?? 'null';
@endphp

<div x-data="orderCalc({{ $designsJson }}, {{ $numDesigns }}, {{ $benchmark }})" class="pb-28">

    {{-- ===== CUSTOMER NOT FOUND MODAL ===== --}}
    @if(session('customer_not_found'))
    <div id="notFoundModal"
         style="position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;
                display:flex;align-items:center;justify-content:center;
                padding:1.5rem;
                background:rgba(0,0,0,0.5);
                backdrop-filter:blur(5px);
                -webkit-backdrop-filter:blur(5px);">
        <div style="background:#fff;border-radius:20px;padding:2rem 1.75rem;
                    max-width:340px;width:100%;text-align:center;
                    box-shadow:0 24px 60px rgba(0,0,0,0.22);">

            {{-- Icon --}}
            <div style="width:60px;height:60px;background:#FFF0EF;border-radius:50%;
                        display:flex;align-items:center;justify-content:center;margin:0 auto 1.1rem;">
                <svg style="width:28px;height:28px;" fill="none" viewBox="0 0 24 24" stroke="#FF3B30" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
            </div>

            <h2 style="font-size:1.125rem;font-weight:700;color:#1D1D1F;margin-bottom:0.55rem;line-height:1.3;">
                Account Not Found
            </h2>
            <p style="font-size:0.875rem;color:#6E6E73;line-height:1.65;margin-bottom:1.6rem;">
                Your email address is not registered in our system.
                Please contact the <strong style="color:#1D1D1F;">Casualite admin</strong>
                to create your account before placing an order.
            </p>

            <button onclick="document.getElementById('notFoundModal').style.display='none'"
                style="width:100%;background:#1D1D1F;color:#fff;font-size:0.9375rem;
                       font-weight:600;border:none;border-radius:12px;
                       padding:0.875rem 1rem;cursor:pointer;
                       transition:background 0.15s;">
                OK, Got It
            </button>
        </div>
    </div>
    @endif

    {{-- ===== DUPLICATE ORDER MODAL ===== --}}
    @if(session('duplicate_order'))
    <div id="duplicateOrderModal"
         style="position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;
                display:flex;align-items:center;justify-content:center;
                padding:1.5rem;
                background:rgba(0,0,0,0.5);
                backdrop-filter:blur(5px);
                -webkit-backdrop-filter:blur(5px);">
        <div style="background:#fff;border-radius:20px;padding:2rem 1.75rem;
                    max-width:340px;width:100%;text-align:center;
                    box-shadow:0 24px 60px rgba(0,0,0,0.22);">

            {{-- Icon --}}
            <div style="width:60px;height:60px;background:#FFF5E6;border-radius:50%;
                        display:flex;align-items:center;justify-content:center;margin:0 auto 1.1rem;">
                <svg style="width:28px;height:28px;" fill="none" viewBox="0 0 24 24" stroke="#FF9500" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>

            <h2 style="font-size:1.125rem;font-weight:700;color:#1D1D1F;margin-bottom:0.55rem;line-height:1.3;">
                Order Already Placed
            </h2>
            <p style="font-size:0.875rem;color:#6E6E73;line-height:1.65;margin-bottom:1.6rem;">
                You have already placed an order for this catalogue.
                Only one order per catalogue is allowed.
                Please contact the <strong style="color:#1D1D1F;">Casualite admin</strong>
                if you need to make changes.
            </p>

            <button onclick="document.getElementById('duplicateOrderModal').style.display='none'"
                style="width:100%;background:#1D1D1F;color:#fff;font-size:0.9375rem;
                       font-weight:600;border:none;border-radius:12px;
                       padding:0.875rem 1rem;cursor:pointer;
                       transition:background 0.15s;">
                OK, Got It
            </button>
        </div>
    </div>
    @endif

    {{-- ===== COVER PHOTO ===== --}}
    @if($coverPhoto)
    <div style="width:100%;height:320px;overflow:hidden;background:#E5E5EA;">
        <img src="{{ Storage::url($coverPhoto) }}"
             alt="{{ $catalogue->name }}"
             style="width:100%;height:100%;object-fit:cover;object-position:top;display:block;">
    </div>
    @else
    <div style="width:100%;height:180px;background:#1D1D1F;display:flex;align-items:center;justify-content:center;">
        <p style="color:#fff;font-size:1.5rem;font-weight:700;letter-spacing:0.05em;">{{ $catalogue->name }}</p>
    </div>
    @endif

    <div class="max-w-lg mx-auto px-4">

        {{-- ===== TITLE ===== --}}
        <div class="section-card px-6 py-5 mt-4 mb-4">
            <h1 class="text-2xl font-bold tracking-tight text-[#1D1D1F] mb-0.5">{{ $catalogue->name }}</h1>
            <p class="text-[#6E6E73] text-sm">
                Casualite Booking Form &nbsp;·&nbsp; No. of Designs {{ $numDesigns }}
            </p>
        </div>

        {{-- ===== DESIGN THUMBNAILS ===== --}}
        @if($catalogue->designs->isNotEmpty())
        <div class="section-card px-5 py-4 mb-4">
            <p class="text-xs font-semibold tracking-widest uppercase text-[#86868B] mb-3">Designs in this Collection</p>
            <div style="display:flex;gap:10px;overflow-x:auto;padding-bottom:4px;-webkit-overflow-scrolling:touch;scrollbar-width:none;">
                @foreach($catalogue->designs as $design)
                <div style="flex-shrink:0;width:88px;text-align:center;">
                    <div style="width:88px;height:88px;border-radius:12px;overflow:hidden;background:#F5F5F7;border:1.5px solid #E8E8ED;margin-bottom:6px;">
                        @if($design->photo)
                            <img src="{{ Storage::url($design->photo) }}"
                                 alt="{{ $design->name }}"
                                 style="width:100%;height:100%;object-fit:cover;display:block;">
                        @else
                            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;">
                                <svg style="width:1.5rem;height:1.5rem;color:#C7C7CC;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        @endif
                    </div>
                    <p style="font-size:0.7rem;color:#1D1D1F;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $design->name }}</p>
                    <p style="font-size:0.65rem;color:#86868B;">PKR {{ lacs_format($design->selling_price, 0) }}</p>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ===== ERRORS ===== --}}
        @if($errors->any())
        <div class="mb-4 bg-[#FFF0EF] border border-[#FFCDD0] rounded-2xl px-5 py-4">
            <p class="text-[#FF3B30] text-sm font-semibold mb-1">Please fix the following:</p>
            <ul class="text-[#FF3B30] text-sm list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('order.submit', $catalogue->order_token) }}" class="space-y-4">
            @csrf

            {{-- ===== CUSTOMER DETAILS ===== --}}
            <div class="section-card px-5 py-5">
                <p class="text-xs font-semibold tracking-widest uppercase text-[#86868B] mb-4">Your Details</p>

                <div class="space-y-3">
                    <div>
                        <label class="field-label">Customer Name <span class="field-required">*</span></label>
                        <input type="text" name="customer_name" value="{{ old('customer_name') }}" required
                            class="form-input" placeholder="Your full name" autocomplete="name">
                    </div>
                    <div>
                        <label class="field-label">Email Address <span class="field-required">*</span></label>
                        <input type="email" name="submitted_email" value="{{ old('submitted_email') }}" required
                            class="form-input" placeholder="you@example.com" autocomplete="email">
                    </div>
                    <div>
                        <label class="field-label">City <span class="field-required">*</span></label>
                        <input type="text" name="city" value="{{ old('city') }}" required
                            class="form-input" placeholder="e.g. Lahore, Karachi" autocomplete="address-level2">
                    </div>
                </div>
            </div>

            {{-- ===== SIZE QUANTITIES ===== --}}
            <div class="section-card">
                <div class="px-5 pt-5 pb-3">
                    <p class="text-xs font-semibold tracking-widest uppercase text-[#86868B] mb-1">Quantity Per Size</p>
                    <p class="text-[#6E6E73] text-xs leading-relaxed">
                        Each quantity applies to <strong class="text-[#1D1D1F]">all {{ $numDesigns }} designs</strong>.
                        E.g. entering 2 for Medium means 2 Medium pieces from every design.
                    </p>
                </div>

                @php
                $sizes = [
                    'xs' => ['label' => 'Extra Small', 'tag' => 'XS'],
                    's'  => ['label' => 'Small',       'tag' => 'S'],
                    'm'  => ['label' => 'Medium',      'tag' => 'M'],
                    'l'  => ['label' => 'Large',       'tag' => 'L'],
                    'xl' => ['label' => 'Extra Large', 'tag' => 'XL'],
                ];
                @endphp

                @foreach($sizes as $key => $size)
                <div class="size-row">
                    <div class="size-label">
                        {{ $size['label'] }}
                        <span>({{ $size['tag'] }})</span>
                    </div>

                    {{-- Stepper --}}
                    <div class="qty-wrap">
                        <button type="button" class="qty-btn"
                            @click="decrement('{{ $key }}')">−</button>
                        <input type="number"
                            name="qty_{{ $key }}"
                            x-ref="qty_{{ $key }}"
                            :value="sizes.{{ $key }}"
                            @input="sizes.{{ $key }} = Math.max(0, parseInt($event.target.value)||0); $event.target.value = sizes.{{ $key }}"
                            min="0" step="1"
                            class="qty-field">
                        <button type="button" class="qty-btn"
                            @click="increment('{{ $key }}')">+</button>
                    </div>

                    {{-- Per-size subtotal --}}
                    <div class="size-subtotal">
                        <span x-show="sizes.{{ $key }} > 0"
                              x-text="'PKR ' + sizeTotal('{{ $key }}').toLocaleString()">
                        </span>
                    </div>
                </div>
                @endforeach

                {{-- Summary row --}}
                <div class="px-5 py-4 bg-[#F9F9FB] border-t border-[#F2F2F7]" x-show="totalPieces > 0">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-[#6E6E73]">
                            <span x-text="totalPieces"></span> pieces/design
                            × {{ $numDesigns }} designs =
                            <span x-text="totalPieces * {{ $numDesigns }}"></span> total pieces
                        </span>
                    </div>
                </div>
            </div>

            {{-- ===== NOTES ===== --}}
            <div class="section-card px-5 py-5">
                <label class="field-label">
                    Special Instructions
                    <span class="text-[#86868B] font-normal text-xs">(optional)</span>
                </label>
                <textarea name="notes" rows="3" class="form-input resize-none"
                    placeholder="Any special requests...">{{ old('notes') }}</textarea>
            </div>

            {{-- ===== SUBMIT ===== --}}
            <div class="pt-1 pb-4">
                <button type="submit" class="submit-btn"
                    :disabled="grandTotal === 0">
                    <span x-show="grandTotal === 0">Select quantities to place order</span>
                    <span x-show="grandTotal > 0"
                          x-text="'Place Order — PKR ' + grandTotal.toLocaleString()"></span>
                </button>
                <p class="text-center text-[#86868B] text-xs mt-3 leading-relaxed px-4">
                    By submitting, you confirm quantities are correct.<br>
                    The Casualite team will confirm your order via email.
                </p>
            </div>

        </form>
    </div>

    {{-- ===== STICKY TOTAL BAR ===== --}}
    <div class="sticky-bar" x-show="grandTotal > 0" x-transition>
        <div class="max-w-lg mx-auto flex items-center justify-between">
            <div>
                <p class="text-[#86868B] text-xs font-medium">Order Total</p>
                <p class="text-[#1D1D1F] text-xl font-bold leading-tight"
                   x-text="'PKR ' + grandTotal.toLocaleString()"></p>
            </div>
            <div class="text-right">
                <p class="text-[#1D1D1F] font-semibold text-sm"
                   x-text="(totalPieces * {{ $numDesigns }}) + ' pieces'"></p>
                <p class="text-[#86868B] text-xs"
                   x-text="totalPieces + ' per design × {{ $numDesigns }} designs'"></p>
            </div>
        </div>
    </div>

</div>

<footer class="text-center py-5">
    <p class="text-[#C7C7CC] text-xs">© {{ date('Y') }} Casualite · Powered by CasualiteOS</p>
</footer>

<script>
function orderCalc(designs, numDesigns, benchmark) {
    return {
        designs,
        numDesigns,
        benchmark,
        sizes: { xs: 0, s: 0, m: 0, l: 0, xl: 0 },

        get totalPieces() {
            return this.sizes.xs + this.sizes.s + this.sizes.m + this.sizes.l + this.sizes.xl;
        },

        // True when total qty exceeds the benchmark and a benchmark is set
        get useDiscount() {
            return this.benchmark !== null && this.totalPieces > this.benchmark;
        },

        // Effective price per design based on current tier
        effectivePrice(d) {
            return this.useDiscount ? d.discount_price : d.selling_price;
        },

        // Total amount for one size key across all designs
        sizeTotal(key) {
            const qty = this.sizes[key];
            return Math.round(this.designs.reduce((sum, d) => sum + qty * this.effectivePrice(d), 0));
        },

        // Grand total = sum of all sizes across all designs
        get grandTotal() {
            return Math.round(this.designs.reduce((sum, d) => {
                return sum + this.totalPieces * this.effectivePrice(d);
            }, 0));
        },

        increment(key) {
            this.sizes[key]++;
        },
        decrement(key) {
            if (this.sizes[key] > 0) this.sizes[key]--;
        },
    };
}
</script>

@endif {{-- end @else (order form) --}}

</body>
</html>
