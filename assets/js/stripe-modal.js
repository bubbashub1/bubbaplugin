(function(){
  "use strict";
  let stripe=null, checkout=null, modal=null, body=null;

  function ensureModal(){
    if(modal)return;
    modal=document.createElement("div");
    modal.className="bh-stripe-modal";
    modal.hidden=true;
    modal.innerHTML='<div class="bh-stripe-modal-backdrop" data-stripe-close></div><section class="bh-stripe-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="bhStripeTitle"><button type="button" class="bh-stripe-modal-close" data-stripe-close aria-label="Close payment">×</button><div class="bh-stripe-modal-head"><span class="eyebrow">Secure payment</span><h2 id="bhStripeTitle">Complete your payment</h2><p>Your payment is securely processed by Stripe.</p></div><div id="bhStripeCheckout" class="bh-stripe-checkout"></div><p id="bhStripeError" class="bh-stripe-error" role="alert"></p></section>';
    document.body.appendChild(modal);
    body=document.body;
    modal.querySelectorAll("[data-stripe-close]").forEach(el=>el.addEventListener("click",close));
    document.addEventListener("keydown",e=>{if(e.key==="Escape"&&!modal.hidden)close();});
  }

  function close(){
    if(!modal)return;
    modal.hidden=true;
    body.classList.remove("bh-stripe-modal-open");
    if(checkout){try{checkout.destroy();}catch(_){} checkout=null;}
    const mount=document.getElementById("bhStripeCheckout");
    if(mount)mount.innerHTML="";
  }

  function loadStripeJs(){return new Promise((resolve,reject)=>{if(typeof window.Stripe==="function")return resolve();const existing=document.querySelector("script[src^=\"https://js.stripe.com/\"]");if(existing){let settled=false;const finish=()=>{if(settled)return;settled=true;typeof window.Stripe==="function"?resolve():reject(new Error("Stripe could not be loaded. Please allow Stripe in your browser and try again."));};existing.addEventListener("load",finish,{once:true});existing.addEventListener("error",()=>{if(settled)return;settled=true;reject(new Error("Stripe could not be loaded. Please allow Stripe in your browser and try again."));},{once:true});setTimeout(finish,8000);return;}const s=document.createElement("script");s.src="https://js.stripe.com/v3/";s.async=true;s.defer=true;s.onload=()=>typeof window.Stripe==="function"?resolve():reject(new Error("Stripe could not be loaded. Please allow Stripe in your browser and try again."));s.onerror=()=>reject(new Error("Stripe could not be loaded. Please allow Stripe in your browser and try again."));document.head.appendChild(s);setTimeout(()=>{if(typeof window.Stripe!=="function")reject(new Error("Stripe could not be loaded. Please allow Stripe in your browser and try again."));},8000);});}

  async function open(options){
    ensureModal();
    modal.hidden=false;
    body.classList.add("bh-stripe-modal-open");
    const title=document.getElementById("bhStripeTitle");
    const error=document.getElementById("bhStripeError");
    const mount=document.getElementById("bhStripeCheckout");
    title.textContent=options.donation?"Support Bubba Hub":"Upgrade to "+(options.plan==="leader_pro"?"Leader Pro":"Family Pro");
    error.textContent="";
    mount.innerHTML='<div class="bh-stripe-loading">Opening secure Stripe checkout…</div>';

    try{
      await loadStripeJs();
      // Use Stripe's hosted Checkout as the reliable payment path.
      // Embedded Checkout requires a correctly configured publishable key and
      // can fail before the payment form is even created. Hosted Checkout
      // works with the existing server-side Stripe configuration.
      const response=await fetch("/api/stripe-checkout.php",{
        method:"POST",credentials:"same-origin",
        headers:{"Content-Type":"application/json","Accept":"application/json"},
        body:JSON.stringify({
          plan:options.plan||"family_pro",
          billing:options.billing||"annual",
          donation:!!options.donation,
          amount:options.amount||10
        })
      });
      const data=await response.json();
      if(!response.ok||!data.url)throw new Error(data.message||data.error||"Unable to open Stripe checkout.");
      mount.innerHTML='<div class="bh-stripe-loading">Redirecting to secure Stripe checkout…</div>';
      window.location.assign(data.url);
    }catch(err){
      mount.innerHTML="";
      error.textContent=err.message||"Unable to open secure checkout.";
      if(/Stripe could not be loaded/i.test(String(err&&err.message||""))){
        const fallback=document.createElement("button");
        fallback.type="button";
        fallback.className="button button-primary";
        fallback.textContent="Continue to secure Stripe checkout →";
        fallback.addEventListener("click",async()=>{
          fallback.disabled=true;
          fallback.textContent="Opening secure checkout…";
          try{
            const response=await fetch("/api/stripe-checkout.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({plan:options.plan||"family_pro",billing:options.billing||"annual",donation:!!options.donation,amount:options.amount||10})});
            const data=await response.json();
            if(!response.ok||!data.url)throw new Error(data.message||"Unable to open secure checkout.");
            location.href=data.url;
          }catch(fallbackError){
            error.textContent=fallbackError.message||"Unable to open secure checkout.";
            fallback.disabled=false;
            fallback.textContent="Continue to secure Stripe checkout →";
          }
        });
        mount.appendChild(fallback);
      }
    }
  }

  window.BHStripeModal={open,close};

  function bind(){
    document.querySelectorAll("[data-stripe-payment]").forEach(button=>{
      if(button.dataset.stripeBound)return;
      button.dataset.stripeBound="1";
      button.addEventListener("click",()=>{
        const billing=document.querySelector('input[name="'+(button.dataset.billingName||"proBilling")+'"]:checked')?.value||button.dataset.billing||"annual";
        open({
          plan:button.dataset.stripePayment||"family_pro",
          billing,
          donation:button.dataset.stripeDonation==="1",
          amount:Number(button.dataset.stripeAmount||10)
        });
      });
    });
  }

  if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",bind,{once:true});else bind();
})();