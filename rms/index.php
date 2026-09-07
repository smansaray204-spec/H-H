<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$store = load_store();
$properties = array_values(array_filter($store['properties'], fn($p) => ($p['status'] ?? '') === 'active'));
usort($properties, fn($a, $b) => ((int)$a['id']) <=> ((int)$b['id']));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Book | Holiday Home Apartments</title>
<style>
:root{--gold:#C5A059;--ink:#1A1A1A;--muted:#6b7280;--line:#e8e8e8;--bg:#f7f7f5}*{box-sizing:border-box}body{margin:0;font-family:Arial,Helvetica,sans-serif;background:var(--bg);color:var(--ink)}.wrap{max-width:1100px;margin:0 auto;padding:30px 18px 50px}.brand{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px}.brand h1{font-family:Georgia,serif;font-size:26px;margin:0}.brand span{font-size:12px;letter-spacing:2px;color:var(--gold)}.panel{background:#fff;border:1px solid var(--line);border-radius:16px;padding:24px;box-shadow:0 10px 35px rgba(0,0,0,.06)}.search{display:grid;grid-template-columns:1fr 1fr .7fr auto;gap:12px;align-items:end}.field label{display:block;font-size:12px;font-weight:700;margin-bottom:7px;text-transform:uppercase;letter-spacing:.6px;color:#444}.field input,.field select,.field textarea{width:100%;padding:13px 12px;border:1px solid #d9d9d9;border-radius:10px;font:inherit}.btn{border:0;border-radius:10px;padding:13px 18px;font-weight:700;cursor:pointer}.btn.gold{background:var(--gold);color:#111}.btn.dark{background:var(--ink);color:#fff}.results{margin-top:24px;display:grid;grid-template-columns:repeat(3,1fr);gap:16px}.card{border:1px solid var(--line);border-radius:14px;overflow:hidden;background:#fff}.photo{height:155px;background:linear-gradient(135deg,#ddd,#f5f5f5);display:flex;align-items:center;justify-content:center;color:#888;font-size:14px}.card-body{padding:18px}.loc{font-size:12px;color:var(--gold);font-weight:700}.card h3{font-family:Georgia,serif;margin:7px 0;font-size:22px}.price{font-weight:700;margin:8px 0 14px}.meta{display:flex;gap:15px;color:#666;font-size:13px;margin-bottom:16px}.empty{padding:30px;text-align:center;color:var(--muted);border:1px dashed #ccc;border-radius:12px;background:#fff;grid-column:1/-1}.modal{position:fixed;inset:0;background:rgba(0,0,0,.55);display:none;align-items:center;justify-content:center;padding:20px;z-index:50}.modal.open{display:flex}.modal-card{max-width:650px;width:100%;background:#fff;border-radius:16px;padding:24px;max-height:90vh;overflow:auto}.modal-head{display:flex;justify-content:space-between;align-items:center}.modal-head h2{font-family:Georgia,serif;margin:0}.close{border:0;background:#eee;width:36px;height:36px;border-radius:50%;cursor:pointer}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px}.full{grid-column:1/-1}.notice{padding:12px;border-radius:10px;background:#f7f2e7;margin:12px 0;display:none}.notice.error{background:#fff0f0;color:#8a2020}.success{padding:30px;text-align:center}.success h2{font-family:Georgia,serif}.small{font-size:12px;color:var(--muted)}@media(max-width:800px){.search,.results,.form-grid{grid-template-columns:1fr}.full{grid-column:auto}.brand{gap:10px;align-items:flex-start;flex-direction:column}}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand"><div><h1>Holiday Home Apartments</h1><span>RESERVATION MANAGEMENT SYSTEM</span></div><a href="/" class="btn dark" style="text-decoration:none">Back to Website</a></div>
  <div class="panel">
    <h2 style="font-family:Georgia,serif;margin-top:0">Check availability</h2>
    <div class="search">
      <div class="field"><label>Check-in</label><input type="date" id="checkIn"></div>
      <div class="field"><label>Check-out</label><input type="date" id="checkOut"></div>
      <div class="field"><label>Guests</label><select id="guests"><option>1</option><option>2</option><option>3</option><option>4</option><option>5</option><option>6</option></select></div>
      <button class="btn gold" id="searchBtn">Search</button>
    </div>
  </div>
  <div id="results" class="results"></div>
</div>

<div class="modal" id="modal"><div class="modal-card">
  <div class="modal-head"><h2>Reserve your apartment</h2><button class="close" id="closeBtn">×</button></div>
  <div id="formNotice" class="notice"></div>
  <form id="bookingForm">
    <input type="hidden" id="propertyId">
    <div id="bookingSummary" class="small"></div>
    <div class="form-grid">
      <div class="field"><label>Full name *</label><input id="guestName" required></div>
      <div class="field"><label>Phone *</label><input id="guestPhone" required></div>
      <div class="field"><label>Email</label><input id="guestEmail" type="email"></div>
      <div class="field"><label>Guests *</label><input id="guestCount" type="number" min="1" value="1" required></div>
      <div class="field"><label>Check-in *</label><input id="formIn" type="date" required></div>
      <div class="field"><label>Check-out *</label><input id="formOut" type="date" required></div>
      <div class="field full"><label>Notes</label><textarea id="notes" rows="3" placeholder="Special requests or arrival details"></textarea></div>
      <div class="full"><button class="btn gold" style="width:100%" type="submit">Confirm Reservation</button></div>
    </div>
  </form>
</div></div>
<script>
const $=id=>document.getElementById(id);let selected=null;
const fmt=n=>new Intl.NumberFormat('en-US',{style:'currency',currency:'USD',maximumFractionDigits:0}).format(n);
function esc(s){return String(s).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
function setMin(){let t=new Date();t.setHours(0,0,0,0);const d=t.toISOString().slice(0,10);$('checkIn').min=d;$('checkOut').min=d;$('formIn').min=d;$('formOut').min=d;}
async function search(){const ci=$('checkIn').value,co=$('checkOut').value,g=$('guests').value;if(!ci||!co||ci>=co){$('results').innerHTML='<div class="empty">Choose a valid check-in and check-out date.</div>';return;} $('results').innerHTML='<div class="empty">Checking availability…</div>';const r=await fetch(`api.php?action=availability&check_in=${encodeURIComponent(ci)}&check_out=${encodeURIComponent(co)}&guests=${g}`);const d=await r.json();if(!r.ok){$('results').innerHTML=`<div class="empty">${esc(d.error||'Could not search availability.')}</div>`;return;} if(!d.properties.length){$('results').innerHTML='<div class="empty">No apartments are available for those dates.</div>';return;} $('results').innerHTML=d.properties.map(p=>`<div class="card"><div class="photo">Apartment image</div><div class="card-body"><div class="loc">${esc(p.location)}</div><h3>${esc(p.name)}</h3><div class="price">${fmt(p.rate)} / ${esc(p.rate_period)}</div><div class="meta"><span>🛏 ${p.bedrooms} bed${p.bedrooms==1?'':'s'}</span><span>🛁 ${p.bathrooms} bath${p.bathrooms==1?'':'s'}</span><span>👥 ${p.capacity}</span></div><button class="btn dark" style="width:100%" onclick='openBooking(${JSON.stringify(p)})'>Reserve</button></div></div>`).join('');}
function openBooking(p){selected=p;$('propertyId').value=p.id;$('formIn').value=$('checkIn').value;$('formOut').value=$('checkOut').value;$('guestCount').value=$('guests').value;$('bookingSummary').textContent=`${p.name} · ${p.location} · ${fmt(p.rate)} / ${p.rate_period}`;$('formNotice').style.display='none';$('modal').classList.add('open');}
$('searchBtn').onclick=search;$('closeBtn').onclick=()=>$('modal').classList.remove('open');$('modal').onclick=e=>{if(e.target.id==='modal')$('modal').classList.remove('open')};
$('bookingForm').onsubmit=async e=>{e.preventDefault();const notice=$('formNotice');notice.style.display='none';const body={property_id:+$('propertyId').value,guest_name:$('guestName').value.trim(),guest_phone:$('guestPhone').value.trim(),guest_email:$('guestEmail').value.trim(),guests:+$('guestCount').value,check_in:$('formIn').value,check_out:$('formOut').value,notes:$('notes').value.trim()};const r=await fetch('api.php?action=reserve',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});const d=await r.json();if(!r.ok){notice.className='notice error';notice.textContent=d.error||'Booking failed.';notice.style.display='block';return;} $('modal').querySelector('.modal-card').innerHTML=`<div class="success"><h2>Reservation received</h2><p>Your reservation request has been recorded.</p><p><strong>Reference: ${esc(d.reference)}</strong></p><p>${esc(d.property)} · ${esc(d.check_in)} → ${esc(d.check_out)}</p><p><strong>Total: ${fmt(d.total)}</strong></p><p class="small">Holiday Home can now review the reservation in the admin dashboard and confirm it.</p><button class="btn dark" onclick="location.reload()">Done</button></div>`;};
setMin();
</script>
</body>
</html>
