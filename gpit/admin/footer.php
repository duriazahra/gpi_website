    </main>
  </div>

  <script>
    // Mobile sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const adminSidebar = document.getElementById('adminSidebar');
    if (sidebarToggle && adminSidebar) {
      sidebarToggle.addEventListener('click', () => {
        adminSidebar.classList.toggle('open');
      });
      // Close sidebar on outer click on mobile
      document.addEventListener('click', (e) => {
        if (window.innerWidth < 992 && !adminSidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
          adminSidebar.classList.remove('open');
        }
      });
    }
  </script>
</body>
</html>
