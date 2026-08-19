document.addEventListener('DOMContentLoaded', function () {
  var toggle = document.getElementById('sidebarToggle');
  var sidebar = document.getElementById('sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', function () {
      sidebar.classList.toggle('show');
    });
  }

  var collapseBtn = document.getElementById('sidebarCollapseToggle');
  if (collapseBtn) {
    var collapseIcon = collapseBtn.querySelector('i');
    var syncCollapseIcon = function () {
      var collapsed = document.documentElement.classList.contains('sidebar-collapsed');
      collapseIcon.className = collapsed ? 'bi bi-chevron-right' : 'bi bi-chevron-left';
    };
    syncCollapseIcon();
    collapseBtn.addEventListener('click', function () {
      var collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
      localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0');
      syncCollapseIcon();
    });
  }

  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!confirm(form.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });
});
