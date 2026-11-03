<x-filament-widgets::widget>
    <x-filament::section heading="Parcels per county" collapsible>
        @php($totals = $this->getTotals())
        @if (empty($totals))
            <p class="text-sm text-gray-500">No parcels imported yet.</p>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(10rem,1fr));gap:.75rem">
                @foreach ($totals as $row)
                    <div class="text-sm">
                        <div class="font-medium">{{ $row->county }}</div>
                        <div>{{ number_format($row->total) }} total</div>
                        <div class="text-gray-500">{{ number_format($row->residential) }} residential</div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
