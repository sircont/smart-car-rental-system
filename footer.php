    </div><!-- .admin-main-content -->
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    var t = document.getElementById('sidebarToggle');
    var s = document.getElementById('adminSidebar');
    var backdrop = document.getElementById('adminSidebarBackdrop');
    function setSidebarOpen(open) {
        if (s) s.classList.toggle('show', open);
        if (backdrop) backdrop.classList.toggle('show', open);
    }
    if (t && s) t.addEventListener('click', function(){
        setSidebarOpen(!s.classList.contains('show'));
    });
    if (backdrop) backdrop.addEventListener('click', function(){ setSidebarOpen(false); });
    if (s) {
        var navLinks = s.querySelectorAll('a.nav-link');
        navLinks.forEach(function(link){
            link.addEventListener('click', function(){
                if (window.innerWidth <= 991) setSidebarOpen(false);
            });
        });
    }
})();
</script>
</body>
</html>
