@extends('layouts.app')
@section('title', 'Account Mappings')
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('account-mappings.update') }}" method="POST">
    @csrf @method('PUT')
    <section class="card">
      @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
      <header class="card-header"><h2 class="card-title">Account Mappings</h2></header>
      <div class="card-body">
        <p class="text-muted small">These map internal system roles (e.g. "Stock in Hand", "Accounts Payable") to specific Chart of Accounts entries — every automated voucher posts using these.</p>
        <table class="table table-bordered">
          <thead><tr><th>Role Key</th><th>Mapped Account</th></tr></thead>
          <tbody>
            @foreach($mappings as $m)
            <tr>
              <td>{{ $m->role_key }}</td>
              <td>
                <select name="mappings[{{ $m->role_key }}]" class="form-control select2-js">
                  <option value="">— None —</option>
                  @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}" @selected($acc->id == $m->account_id)>{{ $acc->account_code }} — {{ $acc->name }}</option>
                  @endforeach
                </select>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <footer class="card-footer text-end"><button type="submit" class="btn btn-primary">Save Mappings</button></footer>
    </section>
  </form>
</div></div>
@endsection