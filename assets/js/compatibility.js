/** Copy browser preferences once; do not reset existing Ashna values. */
(function () {
    'use strict';
    try {
        ['admin_dark_mode', 'pk_enrolled'].forEach(function (suffix) {
            var key = 'ashna_' + suffix;
            var previous = window.localStorage.getItem('signa_' + suffix);
            if (window.localStorage.getItem(key) === null && previous !== null) {
                window.localStorage.setItem(key, previous);
            }
        });
        var tab = window.sessionStorage.getItem('signa_v2_active_tab');
        if (window.sessionStorage.getItem('ashna_v2_active_tab') === null && tab !== null) {
            window.sessionStorage.setItem('ashna_v2_active_tab', tab.replace(/^signa-/, 'ashna-'));
        }
    } catch (error) {
        // Browser storage can be unavailable; login must still work.
    }
}());
