@php
  $pjSpec = old('fabric_specs', ($order ?? null)?->fabric_specs ?? []);
  $pjLines = old('items') ?? ($procSeed ?? []);
  $pjLab = $pjSpec['lab_requirements'] ?? [];
  $pjNotes = $pjSpec['notes'] ?? [];
  $pjPacks = $packagings->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->values();
@endphp
<script>
(function () {
  const packs = @json($pjPacks);
  const seedLines = @json(array_values((array) $pjLines));
  const seedLab = @json(array_values((array) $pjLab));
  const seedNotes = @json(array_values((array) $pjNotes));
  const defaultLab = ['Strike-Off', 'Lab Dips', 'Shrinkage', 'Colour Fastness (Wash)', 'Colour Fastness (Rubbing)', 'Finish'];
  const editingId = @json(($order ?? null)?->id);
  let rowIdx = 0, labIdx = 0, noteIdx = 0, started = false;

  const num = v => parseFloat(String(v ?? '').replace(/,/g, '')) || 0;
  const fmt = (n, d = 2) => Number(n).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
  const esc = s => $('<div>').text(s ?? '').html();

  function packOptions(sel) {
    return '<option value="">—</option>' + packs.map(p => `<option value="${p.id}" ${String(sel) === String(p.id) ? 'selected' : ''}>${esc(p.name)}</option>`).join('');
  }

  window.addProcRow = function (r) {
    r = r || {};
    const i = rowIdx++;
    const ordered = r.ordered !== undefined && r.ordered !== null && r.ordered !== '' ? num(r.ordered) : null;
    $('#procItemsBody').append(`
      <tr class="proc-item-row" data-ordered="${ordered ?? ''}">
        <td class="text-muted text-center proc-no"></td>
        <td><input name="items[${i}][collection]" class="form-control form-control-sm" value="${esc(r.collection)}"></td>
        <td><input name="items[${i}][pattern_code]" class="form-control form-control-sm font-monospace proc-pattern" value="${esc(r.pattern_code)}"></td>
        <td><input name="items[${i}][design]" class="form-control form-control-sm" value="${esc(r.design)}"></td>
        <td><input name="items[${i}][color_scheme]" class="form-control form-control-sm" value="${esc(r.color_scheme)}"></td>
        <td><select name="items[${i}][product_id]" class="form-control form-control-sm">${packOptions(r.product_id)}</select></td>
        <td class="text-end text-muted">${ordered === null ? '—' : fmt(ordered)}</td>
        <td><input type="number" name="items[${i}][quantity]" class="form-control form-control-sm text-end proc-qty" step="any" min="0" value="${r.quantity ?? 0}"></td>
        <td class="text-end proc-pct"></td>
        <td><input type="number" name="items[${i}][rate]" class="form-control form-control-sm text-end proc-rate" step="any" min="0" value="${r.rate ?? 0}"></td>
        <td class="text-end proc-amount-cell">0.00</td>
        <td class="text-center">
          <input type="hidden" name="items[${i}][job_item_id]" value="${r.job_item_id ?? ''}">
          <a href="#" class="text-danger remove-proc-row" title="Remove"><i class="fa fa-times"></i></a>
        </td>
      </tr>`);
  };

  window.recalcProcTotal = function () {
    let subtotal = 0, qtyT = 0, ordT = 0;
    $('#procItemsBody tr').each(function (n) {
      const t = $(this), q = num(t.find('.proc-qty').val()), rate = num(t.find('.proc-rate').val());
      const ord = t.data('ordered') === '' ? null : num(t.data('ordered'));
      const amt = Math.round(q * rate * 100) / 100;
      t.find('.proc-no').text(n + 1);
      t.find('.proc-amount-cell').text(fmt(amt));
      if (ord) {
        const pct = (q / ord - 1) * 100;
        t.find('.proc-pct').html(`<span class="${pct < 0 ? 'text-danger' : (pct > 25 ? 'text-warning' : 'text-success')}">${pct >= 0 ? '+' : ''}${fmt(pct, 0)}%</span>`);
        ordT += ord;
      } else t.find('.proc-pct').text('');
      subtotal += amt; qtyT += q;
    });
    const gstOn = $('#gst_applicable').val() === '1';
    const gstRate = gstOn ? (num($('#tax_select').find('option:selected').data('rate'))) : 0;
    const gst = subtotal * gstRate / 100;
    $('#procOrdered').text(ordT ? fmt(ordT) : ''); $('#procQty').text(fmt(qtyT));
    $('#procSubtotalDisplay').text(fmt(subtotal)); $('#procGstDisplay').text(fmt(gst)); $('#procTotalDisplay').text(fmt(subtotal + gst));
  };

  window.addLabReqRow = function (label, value) {
    const i = labIdx++;
    $('#labReqBody').append(`
      <tr class="lab-req-row">
        <td width="42%"><input name="fabric_specs[lab_requirements][${i}][label]" class="form-control form-control-sm fw-semibold" value="${esc(label)}" placeholder="Requirement"></td>
        <td><input name="fabric_specs[lab_requirements][${i}][value]" class="form-control form-control-sm" value="${esc(value)}" placeholder="Value"></td>
        <td width="1%"><a href="#" class="text-danger remove-lab-req-row"><i class="fa fa-times"></i></a></td>
      </tr>`);
  };

  function addNote(text) {
    const i = noteIdx++;
    $('#procNotesBody').append(`
      <tr class="proc-note-row">
        <td width="4%" class="text-muted pt-2 note-no"></td>
        <td><input name="fabric_specs[notes][${i}]" class="form-control form-control-sm" value="${esc(text)}"></td>
        <td width="1%"><a href="#" class="text-danger remove-note-row"><i class="fa fa-times"></i></a></td>
      </tr>`);
    renumberNotes();
  }
  const renumberNotes = () => $('#procNotesBody .note-no').each((i, td) => $(td).text((i + 1) + '.'));

  function loadJob(jobId, replace) {
    if (!jobId) { $('#jobHint').text('No customer order — enter the patterns to process for stock.'); return; }
    fetch(`{{ url('purchase-orders/job-items') }}/${jobId}` + (editingId ? `?exclude_po=${editingId}` : ''))
      .then(r => r.json()).then(data => {
        $('#jobHint').text(`${data.customer}${data.customer_po ? ' · PO ' + data.customer_po : ''}${data.delivery_date ? ' · delivery ' + data.delivery_date : ''}`);
        if (!replace) return;
        $('#procItemsBody').empty(); rowIdx = 0;
        (data.items || []).forEach(it => {
          const remaining = Math.max(0, it.ordered - it.planned);
          addProcRow({ ...it, job_item_id: it.id, quantity: Math.round(remaining * 1000) / 1000, rate: 0 });
        });
        if (!$('#procItemsBody tr').length) addProcRow();
        recalcProcTotal();
      });
  }

  // Called by the page when Processing is chosen (create) or on load (edit)
  window.procInit = function () {
    if (started) return;
    started = true;
    (seedLines.length ? seedLines : [{}]).forEach(addProcRow);
    (seedLab.length ? seedLab : defaultLab.map(l => ({ label: l, value: '' }))).forEach(r => addLabReqRow(r.label, r.value));
    seedNotes.forEach(addNote);
    if (!seedNotes.length) addNote('');
    recalcProcTotal();
    if ($('#job_select').val()) loadJob($('#job_select').val(), false);
  };

  window.procReset = function () {
    started = false; rowIdx = labIdx = noteIdx = 0;
    $('#procItemsBody, #labReqBody, #procNotesBody').empty();
  };

  $(document).on('change', '#job_select', function () {
    if ($('#po_type').val() !== 'processing' || !started) return;
    const hasLines = $('#procItemsBody .proc-pattern').filter((_, el) => el.value.trim() !== '').length > 0;
    if (hasLines && !confirm('Replace the lines with this customer order\'s lines?')) { loadJob($(this).val(), false); return; }
    loadJob($(this).val(), true);
  });
  $(document).on('click', '#addProcRowBtn', function () { addProcRow(); recalcProcTotal(); });
  $(document).on('click', '.remove-proc-row', function (e) {
    e.preventDefault();
    if ($('#procItemsBody tr').length > 1) $(this).closest('tr').remove();
    recalcProcTotal();
  });
  $(document).on('input change', '.proc-qty, .proc-rate, #gst_applicable, #tax_select', recalcProcTotal);
  $(document).on('click', '#procApplyAllow', function () {
    const pct = num($('#procAllow').val()), r10 = $('#procRound10').is(':checked');
    $('#procItemsBody tr').each(function () {
      const ord = $(this).data('ordered');
      if (ord === '' || !num(ord)) return;
      let q = num(ord) * (1 + pct / 100);
      q = r10 ? Math.ceil(q / 10) * 10 : Math.round(q * 100) / 100;
      $(this).find('.proc-qty').val(q);
    });
    recalcProcTotal();
  });
  $(document).on('click', '#addLabReqBtn', () => addLabReqRow('', ''));
  $(document).on('click', '.remove-lab-req-row', function (e) { e.preventDefault(); $(this).closest('tr').remove(); });
  $(document).on('click', '#addProcNoteBtn', () => addNote(''));
  $(document).on('click', '.remove-note-row', function (e) { e.preventDefault(); $(this).closest('tr').remove(); renumberNotes(); });
})();
</script>
