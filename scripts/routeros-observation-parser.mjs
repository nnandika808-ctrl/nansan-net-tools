// A narrow lexical observer for generated RouterOS configuration, not a full
// RouterOS semantic verifier. It never reads a third party's generator source.
export function tokens(line){
 const result=[];let token='',quoted=false,escape=false,depth=0;
 for(const character of line){
  if(escape){token+=character;escape=false;continue;}
  if(character==='\\'){escape=true;continue;}
  if(character==='"'){quoted=!quoted;continue;}
  if(!quoted&&character==='(')depth++;
  if(!quoted&&character===')')depth--;
  if(/\s/.test(character)&&!quoted&&depth===0){if(token){result.push(token);token='';}}else token+=character;
 }
 if(token)result.push(token);return result;
}
export function fields(parts){const result={};for(const part of parts){const index=part.indexOf('=');if(index>0)result[part.slice(0,index)]=part.slice(index+1);else if(part==='fib')result.fib=true;}return result;}
export function parse(script){let path='';const entries=[];for(const raw of script.split('\n')){const line=raw.trim();if(!line||line.startsWith('#'))continue;const parts=tokens(line);let operation=parts.findIndex(p=>['add','set','remove'].includes(p));if(parts[0]?.startsWith('/')){if(operation<0){path=parts.join(' ');continue;}path=parts.slice(0,operation).join(' ');}else operation=0;const data=fields(parts.slice(operation+1));delete data.comment;entries.push({path,operation:parts[operation],fields:data});}return entries;}
