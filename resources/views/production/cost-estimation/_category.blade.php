{{-- Expects: $category (key), $label, $canEdit --}}
<div class="card overflow-hidden">
    <div class="px-5 py-3 border-b border-[#F2F2F7] flex items-center justify-between">
        <h3 class="text-sm font-semibold text-[#1D1D1F]">{{ $label }}</h3>
        <span class="text-xs font-semibold text-[#0071E3] tabular-nums" x-text="formatPkr(subtotal('{{ $category }}'))"></span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-[#F2F2F7] bg-[#FAFAFA]">
                    <th class="text-left text-[11px] font-semibold text-[#6E6E73] uppercase tracking-widest px-3 py-2">Particulars</th>
                    <th class="text-right text-[11px] font-semibold text-[#6E6E73] uppercase tracking-widest px-2 py-2 w-20">Avg</th>
                    <th class="text-right text-[11px] font-semibold text-[#6E6E73] uppercase tracking-widest px-2 py-2 w-28">Qty</th>
                    <th class="text-right text-[11px] font-semibold text-[#6E6E73] uppercase tracking-widest px-2 py-2 w-28">Rate</th>
                    <th class="text-right text-[11px] font-semibold text-[#6E6E73] uppercase tracking-widest px-2 py-2 w-28">Amount</th>
                    @if($canEdit)<th class="w-8"></th>@endif
                </tr>
            </thead>
            <tbody class="divide-y divide-[#F2F2F7]">
                <template x-for="(row, idx) in items['{{ $category }}']" :key="idx">
                    <tr>
                        <td class="px-3 py-2">
                            <input type="text" :name="'items[{{ $category }}][' + idx + '][particulars]'"
                                   x-model="row.particulars" class="apple-input text-xs" {{ $canEdit ? '' : 'disabled' }}>
                        </td>
                        <td class="px-2 py-2">
                            <input type="number" step="0.01" :name="'items[{{ $category }}][' + idx + '][avg]'"
                                   x-model="row.avg" class="apple-input text-xs text-right" {{ $canEdit ? '' : 'disabled' }}>
                        </td>
                        <td class="px-2 py-2">
                            <input type="number" step="0.01" :name="'items[{{ $category }}][' + idx + '][qty]'"
                                   x-model="row.qty" class="apple-input text-xs text-right" {{ $canEdit ? '' : 'disabled' }}>
                        </td>
                        <td class="px-2 py-2">
                            <input type="number" step="0.01" :name="'items[{{ $category }}][' + idx + '][rate]'"
                                   x-model="row.rate" class="apple-input text-xs text-right" {{ $canEdit ? '' : 'disabled' }}>
                        </td>
                        <td class="px-2 py-2 text-right">
                            <span class="text-xs font-medium text-[#1D1D1F] tabular-nums" x-text="formatNum(rowAmount(row))"></span>
                        </td>
                        @if($canEdit)
                        <td class="px-1 py-2 text-center">
                            <button type="button" class="text-[#FF3B30] text-xs" @click="removeRow('{{ $category }}', idx)">✕</button>
                        </td>
                        @endif
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    @if($canEdit)
    <div class="px-3 py-2 border-t border-[#F2F2F7]">
        <button type="button" class="text-[#0066CC] text-xs hover:underline" @click="addRow('{{ $category }}')">+ Add Row</button>
    </div>
    @endif
</div>
