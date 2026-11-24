(()=>{'use strict';
function duplicateSection(source,createId){
 if(!source||source.type!=='section'||!Array.isArray(source.children)||typeof createId!=='function')throw new TypeError('A valid section and ID factory are required.');
 const copy=JSON.parse(JSON.stringify(source));
 copy.id=createId('container-copy');
 copy.children=copy.children.map(child=>{
  if(!child||typeof child!=='object'||typeof child.type!=='string')throw new TypeError('Section contains an invalid child block.');
  return {...child,id:createId('block-copy')};
 });
 return copy;
}
function createHistory(limit=50){
 const max=Number.isInteger(limit)?Math.max(1,Math.min(100,limit)):50;
 const undoStack=[],redoStack=[];
 const clone=value=>JSON.parse(JSON.stringify(value));
 const same=(a,b)=>JSON.stringify(a)===JSON.stringify(b);
 const hasDifferent=(stack,current)=>stack.some(snapshot=>!same(snapshot,current));
 return Object.freeze({
  checkpoint(document){
   const snapshot=clone(document),last=undoStack[undoStack.length-1];
   if(!last||!same(last,snapshot))undoStack.push(snapshot);
   if(undoStack.length>max)undoStack.shift();
   redoStack.length=0;
  },
  undo(current){
   const currentSnapshot=clone(current);
   while(undoStack.length){
    const previous=undoStack.pop();
    if(same(previous,currentSnapshot))continue;
    redoStack.push(currentSnapshot);
    if(redoStack.length>max)redoStack.shift();
    return clone(previous);
   }
   return null;
  },
  redo(current){
   const currentSnapshot=clone(current);
   while(redoStack.length){
    const next=redoStack.pop();
    if(same(next,currentSnapshot))continue;
    undoStack.push(currentSnapshot);
    if(undoStack.length>max)undoStack.shift();
    return clone(next);
   }
   return null;
  },
  reset(){undoStack.length=0;redoStack.length=0;},
  canUndo(current){return !!current&&hasDifferent(undoStack,current);},
  canRedo(current){return !!current&&hasDifferent(redoStack,current);}
 });
}
function canInlineEdit(type){return ['heading','paragraph','button'].includes(type);}
function normaliseInlineText(value,maxLength){
 const limit=Number.isInteger(maxLength)&&maxLength>=0?maxLength:10000;
 return String(value??'').replace(/\r\n?/g,'\n').replace(/[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]/g,'').slice(0,limit);
}
function commitInlineText(block,value){
 if(!block||typeof block!=='object'||!canInlineEdit(block.type))return {ok:false,changed:false,error:'This element cannot be edited inline.'};
 const max=block.type==='heading'?500:block.type==='button'?120:10000;
 const text=normaliseInlineText(value,max);
 if(block.type==='button'&&!text.trim())return {ok:false,changed:false,error:'Button text cannot be empty.'};
 if(!block.props||typeof block.props!=='object'||Array.isArray(block.props))block.props={};
 const changed=String(block.props.text??'')!==text;
 block.props.text=text;
 return {ok:true,changed,text};
}
const api=Object.freeze({duplicateSection,createHistory,canInlineEdit,normaliseInlineText,commitInlineText});
if(typeof window!=='undefined')window.BHVisualBuilderDocumentActions=api;
if(typeof module!=='undefined'&&module.exports)module.exports=api;
})();