@extends('web::layouts.app')

@section('title', 'Market Stocking Dashboard')

@section('content')
<style>
    .table-sm td, .table-sm th { padding: 0.15rem 0.25rem !important; font-size: 0.85rem; }
    .card-body { padding: 0.5rem !important; }
    .container-fluid { padding-left: 0.5rem !important; padding-right: 0.5rem !important; }
    #multibuy-text { font-size: 0.8rem; line-height: 1.1; }
    .list-header { cursor: pointer; transition: background 0.2s; }
    .list-header:hover { background: #f1f1f1 !important; }
</style>

<div class="container-fluid">
    <div class="row mb-2">
        <div class="col-12 d-flex align-items-center justify-content-between">
            <h1 class="h3 mb-0"><i class="fas fa-warehouse text-success"></i> Market Stocking Dashboard</h1>
            <div>
                <button class="btn btn-xs btn-outline-light py-0 mr-1" id="copy-multibuy" style="color: #333; border-color: #ccc;">Copy Checked</button>
                <a href="{{ route('seat-importing.dashboard') }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-chart-line"></i> Market Analysis</a>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show py-1 px-2" role="alert"><small>{{ session('success') }}</small><button type="button" class="close py-1" data-dismiss="alert"><span>&times;</span></button></div>
    @endif

    <div class="row mb-3">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white py-1 d-flex justify-content-between align-items-center">
                    <small class="font-weight-bold">Multibuy Export</small>
                    <div class="d-flex align-items-center">
                        <div class="input-group input-group-sm mr-2" style="width: 100px;">
                            <div class="input-group-prepend"><span class="input-group-text px-1" style="font-size: 0.7rem">Imp%</span></div>
                            <input type="number" id="import-pct" class="form-control px-1" value="20" min="1" max="1000" style="font-size: 0.7rem">
                        </div>
                        <button class="btn btn-xs btn-info py-0" id="push-discord-stocking" style="font-size: 0.7rem"><i class="fab fa-discord"></i> Discord</button>
                    </div>
                </div>
                <div class="card-body p-0"><textarea id="multibuy-text" class="form-control form-control-sm border-0" rows="3" readonly placeholder="Check items in the lists below..."></textarea></div>
            </div>
        </div>
    </div>

    @forelse($lists as $list)
        <div class="card mb-3 shadow-sm stocking-list-card" data-list-id="{{ $list->id }}" data-hub-id="{{ $list->hub_id }}">
            <div class="card-header bg-light d-flex align-items-center justify-content-between py-1 list-header" data-toggle="collapse" data-target="#list-body-{{ $list->id }}">
                <h6 class="mb-0 font-weight-bold text-secondary">
                    <i class="fas fa-list-ul mr-2"></i> {{ $list->label }} 
                    <small class="text-primary ml-2"><i class="fas fa-map-marker-alt"></i> {{ $list->hub?->name ?? 'Unlinked' }}</small>
                    <small class="text-muted ml-2">({{ $list->items->count() }} items)</small>
                </h6>
                <div>
                    <form action="{{ route('seat-importing.stocking.destroy-list', $list) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete entire table?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-xs btn-outline-danger py-0 border-0"><i class="fas fa-trash"></i></button>
                    </form>
                    <i class="fas fa-chevron-down ml-2"></i>
                </div>
            </div>
            <div id="list-body-{{ $list->id }}" class="collapse show">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 30px;" class="text-center"><input type="checkbox" class="select-all-list"></th>
                                    <th>Item</th>
                                    <th class="text-right text-nowrap">Weekly Vol</th>
                                    <th class="text-right text-nowrap">Live Stock</th>
                                    <th class="text-right text-nowrap">Hub Sell</th>
                                    <th class="text-right text-nowrap">Jita Sell</th>
                                    <th class="text-right text-nowrap">Markup %</th>
                                    <th style="width: 40px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($list->items as $item)
                                    <tr data-item-id="{{ $item->id }}">
                                        <td class="text-center"><input type="checkbox" class="multibuy-check" data-name="{{ $item->type->typeName }}" data-qty="{{ $item->quantity }}"></td>
                                        <td><strong>{{ $item->type->typeName }}</strong></td>
                                        <td class="text-right">{{ number_format($item->quantity, 0) }}</td>
                                        <td class="text-right {{ ($item->live_data?->current_stock ?? 0) < ($item->quantity * 0.5) ? 'text-danger font-weight-bold' : '' }}">
                                            {{ number_format($item->live_data?->current_stock ?? 0, 0) }}
                                        </td>
                                        <td class="text-right text-muted small">{{ number_format($item->live_data?->local_sell_price ?? 0, 2) }}</td>
                                        <td class="text-right text-muted small">{{ number_format($item->live_data?->jita_sell_price ?? 0, 2) }}</td>
                                        <td class="text-right small">
                                            <span class="{{ ($item->live_data?->markup_pct ?? 0) >= 25 ? 'text-success font-weight-bold' : '' }}">
                                                {{ number_format($item->live_data?->markup_pct ?? 0, 1) }}%
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <button class="btn btn-xs btn-outline-danger border-0 remove-item-btn" data-id="{{ $item->id }}"><i class="fas fa-times"></i></button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="card"><div class="card-body text-center py-5 text-muted"><i class="fas fa-box-open fa-3x mb-3"></i><h5>No saved stocking lists.</h5><p>Use the <strong>Market Analysis</strong> dashboard to select items and save them here.</p></div></div>
    @endforelse
</div>
@endsection

@push('javascript')
<script>
$(function() {
    function updateMultibuy() { 
        var lines = []; 
        var multiplier = parseFloat($('#import-pct').val() || 20) / 100.0;
        $('.multibuy-check:checked').each(function() { 
            var qty = Math.ceil($(this).data('qty') * multiplier);
            lines.push($(this).data('name') + ' ' + qty); 
        }); 
        $('#multibuy-text').val(lines.join('\n')); 
    }
    
    $(document).on('change', '.multibuy-check, #import-pct', updateMultibuy);
    
    $(document).on('change', '.select-all-list', function() { 
        $(this).closest('.card').find('.multibuy-check').prop('checked', $(this).prop('checked')); 
        updateMultibuy(); 
    });

    $('#copy-multibuy').click(function() { 
        var el = document.getElementById('multibuy-text'); 
        el.select(); document.execCommand('copy'); 
        $(this).text('Copied!'); setTimeout(() => $(this).text('Copy Checked'), 2000); 
    });

    $('.remove-item-btn').click(function() {
        if(!confirm('Remove item from list?')) return;
        var id = $(this).data('id');
        var $row = $(this).closest('tr');
        $.ajax({ url: '{{ url("seat-importing/stocking/item") }}/' + id, type: 'DELETE', data: { _token: '{{ csrf_token() }}' } })
            .done(function() { $row.fadeOut(); updateMultibuy(); });
    });

    $('#push-discord-stocking').click(function() {
        var text = $('#multibuy-text').val();
        if(!text) { alert('List is empty'); return; }
        
        // Find the first checked list's hub_id to use for location naming
        var hubId = $('.multibuy-check:checked').first().closest('.stocking-list-card').data('hub-id');
        
        var $btn = $(this);
        $btn.prop('disabled', true).text('Pushing...');
        $.post('{{ route("seat-importing.push.discord") }}', { _token: '{{ csrf_token() }}', text: text, hub_id: hubId })
            .done(function(d) { alert(d.success || d.error); })
            .always(function() { $btn.prop('disabled', false).text('Discord'); });
    });
});
</script>
@endpush
