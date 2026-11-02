// Inspect visibility only: never read input values, cookies or browser storage.
export function hasVisibleSecrets(doc){
 return [...doc.querySelectorAll('input[type="password"],input[autocomplete="one-time-code"]')].some(input=>{
  const style=doc.defaultView.getComputedStyle(input);
  return input.getClientRects().length>0&&style.visibility!=='hidden'&&style.display!=='none'&&style.opacity!=='0';
 });
}
export const authGuardFunction='() => ('+hasVisibleSecrets.toString()+')(document)';
