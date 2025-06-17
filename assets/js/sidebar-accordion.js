document.addEventListener('DOMContentLoaded', function () {
  const toggleBtn = document.querySelector('.toggle-sidebar-btn');
  const body = document.body;

  // Restore sidebar toggle state from localStorage
  const sidebarToggled = localStorage.getItem('sidebarToggled');
  if (sidebarToggled === 'true') {
    body.classList.add('toggle-sidebar');
  } else {
    body.classList.remove('toggle-sidebar');
  }

  if (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
      body.classList.toggle('toggle-sidebar');
      // Save the current state to localStorage
      localStorage.setItem('sidebarToggled', body.classList.contains('toggle-sidebar'));
    });
  } else {
    console.log('Toggle sidebar button not found');
  }
});
