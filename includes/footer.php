    </div> <!-- End Main Content -->

    <script>
        // Sidebar toggle (mobile)
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }

        // Dark mode
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            updateThemeIcon(next);
        }

        function updateThemeIcon(theme) {
            const icon = document.getElementById('themeIcon');
            if (icon) {
                icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
        }

        // Restore theme on load
        (function() {
            const saved = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', saved);
            updateThemeIcon(saved);
        })();

        // Toast notifications
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = 'toast-item toast-' + type;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        // Confirm delete modal
        let confirmCallback = null;
        function confirmDelete(title, message, callback) {
            document.getElementById('confirmTitle').textContent = title || 'Подтверждение';
            document.getElementById('confirmMessage').textContent = message || 'Вы уверены?';
            document.getElementById('confirmIcon').innerHTML = '<i class="fas fa-trash-alt text-danger fa-2x"></i>';
            confirmCallback = callback;
            const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
            modal.show();
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('confirmBtn').addEventListener('click', function() {
                if (confirmCallback) confirmCallback();
                bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
            });
        });

        // Global helper: replace alert() calls
        window._originalAlert = window.alert;
        window.alert = function(msg) {
            showToast(msg, 'info');
        };
    </script>
</body>
</html>
