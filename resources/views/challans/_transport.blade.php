{{-- Optional transport details. $challan is set on edit, null on create. --}}
<h6 class="mt-3 mb-2 text-dark" style="font-size:.95rem;font-weight:600">Transport Details <small class="text-muted fw-normal">(optional)</small></h6>
<div class="row">
  <div class="col-md-4 mb-3">
    <label>Vehicle No.</label>
    <input type="text" name="vehicle_no" class="form-control text-uppercase" maxlength="30" placeholder="TKN-1234"
           value="{{ old('vehicle_no', $challan->vehicle_no ?? '') }}">
  </div>
  <div class="col-md-4 mb-3">
    <label>Driver Name</label>
    <input type="text" name="driver_name" class="form-control" maxlength="100"
           value="{{ old('driver_name', $challan->driver_name ?? '') }}">
  </div>
  <div class="col-md-4 mb-3">
    <label>Driver Contact No.</label>
    <input type="tel" name="driver_contact" class="form-control" maxlength="30" placeholder="03xx-xxxxxxx"
           value="{{ old('driver_contact', $challan->driver_contact ?? '') }}">
  </div>
</div>
