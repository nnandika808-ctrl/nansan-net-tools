import {describe,it,expect} from 'vitest';
import {generatorDefaults,generatorFields,changeGeneratorField} from './generator-form';
import schema from '../../generators/G04.json';
import type {FormSchema} from '@/types';

describe('Starlink dynamic form',()=>{
 it('adds WAN fields and monitoring defaults while preserving custom targets',()=>{
  const form=schema as FormSchema;
  let values=generatorDefaults(form);
  values=changeGeneratorField(form,values,'count','3');
  expect(generatorFields(form,values).filter(f=>f.key.startsWith('connection-'))).toHaveLength(3);
  expect(values.netwatchTargets).toBe('1.1.1.1,1.1.1.2,1.1.1.3');
  values=changeGeneratorField(form,values,'mode','manual');
  expect(generatorFields(form,values).filter(f=>f.key.startsWith('connection-'))).toHaveLength(6);
  values.netwatchTargets='9.9.9.9,8.8.8.8,1.1.1.1';
  expect(changeGeneratorField(form,values,'count','4').netwatchTargets).toBe(values.netwatchTargets);
 });
});
