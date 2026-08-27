@extends('layouts.app')
@section('title', ucfirst($type) . ' Voucher | New')
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('vouchers.store', $type) }}" method="POST" onkeydown="return event.key != 'Enter';">
    @csrf
    <section class="card">
      <header class="card-header"><h2 class="card-title">New {{ ucfirst($type) }} Voucher</h2></header>
      <div class="card-body">
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="row">
          <div class="col-md-3 mb-3">
            <label>Voucher Date</label>
            <input type="date" name="voucher_date" class="form-control" value="{{ date('Y-m-d') }}" required>
          </div>
          <div class="col-md-9 mb-3">
            <label>Narration</label>
            <input type="text" name="narration" class="form-control">
          </div>
        </div>

        <table class="table table-bordered" id="linesTable">
          <thead><tr><th>Account</th><th>Party (optional)</th><th>Debit</th><th>Credit</th><th></th></tr></thead>
          <tbody id="linesBody">
            @for($i = 0; $i < 2; $i++)
            <tr class="line-row">
              <td>
                <select name="lines[{{ $i }}][account_id]" class="form-control select2-js" required>
                  <option value="">Select Account</option>
                  @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}">{{ $acc->account_code }} — {{ $acc->name }}</option>
                  @endforeach
                </select>
              </td>
              <td>
                <select name="lines[{{ $i }}][party_type]" class="form-control party-type-select">
                  <option value="">None</option>
                  <option value="customer">Customer</option>
                  <option value="vendor">Vendor</option>
                </select>
                <select name="lines[{{ $i }}][party_id]" class="form-control party-id-select mt-1" style="display:none"></select>
              </td>
              <td><input type="number" name="lines[{{ $i }}][debit]" class="form-control debit-input" step="any" min="0" value="0"></td>
              <td><input type="number" name="lines[{{ $i }}][credit]" class="form-control credit-input" step="any" min="0" value="0"></td>
              <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
            </tr>
            @endfor
          </tbody>
          <tfoot>
            <tr class="fw-bold">
              <td colspan="2" class="text-end">Total:</td>
              <td id="totalDebit">0.00</td>
              <td id="totalCredit">0.00</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
        <button type="button" class="btn btn-outline-primary" id="addRowBtn">Add Line</button>

        <div class="alert alert-warning mt-3" id="balanceWarning" style="display:none">
          Debit and Credit totals must match before saving.
        </div>
      </div>
      <footer class="card-footer text-end"><button type="submit" class="btn btn-success">Save Voucher</button></footer>
    </section>
  </form>
</div></div>

<script>
  let rowIndex = 2;
  const customers = @json($customers->map(fn($c) => ['id' => $c->id, 'name' => $c->name]));
  const vendors    = @json($vendors->map(fn($v) => ['id' => $v->id, 'name' => $v->name]));

  $(document).ready(function () { $('.select2-js').select2({ width: '100%' }); recalcTotals(); });

  function accountOptionsHtml() {
    let html = '<option value="">Select Account</option>';
    @foreach($accounts as $acc)
      html += `<option value="{{ $acc->id }}">{{ $acc->account_code }} — {{ $acc->name }}</option>`;
    @endforeach
    return html;
  }

  $('#addRowBtn').on('click', function () {
    const idx = rowIndex++;
    const row = $(`
      <tr class="line-row">
        <td><select name="lines[${idx}][account_id]" class="form-control select2-js" required>${accountOptionsHtml()}</select></td>
        <td>
          <select name="lines[${idx}][party_type]" class="form-control party-type-select">
            <option value="">None</option><option value="customer">Customer</option><option value="vendor">Vendor</option>
          </select>
          <select name="lines[${idx}][party_id]" class="form-control party-id-select mt-1" style="display:none"></select>
        </td>
        <td><input type="number" name="lines[${idx}][debit]" class="form-control debit-input" step="any" min="0" value="0"></td>
        <td><input type="number" name="lines[${idx}][credit]" class="form-control credit-input" step="any" min="0" value="0"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
      </tr>
    `);
    $('#linesBody').append(row);
    row.find('.select2-js').select2({ width: '100%' });
  });

  $(document).on('change', '.party-type-select', function () {
    const row = $(this).closest('tr');
    const $partyId = row.find('.party-id-select');
    const type = $(this).val();

    if (!type) { $partyId.hide().html(''); return; }

    const list = type === 'customer' ? customers : vendors;
    let html = '<option value="">Select ' + (type === 'customer' ? 'Customer' : 'Vendor') + '</option>';
    list.forEach(p => html += `<option value="${p.id}">${p.name}</option>`);
    $partyId.html(html).show();
  });

  $(document).on('input', '.debit-input, .credit-input', recalcTotals);

  function recalcTotals() {
    let debit = 0, credit = 0;
    $('.debit-input').each(function () { debit += parseFloat($(this).val()) || 0; });
    $('.credit-input').each(function () { credit += parseFloat($(this).val()) || 0; });
    $('#totalDebit').text(debit.toFixed(2));
    $('#totalCredit').text(credit.toFixed(2));
    $('#balanceWarning').toggle(Math.abs(debit - credit) > 0.01);
  }

  $(document).on('click', '.remove-row', function () {
    if ($('.line-row').length > 2) { $(this).closest('tr').remove(); recalcTotals(); }
  });
</script>
@endsection