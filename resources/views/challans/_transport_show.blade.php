{{-- Read-only transport line; renders nothing when no details were entered. --}}
@if($challan->hasTransportDetails())
  <div class="mb-3">
    <strong>Transport:</strong>
    {{ collect([
        $challan->vehicle_no ? 'Vehicle ' . $challan->vehicle_no : null,
        $challan->driver_name ? 'Driver ' . $challan->driver_name : null,
        $challan->driver_contact,
    ])->filter()->implode(' · ') }}
    @if($challan->driver_contact)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $challan->driver_contact) }}" class="small ms-1">Call</a>@endif
  </div>
@endif
