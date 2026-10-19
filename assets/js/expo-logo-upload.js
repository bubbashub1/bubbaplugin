document.addEventListener('DOMContentLoaded',function(){
document.querySelectorAll('[data-expo-logo-upload]').forEach(function(panel){
const file=panel.querySelector('[data-expo-logo-file]'),button=panel.querySelector('[data-expo-upload-button]'),status=panel.querySelector('[data-expo-upload-status]'),preview=panel.querySelector('[data-expo-logo-preview]');
const form=panel.closest('[data-expo-discovery-form]')||document,logo=form.querySelector('[name="logo_url"]');if(!logo)return;
function show(){if(logo.value.trim().startsWith('https://')){preview.src=logo.value.trim();preview.hidden=false;}else{preview.removeAttribute('src');preview.hidden=true;}}
logo.addEventListener('input',show);show();
button.addEventListener('click',async function(){
const selected=file.files[0];if(!selected){status.textContent='Choose an image first.';return;}
if(selected.size>2097152||!['image/png','image/jpeg','image/webp'].includes(selected.type)){status.textContent='Use PNG, JPEG or WebP under 2 MB.';return;}
const isAdmin=location.pathname.startsWith('/admin/');button.disabled=true;status.textContent='Uploading logo…';
try{
const auth=await fetch(isAdmin?'/api/expo-admin-exhibitors.php':'/api/expo-exhibitors.php',{credentials:'same-origin',cache:'no-store'});const data=await auth.json();if(!auth.ok||!data.csrf_token)throw Error('Security token unavailable');
const payload=new FormData();payload.append('logo',selected);
const r=await fetch('/api/expo-logo-upload.php',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':data.csrf_token},body:payload});const result=await r.json();if(!r.ok||!result.ok)throw Error(result.error||'Upload failed');
logo.value=result.logo_url;logo.dispatchEvent(new Event('input',{bubbles:true}));status.textContent='Logo uploaded. Save or submit the form to use it.';
}catch(e){status.textContent='Logo upload failed: '+e.message;}finally{button.disabled=false;}
});
});
});