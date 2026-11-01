/* Bubba Hub wizard guide: presentation only; booking parsing and saving remain owned by admin-new-listing.js. */
document.addEventListener('DOMContentLoaded',()=>{
 const form=document.getElementById('newListingForm'),nav=document.getElementById('adminListingSteps');
 if(!form||!nav)return;
 const guide=document.createElement('aside');guide.className='bh-wizard-guide';
 guide.innerHTML='<strong>Listing studio</strong><p id="bhWizardProgress" role="status">Preparing your listing…</p><div class="bh-wizard-meter"><span></span></div><p class="bh-wizard-hint">Start with a website link to discover details, then review your booking sessions.</p><button type="button" class="button button-soft" id="bhWizardGoBookings">Review booking sessions</button>';
 form.prepend(guide);
 const css=document.createElement('style');css.textContent='.bh-wizard-guide{border:1px solid #cfe2d3;background:linear-gradient(135deg,#f0f8f3,#fff9eb);padding:14px;border-radius:16px;margin-bottom:14px;color:#194b36}.bh-wizard-guide p{font-size:13px;margin:7px 0}.bh-wizard-meter{height:7px;background:#dbe8df;border-radius:20px;overflow:hidden}.bh-wizard-meter span{display:block;background:#167c66;height:100%;width:0;transition:width .2s}.bh-wizard-guide .button{margin-top:8px;width:100%;white-space:normal}.bh-wizard-layout{min-width:0}';document.head.append(css);
 const fields=['title','organisation_name','category','description','email','venue_name','town'];
 const update=()=>{
  const complete=fields.filter(name=>String(form.elements.namedItem(name)?.value||'').trim()).length;
  const sessions=document.querySelectorAll('#adminScheduleRows [data-session-row]').length;
  guide.querySelector('#bhWizardProgress').textContent=complete+' of '+fields.length+' key details filled'+(sessions?' · '+sessions+' session rows':'');
  guide.querySelector('.bh-wizard-meter span').style.width=Math.round(100*complete/fields.length)+'%';
 };
 form.addEventListener('input',update);form.addEventListener('change',update);update();
 guide.querySelector('#bhWizardGoBookings').addEventListener('click',()=>{
  const buttons=[...nav.querySelectorAll('button')];const schedule=buttons.find(b=>/schedule/i.test(b.textContent));
  if(schedule)schedule.click();
  const section=document.getElementById('adminListingSchedule');
  if(section)section.scrollIntoView({behavior:'smooth',block:'start'});
  document.getElementById('adminIcalUrl')?.focus({preventScroll:true});
 });
 const bookingStatus=document.getElementById('adminIcalMessage');
 if(bookingStatus){
  const observer=new MutationObserver(()=>{const hint=guide.querySelector('.bh-wizard-hint');const message=bookingStatus.textContent.trim();if(message)hint.textContent='Booking importer: '+message.slice(0,180);});
  observer.observe(bookingStatus,{childList:true,characterData:true,subtree:true});
 }
});