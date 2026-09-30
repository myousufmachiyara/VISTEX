{{--
  Terms & Conditions picker, shared by create and edit.
    $terms        master rows to offer (TermAndCondition)
    $selectedIds  ids ticked on this PO
    $orphans      saved PO terms whose master row was deleted (edit only)
    $fixedType    PO type on edit; null on create (JS filters as the type is chosen)
--}}
@php
  $selectedIds = array_map('intval', $selectedIds ?? []);
  $orphans = $orphans ?? collect();
@endphp
<div id="termsSection" class="mt-3" @unless($fixedType) style="display:none" @endunless>
  <input type="hidden" name="terms_submitted" value="1">
  <hr>
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h6 class="mb-0">Terms &amp; Conditions <small class="text-muted" id="termsCount"></small></h6>
    <div class="small">
      <a href="#" id="termsAll">Select all</a> · <a href="#" id="termsNone">Clear</a>
      @can('terms_and_conditions.index') · <a href="{{ route('terms_and_conditions.index') }}" target="_blank">Manage master</a>@endcan
    </div>
  </div>

  @if($terms->isEmpty() && $orphans->isEmpty())
    <p class="text-muted small mb-0">No terms &amp; conditions in the master yet.</p>
  @endif

  <div class="row">
    @foreach($terms as $t)
    <div class="col-md-6 mb-2 term-item" data-applies="{{ $t->applies_to }}">
      <label class="d-block border rounded p-2 h-100 mb-0" style="cursor:pointer">
        <input type="checkbox" class="form-check-input me-2 term-check" name="term_ids[]" value="{{ $t->id }}"
               data-default="{{ $t->is_default_checked ? 1 : 0 }}" @checked(in_array($t->id, $selectedIds))>
        <strong>{{ $t->title }}</strong>
        @if(!$t->is_active)<span class="badge bg-secondary ms-1">inactive in master</span>@endif
        <div class="small text-muted ms-4" style="white-space:pre-line">{{ $t->description }}</div>
      </label>
    </div>
    @endforeach

    @foreach($orphans as $o)
    <div class="col-md-6 mb-2 term-item" data-applies="all">
      <label class="d-block border rounded p-2 h-100 mb-0" style="cursor:pointer">
        <input type="checkbox" class="form-check-input me-2 term-check" name="keep_snapshot_ids[]" value="{{ $o->id }}" checked>
        <strong>{{ $o->title }}</strong> <span class="badge bg-warning text-dark ms-1">removed from master</span>
        <div class="small text-muted ms-4" style="white-space:pre-line">{{ $o->description }}</div>
      </label>
    </div>
    @endforeach
  </div>
</div>

<script>
(function () {
  const fixedType = @json($fixedType);
  const restoring = @json(old('terms_submitted') !== null);   // back after a validation error: keep the user's ticks
  const touched = new WeakSet();

  function count() {
    const n = $('#termsSection .term-item:visible .term-check:checked').length;
    $('#termsCount').text(n ? `(${n} selected)` : '');
  }

  // Show only terms for this PO type; hidden ones are disabled so they are not posted
  window.refreshTerms = function (type) {
    if (!type) { $('#termsSection').hide(); return; }
    $('#termsSection').show();
    $('#termsSection .term-item').each(function () {
      const applies = $(this).data('applies');
      const ok = applies === 'all' || applies === type;
      const cb = $(this).find('.term-check')[0];
      $(this).toggle(ok);
      cb.disabled = !ok;
      if (ok && !restoring && !fixedType && !touched.has(cb)) cb.checked = cb.dataset.default === '1';
    });
    count();
  };

  $(document).on('change', '#termsSection .term-check', function () { touched.add(this); count(); });
  $(document).on('click', '#termsAll, #termsNone', function (e) {
    e.preventDefault();
    const on = this.id === 'termsAll';
    $('#termsSection .term-item:visible .term-check').each(function () { this.checked = on; touched.add(this); });
    count();
  });

  if (fixedType) $(function () { refreshTerms(fixedType); });
})();
</script>
