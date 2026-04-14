// Function to load the navbar component dynamically
function CallNavbar() {
    fetch('navbar.html')
        .then(response => response.text())
        .then(data => {
            document.getElementById('navbar-container').innerHTML = data;
            
            // Add active class to current page in sidebar
            const currentPage = window.location.pathname.split('/').pop();
            const menuItems = document.querySelectorAll('.sidebar-menu li a');
            
            menuItems.forEach(item => {
                const href = item.getAttribute('href');
                if (href === currentPage || (currentPage === '' && href === 'dashboard.php')) {
                    item.parentElement.classList.add('active');
                } else {
                    item.parentElement.classList.remove('active');
                }
            });
        })
        .catch(error => console.error('Error loading navbar:', error));
}

// Make function available globally
window.CallNavbar = CallNavbar;

//s