/* ═══════════════════════════════════════════════════
   UAA Move In/Out v3 — app.js  (FINAL)
   ═══════════════════════════════════════════════════ */
(function($){
'use strict';

/* ── Room Definitions ─────────────────────────────── */
const ROOMS = [
  { id:'corridor', name:'Corridor', icon:'🚪', items:[
    {id:'cr-floor',   label:'Floor / Wall'},
    {id:'cr-ceiling', label:'Ceiling / Door'},
    {id:'cr-lights',  label:'Lights / Switches'},
    {id:'cr-sockets', label:'Sockets / Ringing Bell'},
  ]},
  { id:'sitting', name:'Sitting Area / Living Room', icon:'🛋️', items:[
    {id:'sa-floor',    label:'Floor / Wall'},
    {id:'sa-ceiling',  label:'Ceiling / Windows'},
    {id:'sa-lights',   label:'Lights / Sockets'},
    {id:'sa-switches', label:'Switches / Others'},
    {id:'sa-ac',       label:'A/C Grill / Thermostat'},
  ]},
  { id:'bedroom1', name:'Bedroom 1', icon:'🛏️', items:[
    {id:'b1-floor',    label:'Floor / Wall / Ceiling'},
    {id:'b1-door',     label:'Door / Window / Wardrobe'},
    {id:'b1-switches', label:'Switches / Sockets'},
    {id:'b1-lights',   label:'Lights / Others'},
    {id:'b1-ac',       label:'A/C Grill / Thermostat'},
  ]},
  { id:'bedroom2', name:'Bedroom 2', icon:'🛏️', items:[
    {id:'b2-floor',    label:'Floor / Wall / Ceiling'},
    {id:'b2-door',     label:'Door / Window / Wardrobe'},
    {id:'b2-switches', label:'Switches / Sockets'},
    {id:'b2-lights',   label:'Lights / Others'},
    {id:'b2-ac',       label:'A/C Grill / Thermostat'},
  ]},
  { id:'bedroom3', name:'Bedroom 3', icon:'🛏️', items:[
    {id:'b3-floor',    label:'Floor / Wall / Ceiling'},
    {id:'b3-door',     label:'Door / Window / Wardrobe'},
    {id:'b3-switches', label:'Switches / Sockets'},
    {id:'b3-lights',   label:'Lights / Others'},
    {id:'b3-ac',       label:'A/C Grill / Thermostat'},
  ]},
  { id:'toilet', name:'Bathroom / Toilet', icon:'🚿', items:[
    {id:'tl-floor',     label:'Floor / Wall'},
    {id:'tl-ceiling',   label:'Ceiling / Window / Door'},
    {id:'tl-washbasin', label:'WashBasin / Shower'},
    {id:'tl-wc',        label:'WC / Bidet / Shataf'},
    {id:'tl-tray',      label:'Shower Tray & Accessories'},
    {id:'tl-glass',     label:'Shower Glass Door'},
    {id:'tl-heater',    label:'Water Heater / Exhaust Fan'},
    {id:'tl-electric',  label:'Electric / Lights'},
  ]},
  { id:'kitchen', name:'Kitchen', icon:'🍳', items:[
    {id:'kt-floor',   label:'Floor / Wall'},
    {id:'kt-ceiling', label:'Ceiling / Window'},
    {id:'kt-door',    label:'Door / Kitchen Cabinet'},
    {id:'kt-socket',  label:'Socket / Switch'},
    {id:'kt-light',   label:'Lights'},
    {id:'kt-fridge',  label:'Fridge / Gas Oven'},
    {id:'kt-hood',    label:'Hood / Dishwasher'},
    {id:'kt-washer',  label:'Washing Machine / Exhaust Fan'},
  ]},
  { id:'balcony', name:'Balcony', icon:'🌤️', items:[
    {id:'bl-floor',   label:'Floor / Wall'},
    {id:'bl-railing', label:'Railing / Glass'},
    {id:'bl-light',   label:'Light / Socket'},
  ]},
  { id:'storeroom', name:'Store Room / Laundry', icon:'📦', items:[
    {id:'sr-floor', label:'Floor / Wall'},
    {id:'sr-door',  label:'Door'},
    {id:'sr-light', label:'Light / Socket'},
  ]},
  { id:'common', name:'Common Areas / Others', icon:'🏢', items:[
    {id:'co-floor',  label:'Floor / Wall / Ceiling'},
    {id:'co-door',   label:'Main Door / Windows'},
    {id:'co-lights', label:'Lights / Sockets'},
    {id:'co-ac',     label:'A/C'},
    {id:'co-others', label:'Others'},
  ]},
];

/* ── State ────────────────────────────────────────── */
const state = {
  inspectionType: 'Move In',
  propertyId: '', propertyName: '',
  unitId: '', unitNo: '', unitStatus: '',
  itemData: {}, roomPhotos: {},
};
ROOMS.forEach(function(r){
  r.items.forEach(function(i){ state.itemData[i.id]={status:'ok',price:'',notes:''}; });
  state.roomPhotos[r.id] = [];
});
const sigState = {};

/* ── Init ─────────────────────────────────────────── */
$(document).ready(function(){
  if (!$('#uaaMioApp').length) return;
  $('#f-date').val(new Date().toISOString().split('T')[0]);
  loadProperties();
  loadUnitTypes();
  buildRooms();
  initSigPads();

  $('.uaa-type-btn').on('click', function(){
    state.inspectionType = $(this).data('type');
    $('.uaa-type-btn').removeClass('active-in active-out');
    $(this).addClass(state.inspectionType === 'Move In' ? 'active-in' : 'active-out');
  });

  $('#f-property').on('change', function(){
    state.propertyId   = $(this).val();
    state.propertyName = $(this).find('option:selected').text();
    loadUnits();
    clearTenant();
  });

  $('#f-unit').on('change', function(){
    const opt         = $(this).find('option:selected');
    state.unitId      = $(this).val();
    state.unitNo      = opt.data('unitno') || '';
    state.unitStatus  = opt.data('status') || '';
    const utype       = opt.data('unittype') || '';
    if (utype){
      $('#f-unittype option').each(function(){
        if ($(this).val() === utype || $(this).text().toLowerCase() === utype.toLowerCase()){
          $(this).prop('selected', true); return false;
        }
      });
    }
    showUnitStatus(state.unitStatus);
    loadTenant();
  });
});

/* ── Step navigation ──────────────────────────────── */
window.uaaGoStep = function(n){
  if (n === 3) buildSummary();
  $('.uaa-page').removeClass('active');
  $('#pg-' + n).addClass('active');
  for (let i = 1; i <= 3; i++){
    $('#stab-' + i).removeClass('active done');
    if (i === n)     $('#stab-' + i).addClass('active');
    else if (i < n)  $('#stab-' + i).addClass('done');
  }
  $('html,body').animate({scrollTop: 0}, 200);
};

/* ── Unit status ──────────────────────────────────── */
function showUnitStatus(status){
  const msg = $('#unit-status-msg');
  if (!status){ msg.hide(); return; }
  const s = status.toUpperCase();
  let html = '';
  if (s === 'VACANT' || s === 'AVAILABLE')
    html = '<span class="unit-status-vacant">✓ Vacant — Available</span>';
  else if (s === 'OCCUPIED' || s === 'LEASED' || s === 'RENTED')
    html = '<span class="unit-status-occupied">● Occupied</span>';
  else if (s === 'BLOCKED' || s === 'INACTIVE' || s === 'RESERVED')
    html = '<span class="unit-status-blocked">⊘ Blocked / Reserved</span>';
  else
    html = '<span class="unit-status-occupied">● ' + status + '</span>';
  msg.html(html).show();
}

/* ── Load Properties ──────────────────────────────── */
function loadProperties(){
  $.post(UAA_MIO.ajax_url, {action:'uaa_mio_get_properties', nonce:UAA_MIO.nonce}, function(res){
    const sel = $('#f-property').empty().append('<option value="">— Select Property —</option>');
    if (res.success && res.data && res.data.length){
      res.data.forEach(function(p){
        sel.append($('<option>').val(p.id).text(p.name));
      });
    } else {
      sel.append('<option disabled>Cannot load — check Settings</option>');
    }
  });
}

/* ── Load Unit Types ──────────────────────────────── */
function loadUnitTypes(){
  $.post(UAA_MIO.ajax_url, {action:'uaa_mio_get_unit_types', nonce:UAA_MIO.nonce}, function(res){
    const sel = $('#f-unittype').empty().append('<option value="">— Select Unit Type —</option>');
    if (res.success && res.data && res.data.length){
      res.data.forEach(function(t){
        sel.append($('<option>').val(t.code).text(t.label));
      });
    }
  });
}

/* ── Load Units ───────────────────────────────────── */
function loadUnits(){
  $('#f-unit').html('<option value="">Loading units...</option>');
  $('#unit-status-msg').hide();
  if (!state.propertyId) return;
  $.post(UAA_MIO.ajax_url, {
    action:'uaa_mio_get_units', nonce:UAA_MIO.nonce, property_id:state.propertyId
  }, function(res){
    const sel = $('#f-unit').empty().append('<option value="">— Select Unit —</option>');
    if (res.success && res.data && res.data.length){
      res.data.forEach(function(u){
        const status = (u.status || '').toUpperCase();
        let label = 'Unit ' + u.unit_no;
        if (u.unit_type) label += ' — ' + u.unit_type;
        if (status && status !== 'ACTIVE') label += ' [' + status + ']';
        const opt = $('<option>').val(u.unit_id).text(label)
          .data('unitno', u.unit_no).data('unittype', u.unit_type).data('status', u.status);
        if (status === 'BLOCKED' || status === 'INACTIVE') opt.css('color','#9CA3AF');
        sel.append(opt);
      });
    } else {
      sel.append('<option disabled>No units found</option>');
    }
  });
}

/* ── Load Tenant ──────────────────────────────────── */
function loadTenant(){
  clearTenant();
  if (!state.propertyId || !state.unitId) return;
  $.post(UAA_MIO.ajax_url, {
    action:'uaa_mio_get_tenant', nonce:UAA_MIO.nonce,
    property_id:state.propertyId, unit_id:state.unitId
  }, function(res){
    if (res.success && res.data){
      const t = res.data;
      $('#f-tenant').val(t.name  || '').addClass('prefilled');
      $('#f-phone').val(t.phone  || '').addClass('prefilled');
      $('#f-email').val(t.email  || '').addClass('prefilled');
      $('#tf-name').text(t.name  || '');
      $('#tenantFound').show(); $('#tenantNotFound').hide();
    } else {
      $('#tenantFound').hide(); $('#tenantNotFound').show();
    }
  });
}
function clearTenant(){
  $('#f-tenant,#f-phone,#f-email').val('').removeClass('prefilled');
  $('#tenantFound,#tenantNotFound').hide();
}

/* ── Build rooms UI ───────────────────────────────── */
function buildRooms(){
  const container = $('#uaaRoomsContainer').empty();
  ROOMS.forEach(function(room){
    const rows = room.items.map(function(item){
      return `
      <tr id="tr-${item.id}">
        <td style="width:22%"><strong style="font-size:12px;color:#111827">${item.label}</strong></td>
        <td style="width:34%">
          <div class="uaa-status-btns">
            <button class="uaa-s-btn ok active" id="sb-ok-${item.id}"    onclick="uaaSetStatus('${item.id}','${room.id}','ok')">✓ Good</button>
            <button class="uaa-s-btn maint"     id="sb-maint-${item.id}" onclick="uaaSetStatus('${item.id}','${room.id}','maintenance')">⚠ Maint.</button>
            <button class="uaa-s-btn dmg"       id="sb-dmg-${item.id}"   onclick="uaaSetStatus('${item.id}','${room.id}','damaged')">✕ Damaged</button>
          </div>
        </td>
        <td style="width:28%">
          <input class="uaa-notes-input" type="text" placeholder="Notes..." id="notes-${item.id}"
            onchange="state.itemData['${item.id}'].notes=this.value"/>
        </td>
        <td style="width:16%;text-align:right">
          <div class="uaa-price-wrap" id="pw-${item.id}" style="opacity:0.25;pointer-events:none;justify-content:flex-end">
            <span>AED</span>
            <input class="uaa-price-input" type="number" min="0" step="0.01" placeholder="0.00"
              id="price-${item.id}"
              oninput="uaaSetPrice('${item.id}',this.value)"
              onchange="uaaSetPrice('${item.id}',this.value)"/>
          </div>
        </td>
      </tr>`;
    }).join('');

    const block = $(`
    <div class="uaa-room-block" id="rb-${room.id}">
      <div class="uaa-room-head" id="rh-${room.id}">
        <span class="rh-icon">${room.icon}</span>
        <span class="rh-name">${room.name}</span>
        <span class="rh-badge" id="badge-${room.id}">All OK</span>
        <span class="rh-toggle">▼</span>
      </div>
      <div class="uaa-room-body" id="rbd-${room.id}">
        <table class="uaa-insp-table">
          <thead><tr>
            <th style="width:22%">Item</th>
            <th style="width:34%">Condition</th>
            <th style="width:28%">Notes / Description</th>
            <th style="width:16%;text-align:right">Charge (AED)</th>
          </tr></thead>
          <tbody>${rows}</tbody>
        </table>
        <div class="uaa-photo-section">
          <div class="uaa-photo-label">📷 Photos — ${room.name}</div>
          <div class="uaa-upload-zone" id="uz-${room.id}">
            <input type="file" id="phi-${room.id}" accept="image/*" multiple style="display:none"/>
            <span class="uz-icon">📸</span>
            <span class="uz-text">Tap to add photos &nbsp;·&nbsp; Click photo to view full screen</span>
          </div>
          <div class="uaa-photo-grid" id="pg-${room.id}"></div>
        </div>
      </div>
    </div>`);

    container.append(block);

    block.find('.uaa-room-head').on('click', function(){
      block.find('.uaa-room-body').toggleClass('hidden');
      $(this).toggleClass('collapsed');
    });

    // Click upload zone (not inside a photo thumb)
    block.find('#uz-' + room.id).on('click', function(e){
      if (!$(e.target).closest('.uaa-thumb').length){
        document.getElementById('phi-' + room.id).click();
      }
    });

    block.find('#phi-' + room.id).on('change', function(){
      handlePhotos(room.id, this.files);
      this.value = '';
    });
  });
}

/* ── Status change ────────────────────────────────── */
window.uaaSetStatus = function(itemId, roomId, status){
  state.itemData[itemId].status = status;
  ['ok','maint','dmg'].forEach(function(k){ $('#sb-' + k + '-' + itemId).removeClass('active'); });
  const btnMap = {ok:'ok', maintenance:'maint', damaged:'dmg'};
  $('#sb-' + btnMap[status] + '-' + itemId).addClass('active');
  const tr = $('#tr-' + itemId);
  tr.removeClass('row-dmg row-maint');
  if (status === 'damaged')     tr.addClass('row-dmg');
  if (status === 'maintenance') tr.addClass('row-maint');
  const pw = $('#pw-' + itemId);
  const on = (status !== 'ok');
  pw.css({opacity: on ? '1' : '0.25', 'pointer-events': on ? 'auto' : 'none'});
  if (!on){ state.itemData[itemId].price = ''; $('#price-' + itemId).val(''); }
  updateRoomBadge(roomId);
  updateChargesBar();
};

window.uaaSetPrice = function(itemId, val){
  state.itemData[itemId].price = val;
  updateChargesBar();
};

function updateRoomBadge(roomId){
  const room = ROOMS.find(function(r){ return r.id === roomId; });
  if (!room) return;
  let d = 0, m = 0;
  room.items.forEach(function(item){
    const s = state.itemData[item.id]?.status;
    if (s === 'damaged')     d++;
    if (s === 'maintenance') m++;
  });
  const badge = $('#badge-' + roomId);
  if (d > 0){
    badge.text(d + ' Damaged').css({background:'rgba(220,38,38,0.3)',color:'#FCA5A5',border:'1px solid rgba(220,38,38,0.4)'});
  } else if (m > 0){
    badge.text(m + ' Maintenance').css({background:'rgba(180,83,9,0.3)',color:'#FDE68A',border:'1px solid rgba(180,83,9,0.4)'});
  } else {
    badge.text('All OK').css({background:'rgba(201,169,110,0.15)',color:'#E8D4A8',border:'1px solid rgba(201,169,110,0.25)'});
  }
}

function updateChargesBar(){
  let sub = 0;
  ROOMS.forEach(function(r){
    r.items.forEach(function(i){
      const d = state.itemData[i.id];
      if ((d.status === 'damaged' || d.status === 'maintenance') && d.price)
        sub += parseFloat(d.price) || 0;
    });
  });
  const vat = sub * 0.05, total = sub + vat;
  $('#bar-subtotal').text('AED ' + sub.toFixed(2));
  $('#bar-vat').text('AED '   + vat.toFixed(2));
  $('#bar-total').text('AED ' + total.toFixed(2));
}

/* ── Photo handling — upload to WP media immediately ── */
function handlePhotos(roomId, files){
  Array.from(files).forEach(function(file){
    if (!file.type.startsWith('image/')) return;

    // Show a temporary preview with uploading indicator
    const previewUrl = URL.createObjectURL(file);
    const idx = state.roomPhotos[roomId].length;
    // Reserve slot with null — replaced when upload finishes
    state.roomPhotos[roomId].push(null);

    const grid  = $('#pg-' + roomId);
    const thumb = $(`
    <div class="uaa-thumb" id="thumb-${roomId}-${idx}">
      <img src="${previewUrl}" alt="" style="opacity:0.5"/>
      <div class="ph-uploading" id="phup-${roomId}-${idx}" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.45);border-radius:8px;color:#fff;font-size:11px;font-weight:700">Uploading…</div>
      <input class="ph-cap" type="text" placeholder="Caption…" style="display:none"/>
    </div>`);

    // Lightbox on click
    thumb.find('img').on('click', function(){
      $('#uaaLightboxImg').attr('src', previewUrl);
      $('#uaaLightboxCap').text(state.roomPhotos[roomId][idx]?.caption || '');
      $('#uaaLightbox').css('display','flex');
    });
    grid.append(thumb);

    // Upload to WP media library via AJAX
    shrinkImage(file, function(blob, name){
    const formData = new FormData();
    formData.append('action', 'uaa_mio_upload_photo');
    formData.append('nonce',  UAA_MIO.nonce);
    formData.append('photo',  blob, name);

    $.ajax({
      url:         UAA_MIO.ajax_url,
      type:        'POST',
      data:        formData,
      processData: false,
      contentType: false,
      success: function(res){
        if (res.success && res.data && res.data.url){
          // Store WP media URL — NOT base64
          state.roomPhotos[roomId][idx] = {url: res.data.url, caption: ''};
          // Update thumb: full opacity, show delete & caption, update lightbox
          thumb.find('img').css('opacity','1').attr('src', res.data.url);
          thumb.find('#phup-' + roomId + '-' + idx).remove();
          thumb.find('.ph-cap').show().on('change', function(){
            if (state.roomPhotos[roomId][idx])
              state.roomPhotos[roomId][idx].caption = this.value;
          });
          const delBtn = $('<button class="del-ph" title="Remove">×</button>');
          delBtn.on('click', function(){ removePhoto(roomId, idx); });
          thumb.append(delBtn);

          // Re-bind lightbox to the WP URL
          thumb.find('img').off('click').on('click', function(){
            $('#uaaLightboxImg').attr('src', res.data.url);
            $('#uaaLightboxCap').text(state.roomPhotos[roomId][idx]?.caption || '');
            $('#uaaLightbox').css('display','flex');
          });
        } else {
          // Upload failed — remove thumb
          thumb.remove();
          state.roomPhotos[roomId][idx] = null;
        }
      },
      error: function(){
        thumb.remove();
        state.roomPhotos[roomId][idx] = null;
      }
    });
    });
  });
}

/* Resize a phone photo (often 5-10 MB) to max 1600px JPEG before it is uploaded */
function shrinkImage(file, done){
  const img = new Image();
  const url = URL.createObjectURL(file);
  img.onload = function(){
    const max = 1600, r = Math.min(1, max / Math.max(img.width, img.height));
    const c = document.createElement('canvas');
    c.width = Math.round(img.width * r); c.height = Math.round(img.height * r);
    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
    URL.revokeObjectURL(url);
    c.toBlob(function(b){ done(b || file, 'photo.jpg'); }, 'image/jpeg', 0.82);
  };
  img.onerror = function(){ URL.revokeObjectURL(url); done(file, file.name || 'photo.jpg'); };
  img.src = url;
}

window.removePhoto = function(roomId, idx){
  state.roomPhotos[roomId][idx] = null;
  $('#thumb-' + roomId + '-' + idx).remove();
};

/* ── Summary table ────────────────────────────────── */
function buildSummary(){
  const tbody = $('#summaryBody').empty();
  const tfoot = $('#summaryFoot').empty();
  let sub = 0, has = false;
  ROOMS.forEach(function(r){
    r.items.forEach(function(i){
      const d = state.itemData[i.id], s = d.status;
      if (s === 'damaged' || s === 'maintenance'){
        has = true;
        const price = parseFloat(d.price) || 0;
        sub += price;
        const badge = s === 'damaged'
          ? '<span class="uaa-cond damaged">✕ Damaged</span>'
          : '<span class="uaa-cond maintenance">⚠ Maintenance</span>';
        const tr = $('<tr>').addClass(s === 'damaged' ? 'row-dmg' : 'row-maint');
        tr.html('<td>' + r.name + '</td><td>' + i.label + '</td><td style="text-align:center">' + badge + '</td><td style="text-align:right;font-weight:800;color:#DC2626">AED ' + price.toFixed(2) + '</td>');
        tbody.append(tr);
      }
    });
  });
  if (!has){
    tbody.html('<tr><td colspan="4" style="text-align:center;color:#9CA3AF;padding:24px;font-size:13px">✓ No charges — unit in good condition</td></tr>');
  }
  const vat = sub * 0.05, total = sub + vat;
  tfoot.html(`
    <tr style="background:#F9FAFB">
      <td colspan="3" style="text-align:right;padding:10px 13px;font-weight:700;color:#6B7280">Subtotal</td>
      <td style="text-align:right;padding:10px 13px;font-weight:800;color:#111827">AED ${sub.toFixed(2)}</td>
    </tr>
    <tr style="background:#F9FAFB">
      <td colspan="3" style="text-align:right;padding:10px 13px;font-weight:700;color:#6B7280">VAT (5%)</td>
      <td style="text-align:right;padding:10px 13px;font-weight:800;color:#111827">AED ${vat.toFixed(2)}</td>
    </tr>
    <tr>
      <td colspan="3" style="text-align:right;padding:14px 16px;font-weight:900;font-size:15px;color:#fff">TOTAL DUE</td>
      <td style="text-align:right;padding:14px 16px;font-weight:900;font-size:16px;color:#C9A96E">AED ${total.toFixed(2)}</td>
    </tr>`);
}

/* ── Signature pads ───────────────────────────────── */
function initSigPads(){
  ['tenantSig','inspSig'].forEach(function(id){
    const canvas = document.getElementById(id);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    canvas.width = canvas.offsetWidth || 400;
    ctx.strokeStyle = '#0D0D0D';
    ctx.lineWidth   = 2.5;
    ctx.lineCap     = 'round';
    ctx.lineJoin    = 'round';
    sigState[id] = {ctx, canvas, drawing:false, hasData:false};
    const getPos = function(e){
      const r = canvas.getBoundingClientRect();
      const t = e.touches ? e.touches[0] : e;
      return {
        x:(t.clientX - r.left) * (canvas.width  / r.width),
        y:(t.clientY - r.top)  * (canvas.height / r.height),
      };
    };
    canvas.addEventListener('mousedown',  function(e){ const p=getPos(e); sigState[id].drawing=true; ctx.beginPath(); ctx.moveTo(p.x,p.y); });
    canvas.addEventListener('mousemove',  function(e){ if(!sigState[id].drawing)return; const p=getPos(e); ctx.lineTo(p.x,p.y); ctx.stroke(); sigState[id].hasData=true; });
    canvas.addEventListener('mouseup',    function(){ sigState[id].drawing=false; });
    canvas.addEventListener('mouseleave', function(){ sigState[id].drawing=false; });
    canvas.addEventListener('touchstart', function(e){ e.preventDefault(); const p=getPos(e); sigState[id].drawing=true; ctx.beginPath(); ctx.moveTo(p.x,p.y); },{passive:false});
    canvas.addEventListener('touchmove',  function(e){ e.preventDefault(); if(!sigState[id].drawing)return; const p=getPos(e); ctx.lineTo(p.x,p.y); ctx.stroke(); sigState[id].hasData=true; },{passive:false});
    canvas.addEventListener('touchend',   function(){ sigState[id].drawing=false; });
  });
}
window.uaaClearSig = function(id){
  const s = sigState[id];
  if (s){ s.ctx.clearRect(0,0,s.canvas.width,s.canvas.height); s.hasData=false; }
};
function getSig(id){
  return (sigState[id] && sigState[id].hasData) ? sigState[id].canvas.toDataURL('image/png') : '';
}

/* ── Submit — photos already uploaded, payload is small ── */
window.uaaSubmitReport = async function(){
  if (!state.propertyId){ alert('Please select a property.'); uaaGoStep(1); return; }
  if (!state.unitId)     { alert('Please select a unit.');    uaaGoStep(1); return; }
  if (!$('#f-tenant').val()){ alert('Please enter tenant name.'); uaaGoStep(1); return; }
  if (!$('#f-inspector').val()){ alert('Please select an inspector.'); uaaGoStep(1); return; }

  // Check any photos still uploading
  let stillUploading = false;
  ROOMS.forEach(function(r){
    (state.roomPhotos[r.id] || []).forEach(function(p, i){
      if ($('#phup-' + r.id + '-' + i).length) stillUploading = true;
    });
  });
  if (stillUploading){
    alert('Please wait — photos are still uploading.');
    return;
  }

  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  showLoading('Building report data...');

  // Build rooms data — photos are already WP URLs, NO base64
  const roomsData = ROOMS.map(function(room){
    const photos = (state.roomPhotos[room.id] || []).filter(Boolean).map(function(p){
      return {url: p.url, caption: p.caption || ''};
    });
    return {
      id:   room.id,
      name: room.name,
      icon: room.icon,
      items: room.items.map(function(item){
        return {
          id:     item.id,
          label:  item.label,
          status: (state.itemData[item.id] || {}).status || 'ok',
          price:  (state.itemData[item.id] || {}).price  || '',
          notes:  (state.itemData[item.id] || {}).notes  || '',
        };
      }),
      photos: photos,
    };
  });

  // Totals
  let sub = 0;
  roomsData.forEach(function(r){
    r.items.forEach(function(i){
      if ((i.status === 'damaged' || i.status === 'maintenance') && i.price)
        sub += parseFloat(i.price) || 0;
    });
  });
  const vat = sub * 0.05, total = sub + vat;

  const unitTypeLabel = $('#f-unittype option:selected').text() || $('#f-unittype').val() || '';

  const payload = {
    action:              'uaa_mio_save_report',
    nonce:               UAA_MIO.nonce,
    inspection_type:     state.inspectionType,
    contract_no:         $('#f-contract').val(),
    date:                $('#f-date').val(),
    property_id:         state.propertyId,
    property_name:       state.propertyName,
    unit_id:             state.unitId,
    unit_no:             state.unitNo,
    unit_type:           unitTypeLabel,
    beds:                $('#f-beds').val(),
    tenant_name:         $('#f-tenant').val(),
    tenant_phone:        $('#f-phone').val(),
    tenant_email:        $('#f-email').val(),
    inspector:           $('#f-inspector').val(),
    keys:                $('#f-keys').val(),
    parking_cards:       $('#f-parking').val(),
    comments:            $('#f-comments').val(),
    rooms:               roomsData,
    subtotal:            sub.toFixed(2),
    vat_amount:          vat.toFixed(2),
    total_amount:        total.toFixed(2),
    tenant_signature:    getSig('tenantSig'),
    inspector_signature: getSig('inspSig'),
  };

  showLoading('Saving report & generating PDF...');

  // Send as JSON so PHP reads via php://input — avoids max_input_vars limits
  $.ajax({
    url:         UAA_MIO.ajax_url + '?action=uaa_mio_save_report&nonce=' + UAA_MIO.nonce,
    type:        'POST',
    contentType: 'application/json',
    data:        JSON.stringify(payload),
    success: function(res){
      hideLoading();
      btn.disabled = false;
      if (res.success){
        $('#successRef').text('Report Reference: ' + res.data.report_number);
        if (res.data.pdf_url) {
          // Download button — forces download with proper filename
          var dlUrl = res.data.pdf_url;
          $('#pdfDownloadLink').attr('href', dlUrl);
          // View button — opens in new tab
          $('#pdfViewLink').attr('href', dlUrl);
        }
        uaaGoStep(4);
      } else {
        alert('Error: ' + (res.data || 'Could not save. Check PHP error log.'));
      }
    },
    error: function(xhr){
      hideLoading();
      btn.disabled = false;
      const msg = (xhr.responseJSON && xhr.responseJSON.data) || xhr.responseText.substring(0,300) || 'Unknown error';
      alert('Server error: ' + msg);
    }
  });
};

function showLoading(t){ $('#uaaLoadingText').text(t || 'Please wait...'); $('#uaaLoading').show(); }
function hideLoading(){ $('#uaaLoading').hide(); }

})(jQuery);
