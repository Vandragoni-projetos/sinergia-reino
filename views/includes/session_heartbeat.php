<script>
(function() {
    var CHECK_INTERVAL_MS = 20000;
    var tid;
    function check() {
        fetch('/api/api?action=check_session', { credentials: 'same-origin' })
            .then(function(r) {
                if (r.status === 401) {
                    return r.json().then(function(d) {
                        var err = (d && d.error) || '';
                        window.location.href = '/login?' + (err === 'session_timeout' ? 'session_timeout' : 'session_replaced') + '=1';
                    });
                }
            })
            .catch(function() {});
    }
    tid = setInterval(check, CHECK_INTERVAL_MS);
})();
</script>
