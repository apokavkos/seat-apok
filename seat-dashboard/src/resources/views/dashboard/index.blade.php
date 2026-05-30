@extends('web::layouts.grids.12')

@section('title', 'Custom Dashboard')
@section('page_header', 'Custom Dashboard')

@section('full')
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Industrial Slots Summary</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Manufacturing</th>
                                    <th>Science</th>
                                    <th>Reaction</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="font-weight-bold">
                                    <td>{{ $summary['manu_used'] }} / {{ $summary['manu_total'] }}</td>
                                    <td>{{ $summary['science_used'] }} / {{ $summary['science_total'] }}</td>
                                    <td>{{ $summary['reactions_used'] }} / {{ $summary['reactions_total'] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Character ISK Summary</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Metric</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="font-weight-bold">
                                    <td>Total Character ISK</td>
                                    <td>{{ number_format($total_char_isk, 2) }} ISK</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Corp Wallet Balances ({{ $division_labels[$wallet_division] }})</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Corporation</th>
                                    <th>Division</th>
                                    <th>Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($wallet_balances as $wallet)
                                    <tr>
                                        <td>{{ $wallet->corp_name }}</td>
                                        <td>
                                            @if($wallet->division_name)
                                                {{ $wallet->division_name }}
                                            @elseif($wallet->division == 1)
                                                Master
                                            @else
                                                Division {{ $wallet->division }}
                                            @endif
                                        </td>
                                        <td>{{ number_format($wallet->balance, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center">No data found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3">
                                        <form action="{{ route('seat-dashboard::index') }}" method="GET" class="d-flex justify-content-between">
                                            <div class="form-group mb-0 mr-2 flex-grow-1">
                                                <select name="corporation_id" id="corporation_id" class="form-control select2" onchange="this.form.submit()">
                                                    <option value="">All Corporations</option>
                                                    @foreach ($corporations as $corp)
                                                        <option value="{{ $corp->corporation_id }}" {{ $selected_corp_id == $corp->corporation_id ? 'selected' : '' }}>
                                                            {{ $corp->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="form-group mb-0">
                                                <select name="wallet_division" id="wallet_division" class="form-control select2" onchange="this.form.submit()">
                                                    @foreach ($division_labels as $id => $label)
                                                        <option value="{{ $id }}" {{ $wallet_division == $id ? 'selected' : '' }}>
                                                            {{ $label }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Character ISK & Industry Details</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="character-isk-table">
                            <thead>
                                <tr>
                                    <th>Character</th>
                                    <th>Balance</th>
                                    <th>Manu</th>
                                    <th>Science</th>
                                    <th>Reactions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($character_wallets as $wallet)
                                    <tr>
                                        <td>@include('web::partials.character', ['character' => $wallet->character])</td>
                                        <td data-order="{{ $wallet->balance }}">{{ number_format($wallet->balance, 2) }}</td>
                                        <td>{{ $wallet->manu_slots }}</td>
                                        <td>{{ $wallet->science_slots }}</td>
                                        <td>{{ $wallet->reactions_slots }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Active Industry Jobs (Total)</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Activity</th>
                                    <th>Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($industry_jobs_totals as $job)
                                    <tr>
                                        <td>{{ $activity_mapping[$job->activity_id] ?? 'Unknown (' . $job->activity_id . ')' }}</td>
                                        <td>{{ number_format($job->count) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">System Cost Index</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#add-system-modal">
                            <i class="fas fa-plus"></i> Add System/Constellation
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="system-index-table">
                            <thead>
                                <tr>
                                    <th>System</th>
                                    <th>Manufacturing</th>
                                    <th>TE</th>
                                    <th>ME</th>
                                    <th>Copying</th>
                                    <th>Invention</th>
                                    <th>Reaction</th>
                                    <th class="text-right">Reorder / Actions</th>
                                </tr>
                            </thead>
                            <tbody id="sortable-systems">
                                @forelse($tracked_systems as $system)
                                    <tr data-id="{{ $system->id }}">
                                        <td>{{ $system->solar_system->name }}</td>
                                        <td>{{ number_format(($system->indexes['manufacturing'] ?? 0) * 100, 2) }}%</td>
                                        <td>{{ number_format(($system->indexes['researching_time_efficiency'] ?? 0) * 100, 2) }}%</td>
                                        <td>{{ number_format(($system->indexes['researching_material_efficiency'] ?? 0) * 100, 2) }}%</td>
                                        <td>{{ number_format(($system->indexes['copying'] ?? 0) * 100, 2) }}%</td>
                                        <td>{{ number_format(($system->indexes['invention'] ?? 0) * 100, 2) }}%</td>
                                        <td>{{ number_format(($system->indexes['reaction'] ?? 0) * 100, 2) }}%</td>
                                        <td class="text-right">
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-xs btn-default move-up"><i class="fas fa-chevron-up"></i></button>
                                                <button type="button" class="btn btn-xs btn-default move-down"><i class="fas fa-chevron-down"></i></button>
                                                <form action="{{ route('seat-dashboard::remove-system', $system->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">No systems tracked</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add System Modal -->
    <div class="modal fade" id="add-system-modal" role="dialog" aria-labelledby="addSystemModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form action="{{ route('seat-dashboard::add-system') }}" method="POST" id="add-system-form">
                    @csrf
                    <input type="hidden" name="id" id="selected-id">
                    <input type="hidden" name="type" id="selected-type">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addSystemModalLabel">Track New Solar System or Constellation</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="system-select">Search (min 3 chars)</label>
                            <select id="system-select" class="form-control" style="width: 100%"></select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="add-btn" disabled>Add</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@push('javascript')
<script>
    $(function () {
        // Character table sorting
        $('#character-isk-table').DataTable({
            "paging": false,
            "searching": false,
            "info": false,
            "autoWidth": false,
            "responsive": true,
            "order": [[1, "desc"]]
        });

        // Initialize Corporation and Division select dropdowns
        $('#corporation_id, #wallet_division').select2();

        // Initialize Select2
        $('#system-select').select2({
            ajax: {
                url: '{{ route("seat-dashboard::search-systems") }}',
                dataType: 'json',
                delay: 250,
                data: function (params) { return { q: params.term }; },
                processResults: function (data) { return { results: data.results }; },
                cache: true
            },
            placeholder: 'Search system or constellation...',
            minimumInputLength: 3,
            dropdownParent: $('#add-system-modal')
        }).on('select2:select', function (e) {
            var data = e.params.data;
            $('#selected-id').val(data.id);
            $('#selected-type').val(data.type);
            $('#add-btn').prop('disabled', false);
        });

        $('#add-system-modal').on('shown.bs.modal', function () {
            $('#system-select').select2('open');
        });

        // Reordering Logic
        function updateOrder() {
            var order = [];
            $('#sortable-systems tr').each(function() {
                var id = $(this).data('id');
                if (id) order.push(id);
            });

            $.post('{{ route("seat-dashboard::reorder-systems") }}', {
                _token: '{{ csrf_token() }}',
                order: order
            });
        }

        $('.move-up').click(function() {
            var row = $(this).closest('tr');
            row.prev().before(row);
            updateOrder();
        });

        $('.move-down').click(function() {
            var row = $(this).closest('tr');
            row.next().after(row);
            updateOrder();
        });
    });
</script>
@endpush
