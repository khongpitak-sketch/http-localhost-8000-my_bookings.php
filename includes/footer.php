</main>

<footer class="bg-white border-top py-3 mt-auto">
    <div class="container text-center text-muted small">
        <div class="fw-semibold">ระบบขออนุญาตใช้รถยนต์ คณะวิทยาการจัดการ มหาวิทยาลัยนราธิวาสราชนครินทร์</div>
        <div>Princess of Naradhiwas University - Faculty of Management Sciences</div>
        <div class="mt-1">
            <span class="badge bg-light text-secondary border px-2 py-1" style="font-weight: 500;">
                <i class="fas fa-code-branch me-1 text-primary"></i><?= defined('APP_VERSION_FULL') ? APP_VERSION_FULL : 'Version 2.0' ?>
            </span>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<!-- Table Sorter & Pagination (10 Records / Page & Asc/Desc Toggle) -->
<script src="assets/js/table-sorter.js?v=<?= defined('APP_VERSION') ? APP_VERSION : '2.0' ?>"></script>

<?php if (!empty($isLoggedIn)): ?>
<script>
// ระบบรักษาสถานะการเชื่อมต่อ (Session Keep-Alive) ป้องกันระบบเด้งออกระหว่างเปิดทิ้งไว้
(function() {
    var lastPing = Date.now();
    function sendHeartbeat() {
        fetch('heartbeat.php', { method: 'GET', credentials: 'same-origin' })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                lastPing = Date.now();
            })
            .catch(function(err) {});
    }
    // ส่งสัญญาณทุกๆ 10 นาที
    setInterval(sendHeartbeat, 10 * 60 * 1000);
    // เมื่อสลับแท็บกลับมาทำงาน ถ้าเกิน 5 นาทีแล้วให้ส่งสัญญาณทันที
    window.addEventListener('focus', function() {
        if (Date.now() - lastPing > 5 * 60 * 1000) {
            sendHeartbeat();
        }
    });
})();
</script>
<?php endif; ?>

</body>
</html>
