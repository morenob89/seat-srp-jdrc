@extends('web::layouts.grids.12')

@section('title', trans('srp::srp.payouts'))
@section('page_header', trans('srp::srp.payouts'))

@push('head')
<style>
    .srp-payout-cell .srp-payout-input { display: none !important; }
    tr.editing .srp-payout-cell .srp-payout-value { display: none; }
    tr.editing .srp-payout-cell .srp-payout-input { display: inline-block !important; }
    tr.editing .srp-edit-btn { display: none; }
    .srp-edit-actions { display: none; }
    tr.editing .srp-edit-actions { display: inline-flex; }
    .srp-payout-input { max-width: 160px; }
    .srp-meta-row td { background: rgba(0,0,0,.03); font-weight: 600; }
    .srp-edited-badge { font-size: .7rem; }
    .srp-edit-btn { display: inline-flex; align-items: center; gap: .35rem; }
</style>
@endpush

@php
    $opKeys = array_keys($operationTypes);
    $colCount = 1 + count($opKeys) + ($canEdit ? 1 : 0);
@endphp

@section('full')
    <div class="card card-primary card-solid">
        <div class="card-header">
            <h3 class="card-title">{{ trans('srp::srp.payouts') }}</h3>
            @if($canEdit)
                <div class="card-tools">
                    <form action="{{ route('srp.payouts.reset') }}" method="post" class="d-inline"
                          onsubmit="return confirm('{{ trans('srp::srp.payouts_reset_confirm') }}');">
                        {{ csrf_field() }}
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-undo"></i> {{ trans('srp::srp.payouts_reset') }}
                        </button>
                    </form>
                </div>
            @endif
        </div>
        <div class="card-body">
            <p class="text-muted">{{ trans('srp::srp.payouts_desc') }}</p>

            <table class="table table-bordered table-hover" id="srp-payouts">
                <thead>
                    <tr>
                        <th>{{ trans('srp::srp.ship_class') }}</th>
                        @foreach($operationTypes as $opKey => $opLabel)
                            <th class="text-right">{{ $opLabel }}</th>
                        @endforeach
                        @if($canEdit)
                            <th class="text-right" style="width: 1%;"></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $meta => $metaRows)
                        <tr class="srp-meta-row">
                            <td colspan="{{ $colCount }}">{{ $meta }}</td>
                        </tr>
                        @foreach($metaRows as $row)
                            <tr data-key="{{ $row['key'] }}">
                                <td>{{ $row['hull'] }}</td>
                                @foreach($opKeys as $opKey)
                                    @php $value = (int) ($row[$opKey] ?? 0); @endphp
                                    <td class="text-right srp-payout-cell" data-op="{{ $opKey }}" data-value="{{ $value }}">
                                        <span class="srp-payout-value">
                                            @if($value > 0)
                                                {{ number_format($value) }}
                                            @else
                                                <span class="text-muted">{{ trans('srp::srp.payouts_manual') }}</span>
                                            @endif
                                            @if(!empty($row['overridden'][$opKey]))
                                                <span class="badge badge-info srp-edited-badge" title="Overrides the code default">{{ trans('srp::srp.payouts_edited') }}</span>
                                            @endif
                                        </span>
                                        @if($canEdit)
                                            <input type="number" min="0" step="any"
                                                   class="form-control form-control-sm text-right srp-payout-input"
                                                   value="{{ $value }}" />
                                        @endif
                                    </td>
                                @endforeach
                                @if($canEdit)
                                    <td class="text-right">
                                        <button type="button" class="btn btn-sm btn-outline-primary srp-edit-btn">
                                            <i class="fas fa-edit"></i> {{ trans('srp::srp.payouts_edit') }}
                                        </button>
                                        <span class="srp-edit-actions btn-group">
                                            <button type="button" class="btn btn-sm btn-success srp-save-btn">{{ trans('srp::srp.payouts_save') }}</button>
                                            <button type="button" class="btn btn-sm btn-secondary srp-cancel-btn">{{ trans('srp::srp.payouts_cancel') }}</button>
                                        </span>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@stop

@if($canEdit)
@push('javascript')
<script type="application/javascript">
    $(function () {
        var baseUrl = "{{ url('srp/payouts') }}";
        var manualLabel = "{{ trans('srp::srp.payouts_manual') }}";
        var editedLabel = "{{ trans('srp::srp.payouts_edited') }}";

        function formatValue(v) {
            v = parseInt(v, 10) || 0;
            if (v <= 0) {
                return '<span class="text-muted">' + manualLabel + '</span>';
            }
            return v.toLocaleString('en-US');
        }

        $('#srp-payouts').on('click', '.srp-edit-btn', function () {
            $(this).closest('tr').addClass('editing');
        });

        $('#srp-payouts').on('click', '.srp-cancel-btn', function () {
            var row = $(this).closest('tr');
            row.find('.srp-payout-cell').each(function () {
                $(this).find('.srp-payout-input').val($(this).data('value'));
            });
            row.removeClass('editing');
        });

        $('#srp-payouts').on('click', '.srp-save-btn', function () {
            var row = $(this).closest('tr');
            var key = row.data('key');
            var payload = {};
            row.find('.srp-payout-cell').each(function () {
                payload[$(this).data('op')] = $(this).find('.srp-payout-input').val();
            });

            var btn = $(this);
            btn.prop('disabled', true);

            $.ajax({
                type: 'POST',
                url: baseUrl + '/' + encodeURIComponent(key),
                data: payload,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                dataType: 'json',
                timeout: 5000
            }).done(function (data) {
                row.find('.srp-payout-cell').each(function () {
                    var cell = $(this);
                    var op = cell.data('op');
                    var value = parseInt(data.row[op], 10) || 0;
                    var overridden = data.row.overridden && data.row.overridden[op];

                    var html = formatValue(value);
                    if (overridden) {
                        html += ' <span class="badge badge-info srp-edited-badge" title="Overrides the code default">' + editedLabel + '</span>';
                    }
                    cell.data('value', value);
                    cell.attr('data-value', value);
                    cell.find('.srp-payout-value').html(html);
                    cell.find('.srp-payout-input').val(value);
                });
                row.removeClass('editing');
            }).fail(function () {
                alert('Failed to save payout. Please try again.');
            }).always(function () {
                btn.prop('disabled', false);
            });
        });
    });
</script>
@endpush
@endif
