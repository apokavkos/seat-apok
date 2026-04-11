@if ($items->isEmpty())
    <div class="text-center py-4 text-muted"><i class="fas fa-warehouse fa-2x mb-2"></i><br>All items are above the stock threshold.</div>
@else
<div class="table-responsive">
    <table class="table table-sm table-hover seat-importing-table">
        <thead class="thead-light">
            <tr>
                <th class="text-center" style="width: 30px;"><input type="checkbox" class="select-all-stock"></th>
                <th>Item</th>
                <th class="text-right text-nowrap">Weekly Vol</th>
                <th class="text-right text-nowrap">Stock</th>
                <th class="text-right text-nowrap">Stock %</th>
                <th class="text-right text-nowrap">Resupply</th>
                <th class="text-right text-nowrap">Hub Sell</th>
                <th class="text-right text-nowrap">Jita Sell</th>
                <th class="text-right text-nowrap">Markup %</th>
                <th class="text-right text-nowrap">Weekly Profit</th>
                <th class="text-right text-nowrap">Investment</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
            @php
                $stockPct = $item->stockPct();
                $stockClass = $stockPct !== null && $stockPct < 10 ? 'table-danger' : ($stockPct !== null && $stockPct < 25 ? 'table-warning' : '');
                $resupplyAmount = max(0, ceil($item->weekly_volume - $item->current_stock));
                $investment = $resupplyAmount * $item->jita_sell_price;
            @endphp
            <tr class="{{ $stockClass }}">
                <td class="text-center"><input type="checkbox" class="multibuy-check" data-name="{{ $item->type_name }}" data-qty="{{ $resupplyAmount }}"></td>
                <td><a href="#" class="item-detail-link font-weight-bold" data-type-id="{{ $item->type_id }}">{{ $item->type_name }}</a><span class="d-none">category:{{ $item->category_name }} group:{{ $item->group_name }}</span></td>
                <td class="text-right" data-order="{{ $item->weekly_volume }}">{{ number_format($item->weekly_volume, 0) }}</td>
                <td class="text-right" data-order="{{ $item->current_stock }}">{{ number_format($item->current_stock, 0) }}</td>
                <td class="text-right font-weight-bold" data-order="{{ $stockPct ?? -1 }}">{{ $stockPct !== null ? number_format($stockPct, 1).'%' : '—' }}</td>
                <td class="text-right font-weight-bold text-primary" data-order="{{ $resupplyAmount }}">{{ number_format($resupplyAmount, 0) }}</td>
                <td class="text-right font-weight-bold" data-order="{{ $item->local_sell_price }}">{{ number_format($item->local_sell_price, 2) }}</td>
                <td class="text-right" data-order="{{ $item->jita_sell_price }}">{{ number_format($item->jita_sell_price, 2) }}</td>
                <td class="text-right {{ $item->markup_pct >= 25 ? 'text-success font-weight-bold' : '' }}">{{ number_format($item->markup_pct, 1) }}%</td>
                <td class="text-right font-weight-bold text-success" data-order="{{ $item->weekly_profit }}">{{ number_format($item->weekly_profit, 0) }}</td>
                <td class="text-right font-weight-bold text-info" data-order="{{ $investment }}">{{ number_format($investment, 0) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
