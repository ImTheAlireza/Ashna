const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('assets/js/compatibility.js', 'utf8');
function storage(values = {}) {
    return { values, getItem(k) { return k in values ? values[k] : null; }, setItem(k, v) { values[k] = v; } };
}
const localStorage = storage({ signa_admin_dark_mode: '1', signa_pk_enrolled: '1' });
const sessionStorage = storage({ signa_v2_active_tab: 'signa-tab-security' });
vm.runInNewContext(source, { window: { localStorage, sessionStorage } });
assert.equal(localStorage.values.ashna_admin_dark_mode, '1');
assert.equal(localStorage.values.ashna_pk_enrolled, '1');
assert.equal(sessionStorage.values.ashna_v2_active_tab, 'ashna-tab-security');
localStorage.values.ashna_admin_dark_mode = '0';
sessionStorage.values.ashna_v2_active_tab = 'ashna-tab-dashboard';
vm.runInNewContext(source, { window: { localStorage, sessionStorage } });
assert.equal(localStorage.values.ashna_admin_dark_mode, '0');
assert.equal(sessionStorage.values.ashna_v2_active_tab, 'ashna-tab-dashboard');
vm.runInNewContext(source, { window: { get localStorage() { throw new Error('Storage blocked'); } } });
console.log('PASS: browser preferences/enrollment migrate once, keep new values, tolerate blocked storage.');
