import '@testing-library/jest-dom/vitest';
import {vi,afterEach} from 'vitest';
import {cleanup} from '@testing-library/react';
afterEach(()=>{cleanup();vi.restoreAllMocks();});
Object.defineProperty(window,'matchMedia',{writable:true,value:vi.fn().mockImplementation(query=>({matches:true,media:query,addEventListener:vi.fn(),removeEventListener:vi.fn()}))});
Object.defineProperty(navigator,'clipboard',{configurable:true,value:{writeText:vi.fn().mockResolvedValue(undefined)}});
