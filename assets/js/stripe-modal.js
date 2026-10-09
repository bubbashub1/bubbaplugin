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
    stripe=null;
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

      const response=await fetch("/api/stripe-checkout.php",{
        method:"POST",
        credentials:"same-origin",
        headers:{"Content-Type":"application/json","Accept":"application/json"},
        body:JSON.stringify({
          plan:options.plan||"family_pro",
          billing:options.billing||"annual",
          donation:!!options.donation,
          amount:Math.round(Number(options.amount||5)*100),
          embedded:true
        })
      });
      const data=await response.json();
      if(!response.ok||!data.client_secret)throw new Error(data.message||data.error||"Unable to open Stripe checkout.");
      if(!data.publishable_key)throw new Error("Stripe embedded checkout is not configured. Please add the Stripe publishable key to Bubba Hub's server configuration.");
      if(typeof window.Stripe!=="function")throw new Error("Stripe could not be loaded. Please allow Stripe in your browser and try again.");

      stripe=window.Stripe(data.publishable_key);
      if(!stripe||typeof stripe.initEmbeddedCheckout!=="function")throw new Error("Stripe embedded checkout is not available in this browser.");

      checkout=await stripe.initEmbeddedCheckout({clientSecret:data.client_secret});
      mount.innerHTML="";
      checkout.mount("#bhStripeCheckout");
    }catch(err){
      if(checkout){try{checkout.destroy();}catch(_){} checkout=null;}
      mount.innerHTML="";

      // Keep checkout inside the Bubba Hub modal; display errors without redirecting.
      error.textContent=err.message||"Unable to open secure checkout inside the modal.";

    }
  }

  window.BHStripeModal={open,close};

  function bind(){
    document.querySelectorAll("[data-stripe-payment], [data-stripe-donation]").forEach(button=>{
      if(button.dataset.stripeBound)return;
      button.dataset.stripeBound="1";
      button.addEventListener("click",()=>{
        const billing=document.querySelector('input[name="'+(button.dataset.billingName||"proBilling")+'"]:checked')?.value||button.dataset.billing||"annual";
        open({
          plan:button.dataset.stripePayment||"family_pro",
          billing,
          donation:button.dataset.stripeDonation==="1",
          amount:Number(button.dataset.stripeAmount||5)
        });
      });
    });
  }

  if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",bind,{once:true});else bind();
})();
