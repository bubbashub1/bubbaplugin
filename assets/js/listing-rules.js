/* Bubba Hub rule-based listing suggestions. No AI or external services. */
(function(root){
"use strict";
const rules=[
{category:"Baby classes",tags:["baby massage"],words:["baby massage","infant massage"]},
{category:"Baby classes",tags:["baby yoga"],words:["baby yoga","infant yoga"]},
{category:"Baby classes",tags:["sensory play"],words:["baby sensory","sensory baby","sensory play"]},
{category:"Toddler groups",tags:["toddler group"],words:["toddler group","stay and play","parent and toddler"]},
{category:"Music & movement",tags:["music","movement"],words:["music class","sing and sign","rhyme time","music and movement"]},
{category:"Swimming",tags:["swimming"],words:["baby swimming","swimming lesson","swim class"]},
{category:"Soft play",tags:["soft play"],words:["soft play"]},
{category:"Family activities",tags:["family activities"],words:["family fun","family activities","family event"]}
];
function normalise(v){return String(v||"").replace(/<[^>]*>/g," ").replace(/\s+/g," ").trim();}
function suggest(data){
const title=normalise(data.title),description=normalise(data.description),text=(title+" "+description).toLowerCase();
const matches=rules.map(r=>({...r,score:r.words.reduce((n,w)=>n+(text.includes(w)?1:0),0)})).filter(r=>r.score).sort((a,b)=>b.score-a.score);
const age=text.match(/(?:ages?\s*)?(\d+)\s*(?:-|–|to)\s*(\d+)\s*(months?|years?|yrs?)/i);
const price=text.match(/£\s*(\d+(?:\.\d{1,2})?)/);
return {title,description,category:matches[0]?.category||"",categories:[...new Set(matches.map(r=>r.category))],tags:[...new Set(matches.flatMap(r=>r.tags))],age_range:age?age[1]+"–"+age[2]+" "+age[3]:"",price_from:price?Number(price[1]):null,source:"rule-based",review_required:true};
}
root.BubbaListingRules={suggest,rules};
})(typeof window!=="undefined"?window:globalThis);
