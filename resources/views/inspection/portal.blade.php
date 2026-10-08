<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex,nofollow">
<title>Move In / Out Report — United Arab Agencies</title>
<link rel="stylesheet" href="{{ asset('css/move-inout.css') }}?v=1">
<style>body{margin:0;background:#F7F8FA}.uaa-frontend-wrap{max-width:1020px}</style>
</head>
<body>
<div class="uaa-frontend-wrap uaa-mio-wrap" id="uaaMioApp">

    <!-- Brand header -->
    <div class="uaa-brand-bar">
        <img src="{{ asset('images/uaa-logo.png') }}" alt="United Arab Agencies"/>
        <div class="uaa-brand-bar-right">
            <div class="uaa-report-title">Move In / Out Report</div>
            <div class="uaa-report-sub">Property Inspection System</div>
        </div>
    </div>

    <!-- Step bar -->
    <div class="uaa-step-bar">
        <div class="uaa-step active" id="stab-1"><span class="sn">1</span> Property &amp; Tenant</div>
        <div class="uaa-step"        id="stab-2"><span class="sn">2</span> Room Inspection</div>
        <div class="uaa-step"        id="stab-3"><span class="sn">3</span> Summary &amp; Sign</div>
    </div>

    <!-- PAGE 1 -->
    <div class="uaa-page active" id="pg-1">
        <div class="uaa-card">
            <div class="uaa-card-title">&#128203; Inspection Type</div>
            <div class="uaa-type-toggle">
                <div class="uaa-type-btn active-in" id="type-in" data-type="Move In">
                    <span class="ti">&#128230;</span><strong>Move In</strong><small>Tenant moving in</small>
                </div>
                <div class="uaa-type-btn" id="type-out" data-type="Move Out">
                    <span class="ti">&#128682;</span><strong>Move Out</strong><small>Tenant moving out</small>
                </div>
            </div>
        </div>

        <div class="uaa-card">
            <div class="uaa-card-title">&#127970; Property &amp; Unit</div>
            <div class="uaa-form-grid">
                <div class="uaa-field">
                    <label>Property *</label>
                    <select id="f-property" class="uaa-select"><option value="">Loading properties...</option></select>
                </div>
                <div class="uaa-field">
                    <label>Unit Type *</label>
                    <select id="f-unittype" class="uaa-select"><option value="">Loading unit types...</option></select>
                </div>
                <div class="uaa-field">
                    <label>Unit Number *</label>
                    <select id="f-unit" class="uaa-select"><option value="">Select property first</option></select>
                    <div id="unit-status-msg" style="display:none;margin-top:6px;font-size:11px;font-weight:700"></div>
                </div>
                <div class="uaa-field">
                    <label>Bedrooms</label>
                    <select id="f-beds" class="uaa-select">
                        <option value="">Select</option>
                        <option>Studio</option><option>1 Bedroom</option>
                        <option>2 Bedroom</option><option>3 Bedroom</option>
                        <option>4 Bedroom</option><option>5 Bedroom</option>
                        <option value="N/A">N/A</option>
                    </select>
                </div>
                <div class="uaa-field">
                    <label>Inspection Date *</label>
                    <input type="date" id="f-date" class="uaa-input"/>
                </div>
                <div class="uaa-field">
                    <label>Inspector *</label>
                    <select id="f-inspector" class="uaa-select">
                        <option value="">Select Inspector</option>
                        @foreach($inspectors as $name)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="uaa-card">
            <div class="uaa-card-title">&#128100; Tenant Information</div>
            <div id="tenantFound" class="uaa-tenant-found" style="display:none">
                <span class="tf-badge">&#10003; Auto-loaded</span>
                <strong id="tf-name"></strong>
            </div>
            <div id="tenantNotFound" class="uaa-tenant-notfound" style="display:none">
                &#9888; Enter the tenant details below
            </div>
            <div class="uaa-form-grid">
                <div class="uaa-field span2">
                    <label>Tenant Full Name *</label>
                    <input type="text" id="f-tenant" class="uaa-input" placeholder="Tenant name"/>
                </div>
                <div class="uaa-field">
                    <label>Phone / Mobile</label>
                    <input type="text" id="f-phone" class="uaa-input" placeholder="Phone"/>
                </div>
                <div class="uaa-field">
                    <label>Email Address</label>
                    <input type="email" id="f-email" class="uaa-input" placeholder="Email"/>
                </div>
                <div class="uaa-field">
                    <label>Keys Returned / Handed</label>
                    <input type="number" id="f-keys" class="uaa-input" value="0" min="0"/>
                </div>
                <div class="uaa-field">
                    <label>Parking Cards</label>
                    <input type="number" id="f-parking" class="uaa-input" value="0" min="0"/>
                </div>
                <div class="uaa-field">
                    <label>Contract No.</label>
                    <input type="text" id="f-contract" class="uaa-input" placeholder="e.g. UAA-2024-1234"/>
                </div>
            </div>
        </div>
        <div class="uaa-btn-row">
            <button class="uaa-btn uaa-btn-gold uaa-btn-lg" onclick="uaaGoStep(2)">Next: Room Inspection &rarr;</button>
        </div>
    </div>

    <!-- PAGE 2 -->
    <div class="uaa-page" id="pg-2">
        <div id="uaaRoomsContainer"></div>
        <div class="uaa-charges-bar" id="chargesBar">
            <div class="cb-left"><div class="cb-label">&#128176; Live Total</div></div>
            <div class="cb-amounts">
                <div><div class="cb-num" id="bar-subtotal">AED 0.00</div><div class="cb-sub">Subtotal</div></div>
                <div class="cb-divider"></div>
                <div><div class="cb-num" id="bar-vat">AED 0.00</div><div class="cb-sub">VAT 5%</div></div>
                <div class="cb-divider"></div>
                <div><div class="cb-num cb-total" id="bar-total">AED 0.00</div><div class="cb-sub">Total Due</div></div>
            </div>
            <button class="uaa-btn uaa-btn-white" onclick="uaaGoStep(3)">Summary &rarr;</button>
        </div>
        <div class="uaa-btn-row">
            <button class="uaa-btn uaa-btn-outline" onclick="uaaGoStep(1)">&larr; Back</button>
            <button class="uaa-btn uaa-btn-gold uaa-btn-lg" onclick="uaaGoStep(3)">Next: Summary &rarr;</button>
        </div>
    </div>

    <!-- PAGE 3 -->
    <div class="uaa-page" id="pg-3">
        <div class="uaa-card">
            <div class="uaa-card-title">&#128176; Charges Summary</div>
            <table class="uaa-insp-table" id="summaryTable">
                <thead><tr><th>Area</th><th>Item</th><th style="text-align:center">Condition</th><th style="text-align:right">Amount</th></tr></thead>
                <tbody id="summaryBody"></tbody>
                <tfoot id="summaryFoot"></tfoot>
            </table>
        </div>
        <div class="uaa-card">
            <div class="uaa-card-title">&#128221; Comments</div>
            <textarea id="f-comments" class="uaa-textarea" rows="4" placeholder="Additional observations..."></textarea>
        </div>
        <div class="uaa-card">
            <div class="uaa-card-title">&#9998; Signatures</div>
            <div class="uaa-form-grid">
                <div class="uaa-field">
                    <label>Tenant Signature</label>
                    <div class="uaa-sig-wrap"><canvas id="tenantSig" height="120"></canvas>
                    <button class="sig-clear" onclick="uaaClearSig('tenantSig')">Clear</button></div>
                </div>
                <div class="uaa-field">
                    <label>Inspector Signature</label>
                    <div class="uaa-sig-wrap"><canvas id="inspSig" height="120"></canvas>
                    <button class="sig-clear" onclick="uaaClearSig('inspSig')">Clear</button></div>
                </div>
            </div>
        </div>
        <div class="uaa-btn-row">
            <button class="uaa-btn uaa-btn-outline" onclick="uaaGoStep(2)">&larr; Back</button>
            <button class="uaa-btn uaa-btn-gold uaa-btn-lg" id="submitBtn" onclick="uaaSubmitReport()">&#11015; Save &amp; Generate PDF</button>
        </div>
    </div>

    <!-- PAGE 4: SUCCESS -->
    <div class="uaa-page" id="pg-4">
        <div class="uaa-success-card">
            <div class="uaa-success-ring">&#9989;</div>
            <h2>Report Saved!</h2>
            <p id="successRef" style="font-size:14px;color:#C9A96E;font-weight:700;margin-bottom:20px"></p>
            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-bottom:20px">
              <a href="#" id="pdfDownloadLink" class="uaa-btn uaa-btn-gold uaa-btn-lg" target="_blank">&#11015; Print / Save as PDF</a>
              <a href="#" id="pdfViewLink" class="uaa-btn uaa-btn-dark uaa-btn-lg" target="_blank">&#128196; View Report</a>
            </div>
            @if($isAdmin)
              <a href="{{ url('/admin/move-reports') }}" class="uaa-btn uaa-btn-outline">View All Reports</a>
            @endif
            &nbsp;<button class="uaa-btn uaa-btn-outline" onclick="location.reload()">+ New Inspection</button>
        </div>
    </div>

    <!-- Loading overlay -->
    <div class="uaa-loading" id="uaaLoading" style="display:none">
        <div class="uaa-spinner"></div>
        <p id="uaaLoadingText">Processing...</p>
    </div>

    <!-- Lightbox -->
    <div id="uaaLightbox" onclick="this.style.display='none'" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.94);z-index:99999;align-items:center;justify-content:center;flex-direction:column;cursor:pointer">
        <img id="uaaLightboxImg" src="" style="max-width:92vw;max-height:86vh;border-radius:10px;border:3px solid #C9A96E;box-shadow:0 0 80px rgba(201,169,110,0.3)"/>
        <p id="uaaLightboxCap" style="color:#C9A96E;font-size:13px;margin-top:12px;font-weight:700"></p>
        <p style="color:rgba(255,255,255,0.35);font-size:11px;margin-top:4px">Click anywhere to close</p>
    </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script>
  var UAA_MIO = {ajax_url: "{{ url('/inspection/ajax') }}", nonce: "", plugin_url: "", is_admin: "{{ $isAdmin ? 1 : 0 }}"};
  $.ajaxSetup({headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json'}});
</script>
<script src="{{ asset('js/move-inout.js') }}?v=1"></script>
</body>
</html>
