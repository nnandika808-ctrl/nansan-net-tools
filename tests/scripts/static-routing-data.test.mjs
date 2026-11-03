import {test} from 'node:test';
import assert from 'node:assert/strict';
import {parseStaticRoutingDataset} from '../../scripts/static-routing-data-parser.mjs';

test('rejects executable and remote-fetch commands in dataset inputs',()=>{
  for(const source of ['/system reset-configuration','/tool fetch url="https://example.test/x"','/ip firewall address-list\n:execute "/system reboot"','/ip firewall address-list\nadd address=192.0.2.1 list=Mikrotik-Lab ; /system reboot','/ip firewall address-list\nadd address=192.0.2.1 list=Mikrotik-Lab comment=[/system/reboot]'])assert.throws(()=>parseStaticRoutingDataset(source,'Lab'));
});
test('rejects broad removals and target lists outside the selected app',()=>{
  for(const source of ['/ip firewall raw\nrem [find]','/ip firewall address-list\nrem [find list="Mikrotik-Other"]','/ip firewall address-list\nadd address=192.0.2.1 list=Mikrotik-Other'])assert.throws(()=>parseStaticRoutingDataset(source,'Lab'));
});
test('rejects unapproved fields and RAW actions',()=>{
  for(const source of ['/ip firewall address-list\nadd address=192.0.2.1 list=Mikrotik-Lab script=anything','/ip firewall raw\nadd action=drop address-list=Mikrotik-Lab chain=prerouting'])assert.throws(()=>parseStaticRoutingDataset(source,'Lab'));
});
test('keeps parameter order and duplicate facts while excluding descriptive comments',()=>{
  const result=parseStaticRoutingDataset('# local test data\n/ip firewall address-list\nrem [find list="Mikrotik-Lab"]\nadd address=192.0.2.0/24 list=Mikrotik-Lab comment="Example"\nadd address=192.0.2.0/24 list=Mikrotik-Lab','Lab');
  assert.equal(result.commands.length,3);assert.equal(result.duplicates,1);assert.equal(result.commands[0].operation,'remove');assert.deepEqual(result.commands[1].fields,{address:'192.0.2.0/24',list:'Mikrotik-Lab'});assert.deepEqual(result.commands[1],result.commands[2]);
});
