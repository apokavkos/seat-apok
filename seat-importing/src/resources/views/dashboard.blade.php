@extends('web::layouts.app')

@section('title', 'Market Analysis')

@section('content')
<style>
    .table-sm td, .table-sm th { padding: 0.15rem 0.25rem !important; font-size: 0.85rem; }
    .card-body { padding: 0.5rem !important; }
    .container-fluid { padding-left: 0.5rem !important; padding-right: 0.5rem !important; }
    .nav-pills .nav-link { padding: 0.25rem 0.75rem !important; font-size: 0.9rem; }
    #multibuy-text { font-size: 0.8rem; line-height: 1.1; }
    .h3, h3 { font-size: 1.25rem; }
    .card-header { padding: 0.4rem 0.75rem !important; }
    .badge { padding: 0.25em 0.4em !important; }
    .table-responsive { border: none !important; }
    .filter-row { background: #f8f9fa; border-bottom: 1px solid #dee2e6; }
</style>

<div class="container-fluid">
    <div class="row mb-2">
        <div class="col-12 d-flex align-items-center justify-content-between">
            <h1 class="h3 mb-0"><i class="fas fa-chart-line text-primary"></i> Market Analysis @if ($selectedHub) <small class="text-muted">— {{ $selectedHub->name }}</small> @endif</h1>
            @if($selectedHub && isset($lastImport))
                <small class="text-muted small"><i class="fas fa-sync"></i> {{ $lastImport->completed_at ? $lastImport->completed_at->diffForHumans() : 'never' }}</small>
            @endif
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show py-1 px-2" role="alert"><small>{{ session('success') }}</small><button type="button" class="close py-1" data-dismiss="alert"><span>&times;</span></button></div>
    @endif

    <div class="row mb-2">
        <div class="col-12">@include('seat-importing::partials.hub-selector', ['hubs' => $hubs, 'selectedHub' => $selectedHub])</div>
    </div>

    @if (! $selectedHub)
        <div class="row"><div class="col-12"><div class="card"><div class="card-body text-center py-4"><i class="fas fa-database fa-2x text-muted mb-2"></i><h5 class="text-muted">No hubs configured</h5><p class="text-muted mb-0">Select <strong>+ Add New Hub</strong> above.</p></div></div></div></div>
    @else
        <div class="row mb-2">
            <div class="col-md-3 col-6 mb-1">
                <div class="card bg-warning text-dark shadow-sm">
                    <div class="card-body py-1 px-2 d-flex align-items-center justify-content-between"><div><small class="font-weight-bold">Low Stock</small><h4 class="mb-0">{{ $lowStockItems->count() }}</h4></div><i class="fas fa-exclamation-triangle opacity-50"></i></div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-1">
                <div class="card bg-success text-white shadow-sm">
                    <div class="card-body py-1 px-2 d-flex align-items-center justify-content-between"><div><small class="font-weight-bold">High Markup</small><h4 class="mb-0">{{ $markupItems->count() }}</h4></div><i class="fas fa-tags opacity-50"></i></div>
                </div>
            </div>
            <div class="col-md-6 mb-1">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-dark text-white py-0 px-2 d-flex justify-content-between align-items-center">
                        <small class="font-weight-bold">Multibuy Export</small>
                        <div class="d-flex align-items-center">
                            <div class="input-group input-group-sm mr-2" style="width: 90px;">
                                <div class="input-group-prepend"><span class="input-group-text px-1" style="font-size: 0.6rem">Imp%</span></div>
                                <input type="number" id="import-pct" class="form-control px-1" value="20" min="1" max="1000" style="font-size: 0.7rem">
                            </div>
                            <button class="btn btn-xs btn-info py-0 mr-1" id="push-discord" style="font-size: 0.7rem">Discord</button>
                            <button class="btn btn-xs btn-success py-0 mr-1" id="save-to-list" style="font-size: 0.7rem">Save</button>
                            <button class="btn btn-xs btn-outline-light py-0" id="copy-multibuy" style="font-size: 0.7rem">Copy</button>
                        </div>
                    </div>
                    <div class="card-body p-0"><textarea id="multibuy-text" class="form-control form-control-sm border-0" rows="2" readonly placeholder="Check items below..."></textarea></div>
                </div>
            </div>
        </div>

        <ul class="nav nav-pills mb-2" id="mainTabs" role="tablist">
            <li class="nav-item"><a class="nav-link active" id="tab-supply" data-toggle="pill" href="#pane-supply" role="tab">Supply</a></li>
            <li class="nav-item"><a class="nav-link" id="tab-markup" data-toggle="pill" href="#pane-markup" role="tab">Opportunity</a></li>
        </ul>

        <div class="tab-content" id="mainTabContent">
            <div class="tab-pane fade show active" id="pane-supply" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header filter-row py-1 px-2">
                        <div class="d-flex gap-2">
                            <select class="form-control form-control-sm filter-category" style="width: 200px;"><option value="">All Categories</option>@foreach($categories as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select>
                            <select class="form-control form-control-sm filter-group ml-2" style="width: 200px;"><option value="">All Groups</option>@foreach($groups as $g)<option value="{{ $g }}">{{ $g }}</option>@endforeach</select>
                        </div>
                    </div>
                    <div class="card-body p-0">@include('seat-importing::partials.table-stock', ['items' => $lowStockItems])</div>
                </div>
            </div>
            <div class="tab-pane fade" id="pane-markup" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header filter-row py-1 px-2">
                        <div class="d-flex gap-2">
                            <select class="form-control form-control-sm filter-category" style="width: 200px;"><option value="">All Categories</option>@foreach($categories as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select>
                            <select class="form-control form-control-sm filter-group ml-2" style="width: 200px;"><option value="">All Groups</option>@foreach($groups as $g)<option value="{{ $g }}">{{ $g }}</option>@endforeach</select>
                        </div>
                    </div>
                    <div class="card-body p-0">@include('seat-importing::partials.table-markup', ['items' => $markupItems])</div>
                </div>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-md-6 mb-2"><div class="card shadow-sm border-0"><div class="card-header bg-light py-1 px-2"><small class="font-weight-bold">Highest Markup (Top 20)</small></div><div class="card-body p-0">@include('seat-importing::partials.table-top-markup', ['items' => $topMarkupItems])</div></div></div>
            <div class="col-md-6 mb-2"><div class="card shadow-sm border-0"><div class="card-header bg-light py-1 px-2"><small class="font-weight-bold">Highest Profit (Top 20)</small></div><div class="card-body p-0">@include('seat-importing::partials.table-top-total', ['items' => $topTotalItems])</div></div></div>
        </div>
    @endif
</div>

{{-- Modals --}}
<div class="modal fade" id="add-hub-modal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-lg" role="document"><div class="modal-content"><form method="POST" action="{{ route('seat-importing.hub.store') }}">@csrf<div class="modal-header py-2"><h6 class="modal-title">Add Market Hub</h6><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div><div class="modal-body py-2">@include('seat-importing::partials._hub-form-fields', ['hub' => null])</div><div class="modal-footer py-1"><button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-success">Create</button></div></form></div></div></div>

<div class="modal fade" id="save-list-modal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog" role="document"><div class="modal-content">
    <div class="modal-header py-2"><h6 class="modal-title">Save Checked Items to List</h6><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
    <div class="modal-body py-2"><div class="form-group mb-0"><label>List Label</label><input type="text" id="list-label" class="form-control form-control-sm" placeholder="e.g. Weekly Staging Resupply"></div></div>
    <div class="modal-footer py-1"><button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancel</button><button type="button" class="btn btn-sm btn-success" id="confirm-save-list">Save List</button></div>
</div></div></div>

<div class="modal fade" id="itemDetailModal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-lg" role="document"><div class="modal-content"><div class="modal-header py-2"><h6 class="modal-title" id="itemDetailModalLabel">Item Detail</h6><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div><div class="modal-body p-2" id="itemDetailBody"></div></div></div></div>
@endsection

@push('javascript')
<script>
$(function () {
    $('.select2-system, .select2-region, .select2-structure').select2({ ajax: { url: function() { return '{{ url('seat-importing/search') }}/' + ($(this).hasClass('select2-system') ? 'systems' : ($(this).hasClass('select2-region') ? 'regions' : 'structures')); }, dataType: 'json', delay: 250, data: p => ({q:p.term}), processResults: d => ({results:d.results}), cache: true }, placeholder: 'Search...', minimumInputLength: 3, dropdownParent: $('#add-hub-modal') });
    
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
    $(document).on('change', '.select-all-stock', function() { $('#pane-supply .multibuy-check').prop('checked', $(this).prop('checked')); updateMultibuy(); });
    $(document).on('change', '.select-all-markup', function() { $('#pane-markup .multibuy-check').prop('checked', $(this).prop('checked')); updateMultibuy(); });
    
    $('#copy-multibuy').click(function() { var el = document.getElementById('multibuy-text'); el.select(); document.execCommand('copy'); var $btn = $(this); $btn.text('Copied!'); setTimeout(() => $btn.text('Copy'), 2000); });

    $('#push-discord').click(function() {
        var text = $('#multibuy-text').val();
        if(!text) { alert('List is empty'); return; }
        var hubId = '{{ $selectedHub->id ?? "" }}';
        var $btn = $(this);
        var originalText = $btn.html();
        $btn.prop('disabled', true).text('Pushing...');
        $.post('{{ route("seat-importing.push.discord") }}', { _token: '{{ csrf_token() }}', text: text, hub_id: hubId }).done(function(d) { alert(d.success || d.error); }).fail(function() { alert('Error pushing to Discord.'); }).always(function() { $btn.prop('disabled', false).html(originalText); });
    });

    $('#save-to-list').click(function() { 
        if($('.multibuy-check:checked').length === 0) { alert('No items checked'); return; } 
        $('#save-list-modal').modal('show'); 
    });

    $('#confirm-save-list').click(function() {
        var label = $('#list-label').val();
        if(!label) { alert('Label required'); return; }
        var hubId = '{{ $selectedHub->id ?? "" }}';
        var items = [];
        $('.multibuy-check:checked').each(function() {
            items.push({ type_id: $(this).closest('tr').find('.item-detail-link').data('type-id'), qty: $(this).data('qty') });
        });
        $.post('{{ route("seat-importing.stocking.save") }}', { _token: '{{ csrf_token() }}', label: label, items: items, hub_id: hubId }).done(function(d) { alert(d.success); $('#save-list-modal').modal('hide'); }).fail(function() { alert('Error saving list'); });
    });

    $(document).on('click', '.item-detail-link', function (e) { e.preventDefault(); var typeId = $(this).data('type-id'); $('#itemDetailModalLabel').text($(this).text()); $('#itemDetailModal').modal('show'); $.get('{{ url('seat-importing/item') }}/' + typeId).done(function(d){ var isk = v => parseFloat(v).toLocaleString() + ' ISK'; $('#itemDetailBody').html(`<div class="row"><div class="col-6"><table class="table table-sm mb-0"><tr><th>Vol</th><td>${d.volume_m3}</td></tr><tr><th>Stock</th><td>${d.current_stock}/${d.weekly_volume}</td></tr></table></div><div class="col-6"><table class="table table-sm mb-0"><tr><th>Jita</th><td>${isk(d.jita_sell)}</td></tr><tr><th>Markup</th><td>${d.markup_pct}%</td></tr></table></div></div>`); }); });

    if ($.fn.DataTable) { 
        $('.seat-importing-table').each(function() {
            var $table = $(this);
            var table = $table.DataTable({ order: [], pageLength: 50, "columnDefs": [{ "orderable": false, "targets": 0 }], "dom": 'ftip' }); 
            var $pane = $table.closest('.tab-pane');
            $pane.find('.filter-category').on('change', function() { table.column(1).search($(this).val() ? 'category:' + $(this).val() : '', false, false).draw(); });
            $pane.find('.filter-group').on('change', function() { table.column(1).search($(this).val() ? 'group:' + $(this).val() : '', false, false).draw(); });
        });
    }
});
</script>
@endpush
