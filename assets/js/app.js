document.addEventListener('DOMContentLoaded', function () {
  // Man hinh nho: nut hamburger tren topbar truot sidebar vao/ra
  var mobileToggle = document.getElementById('sidebarToggle');
  var sidebar = document.getElementById('sidebar');
  if (mobileToggle && sidebar) {
    mobileToggle.addEventListener('click', function () {
      sidebar.classList.toggle('show');
    });
  }

  // Man hinh lon: nut hamburger ngay tren thanh menu thu gon sidebar ve dang chi icon,
  // nho trang thai qua localStorage de giu nguyen khi chuyen trang
  var collapseToggle = document.getElementById('sidebarCollapseToggle');
  if (collapseToggle) {
    collapseToggle.addEventListener('click', function () {
      var collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
      localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0');
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
